<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Locale;
use App\Services\I18n\I18nFileService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LocaleController extends Controller
{
    public function __construct(
        private I18nFileService $i18nFiles
    ) {}

    public function index(Request $request): Response
    {
        $locales = Locale::orderByRaw('is_default DESC')->orderBy('sort_order')->orderBy('code')->get();

        $translationsLocale = $request->query('translations_locale', 'en');
        $translationsGroup = $request->query('translations_group', '');
        $translationsSearch = $request->query('translations_search', '');
        $translationsMissingOnly = $request->boolean('translations_missing');

        $enFlat = $this->i18nFiles->getFlatDictionary('en');
        $localeFlat = $this->i18nFiles->getFlatDictionary($translationsLocale);

        $allKeys = $this->i18nFiles->allKeys();
        if ($allKeys === []) {
            $allKeys = array_keys($enFlat);
        }
        if ($allKeys === []) {
            $allKeys = array_keys($localeFlat);
        }

        $translations = [];
        foreach ($allKeys as $flatKey) {
            $enVal = $enFlat[$flatKey] ?? '';
            $val = $localeFlat[$flatKey] ?? '';

            if ($translationsGroup !== '' && ! str_starts_with($flatKey, $translationsGroup.'.')) {
                continue;
            }
            if ($translationsSearch !== '') {
                $search = $translationsSearch;
                if (stripos($flatKey, $search) === false && stripos($val, $search) === false && stripos($enVal, $search) === false) {
                    continue;
                }
            }
            if ($translationsMissingOnly && $val !== '' && $val !== $enVal) {
                continue;
            }

            $parts = explode('.', $flatKey);
            $group = count($parts) > 1 ? $parts[0] : 'app';
            $key = count($parts) > 1 ? implode('.', array_slice($parts, 1)) : $flatKey;

            $translations[] = [
                'flat_key' => $flatKey,
                'group' => $group,
                'key' => $key,
                'value' => $val,
                'en_value' => $enVal,
            ];
        }

        usort($translations, fn ($a, $b) => strcmp($a['flat_key'], $b['flat_key']));
        $groups = $this->i18nFiles->groups();

        return Inertia::render('Admin/Locales/Index', [
            'locales' => $locales,
            'translations' => $translations,
            'translationsLocale' => $translationsLocale,
            'translationsGroup' => $translationsGroup,
            'translationsSearch' => $translationsSearch,
            'translationsMissingOnly' => $translationsMissingOnly,
            'groups' => $groups,
        ]);
    }

    public function store(Request $request): \Illuminate\Http\RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:10', 'unique:locales,code', 'regex:/^[a-z]{2,6}$/'],
            'name' => ['required', 'string', 'max:128'],
            'native_name' => ['nullable', 'string', 'max:128'],
            'flag' => ['nullable', 'string', 'max:20'],
            'enabled' => ['boolean'],
            'is_rtl' => ['boolean'],
            'sort_order' => ['integer', 'min:0'],
        ]);
        $validated['enabled'] = $validated['enabled'] ?? true;
        $validated['is_rtl'] = $validated['is_rtl'] ?? false;
        $validated['sort_order'] = $validated['sort_order'] ?? 0;
        $validated['native_name'] = $validated['native_name'] ?? $validated['name'];

        Locale::create($validated);
        $this->i18nFiles->createLocaleFile($validated['code'], true);
        $this->i18nFiles->invalidateCache();

        return redirect()->route('admin.locales.index')->with('success', 'Locale added.');
    }

    public function update(Request $request, Locale $locale): \Illuminate\Http\RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:128'],
            'native_name' => ['nullable', 'string', 'max:128'],
            'flag' => ['nullable', 'string', 'max:20'],
            'enabled' => ['boolean'],
            'is_rtl' => ['boolean'],
            'sort_order' => ['integer', 'min:0'],
        ]);

        $locale->update($validated);
        $this->i18nFiles->invalidateCache();

        return back()->with('success', 'Locale updated.');
    }

    public function setDefault(Locale $locale): \Illuminate\Http\RedirectResponse
    {
        if (! $locale->enabled) {
            return back()->with('error', 'Enable the locale first.');
        }
        Locale::where('is_default', true)->update(['is_default' => false]);
        $locale->update(['is_default' => true]);
        $this->i18nFiles->invalidateCache();

        return back()->with('success', $locale->name.' is now the default language.');
    }

    public function destroy(Locale $locale): \Illuminate\Http\RedirectResponse
    {
        if ($locale->is_default) {
            return back()->with('error', 'Cannot delete the default language. Set another as default first.');
        }
        $enabledCount = Locale::where('enabled', true)->count();
        if ($enabledCount <= 1) {
            return back()->with('error', 'Cannot delete the last enabled language.');
        }
        $locale->delete();
        $this->i18nFiles->invalidateCache();

        return back()->with('success', 'Language removed.');
    }
}
