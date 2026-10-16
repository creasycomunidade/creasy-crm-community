<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Currency;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CurrencyController extends Controller
{
    public function index(): Response
    {

        $currencies = Currency::orderBy('code')->get();

        return Inertia::render('Admin/Currencies/Index', ['currencies' => $currencies]);
    }

    public function store(Request $request): \Illuminate\Http\RedirectResponse
    {
        $request->merge(['code' => strtoupper(trim((string) $request->input('code')))]);

        $validated = $request->validate([
            'code' => ['required', 'string', 'min:2', 'max:10', 'unique:currencies,code'],
            'symbol' => ['required', 'string', 'max:10'],
            'decimals' => ['nullable', 'integer', 'min:0', 'max:6'],
            'exchange_rate' => ['nullable', 'numeric', 'min:0.00000001'],
            'is_default' => ['boolean'],
            'enabled' => ['boolean'],
        ]);

        $validated['decimals'] = $validated['decimals'] ?? 2;
        $validated['exchange_rate'] = $validated['exchange_rate'] ?? 1;

        if (! empty($validated['is_default'])) {
            Currency::query()->update(['is_default' => false]);
            $validated['exchange_rate'] = 1;
        }

        Currency::create($validated);

        return back()->with('success', __('Currency added.'));
    }

    public function update(Request $request, Currency $currency): \Illuminate\Http\RedirectResponse
    {

        $validated = $request->validate([
            'symbol' => ['required', 'string', 'max:16'],
            'decimals' => ['integer', 'min:0', 'max:6'],
            'exchange_rate' => ['nullable', 'numeric', 'min:0.00000001'],
            'is_default' => ['boolean'],
            'enabled' => ['boolean'],
        ]);

        if (! empty($validated['is_default'])) {
            Currency::where('code', '!=', $currency->code)->update(['is_default' => false]);
            $validated['exchange_rate'] = 1;
        }

        $currency->update($validated);

        return back()->with('success', __('Currency updated.'));
    }
}
