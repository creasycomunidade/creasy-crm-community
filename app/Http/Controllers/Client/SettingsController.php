<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\ClientSetting;
use App\Models\Currency;
use App\Models\Locale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $supportedLocales = Locale::enabled()->orderByRaw('is_default DESC')->orderBy('sort_order')->get(['code', 'name']);
        if ($supportedLocales->isEmpty()) {
            $supportedLocales = collect([['code' => 'en', 'name' => 'English']]);
        }
        $supportedCurrencies = Currency::where('enabled', true)->orderBy('code')->get(['code', 'symbol']);

        $client = null;
        if ($user->client_id && $user->isClientAdministrator()) {
            $c = $user->client;
            if ($c) {
                $client = [
                    'id' => $c->id,
                    'name' => $c->name,
                    'email' => $c->email,
                    'phone' => $c->phone,
                    'address' => $c->address,
                ];
            }
        }

        return Inertia::render('client/Settings/Index', [
            'preferences' => [
                'locale' => $user->locale ?? config('app.locale', 'pt'),
                'display_currency' => $user->display_currency ?? Currency::defaultCode() ?? 'BRL',
                'theme' => $user->theme ?? 'light',
                'timezone' => $user->timezone ?? 'America/Sao_Paulo',
            ],
            'supportedLocales' => $supportedLocales->map(fn ($l) => ['code' => $l->code, 'name' => $l->name]),
            'supportedCurrencies' => $supportedCurrencies->map(fn ($c) => ['code' => $c->code, 'name' => $c->code, 'symbol' => $c->symbol ?? $c->code]),
            'client' => $client,
            'digestEnabled' => $user->client_id
                ? ClientSetting::get($user->client_id, 'weekly_digest_enabled', '1') !== '0'
                : true,
        ]);
    }

    public function notifications(Request $request): Response
    {
        $user = $request->user();

        $preferences = $user->notificationPreferences
            ->groupBy('event')
            ->map(fn ($group) => $group->mapWithKeys(fn ($p) => [$p->channel => (bool) $p->enabled]));

        return Inertia::render('client/Settings/Notifications', [
            'preferences' => $preferences,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        $localeCodes = Locale::enabled()->pluck('code')->all() ?: ['pt'];
        $currencyCodes = Currency::where('enabled', true)->pluck('code')->all() ?: ['BRL'];

        $validated = $request->validate([
            'locale' => ['nullable', 'string', 'max:16', Rule::in($localeCodes)],
            'display_currency' => ['nullable', 'string', 'max:10', Rule::in($currencyCodes)],
            'theme' => ['nullable', 'string', 'in:light,dark'],
            'timezone' => ['nullable', 'string', 'max:64', 'timezone:all'],
            'client_name' => ['nullable', 'string', 'max:255'],
            'client_email' => ['nullable', 'email', 'max:255'],
            'client_phone' => ['nullable', 'string', 'max:64'],
            'client_address' => ['nullable', 'string'],
            'weekly_digest_enabled' => ['nullable', 'boolean'],
        ]);

        if (array_key_exists('locale', $validated) && $validated['locale'] !== null) {
            $user->locale = $validated['locale'];
        }
        if (array_key_exists('display_currency', $validated) && $validated['display_currency'] !== null) {
            $user->display_currency = $validated['display_currency'];
        }
        if (array_key_exists('theme', $validated) && $validated['theme'] !== null) {
            $user->theme = $validated['theme'];
        }
        if (array_key_exists('timezone', $validated) && $validated['timezone'] !== null) {
            $user->timezone = $validated['timezone'];
        }
        $user->save();

        if ($user->client_id && $user->isClientAdministrator() && $user->client) {
            $client = $user->client;
            if (array_key_exists('client_name', $validated)) {
                $client->name = $validated['client_name'] ?? $client->name;
            }
            if (array_key_exists('client_email', $validated)) {
                $client->email = $validated['client_email'];
            }
            if (array_key_exists('client_phone', $validated)) {
                $client->phone = $validated['client_phone'];
            }
            if (array_key_exists('client_address', $validated)) {
                $client->address = $validated['client_address'];
            }
            $client->save();
        }

        // Digest preference
        if ($user->client_id && array_key_exists('weekly_digest_enabled', $validated)) {
            ClientSetting::set($user->client_id, 'weekly_digest_enabled', $validated['weekly_digest_enabled'] ? '1' : '0');
        }

        return redirect()->route('client.settings.index')->with('success', __('Settings saved.'));
    }
}
