<?php

use App\Domain\Feeds\Events\FeedFailed;
use App\Domain\Feeds\Events\FeedImported;
use App\Domain\Feeds\FeedErrorSeverity;
use App\Domain\Feeds\FeedItemMatchStatus;
use App\Domain\Feeds\FeedItemValidationStatus;
use App\Domain\Feeds\FeedRunOutcome;
use App\Domain\Feeds\FeedRunStatus;
use App\Domain\Feeds\Jobs\FinalizeFeedRun;
use App\Domain\Feeds\Jobs\MatchFeedItems;
use App\Domain\Feeds\Jobs\ParseFeedPayload;
use App\Domain\Feeds\Jobs\PublishFeedRun;
use App\Models\FeedError;
use App\Models\FeedItem;
use App\Models\FeedMapping;
use App\Models\FeedRun;
use App\Models\FeedSource;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Feeds\FeedPipelineFixtures;

/**
 * A run in `parsing` over the given payload, then the parse job.
 */
function parsePayload(FeedSource $source, string $payload, ?FeedMapping $mapping = null): FeedRun
{
    $run = FeedRun::factory()->forSource($source)->running(FeedRunStatus::Parsing)->create([
        'payload_path' => FeedPipelineFixtures::storePayload($source, $payload),
        'feed_mapping_id' => $mapping?->id,
    ]);

    $job = (new ParseFeedPayload($run->id))->withFakeQueueInteractions();
    app()->call([$job, 'handle']);

    return $run->refresh();
}

/**
 * @return array<string, int>
 */
function storedErrorCodes(FeedRun $run): array
{
    return FeedError::query()->where('feed_run_id', $run->id)->pluck('code')->countBy()->sortKeys()->all();
}

beforeEach(function () {
    Storage::fake('local');
    Event::fake([FeedImported::class, FeedFailed::class]);
    FeedPipelineFixtures::currencies();
    $this->source = FeedSource::factory()->for(FeedPipelineFixtures::merchant())->active()->create(['delimiter' => null]);
});

it('stages every row with its validation verdict and fails the run above the reject threshold', function () {
    $run = parsePayload($this->source, FeedPipelineFixtures::fixture('invalid-rows.csv'));

    expect($run->status)->toBe(FeedRunStatus::Failed)
        ->and($run->failure_code)->toBe('REJECT_THRESHOLD_EXCEEDED')
        ->and($run->failure_reason)->toContain('More than 20% of rows failed validation')
        ->and($run->only(['rows_read', 'rows_valid', 'rows_invalid']))->toBe(['rows_read' => 10, 'rows_valid' => 2, 'rows_invalid' => 8])
        ->and($run->errors)->toBeGreaterThan(8);

    $items = FeedItem::query()->where('feed_run_id', $run->id)->orderBy('row_number')->get();
    expect($items)->toHaveCount(10)
        ->and($items[0]->validation_status)->toBe(FeedItemValidationStatus::Valid)
        ->and($items[0]->raw_payload)->toBeNull()
        ->and($items[0]->reference_price_minor)->toBe(5480)
        ->and($items[1]->validation_status)->toBe(FeedItemValidationStatus::Invalid)
        ->and($items[1]->match_status)->toBe(FeedItemMatchStatus::Skipped)
        ->and($items[1]->raw_payload['title'])->toBe('Whey Isolate 90 Vanilla 900 g')
        ->and($items[7]->merchant_sku)->toHaveLength(128)
        ->and($items[9]->validation_status)->toBe(FeedItemValidationStatus::Warning)
        ->and($items[9]->raw_payload)->not->toBeNull();

    expect(storedErrorCodes($run))->toMatchArray([
        'MISSING_SKU' => 1,
        'MISSING_REQUIRED_FIELD' => 1,
        'INVALID_PRICE' => 1,
        'INVALID_CURRENCY' => 1,
        'INVALID_AVAILABILITY' => 1,
        'INVALID_URL' => 1,
        'FIELD_TOO_LONG' => 1,
        'IMPOSSIBLE_DISCOUNT' => 1,
        'INVALID_STOCK' => 1,
        'URL_DOMAIN_MISMATCH' => 1,
        'INVALID_IMAGE_URL' => 1,
        'REJECT_THRESHOLD_EXCEEDED' => 1,
    ]);

    $priceError = FeedError::query()->where('code', 'INVALID_PRICE')->sole();
    expect($priceError->row_number)->toBe(5)
        ->and($priceError->severity)->toBe(FeedErrorSeverity::Error)
        ->and($priceError->message_params)->toBe(['field' => 'price', 'value' => 'abc'])
        ->and($priceError->item->merchant_sku)->toBe('PEA-302');
    Event::assertDispatched(FeedFailed::class, fn (FeedFailed $event) => $event->code === 'REJECT_THRESHOLD_EXCEEDED');
});

