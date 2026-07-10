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
            background: #eef2f7;
            color: #0f172a;
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 13px;
            margin: 0;
        }

        .sheet {
            page-break-after: always;
            padding: 24px 0;
            text-align: center;
            width: 100%;
        }

        .sheet:last-child {
            page-break-after: auto;
        }

        .card {
            background: #ffffff;
            border: 1px solid #d7dee9;
            border-radius: 18px;
            box-shadow: 0 18px 45px rgba(15, 23, 42, 0.15);
            display: inline-block;
            overflow: hidden;
            text-align: left;
            width: 430px;
        }

        .card-header {
            background: #172033;
            color: #ffffff;
            padding: 20px 22px;
        }

        .company-table,
        .identity-table,
        .details-table,
        .footer-table {
            border-collapse: collapse;
            width: 100%;
        }

        .company-logo-cell {
            vertical-align: middle;
            width: 72px;
        }

        .logo {
            background: #334155;
            border: 1px solid #64748b;
            border-radius: 12px;
            color: #ffffff;
            height: 58px;
            line-height: 58px;
            overflow: hidden;
            text-align: center;
            width: 58px;
        }

        .logo img {
            max-height: 58px;
            max-width: 58px;
            vertical-align: middle;
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

        .card-body {
            padding: 22px;
        }

        .photo-cell {
            padding-right: 18px;
            vertical-align: top;
            width: 138px;
        }

        .photo {
            background: #f1f5f9;
            border: 1px solid #d7dee9;
            border-radius: 16px;
            color: #64748b;
            height: 142px;
            line-height: 142px;
            overflow: hidden;
            text-align: center;
            width: 120px;
        }

        .photo img {
            height: 142px;
            object-fit: cover;
            width: 120px;
        }

        .photo-placeholder {
            font-size: 32px;
            font-weight: 800;
        }

        .identity-cell {
            vertical-align: middle;
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
            margin-top: 20px;
            padding-top: 18px;
        }

        .details-table td {
            padding-bottom: 12px;
            vertical-align: top;
            width: 50%;
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
            border-top: 1px solid #e2e8f0;
            margin-top: 8px;
            padding-top: 18px;
        }

        .footer-info-cell {
            padding-right: 18px;
            vertical-align: middle;
        }

        .footer-qr-cell {
            vertical-align: middle;
            width: 126px;
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
            word-break: break-word;
        }

        .qr {
            background: #ffffff;
            border: 1px solid #d7dee9;
            border-radius: 12px;
            padding: 8px;
            text-align: center;
        }

        .qr svg {
            display: inline-block;
            height: 108px;
            vertical-align: middle;
            width: 108px;
        }

        @media print {
            body {
                background: #ffffff;
            }

            .sheet {
                padding: 0;
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
        $logoSrc = array_key_exists('logoSrc', $card) ? $card['logoSrc'] : ($card['logoUrl'] ?? null);
        $photoSrc = array_key_exists('photoSrc', $card) ? $card['photoSrc'] : ($card['photoUrl'] ?? null);
    @endphp
    <main class="sheet">
        <section class="card" aria-label="Employee ID card">
            <header class="card-header">
                <table class="company-table" role="presentation">
                    <tr>
                        <td class="company-logo-cell">
                            <div class="logo">
                                @if ($logoSrc)
                                    <img src="{{ $logoSrc }}" alt="{{ $company->company_name }} logo">
                                @else
                                    <span class="logo-placeholder">{{ mb_substr($company->company_name ?? config('app.name', 'BIWMS'), 0, 1) }}</span>
                                @endif
                            </div>
                        </td>
                        <td>
                            <div class="company-name">{{ $company->company_name ?? config('app.name', 'BIWMS') }}</div>
                            <div class="subtitle">Employee Identity Card</div>
                        </td>
                    </tr>
                </table>
            </header>

            <div class="card-body">
                <table class="identity-table" role="presentation">
                    <tr>
                        <td class="photo-cell">
                            <div class="photo">
                                @if ($photoSrc)
                                    <img src="{{ $photoSrc }}" alt="{{ $employee->full_name }} photo">
                                @else
                                    <span class="photo-placeholder">{{ $employeeInitials ?: 'ID' }}</span>
                                @endif
                            </div>
                        </td>
                        <td class="identity-cell">
                            <div class="employee-name">{{ $employee->full_name }}</div>
                            <div class="employee-meta">
                                <div>{{ $employee->job_title ?: 'Staff Member' }}</div>
                                <div>{{ $employee->department?->name ?? $employee->department_code ?? 'No department assigned' }}</div>
                            </div>
                            <div class="number-pill">{{ $employee->employee_number }}</div>
                        </td>
                    </tr>
                </table>

                <div class="details">
                    <table class="details-table" role="presentation">
                        <tr>
                            <td>
                                <div class="label">Card No.</div>
                                <div class="value">{{ $employee->id_card_number }}</div>
                            </td>
                            <td>
                                <div class="label">Status</div>
                                <div class="value">{{ str($employee->id_card_status ?? 'active')->headline() }}</div>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <div class="label">Issue Date</div>
                                <div class="value">{{ $employee->id_card_issue_date?->format('M j, Y') ?? '—' }}</div>
                            </td>
                            <td>
                                <div class="label">Expiry Date</div>
                                <div class="value">{{ $employee->id_card_expiry_date?->format('M j, Y') ?? '—' }}</div>
                            </td>
                        </tr>
                    </table>
                </div>

                <div class="footer">
                    <table class="footer-table" role="presentation">
                        <tr>
                            <td class="footer-info-cell">
                                <div class="authorized">Authorized Employee</div>
                                <div class="verify-url">{{ $card['verificationUrl'] }}</div>
                            </td>
                            <td class="footer-qr-cell">
                                <div class="qr" aria-label="Signed employee ID verification QR code">
                                    {!! $card['qrSvg'] !!}
                                </div>
                            </td>
                        </tr>
                    </table>
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
