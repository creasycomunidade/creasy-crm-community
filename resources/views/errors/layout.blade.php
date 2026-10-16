@php
    use App\Models\SystemSetting;

    // Resolve branding with graceful fallback (mirrors HandleInertiaRequests::brandingShare()).
    try {
        $appName  = SystemSetting::get('app_name') ?: config('saas.app_name', config('app.name'));
        $primary  = SystemSetting::get('primary_color') ?: config('saas.branding.primary_color', '#c45100');
        $logoPath = SystemSetting::get('app_logo_path');
        $logoUrl  = $logoPath
            ? \Illuminate\Support\Facades\Storage::disk(SystemSetting::get('app_logo_disk', 'public'))->url($logoPath)
            : null;
        $faviconPath = SystemSetting::get('app_favicon_path');
        $faviconUrl  = $faviconPath
            ? \Illuminate\Support\Facades\Storage::disk(SystemSetting::get('app_favicon_disk', 'public'))->url($faviconPath)
            : null;
    } catch (\Throwable) {
        $appName  = config('saas.app_name', config('app.name'));
        $primary  = config('saas.branding.primary_color', '#c45100');
        $logoUrl  = null;
        $faviconUrl = null;
    }

    // Pick readable text on the primary color from its relative luminance.
    $hex = ltrim($primary, '#');
    if (strlen($hex) === 3) {
        $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
    }
    [$r, $g, $b] = strlen($hex) === 6
        ? [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))]
        : [70, 114, 53];
    $luminance = (0.2126 * $r + 0.7152 * $g + 0.0722 * $b) / 255;
    $onPrimary = $luminance > 0.6 ? '#20321d' : '#ffffff';
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $code }} · {{ $title }} – {{ $appName }}</title>
    @if($faviconUrl)
        <link rel="icon" href="{{ $faviconUrl }}">
        <link rel="apple-touch-icon" href="{{ $faviconUrl }}">
    @else
        <link rel="icon" type="image/svg+xml" href="/creasy-community-icon.svg">
        <link rel="alternate icon" type="image/png" href="/favicon-32x32.png" sizes="32x32">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">
    @endif
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=space-grotesk:400,500,600,700&display=swap" rel="stylesheet" />
    <style>
        :root {
            --primary: {{ $primary }};
            --on-primary: {{ $onPrimary }};
            --ink: #20321d;       /* secondary-900 (dark green) */
            --ink-soft: #566b50;  /* muted green */
            --surface: #faf8ff;   /* brand surface */
        }
        *, *::before, *::after { box-sizing: border-box; }
        html, body { height: 100%; margin: 0; font-family: 'Space Grotesk', ui-sans-serif, system-ui, sans-serif; }
        body {
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            color: var(--ink); padding: 2rem; position: relative; overflow: hidden;
            background:
                radial-gradient(60rem 40rem at 50% -10%, color-mix(in srgb, var(--primary) 22%, transparent), transparent 70%),
                linear-gradient(180deg, var(--surface), #ffffff 60%);
        }
        /* Soft decorative glow blobs in the brand color */
        body::before, body::after {
            content: ''; position: fixed; border-radius: 50%; filter: blur(80px);
            background: var(--primary); opacity: .18; z-index: 0;
        }
        body::before { width: 30rem; height: 30rem; top: -10rem; left: -8rem; }
        body::after  { width: 26rem; height: 26rem; bottom: -10rem; right: -8rem; opacity: .12; }

        .brand {
            position: fixed; top: 1.5rem; left: 1.75rem; z-index: 2;
            display: inline-flex; align-items: center; gap: .6rem;
            font-weight: 700; font-size: 1.15rem; color: var(--ink); text-decoration: none; letter-spacing: -.01em;
        }
        .brand img { height: 2rem; max-width: 170px; object-fit: contain; display: block; }
        .brand .dot {
            width: 1.85rem; height: 1.85rem; border-radius: .55rem; display: inline-flex;
            align-items: center; justify-content: center; background: var(--primary); color: var(--on-primary);
            font-weight: 700; font-size: 1rem;
        }

        .card {
            position: relative; z-index: 1; background: rgba(255, 255, 255, .85);
            backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px);
            border: 1px solid rgba(8, 33, 50, .08); border-radius: 1.25rem;
            padding: 3.25rem 2.75rem; max-width: 460px; width: 100%; text-align: center;
            box-shadow: 0 24px 60px -24px rgba(8, 33, 50, .25), 0 2px 8px rgba(8, 33, 50, .04);
        }
        .code {
            font-size: clamp(5rem, 18vw, 7.5rem); font-weight: 700; line-height: .9;
            letter-spacing: -.04em; margin-bottom: 1rem; color: var(--ink);
            background: linear-gradient(135deg, var(--ink) 35%, var(--primary));
            -webkit-background-clip: text; background-clip: text; -webkit-text-fill-color: transparent;
        }
        h1 { font-size: 1.5rem; font-weight: 600; margin: 0 0 .65rem; letter-spacing: -.01em; }
        p { color: var(--ink-soft); margin: 0 auto 2.25rem; line-height: 1.65; max-width: 32ch; }

        .actions { display: flex; gap: .75rem; justify-content: center; flex-wrap: wrap; }
        .btn {
            display: inline-flex; align-items: center; gap: .5rem; padding: .7rem 1.6rem;
            border-radius: .7rem; text-decoration: none; font-weight: 600; font-size: .95rem;
            transition: transform .15s ease, box-shadow .15s ease, background .15s ease; cursor: pointer; border: 0;
        }
        .btn-primary {
            background: var(--primary); color: var(--on-primary);
            box-shadow: 0 10px 24px -10px color-mix(in srgb, var(--primary) 75%, transparent);
        }
        .btn-primary:hover { transform: translateY(-1px); box-shadow: 0 14px 28px -10px color-mix(in srgb, var(--primary) 80%, transparent); }
        .btn-ghost { background: transparent; color: var(--ink); border: 1px solid rgba(8, 33, 50, .14); }
        .btn-ghost:hover { background: rgba(8, 33, 50, .04); transform: translateY(-1px); }

        @media (prefers-color-scheme: dark) {
            :root { --ink: #eef2f6; --ink-soft: #94a3b8; --surface: #0a1722; }
            body { background:
                radial-gradient(60rem 40rem at 50% -10%, color-mix(in srgb, var(--primary) 18%, transparent), transparent 70%),
                linear-gradient(180deg, #0a1722, #050c13 60%); }
            .brand { color: var(--ink); }
            .card { background: rgba(13, 27, 40, .7); border-color: rgba(255, 255, 255, .08); box-shadow: 0 24px 60px -24px rgba(0, 0, 0, .6); }
            .code { background: linear-gradient(135deg, #eef2f6 35%, var(--primary)); -webkit-background-clip: text; background-clip: text; }
            .btn-ghost { color: var(--ink); border-color: rgba(255, 255, 255, .16); }
            .btn-ghost:hover { background: rgba(255, 255, 255, .06); }
        }
        @media (prefers-reduced-motion: reduce) {
            .btn { transition: none; }
            .btn:hover { transform: none; }
        }
    </style>
</head>
<body>
    <a href="{{ url('/') }}" class="brand" aria-label="{{ $appName }}">
        @if ($logoUrl)
            <img src="{{ $logoUrl }}" alt="{{ $appName }}">
        @else
            <span class="dot">{{ strtoupper(substr($appName, 0, 1)) }}</span>
            <span>{{ $appName }}</span>
        @endif
    </a>

    <main class="card">
        <div class="code">{{ $code }}</div>
        <h1>{{ $title }}</h1>
        <p>{{ $message }}</p>
        <div class="actions">
            <a href="{{ url('/') }}" class="btn btn-primary">Ir para o início</a>
            <a href="javascript:history.back()" class="btn btn-ghost">Voltar</a>
        </div>
    </main>
</body>
</html>
