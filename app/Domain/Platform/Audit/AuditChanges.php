<?php

namespace App\Domain\Platform\Audit;

use Illuminate\Database\Eloquent\Model;

/**
 * Extracts the before/after pair of an updated model for the audit log.
 *
 * Works both before `save()` (dirty attributes vs. original) and right after
 * it (the attributes changed by the last save vs. their previous values).
 * Timestamps are left out. Values are the stored (raw) representation, so
 * encrypted attributes are never decrypted; JSON-cast attributes are decoded
 * so the redactor can reach nested secrets. Hidden and encrypted attributes
 * are masked outright.
 */
final class AuditChanges
{
    /**
     * @return array{before: array<string, mixed>, after: array<string, mixed>}
     */
    public static function fromModel(Model $model): array
    {
        $isPending = $model->isDirty();
        $after = $isPending ? $model->getDirty() : $model->getChanges();
        $previous = $isPending ? $model->getRawOriginal() : $model->getPrevious();

        $ignored = array_filter([$model->getCreatedAtColumn(), $model->getUpdatedAtColumn()]);
        $changes = ['before' => [], 'after' => []];

        foreach ($after as $key => $value) {
            if (in_array($key, $ignored, true)) {
                continue;
            }

            $changes['before'][$key] = self::present($model, $key, $previous[$key] ?? null);
            $changes['after'][$key] = self::present($model, $key, $value);
        }

        return $changes;
    }

    private static function present(Model $model, string $key, mixed $raw): mixed
    {
        if ($raw === null) {
            return null;
        }

        $cast = strtolower((string) ($model->getCasts()[$key] ?? ''));

        if (in_array($key, $model->getHidden(), true) || str_contains($cast, 'encrypt') || $cast === 'hashed') {
            return AuditRedactor::MASK;
        }

        if (is_string($raw) && self::isJsonCast($cast)) {
            $decoded = json_decode($raw, true);

            return json_last_error() === JSON_ERROR_NONE ? $decoded : $raw;
        }

        return $raw;
    }

    private static function isJsonCast(string $cast): bool
    {
        foreach (['array', 'json', 'collection', 'object'] as $jsonCast) {
            if (str_contains($cast, $jsonCast)) {
                return true;
            }
        }

        return false;
    }
}
