<?php

namespace App\Http\Controllers\Merchant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Merchant\SwitchMerchantContextRequest;
use Illuminate\Http\RedirectResponse;

/**
 * Switches the active merchant. Lands on the feeds overview: the previous
 * page may show an id of the old merchant, which is a 404 in the new one.
 */
class MerchantContextController extends Controller
{
    public function update(SwitchMerchantContextRequest $request): RedirectResponse
    {
        $request->session()->put('merchant.active_id', $request->validated('merchant_id'));

        return to_route('merchant.feeds.index');
    }
}
