<?php

use Illuminate\Support\Facades\Storage;

/**
 * A-35: purchase-proof receipts live on a private disk, never public
 * (docs/architecture/phase-4-reviews-orders.md §5). No route serves this
 * disk's contents directly; staff view a receipt only through a short-lived
 * signed URL (comparo.verification.signed_receipt_url_minutes), built
 * elsewhere once the verification module exists.
 */
it('configures the receipts disk as private, local and not served', function () {
    expect(config('filesystems.disks.receipts.driver'))->toBe('local')
        ->and(config('filesystems.disks.receipts.serve'))->toBeFalse()
        ->and(config('filesystems.disks.receipts.visibility'))->toBe('private')
        ->and(config('filesystems.disks.receipts.root'))->toBe(storage_path('app/private/receipts'));
});

it('stores a receipt outside the public disk root, unreachable by a public URL', function () {
    Storage::fake('receipts');

    Storage::disk('receipts')->put('proofs/example.pdf', 'not-a-real-receipt');

    expect(Storage::disk('receipts')->exists('proofs/example.pdf'))->toBeTrue()
        ->and(Storage::disk('public')->exists('proofs/example.pdf'))->toBeFalse();
});

it('has no configured URL generator for the receipts disk', function () {
    expect(config('filesystems.disks.receipts.url'))->toBeNull();
});

it('never serves a receipt through the public storage symlink target', function () {
    expect(config('filesystems.disks.receipts.root'))
        ->not->toBe(config('filesystems.disks.public.root'));
});
