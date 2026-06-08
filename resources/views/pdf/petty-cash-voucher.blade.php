<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Petty Cash Voucher {{ $voucher->voucher_number }}</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; line-height: 1.4; }
        .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #333; padding-bottom: 20px; }
        .company-name { font-size: 24px; font-weight: bold; margin-bottom: 5px; }
        .doc-title { font-size: 18px; margin-top: 10px; color: #555; }
        .voucher-number { font-size: 14px; color: #888; margin-top: 5px; }
        .section { margin-bottom: 20px; }
        .section-title { font-size: 14px; font-weight: bold; border-bottom: 1px solid #ddd; padding-bottom: 5px; margin-bottom: 10px; }
        .info-table { width: 100%; margin-bottom: 15px; }
        .info-table td { padding: 6px 10px; vertical-align: top; }
        .info-table td:first-child { font-weight: bold; width: 30%; color: #555; }
        .lines-table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        .lines-table th, .lines-table td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        .lines-table th { background-color: #f5f5f5; font-weight: bold; }
        .lines-table td.amount { text-align: right; }
        .total-row { font-weight: bold; background-color: #f9f9f9; }
        .status-badge { display: inline-block; padding: 4px 12px; border-radius: 12px; font-size: 11px; font-weight: bold; text-transform: uppercase; }
        .status-pending { background: #fef3c7; color: #92400e; }
        .status-approved { background: #d1fae5; color: #065f46; }
        .status-posted { background: #dbeafe; color: #1e40af; }
        .status-rejected { background: #fee2e2; color: #991b1b; }
        .status-cancelled { background: #f3f4f6; color: #4b5563; }
        .signatures { margin-top: 50px; display: flex; justify-content: space-between; }
        .signature-box { width: 30%; text-align: center; }
        .signature-line { border-top: 1px solid #333; margin-top: 40px; padding-top: 5px; font-size: 11px; }
        .footer { margin-top: 40px; text-align: center; font-size: 10px; color: #888; border-top: 1px solid #ddd; padding-top: 10px; }
    </style>
</head>
<body>
<div class="header">
    <div class="company-name">Bifli Global Resources</div>
    <div class="doc-title">PETTY CASH VOUCHER</div>
    <div class="voucher-number">Voucher No: {{ $voucher->voucher_number }}</div>
    <div style="margin-top: 10px;">
            <span class="status-badge status-{{ $voucher->status->value }}">
                {{ $voucher->status->label() }}
            </span>
    </div>
</div>

<div class="section">
    <div class="section-title">Voucher Information</div>
    <table class="info-table">
        <tr>
            <td>Date:</td>
            <td>{{ $voucher->date->format('F d, Y') }}</td>
        </tr>
        <tr>
            <td>Petty Cash Fund:</td>
            <td>{{ $voucher->fund->name }} ({{ $voucher->fund->code }})</td>
        </tr>
        <tr>
            <td>Fund Location:</td>
            <td>{{ $voucher->fund->location ?? '—' }}</td>
        </tr>
        <tr>
            <td>Fund Custodian:</td>
            <td>{{ $voucher->fund->custodian?->name ?? '—' }}</td>
        </tr>
    </table>
</div>

<div class="section">
    <div class="section-title">Payee Details</div>
    <table class="info-table">
        <tr>
            <td>Payee Name:</td>
            <td>{{ $voucher->payee_name }}</td>
        </tr>
        <tr>
            <td>Payee Description:</td>
            <td>{{ $voucher->payee_description ?? '—' }}</td>
        </tr>
        <tr>
            <td>Purpose:</td>
            <td>{{ $voucher->purpose }}</td>
        </tr>
    </table>
</div>

<div class="section">
    <div class="section-title">Voucher Lines</div>
    <table class="lines-table">
        <thead>
        <tr>
            <th>#</th>
            <th>Expense Account</th>
            <th>Description</th>
            <th class="amount">Amount</th>
            <th>Department</th>
            <th>Project</th>
        </tr>
        </thead>
        <tbody>
        @foreach($voucher->lines as $line)
            <tr>
                <td>{{ $line->line_number }}</td>
                <td>{{ $line->expenseAccount?->name ?? '—' }}</td>
                <td>{{ $line->description }}</td>
                <td class="amount">{{ number_format($line->amount, 2) }}</td>
                <td>{{ $line->department?->name ?? '—' }}</td>
                <td>{{ $line->project?->name ?? '—' }}</td>
            </tr>
        @endforeach
        <tr class="total-row">
            <td colspan="3" style="text-align: right;">Total Amount:</td>
            <td class="amount">{{ number_format($voucher->total_amount, 2) }}</td>
            <td colspan="2"></td>
        </tr>
        </tbody>
    </table>
</div>

@if($voucher->notes)
    <div class="section">
        <div class="section-title">Notes</div>
        <p>{{ $voucher->notes }}</p>
    </div>
@endif

@if($voucher->rejection_reason)
    <div class="section">
        <div class="section-title">Rejection Reason</div>
        <p style="color: #991b1b;">{{ $voucher->rejection_reason }}</p>
    </div>
@endif

{{--<div class="section">--}}
{{--    <div class="section-title">Workflow History</div>--}}
{{--    <table class="info-table">--}}
{{--        <tr>--}}
{{--            <td>Requested By:</td>--}}
{{--            <td>{{ $voucher->requestedBy?->name ?? '—' }} on {{ $voucher->created_at?->format('M d, Y H:i') ?? '—' }}</td>--}}
{{--        </tr>--}}
{{--        <tr>--}}
{{--            <td>Approved By:</td>--}}
{{--            <td>--}}
{{--                @if($voucher->approvedBy)--}}
{{--                    {{ $voucher->approvedBy->name }} on {{ $voucher->updated_at?->format('M d, Y H:i') ?? '—' }}--}}
{{--                @else--}}
{{--                    —--}}
{{--                @endif--}}
{{--            </td>--}}
{{--        </tr>--}}
{{--        <tr>--}}
{{--            <td>Posted By:</td>--}}
{{--            <td>--}}
{{--                @if($voucher->postedBy)--}}
{{--                    {{ $voucher->postedBy->name }} on {{ $voucher->posted_at?->format('M d, Y H:i') ?? '—' }}--}}
{{--                @else--}}
{{--                    —--}}
{{--                @endif--}}
{{--            </td>--}}
{{--        </tr>--}}
{{--    </table>--}}
{{--</div>--}}

<div class="signatures">
    <div class="signature-box">
        <div class="signature-line">Prepared By</div>
        <div>{{ $voucher->requestedBy?->name ?? '_________________' }}</div>
    </div>
    <div class="signature-box">
        <div class="signature-line">Approved By</div>
        <div>{{ $voucher->approvedBy?->name ?? '_________________' }}</div>
    </div>
    <div class="signature-box">
        <div class="signature-line">Received By</div>
        <div>{{ $voucher->payee_name ?? '_________________' }}</div>
    </div>
</div>

<div class="footer">
    This is a computer-generated document. Printed on {{ now()->format('F d, Y H:i') }}.
    @if($voucher->status->value === 'posted')
        <br>GL Entries have been posted. Fund balance updated.
    @endif
</div>
</body>
</html>
