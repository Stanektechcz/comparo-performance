<?php

namespace App\Http\Requests\Admin\Catalogue;

use App\Domain\Matching\ListingMatchStatus;
use App\Domain\Matching\Queries\QueuePages;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Query string of the staff matching console: tab, listing filters, page.
 * Access is enforced by the route (`staff.access` + `matching.review`).
 */
class MatchingQueueRequest extends FormRequest
{
    public const int MAX_SCORE = 100;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'tab' => ['nullable', 'string', Rule::in(MatchingQueueTab::values())],
            'merchant' => ['nullable', 'integer', 'min:1'],
            'status' => ['nullable', 'string', Rule::enum(ListingMatchStatus::class)],
            'minScore' => ['nullable', 'integer', 'min:0', 'max:'.self::MAX_SCORE],
            'maxScore' => ['nullable', 'integer', 'min:0', 'max:'.self::MAX_SCORE, Rule::when($this->filled('minScore'), 'gte:minScore')],
            'page' => ['nullable', 'integer', 'min:1'],
            'perPage' => ['nullable', 'integer', 'min:1', 'max:'.QueuePages::MAX_PER_PAGE],
        ];
    }

    public function tab(): MatchingQueueTab
    {
        return MatchingQueueTab::tryFrom($this->string('tab')->toString()) ?? MatchingQueueTab::Listings;
    }

    public function merchantId(): ?int
    {
        return $this->optionalInt('merchant');
    }

    public function status(): ?ListingMatchStatus
    {
        return ListingMatchStatus::tryFrom($this->string('status')->toString());
    }

    public function minScore(): ?int
    {
        return $this->optionalInt('minScore');
    }

    public function maxScore(): ?int
    {
        return $this->optionalInt('maxScore');
    }

    public function page(): int
    {
        return $this->optionalInt('page') ?? 1;
    }

    public function perPage(): int
    {
        return $this->optionalInt('perPage') ?? QueuePages::DEFAULT_PER_PAGE;
    }

    /**
     * The active listing filters, as echoed back to the filter form.
     *
     * @return array{merchant: ?int, status: ?string, minScore: ?int, maxScore: ?int}
     */
    public function filters(): array
    {
        return [
            'merchant' => $this->merchantId(),
            'status' => $this->status()?->value,
            'minScore' => $this->minScore(),
            'maxScore' => $this->maxScore(),
        ];
    }

    private function optionalInt(string $key): ?int
    {
        return $this->filled($key) ? $this->integer($key) : null;
    }
}
