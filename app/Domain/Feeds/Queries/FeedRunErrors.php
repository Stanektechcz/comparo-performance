<?php

namespace App\Domain\Feeds\Queries;

use App\Domain\Feeds\FeedErrorCode;
use App\Domain\Feeds\FeedErrorSeverity;
use App\Models\FeedError;
use App\Models\FeedRun;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;

/**
 * The structured errors of one merchant's run: grouped by code (fatal first,
 * then by count) with a few sample rows each, or listed page by page.
 * Three queries regardless of the number of codes.
 */
final class FeedRunErrors
{
    private const array SEVERITY_ORDER = ['fatal' => 0, 'error' => 1, 'warning' => 2, 'info' => 3];

    public function __construct(private readonly FeedRunHistory $runs) {}

    /**
     * @return list<FeedErrorGroup>
     *
     * @throws ModelNotFoundException for a missing or foreign run id
     */
    public function grouped(int $merchantId, int $runId, int $samplesPerCode = 5): array
    {
        $run = $this->runs->find($merchantId, $runId);
        $cap = max(1, (int) config('comparo.feeds.max_errors_per_code', 1000));

        $totals = FeedError::query()
            ->where('feed_run_id', $run->id)
            ->selectRaw('code, severity, count(*) as total')
            ->groupBy('code', 'severity')
            ->toBase()
            ->get();

        $samples = $this->samples($run, max(1, $samplesPerCode));
        $groups = [];

        foreach ($totals as $total) {
            $key = $total->code.'|'.$total->severity;
            $groups[] = new FeedErrorGroup(
                code: (string) $total->code,
                severity: FeedErrorSeverity::from((string) $total->severity),
                messageKey: FeedErrorCode::tryFrom((string) $total->code)?->messageKey() ?? 'feeds.errors.'.$total->code,
                count: (int) $total->total,
                capped: (int) $total->total >= $cap,
                samples: $samples[$key] ?? [],
            );
        }

        usort($groups, static fn (FeedErrorGroup $a, FeedErrorGroup $b): int => [self::SEVERITY_ORDER[$a->severity->value], -$a->count, $a->code]
            <=> [self::SEVERITY_ORDER[$b->severity->value], -$b->count, $b->code]);

        return $groups;
    }

    /**
     * @return LengthAwarePaginator<int, FeedError>
     *
     * @throws ModelNotFoundException for a missing or foreign run id
     */
    public function rows(int $merchantId, int $runId, ?string $code = null, int $perPage = 50): LengthAwarePaginator
    {
        $run = $this->runs->find($merchantId, $runId);

        return FeedError::query()
            ->where('feed_run_id', $run->id)
            ->when($code !== null, static fn ($query) => $query->where('code', $code))
            ->orderBy('row_number')
            ->orderBy('id')
            ->paginate($perPage);
    }

    /**
     * Every stored error of a run the caller already resolved through a
     * merchant-scoped query, in row order, with the item's SKU, read lazily
     * in chunks (bounded memory for exports).
     *
     * @return LazyCollection<int, FeedError>
     */
    public function cursor(FeedRun $run, int $chunk = 500): LazyCollection
    {
        return FeedError::query()
            ->where('feed_run_id', $run->id)
            ->with('item:id,merchant_sku')
            ->orderBy('row_number')
            ->orderBy('id')
            ->lazy(max(1, $chunk));
    }

    /**
     * The first rows of every code, in one window-function query.
     *
     * @return array<string, list<array{row_number: int|null, field: string|null, params: array<string, mixed>, merchant_sku: string|null}>>
     */
    private function samples(FeedRun $run, int $perCode): array
    {
        $ranked = DB::table('feed_errors')
            ->leftJoin('feed_items', 'feed_items.id', '=', 'feed_errors.feed_item_id')
            ->where('feed_errors.feed_run_id', $run->id)
            ->select([
                'feed_errors.code', 'feed_errors.severity', 'feed_errors.row_number', 'feed_errors.field',
                'feed_errors.message_params', 'feed_items.merchant_sku',
            ])
            ->selectRaw('ROW_NUMBER() OVER (PARTITION BY feed_errors.code, feed_errors.severity ORDER BY feed_errors.row_number, feed_errors.id) AS position');

        $rows = DB::query()->fromSub($ranked, 'ranked')->where('position', '<=', $perCode)->orderBy('position')->get();
        $samples = [];

        foreach ($rows as $row) {
            $params = is_string($row->message_params) ? json_decode($row->message_params, true) : null;
            $samples[$row->code.'|'.$row->severity][] = [
                'row_number' => $row->row_number === null ? null : (int) $row->row_number,
                'field' => $row->field,
                'params' => is_array($params) ? $params : [],
                'merchant_sku' => $row->merchant_sku,
            ];
        }

        return $samples;
    }
}