it('publishes with warnings when the rejected share stays under the threshold', function () {
    config(['comparo.feeds.max_rejected_ratio' => 0.9]);

    $run = parsePayload($this->source, FeedPipelineFixtures::fixture('invalid-rows.csv'));

    expect($run->status)->toBe(FeedRunStatus::Normalizing)
        ->and($run->parsed_at)->not->toBeNull()
        ->and($run->warnings)->toBe(4);

    MatchFeedItems::dispatchSync($run->id);
    PublishFeedRun::dispatchSync($run->id);
    FinalizeFeedRun::dispatchSync($run->id, FeedRunStatus::Publishing);

    expect($run->refresh()->status)->toBe(FeedRunStatus::Completed)
        ->and($run->outcome)->toBe(FeedRunOutcome::PublishedWithWarnings);
    Event::assertDispatched(FeedImported::class, fn (FeedImported $event) => $event->outcome === 'published_with_warnings');
});

it('keeps the first occurrence of a duplicate SKU and rejects the repeats', function () {
    config(['comparo.feeds.max_rejected_ratio' => 0.5]);

    $run = parsePayload($this->source, FeedPipelineFixtures::fixture('duplicate-sku.csv'));

    $duplicate = FeedError::query()->where('code', 'DUPLICATE_SKU')->sole();
    expect($run->status)->toBe(FeedRunStatus::Normalizing)
        ->and($run->only(['rows_valid', 'rows_invalid']))->toBe(['rows_valid' => 2, 'rows_invalid' => 1])
        ->and($duplicate->row_number)->toBe(4)
        ->and($duplicate->message_params)->toBe(['sku' => 'PEA-186', 'first_line' => 2])
        ->and(FeedItem::query()->where('merchant_sku', 'PEA-186')->orderBy('row_number')->get()
            ->map(fn (FeedItem $item) => [$item->validation_status->isPublishable(), $item->price_minor])->all())
        ->toBe([[true, 4054], [false, null]]);
});

it('uses the mapping pinned on the run and ignores legacy keys in it', function () {
    $mapping = FeedMapping::factory()->forSource($this->source)->current()->create();
    $payload = "sku,name,price,currency,availability,link\nPEA-1,Whey 900 g,19.90,EUR,in_stock,https://peaksupps.de/p/1\n";

    $run = parsePayload($this->source, $payload, $mapping);

    expect($run->status)->toBe(FeedRunStatus::Normalizing)
        ->and(FeedItem::query()->sole()->only(['merchant_sku', 'title', 'price_minor']))
        ->toBe(['merchant_sku' => 'PEA-1', 'title' => 'Whey 900 g', 'price_minor' => 1990]);
});

