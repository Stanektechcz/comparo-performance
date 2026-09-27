<?php

use App\Domain\Orders\DisputeStatus;
use App\Domain\Orders\ReturnStatus;
use App\Domain\Reviews\ReviewStatus;
use App\Models\ContentReport;
use App\Models\DeliveryEvent;
use App\Models\Merchant;
use App\Models\Order;
use App\Models\OrderDispute;
use App\Models\OrderEvent;
use App\Models\OrderItem;
use App\Models\OrderReturn;
use App\Models\Product;
use App\Models\PurchaseProof;
use App\Models\RatingAggregate;
use App\Models\Review;
use App\Models\ReviewModerationEvent;
use App\Models\ReviewReply;
use App\Models\ReviewSignal;
use App\Models\ReviewSubRating;
use App\Models\ReviewVote;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Phase 4 schema invariants (docs/architecture/phase-4-reviews-orders.md §2):
 * one review per user and subject, subject XOR and rating CHECKs, append-only
 * moderation / order / delivery history, user erasure that nulls user ids
 * without touching history, RESTRICT foreign keys, one open return / dispute
 * per order. Written to run on SQLite and PostgreSQL.
 */

/**
 * Runs a write inside a savepoint and reports whether the database rejected
 * it (PostgreSQL aborts the whole test transaction otherwise).
 */
function phase4Rejects(Closure $write): bool
{
    try {
        DB::transaction($write);
    } catch (QueryException) {
        return true;
    }

    return false;
}

/**
 * @return list<string>
 */
function phase4TriggerNames(string $table): array
{
    $names = match (DB::getDriverName()) {
        'sqlite' => DB::table('sqlite_master')->where('type', 'trigger')->where('tbl_name', $table)->pluck('name')->all(),
        'pgsql' => array_column(DB::select('SELECT tgname FROM pg_trigger WHERE tgrelid = ?::regclass AND NOT tgisinternal', [$table]), 'tgname'),
        default => [],
    };
    sort($names);

    return array_map(strval(...), $names);
}

/**
 * @return list<list<string>>
 */
function phase4UniqueColumns(string $table): array
{
    return array_values(array_map(
        static fn (array $index): array => array_values($index['columns']),
        array_filter(Schema::getIndexes($table), static fn (array $index): bool => $index['unique'] && ! $index['primary']),
    ));
}

/**
 * @return array<string, string|null> foreign key column => ON DELETE action (lower case)
 */
function phase4OnDelete(string $table): array
{
    $actions = [];
    foreach (Schema::getForeignKeys($table) as $foreignKey) {
        $actions[$foreignKey['columns'][0]] = is_string($foreignKey['on_delete']) ? strtolower($foreignKey['on_delete']) : null;
    }
    ksort($actions);

    return $actions;
}

const PHASE4_TABLES = [
    'reviews', 'review_sub_ratings', 'review_signals', 'review_moderation_events', 'review_votes', 'content_reports',
    'review_replies', 'purchase_proofs', 'rating_aggregates', 'orders', 'order_items', 'order_events',
    'delivery_events', 'order_returns', 'order_disputes', 'notifications',
];

it('creates every Phase 4 table', function () {
    foreach (PHASE4_TABLES as $table) {
        expect(Schema::hasTable($table))->toBeTrue("missing table {$table}");
    }
});

it('keeps user ids, addresses, names and tracking numbers out of the order history', function () {
    foreach (['order_events', 'delivery_events'] as $table) {
        expect(Schema::getColumnListing($table))->not->toContain('user_id');
    }

    foreach (['orders', 'order_items', 'order_events', 'delivery_events', 'order_returns', 'order_disputes'] as $table) {
        expect(preg_grep('/address|street|postcode|zip|phone|email|name|tracking/i', Schema::getColumnListing($table)))
            ->toBe([], "table {$table}");
    }

    expect(Schema::getColumnListing('review_moderation_events'))->not->toContain('user_id');
});

