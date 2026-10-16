<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use App\Services\StorageManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class SystemSettingsController extends Controller
{
    public function index(): Response
    {
        $generalKeys = ['app_name', 'app_tagline', 'support_email', 'primary_color'];

        $general = [];
        foreach ($generalKeys as $key) {
            $general[$key] = SystemSetting::get($key, '');
        }

        $logoPath    = SystemSetting::get('app_logo_path');
        $faviconPath = SystemSetting::get('app_favicon_path');

        $logoDisk    = SystemSetting::get('app_logo_disk', 'public');
        $faviconDisk = SystemSetting::get('app_favicon_disk', 'public');
        $sm = app(StorageManager::class);
        $sm->ensureDiskReady($logoDisk);
        $sm->ensureDiskReady($faviconDisk);
        $general['logo_url']    = $logoPath    ? Storage::disk($logoDisk)->url($logoPath)       : null;
        $general['favicon_url'] = $faviconPath ? Storage::disk($faviconDisk)->url($faviconPath) : null;

        $advanced = SystemSetting::orderBy('group')
            ->orderBy('key')
            ->whereNotIn('key', array_merge($generalKeys, ['app_logo_path', 'app_favicon_path']))
            ->get()
            ->map(fn ($s) => [
                'id'        => $s->id,
                'key'       => $s->key,
                'value'     => $s->is_secret
                    ? (strlen($s->attributes['value'] ?? '') > 0 ? '••••••••' : '')
                    : ($s->attributes['value'] ?? ''),
                'is_secret' => $s->is_secret,
                'group'     => $s->group,
            ]);

        $byGroup = $advanced->groupBy('group')->map->values();

        $firebase = [
            'enabled'    => SystemSetting::get('firebase_enabled', 'false') === 'true',
            'apiKey'     => SystemSetting::get('firebase_api_key', ''),
            'authDomain' => SystemSetting::get('firebase_auth_domain', ''),
            'projectId'  => SystemSetting::get('firebase_project_id', ''),
            'appId'      => SystemSetting::get('firebase_app_id', ''),
        ];

        return Inertia::render('Admin/Settings/Index', [
            'general'         => $general,
            'settingsByGroup' => $byGroup,
            'firebase'        => $firebase,
        ]);
    }

    public function updateGeneral(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'app_name'      => ['nullable', 'string', 'max:128'],
            'app_tagline'   => ['nullable', 'string', 'max:255'],
            'support_email' => ['nullable', 'email', 'max:255'],
            'primary_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);

        foreach ($validated as $key => $value) {
            SystemSetting::set($key, $value, false, 'general');
        }

        return back()->with('success', __('General settings saved.'));
    }

    public function uploadLogo(Request $request): RedirectResponse
    {
        $request->validate([
            'logo' => ['required', 'image', 'mimes:png,jpg,jpeg,gif,svg,webp', 'max:2048'],
        ]);

        $this->deleteFile('app_logo_path', 'app_logo_disk');

        $sm   = app(StorageManager::class);
        $disk = $sm->diskName();
        $file = $request->file('logo');
        $path = $sm->prefixedPath('branding/logo-'.Str::uuid().'.'.$file->getClientOriginalExtension());
        $sm->disk()->putFileAs(dirname($path), $file, basename($path));

        SystemSetting::set('app_logo_path', $path, false, 'general');
        SystemSetting::set('app_logo_disk', $disk, false, 'general');

        return back()->with('success', __('Logo uploaded.'));
    }

    public function deleteLogo(): RedirectResponse
    {
        $this->deleteFile('app_logo_path', 'app_logo_disk');
        SystemSetting::whereIn('key', ['app_logo_path', 'app_logo_disk'])->delete();

        return back()->with('success', __('Logo removed.'));
    }

    public function uploadFavicon(Request $request): RedirectResponse
    {
        $request->validate([
            'favicon' => ['required', 'file', 'mimes:png,jpg,jpeg,gif,ico,svg,webp', 'max:512'],
        ]);

        $this->deleteFile('app_favicon_path', 'app_favicon_disk');

        $sm   = app(StorageManager::class);
        $disk = $sm->diskName();
        $file = $request->file('favicon');
        $path = $sm->prefixedPath('branding/favicon-'.Str::uuid().'.'.$file->getClientOriginalExtension());
        $sm->disk()->putFileAs(dirname($path), $file, basename($path));

        SystemSetting::set('app_favicon_path', $path, false, 'general');
        SystemSetting::set('app_favicon_disk', $disk, false, 'general');

        return back()->with('success', __('Favicon uploaded.'));
    }

    public function deleteFavicon(): RedirectResponse
    {
        $this->deleteFile('app_favicon_path', 'app_favicon_disk');
        SystemSetting::whereIn('key', ['app_favicon_path', 'app_favicon_disk'])->delete();

        return back()->with('success', __('Favicon removed.'));
    }

    public function updateFirebase(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'firebase_enabled'     => ['required', 'in:true,false'],
            'firebase_api_key'     => ['nullable', 'string', 'max:255'],
            'firebase_auth_domain' => ['nullable', 'string', 'max:255'],
            'firebase_project_id'  => ['nullable', 'string', 'max:128'],
            'firebase_app_id'      => ['nullable', 'string', 'max:255'],
        ]);

        foreach ($validated as $key => $value) {
            SystemSetting::set($key, $value ?? '', false, 'firebase');
        }

        return back()->with('success', __('Firebase settings saved.'));
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'settings'             => ['required', 'array'],
            'settings.*.key'       => ['required', 'string', 'max:128'],
            'settings.*.value'     => ['nullable', 'string'],
            'settings.*.is_secret' => ['boolean'],
            'settings.*.group'     => ['nullable', 'string', 'max:64'],
        ]);

        foreach ($validated['settings'] as $s) {
            $model            = SystemSetting::firstOrNew(['key' => $s['key']]);
            $model->is_secret = $s['is_secret'] ?? false;
            $model->group     = $s['group'] ?? null;
            $value            = $s['value'] ?? null;
            if ($value !== null && $value !== '' && ! ($model->is_secret && preg_match('/^•+$/', (string) $value))) {
                $model->value = $value;
            }
            $model->save();
        }

        return back()->with('success', __('Settings saved.'));
    }

    private function deleteFile(string $pathKey, string $diskKey): void
    {
        $existing = SystemSetting::get($pathKey);
        $disk     = SystemSetting::get($diskKey, 'public');
        if ($existing) {
            app(StorageManager::class)->ensureDiskReady($disk);
            Storage::disk($disk)->delete($existing);
        }
    }
}
