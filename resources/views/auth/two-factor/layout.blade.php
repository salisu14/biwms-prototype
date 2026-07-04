<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Two-Factor Authentication' }} - {{ config('app.name', 'BIWMS') }}</title>
    <style>
        :root {
            color-scheme: light;
            --bg: #f3f4f6;
            --card: #ffffff;
            --ink: #111827;
            --muted: #4b5563;
            --line: #d1d5db;
            --danger-bg: #fef2f2;
            --danger-line: #fecaca;
            --danger-ink: #991b1b;
            --primary: #92400e;
            --primary-hover: #78350f;
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

        .card {
            width: min(100%, {{ $wide ?? false ? '1120px' : '520px' }});
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
            margin: 0 0 18px;
            color: var(--muted);
            line-height: 1.55;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 650;
        }

        input {
            width: 100%;
            min-height: 44px;
            border: 1px solid var(--line);
            border-radius: 6px;
            padding: 10px 12px;
            font: inherit;
        }

        form label:not(:first-child) {
            margin-top: 14px;
        }

        button,
        .button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 42px;
            border: 0;
            border-radius: 6px;
            padding: 10px 16px;
            background: var(--primary);
            color: white;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
        }

        button:hover,
        .button:hover {
            background: var(--primary-hover);
        }

        .secret,
        code {
            border: 1px solid var(--line);
            border-radius: 6px;
            background: #f9fafb;
            color: #111827;
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
        }

        .secret {
            padding: 12px;
            margin-bottom: 18px;
            overflow-wrap: anywhere;
            font-size: 15px;
        }

        .qr-code {
            display: grid;
            place-items: center;
            margin: 18px 0;
        }

        .qr-code svg {
            width: min(100%, 220px);
            height: auto;
            border: 1px solid var(--line);
            border-radius: 8px;
            background: white;
            padding: 12px;
        }

        code {
            padding: 3px 6px;
        }

        .error {
            border: 1px solid var(--danger-line);
            border-radius: 6px;
            padding: 12px;
            margin-bottom: 18px;
            background: var(--danger-bg);
            color: var(--danger-ink);
        }

        .notice {
            border: 1px solid #bbf7d0;
            border-radius: 6px;
            padding: 12px;
            margin-bottom: 18px;
            background: #f0fdf4;
            color: #166534;
        }

        .status-list {
            display: grid;
            gap: 10px;
            margin: 0 0 22px;
        }

        .status-list div {
            display: flex;
            justify-content: space-between;
            gap: 18px;
            border-bottom: 1px solid var(--line);
            padding-bottom: 10px;
        }

        .status-list dt {
            color: var(--muted);
            font-weight: 650;
        }

        .status-list dd {
            margin: 0;
            text-align: right;
        }

        .badge {
            display: inline-flex;
            border-radius: 999px;
            padding: 3px 8px;
            font-size: 12px;
            font-weight: 800;
        }

        .badge-success {
            background: #dcfce7;
            color: #166534;
        }

        .badge-warning {
            background: #fef3c7;
            color: #92400e;
        }

        .codes {
            display: grid;
            gap: 8px;
            margin: 0 0 22px;
            padding-left: 20px;
        }

        .actions {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            align-items: center;
            margin-top: 18px;
        }

        .sr-only {
            position: absolute;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            white-space: nowrap;
            border: 0;
        }

        .stack {
            display: grid;
            gap: 14px;
        }

        .table-wrap {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }

        th,
        td {
            border-bottom: 1px solid var(--line);
            padding: 10px;
            text-align: left;
            vertical-align: top;
        }

        th {
            color: var(--muted);
            font-size: 12px;
            text-transform: uppercase;
        }

        .action-cell {
            display: grid;
            gap: 8px;
            min-width: 180px;
        }

        .action-cell form {
            display: flex;
            gap: 6px;
            align-items: center;
        }

        .action-cell input {
            min-height: 36px;
        }

        .action-cell button {
            min-height: 36px;
            padding: 7px 10px;
            white-space: nowrap;
        }
    </style>
</head>
<body>
    <main class="card">
        {{ $slot }}
    </main>
</body>
</html>
