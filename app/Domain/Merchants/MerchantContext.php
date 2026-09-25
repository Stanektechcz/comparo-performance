<?php

namespace App\Domain\Merchants;

/**
 * The active merchant for the current request (session-selected) plus the
 * user's role in it and every other merchant they belong to. Bound per
 * request by ResolveMerchantContext; merchant-facing code depends on this,
 * never on the raw session value.
 */
final readonly class MerchantContext
{
    /**
     * @param  list<array{id: int, slug: string, name: string, role: MerchantRole}>  $available
     */
    public function __construct(
        public int $merchantId,
        public string $merchantSlug,
        public string $merchantName,
        public MerchantRole $role,
        public array $available,
    ) {}

    /**
     * @return array{active: array{id: int, slug: string, name: string, role: string}, available: list<array{id: int, slug: string, name: string, role: string}>}
     */
    public function toArray(): array
    {
        return [
            'active' => [
                'id' => $this->merchantId,
                'slug' => $this->merchantSlug,
                'name' => $this->merchantName,
                'role' => $this->role->value,
            ],
            'available' => array_map(
                static fn (array $membership): array => [
                    'id' => $membership['id'],
                    'slug' => $membership['slug'],
                    'name' => $membership['name'],
                    'role' => $membership['role']->value,
                ],
                $this->available,
            ),
        ];
    }
}
