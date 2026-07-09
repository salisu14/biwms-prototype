<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Employee ID Card</title>
    <style>
        @page {
            margin: 18mm;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: #eef2f7;
            color: #0f172a;
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 13px;
        }

        .sheet {
            align-items: center;
            display: flex;
            justify-content: center;
            min-height: 100vh;
            page-break-after: always;
            padding: 24px;
        }

        .sheet:last-child {
            page-break-after: auto;
        }

        .card {
            background: #ffffff;
            border: 1px solid #d7dee9;
            border-radius: 18px;
            box-shadow: 0 18px 45px rgba(15, 23, 42, 0.15);
            overflow: hidden;
            width: 430px;
        }

        .header {
            background: linear-gradient(135deg, #0f172a, #334155);
            color: #ffffff;
            padding: 20px 22px;
        }

        .company {
            align-items: center;
            display: flex;
            gap: 14px;
        }

        .logo {
            align-items: center;
            background: rgba(255, 255, 255, 0.14);
            border: 1px solid rgba(255, 255, 255, 0.28);
            border-radius: 12px;
            display: flex;
            height: 58px;
            justify-content: center;
            overflow: hidden;
            width: 58px;
        }

        .logo img {
            max-height: 100%;
            max-width: 100%;
            object-fit: contain;
        }

        .logo-placeholder {
            font-size: 20px;
            font-weight: 700;
        }

        .company-name {
            font-size: 18px;
            font-weight: 800;
            line-height: 1.25;
        }

        .subtitle {
            color: #cbd5e1;
            font-size: 11px;
            letter-spacing: 0.08em;
            margin-top: 4px;
            text-transform: uppercase;
        }

        .body {
            padding: 22px;
        }

        .identity {
            align-items: center;
            display: grid;
            gap: 18px;
            grid-template-columns: 120px 1fr;
        }

        .photo {
            align-items: center;
            background: #f1f5f9;
            border: 1px solid #d7dee9;
            border-radius: 16px;
            display: flex;
            height: 142px;
            justify-content: center;
            overflow: hidden;
            width: 120px;
        }

        .photo img {
            height: 100%;
            object-fit: cover;
            width: 100%;
        }

        .photo-placeholder {
            color: #64748b;
            font-size: 32px;
            font-weight: 800;
        }

        .employee-name {
            color: #0f172a;
            font-size: 22px;
            font-weight: 800;
            line-height: 1.15;
            margin-bottom: 8px;
        }

        .employee-meta {
            color: #475569;
            line-height: 1.55;
        }

        .number-pill {
            background: #fef3c7;
            border: 1px solid #f6d56e;
            border-radius: 999px;
            color: #78350f;
            display: inline-block;
            font-size: 12px;
            font-weight: 700;
            margin-top: 10px;
            padding: 5px 10px;
        }

        .details {
            border-top: 1px solid #e2e8f0;
            display: grid;
            gap: 12px;
            grid-template-columns: 1fr 1fr;
            margin-top: 20px;
            padding-top: 18px;
        }

        .label {
            color: #64748b;
            font-size: 10px;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .value {
            color: #0f172a;
            font-size: 13px;
            font-weight: 700;
            margin-top: 3px;
        }

        .footer {
            align-items: center;
            border-top: 1px solid #e2e8f0;
            display: grid;
            gap: 18px;
            grid-template-columns: 1fr 126px;
            margin-top: 20px;
            padding-top: 18px;
        }

        .authorized {
            color: #0f766e;
            font-size: 14px;
            font-weight: 800;
            margin-bottom: 8px;
        }

        .verify-url {
            color: #64748b;
            font-size: 10px;
            line-height: 1.45;
            overflow-wrap: anywhere;
        }

        .qr {
            align-items: center;
            background: #ffffff;
            border: 1px solid #d7dee9;
            border-radius: 12px;
            display: flex;
            justify-content: center;
            padding: 8px;
        }

        .qr svg {
            display: block;
            height: 108px;
            width: 108px;
        }

        @media print {
            body {
                background: #ffffff;
            }

            .sheet {
                min-height: auto;
                padding: 0;
            }

            .card {
                box-shadow: none;
            }
        }
    </style>
</head>
<body>
@foreach ($cards as $card)
    @php
        /** @var \App\Models\Employee $employee */
        $employee = $card['employee'];
        /** @var \App\Models\CompanyInformation $company */
        $company = $card['company'];
        $employeeInitials = collect(explode(' ', (string) $employee->full_name))
            ->filter()
            ->map(fn (string $part): string => mb_substr($part, 0, 1))
            ->take(2)
            ->implode('');
    @endphp
    <main class="sheet">
        <section class="card" aria-label="Employee ID card">
            <header class="header">
                <div class="company">
                    <div class="logo">
                        @if ($card['logoUrl'])
                            <img src="{{ $card['logoUrl'] }}" alt="{{ $company->company_name }} logo">
                        @else
                            <span class="logo-placeholder">{{ mb_substr($company->company_name ?? config('app.name', 'BIWMS'), 0, 1) }}</span>
                        @endif
                    </div>
                    <div>
                        <div class="company-name">{{ $company->company_name ?? config('app.name', 'BIWMS') }}</div>
                        <div class="subtitle">Employee Identity Card</div>
                    </div>
                </div>
            </header>

            <div class="body">
                <div class="identity">
                    <div class="photo">
                        @if ($card['photoUrl'])
                            <img src="{{ $card['photoUrl'] }}" alt="{{ $employee->full_name }} photo">
                        @else
                            <span class="photo-placeholder">{{ $employeeInitials ?: 'ID' }}</span>
                        @endif
                    </div>

                    <div>
                        <div class="employee-name">{{ $employee->full_name }}</div>
                        <div class="employee-meta">
                            <div>{{ $employee->job_title ?: 'Staff Member' }}</div>
                            <div>{{ $employee->department?->name ?? $employee->department_code ?? 'No department assigned' }}</div>
                        </div>
                        <div class="number-pill">{{ $employee->employee_number }}</div>
                    </div>
                </div>

                <div class="details">
                    <div>
                        <div class="label">Card No.</div>
                        <div class="value">{{ $employee->id_card_number }}</div>
                    </div>
                    <div>
                        <div class="label">Status</div>
                        <div class="value">{{ str($employee->id_card_status ?? 'active')->headline() }}</div>
                    </div>
                    <div>
                        <div class="label">Issue Date</div>
                        <div class="value">{{ $employee->id_card_issue_date?->format('M j, Y') ?? '—' }}</div>
                    </div>
                    <div>
                        <div class="label">Expiry Date</div>
                        <div class="value">{{ $employee->id_card_expiry_date?->format('M j, Y') ?? '—' }}</div>
                    </div>
                </div>

                <div class="footer">
                    <div>
                        <div class="authorized">Authorized Employee</div>
                        <div class="verify-url">{{ $card['verificationUrl'] }}</div>
                    </div>
                    <div class="qr" aria-label="Signed employee ID verification QR code">
                        {!! $card['qrSvg'] !!}
                    </div>
                </div>
            </div>
        </section>
    </main>
@endforeach

@if ($print ?? false)
    <script>
        window.addEventListener('load', () => window.print());
    </script>
@endif
</body>
</html>