it('fails the run when a required field cannot be mapped', function (?array $fieldMap, string $payload, string $field) {
    $mapping = $fieldMap === null ? null : FeedMapping::factory()->forSource($this->source)->current()->create(['field_map' => $fieldMap]);

    $run = parsePayload($this->source, $payload, $mapping);

    expect($run->status)->toBe(FeedRunStatus::Failed)
        ->and($run->failure_code)->toBe('MISSING_REQUIRED_FIELD')
        ->and(FeedError::query()->where('feed_run_id', $run->id)->sole()->only(['severity', 'message_params']))
        ->toBe(['severity' => FeedErrorSeverity::Fatal, 'message_params' => ['field' => $field]])
        ->and(FeedItem::query()->count())->toBe(0);
})->with([
    'pinned mapping without price' => [
        ['merchant_sku' => 'sku', 'title' => 'name', 'availability' => 'availability', 'product_url' => 'link'],
        "sku,name,cost,availability,link\nPEA-1,Whey,19.90,in_stock,https://peaksupps.de/p/1\n",
        'price',
    ],
    'no mapping and unknown headers' => [
        null,
        "col_a,col_b\nPEA-1,Whey\n",
        'merchant_sku',
    ],
]);

it('caps stored errors per code while the metrics keep the true totals', function () {
    config(['comparo.feeds.max_errors_per_code' => 2]);
    $rows = array_map(fn (int $i) => "PEA-{$i},Whey {$i},19.90,EUR,in_stock,https://peaksupps.de/p/{$i}", range(1, 5));

    $run = parsePayload($this->source, "merchant_sku,title,price,currency,availability,url\n".implode("\n", $rows)."\n");

    expect($run->warnings)->toBe(5)
        ->and(storedErrorCodes($run))->toBe(['MISSING_GTIN' => 2]);
});

it('fails with the parser code and line for unreadable payloads', function (string $payload, string $code, ?int $line, array $params) {
    config(['comparo.feeds.max_rows' => 2]);

    $run = parsePayload($this->source, $payload);

    $fatal = FeedError::query()->where('feed_run_id', $run->id)->where('severity', 'fatal')->sole();
    expect($run->failure_code)->toBe($code)
        ->and($fatal->row_number)->toBe($line)
        ->and($fatal->message_params)->toBe($params === [] ? null : $params);
})->with([
    'empty feed' => [FeedPipelineFixtures::HEADER."\n", 'EMPTY_FEED', null, []],
    'unterminated quote' => [FeedPipelineFixtures::HEADER."\n".FeedPipelineFixtures::row('PEA-1')."\nPEA-2,\"broken,1,2\n", 'PARSER_ERROR', 3, ['format' => 'CSV']],
    'too many rows' => [FeedPipelineFixtures::csv([FeedPipelineFixtures::row('A'), FeedPipelineFixtures::row('B'), FeedPipelineFixtures::row('C')]), 'ROW_LIMIT_EXCEEDED', 4, ['limit' => 2]],
]);

it('replaces the staging rows of an earlier attempt of the same run', function () {
    $run = FeedRun::factory()->forSource($this->source)->running(FeedRunStatus::Parsing)->create([
        'payload_path' => FeedPipelineFixtures::storePayload($this->source, FeedPipelineFixtures::csv([FeedPipelineFixtures::row('PEA-1')])),
    ]);
    foreach ([1, 2, 3] as $rowNumber) {
        FeedItem::factory()->create(['feed_run_id' => $run->id, 'row_number' => $rowNumber]);
    }

    app()->call([(new ParseFeedPayload($run->id))->withFakeQueueInteractions(), 'handle']);

    expect(FeedItem::query()->where('feed_run_id', $run->id)->pluck('merchant_sku')->all())->toBe(['PEA-1']);
});

it('leaves a run that is not parsing untouched', function () {
    $run = FeedRun::factory()->forSource($this->source)->running(FeedRunStatus::Normalizing)->create([
        'payload_path' => FeedPipelineFixtures::storePayload($this->source, FeedPipelineFixtures::csv([FeedPipelineFixtures::row('PEA-1')])),
    ]);

    app()->call([(new ParseFeedPayload($run->id))->withFakeQueueInteractions(), 'handle']);

    expect($run->refresh()->status)->toBe(FeedRunStatus::Normalizing)
        ->and(FeedItem::query()->count())->toBe(0);
});
