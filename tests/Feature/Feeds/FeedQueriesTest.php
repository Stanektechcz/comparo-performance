<?php

use App\Domain\Feeds\FeedErrorSeverity;
use App\Domain\Feeds\FeedItemValidationStatus;
use App\Domain\Feeds\FeedRunStatus;
use App\Domain\Feeds\Mapping\FieldMapping;
use App\Domain\Feeds\Queries\FeedErrorGroup;
use App\Domain\Feeds\Queries\FeedRunErrors;
use App\Domain\Feeds\Queries\FeedRunHistory;
use App\Domain\Feeds\Queries\FeedSamplePreview;
use App\Domain\Feeds\Queries\MerchantFeedSources;
use App\Models\FeedError;
use App\Models\FeedItem;
use App\Models\FeedMapping;
use App\Models\FeedRun;
use App\Models\FeedSource;
use App\Models\Merchant;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Feeds\FeedPipelineFixtures;

beforeEach(function () {
    $this->merchant = FeedPipelineFixtures::merchant();
    $this->other = Merchant::factory()->create();
});

describe('merchant feed sources', function () {
    it('lists only the merchant sources with their latest run in a fixed number of queries', function () {
        $sources = FeedSource::factory()->count(3)->for($this->merchant)->create();
        foreach ($sources as $source) {
            FeedRun::factory()->forSource($source)->completed()->create();
            FeedRun::factory()->forSource($source)->failed()->create();
        }
        FeedSource::factory()->for($this->other)->create();

        DB::enableQueryLog();
        $page = app(MerchantFeedSources::class)->paginate($this->merchant->id);
        $page->each(fn (FeedSource $source) => $source->latestRun?->status);
        $queries = count(DB::getQueryLog());

        expect($page->total())->toBe(3)
            ->and($page->getCollection()->every(fn (FeedSource $source) => $source->latestRun?->status === FeedRunStatus::Failed))->toBeTrue()
            ->and($queries)->toBeLessThanOrEqual(4);
    });

    it('does not find another merchant source, run or run errors', function () {
        $foreign = FeedSource::factory()->for($this->other)->create();
        $foreignRun = FeedRun::factory()->forSource($foreign)->completed()->create();

        expect(fn () => app(MerchantFeedSources::class)->find($this->merchant->id, $foreign->id))->toThrow(ModelNotFoundException::class)
            ->and(fn () => app(FeedRunHistory::class)->forSource($this->merchant->id, $foreign->id))->toThrow(ModelNotFoundException::class)
            ->and(fn () => app(FeedRunHistory::class)->find($this->merchant->id, $foreignRun->id))->toThrow(ModelNotFoundException::class)
            ->and(fn () => app(FeedRunErrors::class)->grouped($this->merchant->id, $foreignRun->id))->toThrow(ModelNotFoundException::class)
            ->and(fn () => app(FeedSamplePreview::class)->preview($this->merchant->id, $foreign->id))->toThrow(ModelNotFoundException::class);
    });

    it('lists the runs of a source newest first', function () {
        $source = FeedSource::factory()->for($this->merchant)->create();
        $older = FeedRun::factory()->forSource($source)->completed()->create();
        $newer = FeedRun::factory()->forSource($source)->failed()->create();

        expect(app(FeedRunHistory::class)->forSource($this->merchant->id, $source->id)->pluck('id')->all())->toBe([$newer->id, $older->id]);
    });
});

describe('run errors', function () {
    it('groups errors by code, fatal first, with capped counts and sample rows', function () {
        config(['comparo.feeds.max_errors_per_code' => 3]);
        $run = FeedRun::factory()->forSource(FeedSource::factory()->for($this->merchant)->create())->failed('REJECT_THRESHOLD_EXCEEDED')->create();
        $error = fn (array $attributes) => FeedError::factory()->create(['feed_run_id' => $run->id, ...$attributes]);
        foreach ([7, 3, 5] as $row) {
            $item = FeedItem::factory()->create(['feed_run_id' => $run->id, 'row_number' => $row, 'merchant_sku' => "PEA-{$row}"]);
            $error(['code' => 'INVALID_PRICE', 'row_number' => $row + 1, 'feed_item_id' => $item->id]);
        }
        $error(['code' => 'MISSING_GTIN', 'severity' => FeedErrorSeverity::Warning, 'field' => 'gtin', 'message_params' => null]);
        $error(['code' => 'REJECT_THRESHOLD_EXCEEDED', 'severity' => FeedErrorSeverity::Fatal, 'row_number' => null, 'field' => null, 'message_params' => ['percent' => 20]]);

        $groups = app(FeedRunErrors::class)->grouped($this->merchant->id, $run->id, samplesPerCode: 2);

        expect(array_map(fn (FeedErrorGroup $group) => [$group->code, $group->severity->value, $group->count, $group->capped], $groups))
            ->toBe([
                ['REJECT_THRESHOLD_EXCEEDED', 'fatal', 1, false],
                ['INVALID_PRICE', 'error', 3, true],
                ['MISSING_GTIN', 'warning', 1, false],
            ])
            ->and($groups[0]->messageKey)->toBe('feeds.errors.REJECT_THRESHOLD_EXCEEDED')
            ->and($groups[1]->samples)->toBe([
                ['row_number' => 4, 'field' => 'price', 'params' => ['value' => 'abc'], 'merchant_sku' => 'PEA-3'],
                ['row_number' => 6, 'field' => 'price', 'params' => ['value' => 'abc'], 'merchant_sku' => 'PEA-5'],
            ]);

        expect(app(FeedRunErrors::class)->rows($this->merchant->id, $run->id, 'INVALID_PRICE')->total())->toBe(3);
    });
});