it('restricts every foreign key except the user references erasure nulls', function () {
    $restrict = static fn (array $columns): array => array_fill_keys($columns, 'restrict');

    expect(phase4OnDelete('reviews'))->toBe([
        ...$restrict(['merchant_id', 'product_id', 'purchased_from_merchant_id']),
        'user_id' => 'set null',
        'verified_order_id' => 'restrict',
    ])
        ->and(phase4OnDelete('review_votes'))->toBe(['review_id' => 'restrict', 'user_id' => 'set null'])
        ->and(phase4OnDelete('review_replies'))->toBe(['author_user_id' => 'set null', 'merchant_id' => 'restrict', 'review_id' => 'restrict'])
        ->and(phase4OnDelete('content_reports'))->toBe([
            'decided_by_user_id' => 'set null',
            'reporter_merchant_id' => 'restrict',
            'reporter_user_id' => 'set null',
            'review_id' => 'restrict',
            'review_reply_id' => 'restrict',
        ])
        ->and(phase4OnDelete('purchase_proofs'))->toBe([
            'decided_by_user_id' => 'set null',
            ...$restrict(['merchant_id', 'order_id', 'review_id']),
            'user_id' => 'set null',
        ])
        ->and(phase4OnDelete('orders'))->toBe(['country_id' => 'restrict', 'merchant_id' => 'restrict', 'user_id' => 'set null'])
        ->and(phase4OnDelete('order_items'))->toBe($restrict(['offer_id', 'order_id', 'product_id']))
        ->and(phase4OnDelete('review_sub_ratings'))->toBe($restrict(['review_id']))
        ->and(phase4OnDelete('review_signals'))->toBe($restrict(['duplicate_of_review_id', 'review_id']))
        ->and(phase4OnDelete('rating_aggregates'))->toBe($restrict(['merchant_id', 'product_id']))
        ->and(phase4OnDelete('order_returns'))->toBe($restrict(['order_id']))
        ->and(phase4OnDelete('order_disputes'))->toBe($restrict(['order_id']));

    // Append-only tables: only RESTRICT (SET NULL / CASCADE would be a rejected UPDATE / DELETE).
    expect(phase4OnDelete('review_moderation_events'))->toBe($restrict(['content_report_id', 'review_id']))
        ->and(phase4OnDelete('order_events'))->toBe($restrict(['order_id', 'supersedes_id']))
        ->and(phase4OnDelete('delivery_events'))->toBe($restrict(['order_id', 'supersedes_id']));
});

it('declares the designed unique indexes', function () {
    expect(phase4UniqueColumns('reviews'))->toContain(['user_id', 'product_id'])->toContain(['user_id', 'merchant_id'])
        ->and(phase4UniqueColumns('review_sub_ratings'))->toContain(['review_id', 'dimension'])
        ->and(phase4UniqueColumns('review_signals'))->toContain(['review_id'])
        ->and(phase4UniqueColumns('review_votes'))->toContain(['review_id', 'user_id'])
        ->and(phase4UniqueColumns('review_replies'))->toContain(['review_id'])
        ->and(phase4UniqueColumns('purchase_proofs'))->toContain(['order_id'])
        ->and(phase4UniqueColumns('rating_aggregates'))->toContain(['product_id'])->toContain(['merchant_id'])
        ->and(phase4UniqueColumns('orders'))->toContain(['public_reference'])->toContain(['click_reference'])
        ->and(phase4UniqueColumns('order_events'))->toContain(['supersedes_id'])
        ->and(phase4UniqueColumns('delivery_events'))->toContain(['supersedes_id']);
});

it('allows one review per user and subject', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create();
    $merchant = Merchant::factory()->create();
    Review::factory()->for($user)->forProduct($product)->create();
    Review::factory()->for($user)->forMerchant($merchant)->create();

    expect(phase4Rejects(fn () => Review::factory()->for($user)->forProduct($product)->create()))->toBeTrue()
        ->and(phase4Rejects(fn () => Review::factory()->for($user)->forMerchant($merchant)->create()))->toBeTrue()
        // Other users, and erased authors (NULL user ids are distinct), never collide.
        ->and(Review::factory()->forProduct($product)->create()->exists)->toBeTrue()
        ->and(Review::factory()->count(2)->forProduct($product)->create(['user_id' => null]))->toHaveCount(2);
});

it('enforces exactly one subject matching the subject type', function () {
    $product = Product::factory()->create();
    $merchant = Merchant::factory()->create();
    $row = static fn (array $overrides): array => [
        'rating' => 4,
        'body' => 'A long enough review body for the check.',
        'submitted_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
        ...$overrides,
    ];

    expect(phase4Rejects(fn () => DB::table('reviews')->insert($row(['subject_type' => 'product', 'product_id' => $product->id, 'merchant_id' => $merchant->id]))))->toBeTrue()
        ->and(phase4Rejects(fn () => DB::table('reviews')->insert($row(['subject_type' => 'product']))))->toBeTrue()
        ->and(phase4Rejects(fn () => DB::table('reviews')->insert($row(['subject_type' => 'merchant', 'product_id' => $product->id]))))->toBeTrue()
        ->and(phase4Rejects(fn () => DB::table('reviews')->insert($row(['subject_type' => 'shop', 'merchant_id' => $merchant->id]))))->toBeTrue()
        ->and(phase4Rejects(fn () => DB::table('reviews')->insert($row(['subject_type' => 'merchant', 'merchant_id' => $merchant->id]))))->toBeFalse()
        ->and(phase4Rejects(fn () => RatingAggregate::factory()->create(['product_id' => $product->id, 'merchant_id' => $merchant->id])))->toBeTrue()
        ->and(phase4Rejects(fn () => RatingAggregate::factory()->forMerchant($merchant)->create(['subject_type' => 'product'])))->toBeTrue()
        ->and(RatingAggregate::factory()->forMerchant($merchant)->create()->exists)->toBeTrue()
        ->and(phase4Rejects(fn () => RatingAggregate::factory()->forMerchant($merchant)->create()))->toBeTrue();
});

