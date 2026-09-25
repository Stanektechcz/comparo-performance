<?php

namespace App\Http\Requests\Merchant\Feeds;

use App\Domain\Feeds\FeedSourceStatus;
use App\Http\Requests\Merchant\ResolvesMerchantFeed;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Pause or resume a feed's schedule (owner/manager).
 */
class ChangeFeedStatusRequest extends FormRequest
{
    use ResolvesMerchantFeed;

    public const string PAUSE = 'pause';

    public const string RESUME = 'resume';

    public function authorize(): bool
    {
        return $this->allowsOnFeed('update');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['action' => ['required', 'string', Rule::in([self::PAUSE, self::RESUME])]];
    }

    public function targetStatus(): FeedSourceStatus
    {
        return $this->string('action')->toString() === self::PAUSE ? FeedSourceStatus::Paused : FeedSourceStatus::Active;
    }
}
