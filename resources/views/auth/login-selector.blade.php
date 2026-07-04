<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign in - {{ config('app.name', 'BIWMS') }}</title>
    <style>
        :root {
            color-scheme: light;
            --bg: #f3f4f6;
            --card: #ffffff;
            --ink: #111827;
            --muted: #4b5563;
            --line: #d1d5db;
            --primary: #92400e;
        }

        * {
            box-sizing: border-box;
        }

        body {
            min-height: 100vh;
            margin: 0;
            display: grid;
            place-items: center;
            padding: 24px;
            background: var(--bg);
            color: var(--ink);
            font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        }

        main {
            width: min(100%, 560px);
            border: 1px solid var(--line);
            border-radius: 8px;
            background: var(--card);
            padding: 28px;
            box-shadow: 0 20px 40px rgb(17 24 39 / 10%);
        }

        h1 {
            margin: 0 0 8px;
            font-size: 24px;
            line-height: 1.2;
        }

        p {
            margin: 0 0 20px;
            color: var(--muted);
            line-height: 1.55;
        }

        nav {
            display: grid;
            gap: 10px;
        }

        a {
            display: flex;
            align-items: center;
            justify-content: space-between;
            min-height: 44px;
            border: 1px solid var(--line);
            border-radius: 6px;
            padding: 10px 12px;
            color: var(--ink);
            font-weight: 700;
            text-decoration: none;
        }

        a:hover {
            border-color: var(--primary);
            color: var(--primary);
        }
    </style>
</head>
<body>
    <main>
        <h1>Sign in to {{ config('app.name', 'BIWMS') }}</h1>
        <p>Select the workspace you need.</p>

        <nav aria-label="Login destinations">
            <a href="/admin/login">Admin <span aria-hidden="true">→</span></a>
            <a href="/finance/login">Finance <span aria-hidden="true">→</span></a>
            <a href="/factory/login">Factory <span aria-hidden="true">→</span></a>
            <a href="/sales/login">Sales <span aria-hidden="true">→</span></a>
            <a href="/procurement/login">Procurement <span aria-hidden="true">→</span></a>
            <a href="/hr/login">HR <span aria-hidden="true">→</span></a>
            <a href="/project/login">Project <span aria-hidden="true">→</span></a>
            <a href="/service/login">Service <span aria-hidden="true">→</span></a>
            <a href="/warehouse/login">Warehouse <span aria-hidden="true">→</span></a>
        </nav>
    </main>
</body>
</html>
