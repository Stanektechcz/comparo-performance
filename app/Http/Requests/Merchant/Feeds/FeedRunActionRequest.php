<?php

namespace App\Http\Requests\Merchant\Feeds;

use App\Http\Requests\Merchant\ResolvesMerchantFeed;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Start a manual run or cancel a run of the feed (owner/manager). No input.
 */
class FeedRunActionRequest extends FormRequest
{
    use ResolvesMerchantFeed;

    public function authorize(): bool
    {
        return $this->allowsOnFeed('run');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
