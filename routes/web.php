<?php

use App\Http\Controllers\CmsPageController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\CurrencyController;
use App\Http\Controllers\I18nController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\ThemeController;
use App\Http\Controllers\WorkspaceFileShareController;
use App\Models\CmsPage;
use App\Models\SystemSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Route;

// Home / Landing
Route::get('/', fn () => redirect()->route('login'))->name('home');
Route::get('/f/{token}', [WorkspaceFileShareController::class, 'show'])
    ->middleware('throttle:120,1')
    ->name('public.workspace-file-shares.show');

// Auth routes
require __DIR__.'/auth.php';

// Locale / currency / theme
Route::put('/locale', [LocaleController::class, 'update'])->name('locale.update');
Route::get('/i18n/{locale}', [I18nController::class, 'show'])->name('i18n.show');
Route::put('/currency', [CurrencyController::class, 'update'])->name('currency.update');
Route::post('/theme/update', [ThemeController::class, 'update'])->name('theme.update');

// Public marketing pages
Route::get('/contact', [ContactController::class, 'show'])->name('contact');
Route::post('/contact', [ContactController::class, 'store'])->name('contact.store');

// Public marketing landing pages
Route::get('/faq', [LandingController::class, 'faq'])->name('faq');
Route::get('/use-cases', [LandingController::class, 'useCases'])->name('use-cases');
Route::get('/about', [LandingController::class, 'about'])->name('about');
Route::get('/integrations', [LandingController::class, 'integrations'])->name('integrations');

// CMS pages (e.g. /p/privacy, /p/terms)
Route::get('/p/{slug}', [CmsPageController::class, 'show'])->name('cms-page.show');

// Sitemap & robots.txt
Route::get('/sitemap.xml', function () {
    $landingEnabled = true;
    try {
        $landingEnabled = SystemSetting::get('landing.page_enabled', '1') === '1';
    } catch (Throwable $e) {
        // table may not exist yet
    }
    $urls = $landingEnabled
        ? [url('/'), url('/faq'), url('/use-cases'), url('/about'), url('/integrations'), url('/contact'), route('login'), route('register')]
        : [route('login'), route('register')];
    try {
        $cmsPages = CmsPage::where('published', true)->get();
        foreach ($cmsPages as $page) {
            $urls[] = route('cms-page.show', $page->slug);
        }
    } catch (Throwable $e) {
        // table may not exist yet
    }
    $xml = '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
    foreach ($urls as $url) {
        $xml .= '<url><loc>'.htmlspecialchars($url).'</loc></url>';
    }
    $xml .= '</urlset>';

    return response($xml, 200)->header('Content-Type', 'application/xml');
})->name('sitemap');

Route::get('/robots.txt', function () {
    $sitemap = route('sitemap');

    return response(
        "User-agent: *\nDisallow: /admin/\nDisallow: /painel/\nSitemap: {$sitemap}",
        200
    )->header('Content-Type', 'text/plain');
})->name('robots');


// ─── Health / readiness probes ───────────────────────────────────────────────
Route::get('/healthz', fn () => response()->json([
    'status' => 'ok',
    'service' => config('app.name'),
    'time' => now()->toIso8601String(),
]))->middleware('throttle:120,1')->name('healthz');

// Protected by a shared secret token (HEALTHZ_TOKEN env var). Set to a random
// string in production and pass via Authorization: Bearer <token> header.
Route::middleware('throttle:30,1')->group(function () {
    $guardHealthz = function (Request $request): bool {
        $token = config('app.healthz_token');

        return filled($token) && hash_equals($token, $request->bearerToken() ?? '');
    };

    Route::get('/healthz/db', function () use ($guardHealthz) {
        if (! $guardHealthz(request())) {
            return response()->json(['error' => 'Unauthorized.'], 401);
        }
        try {
            DB::selectOne('SELECT 1');

            return response()->json(['status' => 'ok', 'db' => 'connected']);
        } catch (Throwable $e) {
            return response()->json(['status' => 'error', 'db' => 'database error'], 503);
        }
    })->name('healthz.db');

    Route::get('/healthz/redis', function () use ($guardHealthz) {
        if (! $guardHealthz(request())) {
            return response()->json(['error' => 'Unauthorized.'], 401);
        }
        try {
            Redis::ping();

            return response()->json(['status' => 'ok', 'redis' => 'connected']);
        } catch (Throwable $e) {
            return response()->json(['status' => 'error', 'redis' => 'redis error'], 503);
        }
    })->name('healthz.redis');

    Route::get('/healthz/queue', function () use ($guardHealthz) {
        if (! $guardHealthz(request())) {
            return response()->json(['error' => 'Unauthorized.'], 401);
        }
        try {
            $size = Queue::size('default');

            return response()->json(['status' => 'ok', 'queue_driver' => config('queue.default'), 'default_size' => $size]);
        } catch (Throwable $e) {
            return response()->json(['status' => 'error', 'queue' => 'queue error'], 503);
        }
    })->name('healthz.queue');
});
