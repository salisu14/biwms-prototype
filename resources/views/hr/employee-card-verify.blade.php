<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Employee Card Verification</title>
    <style>
        * {
            box-sizing: border-box;
        }

        body {
            align-items: center;
            background: linear-gradient(135deg, #f8fafc, #e2e8f0);
            color: #0f172a;
            display: flex;
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            justify-content: center;
            margin: 0;
            min-height: 100vh;
            padding: 24px;
        }

        .panel {
            background: #ffffff;
            border: 1px solid #d7dee9;
            border-radius: 18px;
            box-shadow: 0 20px 55px rgba(15, 23, 42, 0.14);
            max-width: 560px;
            overflow: hidden;
            width: 100%;
        }

        .header {
            background: #0f172a;
            color: #ffffff;
            padding: 24px;
        }

        .title {
            font-size: 22px;
            font-weight: 800;
            margin: 0;
        }

        .company {
            color: #cbd5e1;
            margin-top: 6px;
        }

        .content {
            padding: 24px;
        }

        .status {
            border-radius: 999px;
            display: inline-flex;
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 0.06em;
            margin-bottom: 18px;
            padding: 7px 11px;
            text-transform: uppercase;
        }

        .status-valid {
            background: #dcfce7;
            color: #166534;
        }

        .status-invalid {
            background: #fee2e2;
            color: #991b1b;
        }

        .identity {
            align-items: center;
            display: grid;
            gap: 18px;
            grid-template-columns: 96px 1fr;
        }

        .photo {
            align-items: center;
            background: #f1f5f9;
            border: 1px solid #d7dee9;
            border-radius: 16px;
            display: flex;
            height: 112px;
            justify-content: center;
            overflow: hidden;
            width: 96px;
        }

        .photo img {
            height: 100%;
            object-fit: cover;
            width: 100%;
        }

        .photo-placeholder {
            color: #64748b;
            font-size: 28px;
            font-weight: 800;
        }

        .name {
            font-size: 22px;
            font-weight: 800;
            line-height: 1.15;
        }

        .meta {
            color: #475569;
            line-height: 1.6;
            margin-top: 8px;
        }

        .message {
            color: #475569;
            line-height: 1.6;
            margin: 0;
        }

        @media (max-width: 480px) {
            .identity {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
<main class="panel">
    <header class="header">
        <h1 class="title">Employee Card Verification</h1>
        <div class="company">{{ $company->company_name ?? config('app.name', 'BIWMS') }}</div>
    </header>

    <section class="content">
        @if ($isValid && $employee)
            @php
                $employeeInitials = collect(explode(' ', (string) $employee->full_name))
                    ->filter()
                    ->map(fn (string $part): string => mb_substr($part, 0, 1))
                    ->take(2)
                    ->implode('');
            @endphp
            <div class="status status-valid">Active card</div>
            <div class="identity">
                <div class="photo">
                    @if ($photoUrl)
                        <img src="{{ $photoUrl }}" alt="{{ $employee->full_name }} photo">
                    @else
                        <span class="photo-placeholder">{{ $employeeInitials ?: 'ID' }}</span>
                    @endif
                </div>
                <div>
                    <div class="name">{{ $employee->full_name }}</div>
                    <div class="meta">
                        <div>Employee No. {{ $employee->employee_number }}</div>
                        <div>{{ $employee->job_title ?: 'Staff Member' }}</div>
                        <div>{{ $employee->department?->name ?? $employee->department_code ?? 'No department assigned' }}</div>
                        <div>Status: {{ str($employee->id_card_status ?? 'active')->headline() }}</div>
                    </div>
                </div>
            </div>
        @else
            <div class="status status-invalid">Not active</div>
            <p class="message">
                This employee card could not be verified. It may be expired, revoked, inactive, or unknown.
            </p>
        @endif
    </section>
</main>
</body>
</html>
