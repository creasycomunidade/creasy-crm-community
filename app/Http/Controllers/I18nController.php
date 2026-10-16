<?php

namespace App\Http\Controllers;

use App\Models\Locale;
use App\Services\I18n\I18nFileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class I18nController extends Controller
{
    public function __construct(
        private I18nFileService $i18nFiles
    ) {}

    /**
     * Return JSON dictionary for the given locale (flat key => value) from resources/js/locales/{locale}.json.
     */
    public function show(Request $request, string $locale): JsonResponse
    {
        $enabled = Locale::enabled()->pluck('code')->all();
        if (empty($enabled)) {
            $enabled = ['pt'];
        }
        if (! in_array($locale, $enabled, true)) {
            $locale = Locale::defaultCode();
        }
        if (! in_array($locale, $enabled, true)) {
            $locale = $enabled[0];
        }

        $dictionary = $this->i18nFiles->getFlatDictionary($locale);

        return response()->json(['translation' => $dictionary]);
    }
}
