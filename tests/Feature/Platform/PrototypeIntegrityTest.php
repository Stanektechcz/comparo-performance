<?php

use App\Domain\Platform\PrototypeIntegrity\PrototypeManifest;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->manifestDirectory = storage_path('framework/testing/prototype-manifest');
    File::ensureDirectoryExists($this->manifestDirectory);
});

afterEach(function () {
    File::deleteDirectory($this->manifestDirectory);
});

function writePrototypeManifest(string $directory, string $contents): string
{
    File::put($directory.'/SHA256SUMS', $contents);

    return 'storage/framework/testing/prototype-manifest/SHA256SUMS';
}

it('verifies every protected prototype file in the repository', function () {
    $manifest = PrototypeManifest::fromFile(base_path(PrototypeManifest::DEFAULT_PATH));

    expect($manifest->count())->toBe(137);

    $this->artisan('comparo:verify-prototype')
        ->expectsOutputToContain('137/137 protected files verified')
        ->assertSuccessful();
});

it('fails when a protected file changed', function () {
    $wrong = str_repeat('0', 64);
    $manifest = writePrototypeManifest($this->manifestDirectory, "{$wrong}  README.md\n");

    $this->artisan('comparo:verify-prototype', ['--manifest' => $manifest])
        ->expectsOutputToContain('CHANGED  README.md')
        ->assertFailed();
});

it('fails when a protected file is missing', function () {
    $hash = hash('sha256', 'x');
    $manifest = writePrototypeManifest($this->manifestDirectory, "{$hash}  seed-that-was-deleted.js\n");

    $this->artisan('comparo:verify-prototype', ['--manifest' => $manifest])
        ->expectsOutputToContain('MISSING  seed-that-was-deleted.js')
        ->assertFailed();
});

it('rejects malformed or unsafe manifests', function (string $contents) {
    $manifest = writePrototypeManifest($this->manifestDirectory, $contents);

    $this->artisan('comparo:verify-prototype', ['--manifest' => $manifest])->assertFailed();
})->with([
    'not a hash line' => "hello world\n",
    'path traversal' => str_repeat('a', 64)."  ../outside.txt\n",
    'empty manifest' => "\n",
]);
