<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Employee Payslip</title>
    <style>
        @page {
            margin: 16mm;
        }

        * {
            box-sizing: border-box;
        }

        body {
            background: #eef2f7;
            color: #0f172a;
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 12px;
            margin: 0;
        }

        .sheet {
            page-break-after: always;
            padding: 18px 0;
            width: 100%;
        }

        .sheet:last-child {
            page-break-after: auto;
        }

        .payslip {
            background: #ffffff;
            border: 1px solid #d9e2ef;
            border-radius: 14px;
            box-shadow: 0 18px 45px rgba(15, 23, 42, 0.12);
            margin: 0 auto;
            overflow: hidden;
            width: 100%;
        }

        .header {
            background: #172033;
            color: #ffffff;
            padding: 22px 26px;
        }

        .header-table,
        .summary-table,
        .lines-table,
        .footer-table {
            border-collapse: collapse;
            width: 100%;
        }

        .logo-cell {
            width: 76px;
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

        .company-name {
            font-size: 20px;
            font-weight: 800;
            line-height: 1.25;
        }

        .document-title {
            color: #cbd5e1;
            font-size: 11px;
            letter-spacing: 0.08em;
            margin-top: 5px;
            text-transform: uppercase;
        }

        .number {
            font-size: 13px;
            font-weight: 700;
            text-align: right;
        }

        .body {
            padding: 24px 26px 26px;
        }

        .summary {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            margin-bottom: 20px;
            padding: 14px 16px;
        }

        .summary-table td {
            padding: 6px 10px;
            vertical-align: top;
            width: 25%;
        }

        .label {
            color: #64748b;
            font-size: 10px;
            letter-spacing: 0.07em;
            text-transform: uppercase;
        }

        .value {
            color: #0f172a;
            font-size: 13px;
            font-weight: 700;
            margin-top: 3px;
        }

        .section-title {
            color: #0f172a;
            font-size: 14px;
            font-weight: 800;
            margin: 20px 0 8px;
        }

        .lines-table th {
            background: #172033;
            color: #ffffff;
            font-size: 10px;
            letter-spacing: 0.05em;
            padding: 9px 10px;
            text-align: left;
            text-transform: uppercase;
        }

        .lines-table td {
            border-bottom: 1px solid #e2e8f0;
            padding: 9px 10px;
            vertical-align: top;
        }

        .amount {
            text-align: right;
            white-space: nowrap;
        }

        .totals {
            background: #fef3c7;
            border: 1px solid #f6d56e;
            border-radius: 12px;
            margin-top: 20px;
            padding: 14px 16px;
        }

        .net-pay {
            color: #78350f;
            font-size: 20px;
            font-weight: 900;
            text-align: right;
        }

        .footer {
            border-top: 1px solid #e2e8f0;
            color: #64748b;
            margin-top: 24px;
            padding-top: 14px;
        }

        .status {
            border-radius: 999px;
            display: inline-block;
            font-size: 10px;
            font-weight: 800;
            padding: 4px 10px;
            text-transform: uppercase;
        }

        .status-revoked {
            background: #fee2e2;
            color: #991b1b;
        }

        .status-normal {
            background: #dcfce7;
            color: #166534;
        }

        @media print {
            body {
                background: #ffffff;
            }

            .payslip {
                box-shadow: none;
            }
        }
    </style>
</head>
<body @if($print ?? false) onload="window.print()" @endif>
@foreach ($payslips as $data)
    @php
        /** @var \App\Models\EmployeePayslip $payslip */
        $payslip = $data['payslip'];
        $logoSrc = $data['logoSrc'] ?? null;
    @endphp
    <div class="sheet">
        <article class="payslip" aria-label="Employee payslip {{ $payslip->payslip_number }}">
            <header class="header">
                <table class="header-table">
                    <tr>
                        <td class="logo-cell">
                            <div class="logo">
                                @if ($logoSrc)
                                    <img src="{{ $logoSrc }}" alt="{{ $payslip->company_name }} logo">
                                @else
                                    {{ strtoupper(substr((string) $payslip->company_name, 0, 1)) }}
                                @endif
                            </div>
                        </td>
                        <td>
                            <div class="company-name">{{ $payslip->company_name }}</div>
                            <div class="document-title">Employee Payslip</div>
                        </td>
                        <td class="number">
                            {{ $payslip->payslip_number }}<br>
                            <span class="status {{ $payslip->isRevoked() ? 'status-revoked' : 'status-normal' }}">{{ $payslip->status }}</span>
                        </td>
                    </tr>
                </table>
            </header>

            <main class="body">
                <section class="summary">
                    <table class="summary-table">
                        <tr>
                            <td>
                                <div class="label">Employee</div>
                                <div class="value">{{ $payslip->employee_name }}</div>
                            </td>
                            <td>
                                <div class="label">Employee No.</div>
                                <div class="value">{{ $payslip->employee_number }}</div>
                            </td>
                            <td>
                                <div class="label">Department</div>
                                <div class="value">{{ $payslip->department_name ?: '—' }}</div>
                            </td>
                            <td>
                                <div class="label">Job Title</div>
                                <div class="value">{{ $payslip->job_title ?: '—' }}</div>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <div class="label">Period</div>
                                <div class="value">{{ $payslip->payrollPeriod?->start_date?->format('M d, Y') }} - {{ $payslip->payrollPeriod?->end_date?->format('M d, Y') }}</div>
                            </td>
                            <td>
                                <div class="label">Payment Date</div>
                                <div class="value">{{ $payslip->payment_date?->format('M d, Y') ?: '—' }}</div>
                            </td>
                            <td>
                                <div class="label">Currency</div>
                                <div class="value">{{ $payslip->currency_code }}</div>
                            </td>
                            <td>
                                <div class="label">Generated</div>
                                <div class="value">{{ $payslip->generated_at?->format('M d, Y') ?: '—' }}</div>
                            </td>
                        </tr>
                    </table>
                </section>

                <div class="section-title">Earnings</div>
                <table class="lines-table">
                    <thead>
                    <tr>
                        <th>Description</th>
                        <th>Code</th>
                        <th class="amount">Qty</th>
                        <th class="amount">Rate</th>
                        <th class="amount">Amount</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($payslip->earnings as $line)
                        <tr>
                            <td>{{ $line->description }}</td>
                            <td>{{ $line->pay_code ?: '—' }}</td>
                            <td class="amount">{{ number_format((float) $line->quantity, 2) }}</td>
                            <td class="amount">{{ number_format((float) $line->rate, 2) }}</td>
                            <td class="amount">{{ number_format((float) $line->amount, 2) }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>

                <div class="section-title">Deductions</div>
                <table class="lines-table">
                    <thead>
                    <tr>
                        <th>Description</th>
                        <th>Code</th>
                        <th class="amount">Qty</th>
                        <th class="amount">Rate</th>
                        <th class="amount">Amount</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($payslip->deductions as $line)
                        <tr>
                            <td>{{ $line->description }}</td>
                            <td>{{ $line->pay_code ?: '—' }}</td>
                            <td class="amount">{{ number_format((float) $line->quantity, 2) }}</td>
                            <td class="amount">{{ number_format((float) $line->rate, 2) }}</td>
                            <td class="amount">{{ number_format((float) $line->amount, 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5">No deductions recorded for this period.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>

                <section class="totals">
                    <table class="footer-table">
                        <tr>
                            <td>
                                <div class="label">Amount in Words</div>
                                <div class="value">{{ $payslip->amount_in_words }}</div>
                            </td>
                            <td class="amount">
                                <div class="label">Gross Earnings</div>
                                <div class="value">{{ number_format((float) $payslip->gross_earnings, 2) }}</div>
                            </td>
                            <td class="amount">
                                <div class="label">Deductions</div>
                                <div class="value">{{ number_format((float) $payslip->total_deductions, 2) }}</div>
                            </td>
                            <td class="net-pay">
                                {{ number_format((float) $payslip->net_pay, 2) }}
                            </td>
                        </tr>
                    </table>
                </section>

                <footer class="footer">
                    {{ $payslip->company_address }} @if($payslip->company_phone) · {{ $payslip->company_phone }} @endif @if($payslip->company_email) · {{ $payslip->company_email }} @endif
                </footer>
            </main>
        </article>
    </div>
@endforeach
</body>
</html>
