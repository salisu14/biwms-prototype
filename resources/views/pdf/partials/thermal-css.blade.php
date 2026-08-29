<style>
    @page {
        size: {{ $width_mm ?? 80 }}mm {{ $height_mm ?? 260 }}mm;
        margin: {{ $margin_mm ?? 4 }}mm {{ $margin_mm ?? 4 }}mm {{ $margin_mm ?? 5 }}mm {{ $margin_mm ?? 4 }}mm;
    }

    * {
        box-sizing: border-box;
    }

    body {
        margin: 0;
        padding: 0;
        font-family: DejaVu Sans, sans-serif;
        font-size: {{ $body_font_size ?? 9 }}px;
        color: #111827;
        line-height: 1.25;
    }

    .page {
        width: 100%;
    }

    .header {
        margin-bottom: 6px;
        border-bottom: 1px solid #d1d5db;
        padding-bottom: 5px;
    }

    .company-header {
        margin-bottom: 5px;
        text-align: center;
    }

    .company-logo {
        margin-bottom: 3px;
    }

    .company-logo img {
        max-height: {{ $width_mm === 58 ? 18 : 22 }}px;
        max-width: 100%;
    }

    .company-name {
        font-weight: 700;
        font-size: {{ $width_mm === 58 ? 10 : 11 }}px;
        line-height: 1.15;
        margin-bottom: 1px;
        word-break: break-word;
    }

    .company-trading {
        font-size: {{ $width_mm === 58 ? 7 : 8 }}px;
        color: #374151;
        line-height: 1.15;
        margin-bottom: 2px;
        word-break: break-word;
    }

    .company-meta {
        font-size: {{ $width_mm === 58 ? 7 : 8 }}px;
        color: #4b5563;
        line-height: 1.2;
        word-break: break-word;
    }

    .company-meta .divider {
        padding: 0 3px;
    }

    .title {
        font-size: {{ $title_font_size ?? 12 }}px;
        font-weight: 700;
        text-align: center;
        letter-spacing: 0.2px;
        margin: 0 0 4px;
        text-transform: uppercase;
    }

    .brand {
        font-weight: 700;
        text-align: center;
        margin-bottom: 2px;
        word-break: break-word;
    }

    .meta {
        width: 100%;
        font-size: {{ $meta_font_size ?? 8 }}px;
    }

    .meta td {
        padding: 1px 0;
        vertical-align: top;
        word-break: break-word;
    }

    .section {
        margin-top: 6px;
    }

    .card {
        border: 1px solid #d1d5db;
        border-radius: 3px;
        padding: 5px;
        margin-bottom: 6px;
    }

    .card-title {
        font-weight: 700;
        margin-bottom: 4px;
        text-transform: uppercase;
        font-size: {{ $section_title_font_size ?? 8 }}px;
    }

    .row {
        display: flex;
        justify-content: space-between;
        gap: 6px;
        padding: 1px 0;
        border-bottom: 1px dashed #e5e7eb;
        word-break: break-word;
    }

    .row:last-child {
        border-bottom: 0;
    }

    .label {
        font-weight: 700;
        flex: 0 0 auto;
        max-width: 48%;
    }

    .value {
        text-align: right;
        flex: 1 1 auto;
        word-break: break-word;
    }

    .table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 4px;
        table-layout: fixed;
    }

    .table th,
    .table td {
        border-bottom: 1px solid #d1d5db;
        padding: 3px 0;
        vertical-align: top;
        word-break: break-word;
        overflow-wrap: break-word;
    }

    .table th {
        text-align: left;
        font-size: {{ $table_header_font_size ?? 7 }}px;
        text-transform: uppercase;
        letter-spacing: 0.1px;
    }

    .table .right {
        text-align: right;
    }

    .muted {
        color: #6b7280;
    }

    .strong {
        font-weight: 700;
    }

    .footer {
        margin-top: 8px;
        padding-top: 4px;
        border-top: 1px solid #d1d5db;
        font-size: 7px;
        color: #6b7280;
        text-align: center;
        word-break: break-word;
    }

    .spacer {
        height: 4px;
    }
</style>
