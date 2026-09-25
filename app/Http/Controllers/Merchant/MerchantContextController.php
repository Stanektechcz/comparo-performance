<?php

namespace App\Http\Controllers\Merchant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Merchant\SwitchMerchantContextRequest;
use Illuminate\Http\RedirectResponse;

class MerchantContextController extends Controller
{
    public function update(SwitchMerchantContextRequest $request): RedirectResponse
    {
        $request->session()->put('merchant.active_id', $request->validated('merchant_id'));

        return back();
    }
}
