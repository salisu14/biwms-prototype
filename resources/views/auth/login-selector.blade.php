<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign in - {{ config('app.name', 'BIWMS') }}</title>
    <style>
        :root {
            color-scheme: light;
            --amber-50: #fff7ed;
            --amber-100: #ffedd5;
            --amber-700: #b45309;
            --amber-800: #92400e;
            --card: rgba(255, 255, 255, 0.94);
            --ink: #0f172a;
            --muted: #64748b;
            --line: #e2e8f0;
            --line-strong: #cbd5e1;
            --slate-50: #f8fafc;
            --slate-100: #f1f5f9;
            --slate-400: #94a3b8;
            --shadow: 0 28px 80px rgb(15 23 42 / 14%);
        }

        * {
            box-sizing: border-box;
        }

        body {
            min-height: 100vh;
            margin: 0;
            display: grid;
            place-items: center;
            padding: 28px;
            background:
                radial-gradient(circle at 16% 12%, rgb(245 158 11 / 20%), transparent 30%),
                radial-gradient(circle at 86% 88%, rgb(51 65 85 / 14%), transparent 34%),
                linear-gradient(135deg, var(--amber-50), var(--slate-50) 48%, #eef2ff);
            color: var(--ink);
            font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        }

        main {
            width: min(100%, 820px);
        }

        .portal {
            border: 1px solid rgb(226 232 240 / 88%);
            border-radius: 28px;
            background: var(--card);
            padding: 34px;
            box-shadow: var(--shadow);
            backdrop-filter: blur(14px);
        }

        .brand {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 22px;
            color: var(--amber-800);
            font-size: 12px;
            font-weight: 850;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .brand-mark {
            width: 36px;
            height: 36px;
            display: grid;
            place-items: center;
            border-radius: 13px;
            background: linear-gradient(135deg, var(--amber-800), var(--amber-700));
            color: #ffffff;
            font-size: 14px;
            font-weight: 900;
            box-shadow: 0 12px 24px rgb(146 64 14 / 24%);
        }

        h1 {
            margin: 0;
            max-width: 620px;
            font-size: clamp(30px, 5vw, 46px);
            line-height: 1.05;
        }

        .lead {
            max-width: 590px;
            margin: 12px 0 28px;
            color: var(--muted);
            font-size: 16px;
            line-height: 1.65;
        }

        nav {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
        }

        .workspace-card {
            min-height: 78px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            border: 1px solid var(--line);
            border-radius: 18px;
            padding: 14px 16px;
            background: rgb(248 250 252 / 82%);
            color: var(--ink);
            text-decoration: none;
            transition:
                background 160ms ease,
                border-color 160ms ease,
                box-shadow 160ms ease,
                transform 160ms ease;
        }

        .workspace-card:hover,
        .workspace-card:focus-visible {
            transform: translateY(-2px);
            border-color: rgb(180 83 9 / 42%);
            background: var(--amber-50);
            box-shadow: 0 16px 32px rgb(15 23 42 / 9%);
            outline: none;
        }

        .workspace-card:focus-visible {
            box-shadow:
                0 0 0 3px rgb(245 158 11 / 22%),
                0 16px 32px rgb(15 23 42 / 9%);
        }

        .workspace {
            min-width: 0;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .workspace-icon {
            width: 44px;
            height: 44px;
            flex: 0 0 auto;
            display: grid;
            place-items: center;
            border: 1px solid var(--line);
            border-radius: 15px;
            background: #ffffff;
            color: var(--amber-800);
            font-size: 13px;
            font-weight: 900;
        }

        .workspace-name {
            display: block;
            font-weight: 850;
            line-height: 1.2;
        }

        .workspace-hint {
            display: block;
            margin-top: 4px;
            color: var(--muted);
            font-size: 12px;
            font-weight: 650;
            line-height: 1.3;
        }

        .arrow {
            flex: 0 0 auto;
            color: var(--slate-400);
            font-size: 19px;
            font-weight: 900;
            transition: color 160ms ease, transform 160ms ease;
        }

        .workspace-card:hover .arrow,
        .workspace-card:focus-visible .arrow {
            color: var(--amber-800);
            transform: translateX(2px);
        }

        footer {
            margin-top: 24px;
            padding-top: 20px;
            border-top: 1px solid var(--line);
            color: var(--muted);
            font-size: 12px;
            font-weight: 850;
            letter-spacing: 0.12em;
            text-align: center;
            text-transform: uppercase;
        }

        @media (max-width: 680px) {
            body {
                padding: 16px;
            }

            .portal {
                padding: 24px;
                border-radius: 22px;
            }

            nav {
                grid-template-columns: 1fr;
            }

            .workspace-card {
                min-height: 70px;
                border-radius: 15px;
            }
        }
    </style>
</head>
<body>
    @php
        $workspaces = [
            ['label' => 'Admin', 'href' => '/admin/login', 'initials' => 'AD', 'hint' => 'System control'],
            ['label' => 'Finance', 'href' => '/finance/login', 'initials' => 'FI', 'hint' => 'Ledgers and reports'],
            ['label' => 'Factory', 'href' => '/factory/login', 'initials' => 'FA', 'hint' => 'Production floor'],
            ['label' => 'Sales', 'href' => '/sales/login', 'initials' => 'SA', 'hint' => 'Orders and customers'],
            ['label' => 'Procurement', 'href' => '/procurement/login', 'initials' => 'PR', 'hint' => 'Vendors and purchasing'],
            ['label' => 'HR', 'href' => '/hr/login', 'initials' => 'HR', 'hint' => 'People operations'],
            ['label' => 'Project', 'href' => '/project/login', 'initials' => 'PJ', 'hint' => 'Project delivery'],
            ['label' => 'Service', 'href' => '/service/login', 'initials' => 'SV', 'hint' => 'Service operations'],
            ['label' => 'Warehouse', 'href' => '/warehouse/login', 'initials' => 'WH', 'hint' => 'Stock movement'],
        ];
    @endphp

    <main>
        <section class="portal" aria-labelledby="workspace-title">
            <div class="brand">
                <span class="brand-mark" aria-hidden="true">B</span>
                <span>{{ config('app.name', 'BIWMS') }}</span>
            </div>

            <header>
                <h1 id="workspace-title">Choose your workspace</h1>
                <p class="lead">Select the workspace you need. Your permissions will determine what you can access after login.</p>
            </header>

            <nav aria-label="Login destinations">
                @foreach ($workspaces as $workspace)
                    <a
                        class="workspace-card"
                        href="{{ $workspace['href'] }}"
                        aria-label="Sign in to {{ $workspace['label'] }}"
                    >
                        <span class="workspace">
                            <span class="workspace-icon" aria-hidden="true">{{ $workspace['initials'] }}</span>
                            <span>
                                <span class="workspace-name">{{ $workspace['label'] }}</span>
                                <span class="workspace-hint">{{ $workspace['hint'] }}</span>
                            </span>
                        </span>
                        <span class="arrow" aria-hidden="true">→</span>
                    </a>
                @endforeach
            </nav>

            <footer>Enterprise Resource Planning Portal</footer>
        </section>
    </main>
</body>
</html>
