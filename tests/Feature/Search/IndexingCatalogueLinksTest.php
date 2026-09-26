<?php

use App\Domain\Search\Jobs\ProcessSearchOutbox;
use App\Models\Ingredient;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\SearchIndexOutbox;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

/**
 * F-12: product documents carry variant names and listed ingredients, and
 * ingredient documents count listed products — so variant writes and
 * ingredient_product link changes re-index the product (and the ingredient).
 */

/**
 * @return list<string> "entity:id" per outbox row
 */
function linkOutboxEntries(): array
{
    return SearchIndexOutbox::query()->orderBy('entity')->orderBy('entity_id')->get()
        ->map(static fn (SearchIndexOutbox $row): string => $row->entity->value.':'.$row->entity_id)
        ->all();
}

beforeEach(function () {
    $this->travelTo('2026-09-25 10:00:00');
    Queue::fake([ProcessSearchOutbox::class]);
    $this->product = Product::factory()->create();
    $this->other = Product::factory()->create();
    $this->ingredient = Ingredient::query()->create(['slug' => 'creatine', 'name' => 'Creatine']);
    SearchIndexOutbox::query()->delete();
});

it('queues the product when a variant is renamed or removed', function (Closure $change) {
    $variant = ProductVariant::query()->create(['product_id' => $this->product->id, 'kind' => ProductVariant::FLAVOUR, 'slug' => 'vanilla', 'name' => 'Vanilla']);
    SearchIndexOutbox::query()->delete();

    $change($variant);

    expect(linkOutboxEntries())->toBe(["product:{$this->product->id}"]);
})->with([
    'renamed' => [fn (ProductVariant $variant) => $variant->update(['name' => 'Vanilla Cream'])],
    'removed' => [fn (ProductVariant $variant) => $variant->delete()],
]);

it('queues the product of a new variant after commit only', function () {
    DB::transaction(function () {
        ProductVariant::query()->create(['product_id' => $this->product->id, 'kind' => ProductVariant::PACK, 'slug' => '2kg', 'name' => '2 kg']);

        expect(linkOutboxEntries())->toBe([]);
    });

    expect(linkOutboxEntries())->toBe(["product:{$this->product->id}"]);
});

it('queues both products when a variant moves to another product', function () {
    $variant = ProductVariant::query()->create(['product_id' => $this->product->id, 'kind' => ProductVariant::FLAVOUR, 'slug' => 'cocoa', 'name' => 'Cocoa']);
    SearchIndexOutbox::query()->delete();

    $variant->update(['product_id' => $this->other->id]);

    expect(linkOutboxEntries())->toBe(collect([$this->product->id, $this->other->id])->sort()->map(fn (int $id) => "product:{$id}")->values()->all());
});

it('ignores a variant saved without changes', function () {
    $variant = ProductVariant::query()->create(['product_id' => $this->product->id, 'kind' => ProductVariant::FLAVOUR, 'slug' => 'plain', 'name' => 'Plain']);
    SearchIndexOutbox::query()->delete();

    $variant->save();

    expect(linkOutboxEntries())->toBe([]);
});

it('queues the product and the ingredient when an ingredient link changes', function (Closure $change) {
    $this->product->ingredients()->attach($this->ingredient->id, ['is_listed' => true, 'position' => 1]);
    SearchIndexOutbox::query()->delete();

    $change($this->product, $this->ingredient);

    expect(linkOutboxEntries())->toBe(["ingredient:{$this->ingredient->id}", "product:{$this->product->id}"]);
})->with([
    'listed flag changed' => [fn (Product $product, Ingredient $ingredient) => $product->ingredients()->updateExistingPivot($ingredient->id, ['is_listed' => false])],
    'detached' => [fn (Product $product, Ingredient $ingredient) => $product->ingredients()->detach($ingredient->id)],
    'synced away' => [fn (Product $product, Ingredient $ingredient) => $product->ingredients()->sync([])],
    'detached from the ingredient side' => [fn (Product $product, Ingredient $ingredient) => $ingredient->products()->detach($product->id)],
]);

it('queues the product and the ingredient when an ingredient is attached', function () {
    $this->product->ingredients()->attach($this->ingredient->id, ['is_listed' => true, 'position' => 1]);

    expect(linkOutboxEntries())->toBe(["ingredient:{$this->ingredient->id}", "product:{$this->product->id}"]);
});
