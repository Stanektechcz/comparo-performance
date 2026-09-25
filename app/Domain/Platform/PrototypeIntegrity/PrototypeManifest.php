<?php

namespace App\Domain\Platform\PrototypeIntegrity;

use InvalidArgumentException;

/**
 * The browser prototype is the behaviour specification and must stay byte-for-byte
 * unchanged (ADR 0010). `docs/prototype/SHA256SUMS` lists every protected file in
 * `sha256sum` format; this verifies the working tree against it.
 */
final readonly class PrototypeManifest
{
    public const string DEFAULT_PATH = 'docs/prototype/SHA256SUMS';

    /**
     * @param  array<string, string>  $hashes  relative path => lowercase SHA-256
     */
    private function __construct(private array $hashes) {}

    public static function fromFile(string $manifestPath): self
    {
        if (! is_file($manifestPath)) {
            throw new InvalidArgumentException("Prototype manifest not found: {$manifestPath}");
        }

        return self::parse((string) file_get_contents($manifestPath));
    }

    public static function parse(string $contents): self
    {
        $hashes = [];

        foreach (preg_split('/\R/', $contents) ?: [] as $number => $line) {
            if (trim($line) === '') {
                continue;
            }

            if (preg_match('/^([0-9a-f]{64}) [ *](.+)$/', $line, $match) !== 1) {
                throw new InvalidArgumentException('Malformed prototype manifest line '.($number + 1).'.');
            }

            $raw = str_replace('\\', '/', $match[2]);
            $path = str_starts_with($raw, './') ? substr($raw, 2) : $raw;

            if ($path === '' || str_starts_with($path, '/') || in_array('..', explode('/', $path), true)) {
                throw new InvalidArgumentException('Unsafe path on prototype manifest line '.($number + 1).'.');
            }

            $hashes[$path] = $match[1];
        }

        if ($hashes === []) {
            throw new InvalidArgumentException('Prototype manifest lists no files.');
        }

        return new self($hashes);
    }

    public function count(): int
    {
        return count($this->hashes);
    }

    public function verify(string $basePath): PrototypeIntegrityReport
    {
        $missing = [];
        $changed = [];

        foreach ($this->hashes as $path => $expected) {
            $absolute = rtrim($basePath, '/\\').DIRECTORY_SEPARATOR.$path;

            if (! is_file($absolute)) {
                $missing[] = $path;

                continue;
            }

            if (! hash_equals($expected, (string) hash_file('sha256', $absolute))) {
                $changed[] = $path;
            }
        }

        return new PrototypeIntegrityReport(count($this->hashes), $missing, $changed);
    }
}
