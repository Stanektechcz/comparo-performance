<?php

namespace App\Domain\Platform\PrototypeImport;

use App\Domain\Pricing\History\SnapshotReason;
use App\Models\MarketPriceStat;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * The prototype's synthetic 365-day price series.
 *
 * - market_price_stats: one ALL-market row per product and day (upserted;
 *   demo rows that fall outside the current window are pruned — derived data).
 * - price_snapshots (append-only): each product.hist.byMerchant series is
 *   attached to that merchant's first seed offer for the product, one row per
 *   day at 00:00 UTC; then every offer gets a `first_seen` row with its current
 *   price at source_updated_at. Offers that already have demo snapshots are skipped.
 */
final readonly class PriceHistoryImport
{
    public function __construct(
        private PrototypeSnapshot $snapshot,
        private TimeShift $time,
        private ImportReport $report,
    ) {}

    public function run(): void
    {
        $this->importMarketStats();
        $this->importSnapshots();
    }

    private function importMarketStats(): void
    {
        $now = $this->time->anchorDb();
        $rows = [];
        $earliest = null;
        $productIds = [];

        foreach ($this->snapshot->records('products') as $product) {
            $productId = (int) $product['id'];
            $productIds[] = $productId;
            $minSeries = array_values((array) ($product['hist']['min'] ?? []));
            $avgSeries = array_values((array) ($product['hist']['avg'] ?? []));

            foreach ($minSeries as $index => $minPrice) {
                if ($minPrice === null) {
                    $this->report->note('Missing market-min price point (skipped)', "product {$productId} day {$index}");

                    continue;
                }

                $day = $this->dayOf($index, count($minSeries));
                $earliest = $earliest === null || $day->lessThan($earliest) ? $day : $earliest;
                $rows[] = [
                    'product_id' => $productId,
                    'market' => MarketPriceStat::ALL_MARKETS,
                    'stat_date' => $day->toDateString(),
                    'min_price_minor' => PrototypeSnapshotImporter::minor($minPrice),
                    'avg_price_minor' => PrototypeSnapshotImporter::minor($avgSeries[$index] ?? null),
                    'currency' => PrototypeSnapshotImporter::CURRENCY,
                    'offer_count' => null,
                    'source' => PrototypeSnapshotImporter::SOURCE,
                    'computed_at' => $now,
                ];
            }
        }

        if ($earliest !== null) {
            DB::table('market_price_stats')
                ->where('source', PrototypeSnapshotImporter::SOURCE)
                ->whereIn('product_id', $productIds)
                ->where(fn ($query) => $query
                    ->where('stat_date', '<', $earliest->toDateString())
                    ->orWhere('stat_date', '>', $this->time->anchorDay()->toDateString()))
                ->delete();
        }

        ChunkedWriter::upsert('market_price_stats', $rows, ['product_id', 'market', 'stat_date']);
    }

    private function importSnapshots(): void
    {
        $offers = $this->snapshot->records('offers');
        $alreadyImported = DB::table('price_snapshots')
            ->where('source', PrototypeSnapshotImporter::SOURCE)
            ->distinct()
            ->pluck('offer_id')
            ->flip()
            ->all();

        $firstOfferFor = [];
        foreach ($offers as $offer) {
            $firstOfferFor[$offer['productId'].'|'.$offer['merchantId']] ??= $offer;
        }

        $writer = ChunkedWriter::into('price_snapshots');

        foreach ($this->snapshot->records('products') as $product) {
            foreach ((array) ($product['hist']['byMerchant'] ?? []) as $merchantId => $series) {
                $offer = $firstOfferFor[$product['id'].'|'.$merchantId] ?? null;

                if ($offer === null) {
                    $this->report->note('Merchant price series without an offer (skipped)', "product {$product['id']} merchant {$merchantId}");

                    continue;
                }

                if (! isset($alreadyImported[(int) $offer['id']])) {
                    $this->addSeries($writer, $offer, array_values((array) $series));
                }
            }
        }

        foreach ($offers as $offer) {
            if (! isset($alreadyImported[(int) $offer['id']])) {
                $writer->add($this->snapshotRow(
                    $offer,
                    PrototypeSnapshotImporter::minor($offer['price']),
                    $this->time->db($offer['updated'] ?? null) ?? $this->time->anchorDb(),
                    SnapshotReason::FirstSeen,
                    empty($offer['oldPrice']) ? null : PrototypeSnapshotImporter::minor($offer['oldPrice']),
                    (string) $offer['availability'],
                ));
            }
        }

        $writer->flush();
    }

    /**
     * @param  array<string, mixed>  $offer
     * @param  list<mixed>  $series
     */
    private function addSeries(ChunkedWriter $writer, array $offer, array $series): void
    {
        foreach ($series as $index => $price) {
            if (! is_numeric($price)) {
                $this->report->note('Missing merchant price point (skipped)', "offer {$offer['id']} day {$index}");

                continue;
            }

            $writer->add($this->snapshotRow(
                $offer,
                PrototypeSnapshotImporter::minor($price),
                $this->dayOf($index, count($series))->format(TimeShift::DB_FORMAT),
                SnapshotReason::PrototypeImport,
            ));
        }
    }

    /**
     * @param  array<string, mixed>  $offer
     * @return array<string, mixed>
     */
    private function snapshotRow(array $offer, ?int $priceMinor, string $observedAt, SnapshotReason $reason, ?int $referenceMinor = null, ?string $availability = null): array
    {
        return [
            'offer_id' => (int) $offer['id'],
            'product_id' => (int) $offer['productId'],
            'merchant_id' => (int) $offer['merchantId'],
            'price_minor' => $priceMinor,
            'currency' => PrototypeSnapshotImporter::CURRENCY,
            'reference_price_minor' => $referenceMinor,
            'shipping_minor' => null,
            'shipping_country_code' => null,
            'availability' => $availability,
            'reason' => $reason->value,
            'source' => PrototypeSnapshotImporter::SOURCE,
            'feed_run_id' => null,
            'corrects_snapshot_id' => null,
            'observed_at' => $observedAt,
            'created_at' => $this->time->anchorDb(),
        ];
    }

    /**
     * Series index → calendar day: the last point is the anchor's day.
     */
    private function dayOf(int $index, int $length): CarbonImmutable
    {
        return $this->time->anchorDay()->subDays($length - 1 - $index);
    }
}