it('keeps ratings and sub-ratings between 1 and 5', function (int $rating, bool $accepted) {
    expect(phase4Rejects(fn () => Review::factory()->rating($rating)->create()))->toBe(! $accepted)
        ->and(phase4Rejects(fn () => ReviewSubRating::factory()->dimension('value', $rating)->create()))->toBe(! $accepted);
})->with([
    'zero' => [0, false],
    'one' => [1, true],
    'five' => [5, true],
    'six' => [6, false],
]);

it('rejects a second vote, reply and sub-rating dimension on one review', function () {
    $vote = ReviewVote::factory()->create();
    $reply = ReviewReply::factory()->create();
    $subRating = ReviewSubRating::factory()->dimension('packaging')->create();

    expect(phase4Rejects(fn () => ReviewVote::factory()->notHelpful()->create(['review_id' => $vote->review_id, 'user_id' => $vote->user_id])))->toBeTrue()
        ->and(phase4Rejects(fn () => ReviewReply::factory()->forReview($reply->review)->create()))->toBeTrue()
        ->and(phase4Rejects(fn () => ReviewSubRating::factory()->dimension('packaging')->create(['review_id' => $subRating->review_id])))->toBeTrue()
        ->and(phase4Rejects(fn () => ReviewSignal::factory()->count(2)->create(['review_id' => $subRating->review_id])))->toBeTrue();
});

it('rejects duplicate order references and a second order per proof', function () {
    $order = Order::factory()->fromConversion('clk_abc')->create();
    PurchaseProof::factory()->verified($order)->create();

    expect(phase4Rejects(fn () => Order::factory()->create(['public_reference' => $order->public_reference])))->toBeTrue()
        ->and(phase4Rejects(fn () => Order::factory()->fromConversion('clk_abc')->create()))->toBeTrue()
        ->and(phase4Rejects(fn () => PurchaseProof::factory()->verified($order)->create()))->toBeTrue()
        ->and(Order::factory()->count(2)->create()->pluck('click_reference')->all())->toBe([null, null]);
});

it('allows one open return and one open dispute per order', function () {
    $order = Order::factory()->delivered()->create();
    OrderReturn::factory()->for($order)->refunded()->create();
    OrderReturn::factory()->for($order)->create();

    expect(phase4Rejects(fn () => OrderReturn::factory()->for($order)->sentBack()->create()))->toBeTrue()
        ->and(ReturnStatus::openValues())->toBe(['requested', 'sent_back']);

    OrderDispute::factory()->for($order)->expired()->create();
    OrderDispute::factory()->for($order)->create();

    expect(phase4Rejects(fn () => OrderDispute::factory()->for($order)->create()))->toBeTrue()
        ->and(DisputeStatus::openValues())->toBe(['open'])
        ->and(OrderReturn::factory()->create()->exists)->toBeTrue();
});

it('rejects raw updates and deletes of the append-only Phase 4 history', function (string $table, Closure $create) {
    $row = $create();

    expect(phase4Rejects(fn () => DB::table($table)->where('id', $row->id)->update(['created_at' => now()->subYear()])))->toBeTrue()
        ->and(phase4Rejects(fn () => DB::table($table)->where('id', $row->id)->delete()))->toBeTrue()
        ->and(DB::table($table)->where('id', $row->id)->exists())->toBeTrue()
        ->and(phase4TriggerNames($table))->toBe(DB::getDriverName() === 'pgsql'
            ? ["{$table}_append_only"]
            : ["{$table}_no_delete", "{$table}_no_update"]);
})->with([
    'review_moderation_events' => ['review_moderation_events', fn () => ReviewModerationEvent::factory()->create()],
    'order_events' => ['order_events', fn () => OrderEvent::factory()->create()],
    'delivery_events' => ['delivery_events', fn () => DeliveryEvent::factory()->create()],
]);

