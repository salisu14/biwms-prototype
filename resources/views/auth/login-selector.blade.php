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
            --card: rgba(255, 255, 255, 0.95);
            --ink: #0f172a;
            --muted: #64748b;
            --line: #e2e8f0;
            --slate-50: #f8fafc;
            --slate-100: #f1f5f9;
            --slate-400: #94a3b8;
            --shadow: 0 30px 90px rgb(15 23 42 / 15%);
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
                radial-gradient(circle at 16% 10%, rgb(245 158 11 / 21%), transparent 31%),
                radial-gradient(circle at 88% 92%, rgb(51 65 85 / 15%), transparent 34%),
                linear-gradient(135deg, var(--amber-50), var(--slate-50) 46%, #eef2ff);
            color: var(--ink);
            font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        }

        main {
            width: min(100%, 920px);
            animation: page-in 360ms ease-out both;
        }

        .portal {
            border: 1px solid rgb(226 232 240 / 88%);
            border-radius: 30px;
            background: var(--card);
            padding: 34px;
            box-shadow: var(--shadow);
            backdrop-filter: blur(14px);
        }

        .topbar {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 26px;
        }

        .brand {
            min-width: 0;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .brand-logo,
        .brand-mark {
            width: 44px;
            height: 44px;
            flex: 0 0 auto;
            border-radius: 15px;
        }

        .brand-logo {
            object-fit: contain;
            background: #ffffff;
            border: 1px solid var(--line);
            padding: 6px;
        }

        .brand-mark {
            display: grid;
            place-items: center;
            background: linear-gradient(135deg, var(--amber-800), var(--amber-700));
            color: #ffffff;
            font-size: 16px;
            font-weight: 900;
            box-shadow: 0 12px 24px rgb(146 64 14 / 24%);
        }

        .brand-name {
            display: block;
            color: var(--amber-800);
            font-size: 12px;
            font-weight: 850;
            letter-spacing: 0.08em;
            line-height: 1.2;
            text-transform: uppercase;
        }

        .brand-version {
            display: block;
            margin-top: 4px;
            color: var(--muted);
            font-size: 12px;
            font-weight: 700;
        }

        .env-badge {
            display: inline-flex;
            align-items: center;
            min-height: 28px;
            border: 1px solid rgb(180 83 9 / 28%);
            border-radius: 999px;
            padding: 5px 10px;
            background: var(--amber-100);
            color: var(--amber-800);
            font-size: 11px;
            font-weight: 900;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        h1 {
            max-width: 680px;
            margin: 0;
            font-size: clamp(31px, 5vw, 48px);
            line-height: 1.04;
        }

        .lead {
            max-width: 650px;
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
            --accent: var(--amber-800);
            --accent-soft: var(--amber-50);
            min-height: 92px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            border: 1px solid var(--line);
            border-radius: 20px;
            padding: 16px;
            background: rgb(248 250 252 / 84%);
            color: var(--ink);
            text-decoration: none;
            transition:
                background 170ms ease,
                border-color 170ms ease,
                box-shadow 170ms ease,
                transform 170ms ease;
        }

        .workspace-card:hover,
        .workspace-card:focus-visible {
            transform: translateY(-3px);
            border-color: color-mix(in srgb, var(--accent) 38%, var(--line));
            background: color-mix(in srgb, var(--accent-soft) 78%, #ffffff);
            box-shadow: 0 18px 34px rgb(15 23 42 / 10%);
            outline: none;
        }

        .workspace-card:focus-visible {
            box-shadow:
                0 0 0 3px color-mix(in srgb, var(--accent) 22%, transparent),
                0 18px 34px rgb(15 23 42 / 10%);
        }

        .workspace {
            min-width: 0;
            display: flex;
            align-items: center;
            gap: 13px;
        }

        .workspace-icon {
            width: 46px;
            height: 46px;
            flex: 0 0 auto;
            display: grid;
            place-items: center;
            border: 1px solid color-mix(in srgb, var(--accent) 18%, var(--line));
            border-radius: 16px;
            background: #ffffff;
            color: var(--accent);
        }

        .workspace-icon svg {
            width: 24px;
            height: 24px;
            stroke-width: 1.8;
        }

        .workspace-name {
            display: block;
            font-weight: 850;
            line-height: 1.2;
        }

        .workspace-hint {
            display: block;
            max-width: 260px;
            margin-top: 5px;
            color: var(--muted);
            font-size: 12px;
            font-weight: 650;
            line-height: 1.35;
        }

        .arrow {
            flex: 0 0 auto;
            color: var(--slate-400);
            font-size: 19px;
            font-weight: 900;
            transition: color 170ms ease, transform 170ms ease;
        }

        .workspace-card:hover .arrow,
        .workspace-card:focus-visible .arrow {
            color: var(--accent);
            transform: translateX(3px);
        }

        footer {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-top: 24px;
            padding-top: 20px;
            border-top: 1px solid var(--line);
            color: var(--muted);
            font-size: 12px;
            font-weight: 850;
            letter-spacing: 0.08em;
            text-align: center;
            text-transform: uppercase;
        }

        @keyframes page-in {
            from {
                opacity: 0;
                transform: translateY(10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @media (prefers-reduced-motion: reduce) {
            *,
            *::before,
            *::after {
                animation-duration: 1ms !important;
                scroll-behavior: auto !important;
                transition-duration: 1ms !important;
            }
        }

        @media (max-width: 720px) {
            body {
                padding: 16px;
            }

            .portal {
                padding: 24px;
                border-radius: 22px;
            }

            .topbar {
                flex-direction: column;
                align-items: flex-start;
            }

            nav {
                grid-template-columns: 1fr;
            }

            .workspace-card {
                min-height: 82px;
                border-radius: 16px;
            }
        }
    </style>
</head>
<body>
    @php
        $appName = config('app.name', 'BIWMS');
        $appVersion = config('app.version', '0.1.0');
        $environment = (string) config('app.env', 'production');
        $logoPath = trim((string) config('app.logo_path', 'assets/logo.svg'));
        $hasLogo = $logoPath !== '' && file_exists(public_path($logoPath));
        $currentYear = now()->year;

        $workspaces = [
            [
                'label' => 'Admin',
                'href' => '/admin/login',
                'hint' => 'Administration, security, setup, and master data control.',
                'accent' => '#92400e',
                'soft' => '#fff7ed',
                'icon' => 'shield-check',
            ],
            [
                'label' => 'Finance',
                'href' => '/finance/login',
                'hint' => 'General ledger, cash, receivables, payables, and reports.',
                'accent' => '#0f766e',
                'soft' => '#ecfdf5',
                'icon' => 'banknotes',
            ],
            [
                'label' => 'Factory',
                'href' => '/factory/login',
                'hint' => 'Production orders, WIP, capacity, consumption, and output.',
                'accent' => '#7c3aed',
                'soft' => '#f5f3ff',
                'icon' => 'cog',
            ],
            [
                'label' => 'Sales',
                'href' => '/sales/login',
                'hint' => 'Customers, quotes, sales orders, invoices, and returns.',
                'accent' => '#2563eb',
                'soft' => '#eff6ff',
                'icon' => 'chart',
            ],
            [
                'label' => 'Procurement',
                'href' => '/procurement/login',
                'hint' => 'Vendors, requisitions, purchase orders, and receiving.',
                'accent' => '#a16207',
                'soft' => '#fefce8',
                'icon' => 'shopping-bag',
            ],
            [
                'label' => 'HR',
                'href' => '/hr/login',
                'hint' => 'Employees, onboarding, payroll support, and assignments.',
                'accent' => '#be185d',
                'soft' => '#fdf2f8',
                'icon' => 'users',
            ],
            [
                'label' => 'Project',
                'href' => '/project/login',
                'hint' => 'Project execution, tracking, approvals, and delivery work.',
                'accent' => '#4338ca',
                'soft' => '#eef2ff',
                'icon' => 'briefcase',
            ],
            [
                'label' => 'Service',
                'href' => '/service/login',
                'hint' => 'Service operations, support work, cases, and field activity.',
                'accent' => '#475569',
                'soft' => '#f8fafc',
                'icon' => 'wrench',
            ],
            [
                'label' => 'Warehouse',
                'href' => '/warehouse/login',
                'hint' => 'Stock movement, receipts, shipments, transfers, and bins.',
                'accent' => '#15803d',
                'soft' => '#f0fdf4',
                'icon' => 'archive',
            ],
        ];
    @endphp

    <main>
        <section class="portal" aria-labelledby="workspace-title">
            <div class="topbar">
                <div class="brand">
                    @if ($hasLogo)
                        <img class="brand-logo" src="{{ asset($logoPath) }}" alt="{{ $appName }} logo">
                    @else
                        <span class="brand-mark" aria-hidden="true">B</span>
                    @endif

                    <span>
                        <span class="brand-name">{{ $appName }}</span>
                        <span class="brand-version">Version {{ $appVersion }}</span>
                    </span>
                </div>

                @if ($environment !== 'production')
                    <span class="env-badge">{{ strtoupper($environment) }}</span>
                @endif
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
                        aria-label="Sign in to {{ $workspace['label'] }}. {{ $workspace['hint'] }}"
                        style="--accent: {{ $workspace['accent'] }}; --accent-soft: {{ $workspace['soft'] }};"
                    >
                        <span class="workspace">
                            <span class="workspace-icon" aria-hidden="true">
                                @switch($workspace['icon'])
                                    @case('shield-check')
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M9 12.75 11.25 15 15 9.75"></path>
                                            <path d="M12 3.75c2.03 1.78 4.36 2.6 7 2.48v5.27c0 4.78-2.86 7.93-7 9.75-4.14-1.82-7-4.97-7-9.75V6.23c2.64.12 4.97-.7 7-2.48Z"></path>
                                        </svg>
                                        @break

                                    @case('banknotes')
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M3.75 6.75h16.5v10.5H3.75z"></path>
                                            <path d="M7.5 9.75h.01M16.5 14.25h.01"></path>
                                            <path d="M12 9.75a2.25 2.25 0 1 1 0 4.5 2.25 2.25 0 0 1 0-4.5Z"></path>
                                        </svg>
                                        @break

                                    @case('cog')
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M9.6 4.6 10.4 3h3.2l.8 1.6 1.8.75 1.68-.56 2.27 2.27-.56 1.68.75 1.8 1.6.8v3.2l-1.6.8-.75 1.8.56 1.68-2.27 2.27-1.68-.56-1.8.75-.8 1.6h-3.2l-.8-1.6-1.8-.75-1.68.56-2.27-2.27.56-1.68-.75-1.8-1.6-.8v-3.2l1.6-.8.75-1.8-.56-1.68 2.27-2.27 1.68.56 1.8-.75Z"></path>
                                            <path d="M12 9.25a2.75 2.75 0 1 1 0 5.5 2.75 2.75 0 0 1 0-5.5Z"></path>
                                        </svg>
                                        @break

                                    @case('chart')
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M4.5 19.5h15"></path>
                                            <path d="M7 16.5v-5"></path>
                                            <path d="M12 16.5v-9"></path>
                                            <path d="M17 16.5v-3"></path>
                                        </svg>
                                        @break

                                    @case('shopping-bag')
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M6.75 8.25h10.5l.75 11.25H6L6.75 8.25Z"></path>
                                            <path d="M9 8.25V7.5a3 3 0 0 1 6 0v.75"></path>
                                        </svg>
                                        @break

                                    @case('users')
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M9.75 11.25a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z"></path>
                                            <path d="M3.75 19.5a6 6 0 0 1 12 0"></path>
                                            <path d="M16.5 11.25a2.25 2.25 0 1 0 0-4.5"></path>
                                            <path d="M18 19.5h2.25a4.5 4.5 0 0 0-3.38-4.35"></path>
                                        </svg>
                                        @break

                                    @case('briefcase')
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M9 7.5V6a1.5 1.5 0 0 1 1.5-1.5h3A1.5 1.5 0 0 1 15 6v1.5"></path>
                                            <path d="M4.5 8.25h15v10.5h-15z"></path>
                                            <path d="M4.5 12.75h15"></path>
                                        </svg>
                                        @break

                                    @case('wrench')
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M14.25 6.75a4.5 4.5 0 0 0 5.1 5.1l-7.95 7.95a2.12 2.12 0 0 1-3-3l7.95-7.95Z"></path>
                                            <path d="m7.5 16.5 3 3"></path>
                                        </svg>
                                        @break

                                    @default
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M4.5 7.5h15v12h-15z"></path>
                                            <path d="M7.5 7.5V4.5h9v3"></path>
                                            <path d="M8.25 12h.01M12 12h.01M15.75 12h.01M8.25 15.75h.01M12 15.75h.01M15.75 15.75h.01"></path>
                                        </svg>
                                @endswitch
                            </span>
                            <span>
                                <span class="workspace-name">{{ $workspace['label'] }}</span>
                                <span class="workspace-hint">{{ $workspace['hint'] }}</span>
                            </span>
                        </span>
                        <span class="arrow" aria-hidden="true">→</span>
                    </a>
                @endforeach
            </nav>

            <footer>
                <span>Enterprise Resource Planning Portal</span>
                <span aria-hidden="true">•</span>
                <span>{{ $currentYear }}</span>
            </footer>
        </section>
    </main>
</body>
</html>
