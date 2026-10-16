<?php

namespace App\Http\Controllers;

use App\Models\Locale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LocaleController extends Controller
{
    /**
     * Update locale: user preference (auth) or session (guest/admin).
     * Validates against DB-enabled locales.
     */
    public function update(Request $request): RedirectResponse
    {
        $enabledCodes = Locale::enabled()->pluck('code')->all();
        if (empty($enabledCodes)) {
            $enabledCodes = ['pt'];
        }

        $validated = $request->validate([
            'locale' => ['required', 'string', 'in:'.implode(',', $enabledCodes)],
        ]);

        $user = $request->user();
        if ($user) {
            $user->update(['locale' => $validated['locale']]);
        } else {
            $request->session()->put('locale', $validated['locale']);
        }

        return back();
    }
}