describe('sample preview', function () {
    beforeEach(function () {
        Http::preventStrayRequests();
        Storage::fake('local');
        FeedPipelineFixtures::currencies();
        $this->source = FeedSource::factory()->for($this->merchant)->create(['delimiter' => null]);
    });

    it('previews the latest payload with a suggested mapping and row verdicts, without fetching', function () {
        FeedRun::factory()->forSource($this->source)->completed()->create([
            'payload_path' => FeedPipelineFixtures::storePayload($this->source, FeedPipelineFixtures::fixture('duplicate-sku.csv')),
        ]);

        $preview = app(FeedSamplePreview::class)->preview($this->merchant->id, $this->source->id, limit: 3);

        expect($preview->available)->toBeTrue()
            ->and($preview->headers)->toBe(['merchant_sku', 'title', 'brand', 'ean', 'price', 'currency', 'availability', 'url'])
            ->and($preview->mappingSuggested)->toBeTrue()
            ->and($preview->mapping['gtin'])->toBe('ean')
            ->and($preview->missingRequired)->toBe([])
            ->and(array_map(fn ($row) => $row->status, $preview->rows))
            ->toBe([FeedItemValidationStatus::Warning, FeedItemValidationStatus::Warning, FeedItemValidationStatus::Invalid])
            ->and($preview->rows[0]->values['price_minor'])->toBe(4054)
            ->and($preview->rows[2]->issues[0]['code'])->toBe('DUPLICATE_SKU');
    });

    it('uses the current mapping, or a draft one, and reports unmapped required fields', function () {
        $path = FeedPipelineFixtures::storePayload($this->source, "sku,name,price,availability,link\nPEA-1,Whey,19.90,in_stock,https://peaksupps.de/p/1\n");
        FeedMapping::factory()->forSource($this->source)->current()->create();
        $preview = app(FeedSamplePreview::class);

        $current = $preview->preview($this->merchant->id, $this->source->id, $path);
        $draft = $preview->preview($this->merchant->id, $this->source->id, $path, FieldMapping::fromArray(['merchant_sku' => 'sku', 'title' => 'name']));

        expect($current->mappingSuggested)->toBeFalse()
            ->and($current->rows[0]->status)->not->toBe(FeedItemValidationStatus::Invalid)
            ->and($draft->missingRequired)->toBe(['price', 'availability', 'product_url'])
            ->and($draft->rows[0]->status)->toBe(FeedItemValidationStatus::Invalid);
    });

    it('is unavailable without a stored payload and refuses samples outside the merchant directory', function () {
        $foreign = FeedSource::factory()->for($this->other)->create();
        $foreignPath = FeedPipelineFixtures::storePayload($foreign, FeedPipelineFixtures::fixture('duplicate-sku.csv'));

        expect(app(FeedSamplePreview::class)->preview($this->merchant->id, $this->source->id)->available)->toBeFalse()
            ->and(fn () => app(FeedSamplePreview::class)->preview($this->merchant->id, $this->source->id, $foreignPath))
            ->toThrow(InvalidArgumentException::class);
    });

    it('reports an unreadable sample with its error code', function () {
        $path = FeedPipelineFixtures::storePayload($this->source, FeedPipelineFixtures::HEADER."\n");

        $preview = app(FeedSamplePreview::class)->preview($this->merchant->id, $this->source->id, $path);

        expect($preview->available)->toBeTrue()
            ->and($preview->errorCode)->toBe('EMPTY_FEED')
            ->and($preview->rows)->toBe([]);
    });
});
