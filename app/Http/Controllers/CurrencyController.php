<?php

namespace App\Http\Controllers;

use App\Models\Currency;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CurrencyController extends Controller
{
    /**
     * Update user's display currency (UI only; billing currency is plan-dependent).
     */
    public function update(Request $request): RedirectResponse
    {
        $codes = Currency::where('enabled', true)->pluck('code')->all();

        $validated = $request->validate([
            'currency' => ['required', 'string', Rule::in($codes)],
        ]);

        if ($request->user()) {
            $request->user()->update(['display_currency' => $validated['currency']]);
        } else {
            $request->session()->put('display_currency', $validated['currency']);
        }

        return back();
    }
}
