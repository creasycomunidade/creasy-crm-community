<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ThemeController extends Controller
{
    /**
     * Update the authenticated user's theme preference.
     * Guest requests are ignored (no error); frontend uses localStorage for guests.
     */
    public function update(Request $request)
    {
        $validated = $request->validate([
            'theme' => ['required', 'string', Rule::in(['dark', 'light'])],
        ]);

        $user = $request->user();
        if ($user) {
            $user->update(['theme' => $validated['theme']]);
        }

        return back();
    }
}