it('erases a user by nulling their ids without touching append-only history', function () {
    $user = User::factory()->create();
    $order = Order::factory()->for($user)->delivered()->create();
    $review = Review::factory()->for($user)->purchasedFrom($order->merchant)->verified($order)->approved()->create();
    $vote = ReviewVote::factory()->create(['user_id' => $user->id]);
    $report = ContentReport::factory()->create(['reporter_user_id' => $user->id, 'decided_by_user_id' => $user->id]);
    $reply = ReviewReply::factory()->create(['author_user_id' => $user->id]);
    $proof = PurchaseProof::factory()->forReview($review)->verified($order, $user)->create();
    $moderation = ReviewModerationEvent::factory()->forReview($review)->byActor($user)->create();
    $orderEvent = OrderEvent::factory()->for($order)->create();
    $delivery = DeliveryEvent::factory()->for($order)->delivered()->create();
    $user->notifications()->create(['id' => (string) Str::uuid(), 'type' => 'review.published', 'data' => ['review_id' => $review->id]]);

    $user->delete();

    expect($review->fresh()->user_id)->toBeNull()
        ->and($review->fresh()->verified_order_id)->toBe($order->id)
        ->and($order->fresh()->user_id)->toBeNull()
        ->and($vote->fresh()->user_id)->toBeNull()
        ->and($report->fresh())->reporter_user_id->toBeNull()->decided_by_user_id->toBeNull()
        ->and($reply->fresh()->author_user_id)->toBeNull()
        ->and($proof->fresh())->user_id->toBeNull()->decided_by_user_id->toBeNull()->order_id->toBe($order->id)
        // History is untouched: the actor id stays as recorded (no foreign key).
        ->and($moderation->fresh()->actor_user_id)->toBe($user->id)
        ->and($orderEvent->fresh()->order_id)->toBe($order->id)
        ->and($delivery->fresh()->order_id)->toBe($order->id)
        // The notifiable morph has no foreign key: the eraser removes notifications explicitly.
        ->and(DB::table('notifications')->where('notifiable_id', $user->id)->count())->toBe(1);
});

it('refuses to delete reviewed products, merchants with orders and rows with history', function () {
    $review = Review::factory()->approved()->create();
    ReviewModerationEvent::factory()->forReview($review)->create();
    $order = Order::factory()->create();
    OrderEvent::factory()->for($order)->create();
    $item = OrderItem::factory()->for($order)->create();

    expect(phase4Rejects(fn () => DB::table('products')->where('id', $review->product_id)->delete()))->toBeTrue()
        ->and(phase4Rejects(fn () => DB::table('reviews')->where('id', $review->id)->delete()))->toBeTrue()
        ->and(phase4Rejects(fn () => DB::table('merchants')->where('id', $order->merchant_id)->delete()))->toBeTrue()
        ->and(phase4Rejects(fn () => DB::table('orders')->where('id', $order->id)->delete()))->toBeTrue()
        ->and(phase4Rejects(fn () => DB::table('products')->where('id', $item->product_id)->delete()))->toBeTrue()
        ->and(Review::query()->whereKey($review->id)->exists())->toBeTrue()
        ->and(Order::query()->whereKey($order->id)->exists())->toBeTrue();
});

it('records a correction as a new event superseding the old one exactly once', function () {
    $dispatched = DeliveryEvent::factory()->create();
    $correction = DeliveryEvent::factory()->corrects($dispatched)->create();
    $placed = OrderEvent::factory()->create();
    OrderEvent::factory()->corrects($placed)->create();

    expect($correction->supersedes->is($dispatched))->toBeTrue()
        ->and($dispatched->fresh()->supersededBy->is($correction))->toBeTrue()
        ->and(phase4Rejects(fn () => DeliveryEvent::factory()->corrects($dispatched)->create()))->toBeTrue()
        ->and(phase4Rejects(fn () => OrderEvent::factory()->corrects($placed)->create()))->toBeTrue();
});

it('stores database notifications for a user', function () {
    $user = User::factory()->create();
    $user->notifications()->create(['id' => (string) Str::uuid(), 'type' => 'review.published', 'data' => ['review_id' => 7]]);

    expect($user->unreadNotifications()->sole()->data)->toBe(['review_id' => 7]);
});

it('never serializes review signal hashes or receipt locations', function () {
    $signal = ReviewSignal::factory()->create();
    $proof = PurchaseProof::factory()->create();

    expect($signal->toArray())->not->toHaveKey('ip_hash')->not->toHaveKey('user_agent_hash')
        ->and($proof->toArray())->not->toHaveKey('receipt_path')->not->toHaveKey('order_reference_hash')->not->toHaveKey('receipt_sha256')
        ->and(ReviewStatus::Approved->isPublic())->toBeTrue()
        ->and(collect(ReviewStatus::cases())->filter->isPublic()->count())->toBe(1);
});
