<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>@yield('title') · {{ config('app.name') }}</title>
    <style>
        :root {
            --bg: #f4f5f7;
            --card: #ffffff;
            --text: #1f2937;
            --muted: #6b7280;
            --primary: #4f46e5;
            --primary-soft: #eef2ff;
            --border: #e5e7eb;
            --success: #059669;
            --success-soft: #ecfdf5;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            background: var(--bg);
            color: var(--text);
            line-height: 1.55;
            -webkit-font-smoothing: antialiased;
        }
        main { max-width: 44rem; margin: 0 auto; padding: 1rem 1rem 5rem; }
        .card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 1rem;
            padding: 1.25rem;
            margin-bottom: 1rem;
        }
        h1 { font-size: 1.5rem; margin: 0.25rem 0 0.5rem; }
        h2 { font-size: 1.05rem; margin: 0 0 0.75rem; }
        p { margin: 0.5rem 0; }
        .muted { color: var(--muted); font-size: 0.9rem; }
        .badge {
            display: inline-block;
            padding: 0.15rem 0.7rem;
            border-radius: 999px;
            background: var(--primary-soft);
            color: var(--primary);
            font-size: 0.8rem;
            font-weight: 600;
        }
        .badge-success { background: var(--success-soft); color: var(--success); }
        .prose img { max-width: 100%; height: auto; }
        .btn {
            display: inline-block;
            width: 100%;
            padding: 0.85rem 1.25rem;
            border: none;
            border-radius: 0.75rem;
            background: var(--primary);
            color: #fff;
            font-size: 1rem;
            font-weight: 600;
            text-align: center;
            text-decoration: none;
            cursor: pointer;
        }
        .btn:active { opacity: 0.85; }
        .btn[disabled] { opacity: 0.5; cursor: default; }
        .field { margin-bottom: 0.75rem; }
        .field label { display: block; font-size: 0.85rem; font-weight: 600; margin-bottom: 0.25rem; }
        .field input {
            width: 100%;
            padding: 0.7rem 0.9rem;
            font-size: 1rem;
            border: 1px solid var(--border);
            border-radius: 0.75rem;
        }
        .error { color: #dc2626; font-size: 0.85rem; margin-top: 0.25rem; }
        .flash {
            background: var(--success-soft);
            color: var(--success);
            border-radius: 0.75rem;
            padding: 0.75rem 1rem;
            margin-bottom: 1rem;
            font-weight: 600;
        }
        .day-title { font-weight: 700; margin: 1rem 0 0.5rem; text-transform: capitalize; }
        .slots { display: grid; grid-template-columns: repeat(auto-fill, minmax(7.5rem, 1fr)); gap: 0.5rem; }
        .slot {
            padding: 0.7rem 0.5rem;
            border: 1.5px solid var(--border);
            border-radius: 0.75rem;
            background: var(--card);
            font-size: 0.95rem;
            font-weight: 600;
            text-align: center;
            cursor: pointer;
        }
        .slot .count { display: block; font-size: 0.7rem; font-weight: 400; color: var(--muted); }
        .slot.selected {
            border-color: var(--primary);
            background: var(--primary-soft);
            color: var(--primary);
        }
        .slot.selected .count { color: var(--primary); }
        .save-bar {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            padding: 0.75rem 1rem calc(0.75rem + env(safe-area-inset-bottom));
            background: rgba(255, 255, 255, 0.95);
            border-top: 1px solid var(--border);
        }
        .save-bar .inner { max-width: 44rem; margin: 0 auto; }
        .video-embed {
            position: relative;
            width: 100%;
            aspect-ratio: 16 / 10;
            border: 0;
            border-radius: 1rem;
            overflow: hidden;
        }
    </style>
    @livewireStyles
</head>
<body>
    <main>
        @yield('content')
    </main>
    @livewireScripts
</body>
</html>
