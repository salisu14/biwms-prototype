<style>
    .report-table,
    .expense-report-table {
        border-collapse: collapse;
        width: 100%;
    }

    .report-table th,
    .report-table td,
    .expense-report-table th,
    .expense-report-table td {
        border: 1px solid #94a3b8 !important;
        padding: 10px 14px !important;
        line-height: 1.45 !important;
    }

    .dark .report-table th,
    .dark .report-table td,
    .dark .expense-report-table th,
    .dark .expense-report-table td {
        border-color: #64748b !important;
    }

    .report-table th,
    .expense-report-table th {
        font-weight: 600;
    }

    @if(($includeFilamentTable ?? false) === true)
    .fi-ta table,
    .fi-ta-content table {
        border-collapse: collapse !important;
    }

    .fi-ta table th,
    .fi-ta table td,
    .fi-ta-content table th,
    .fi-ta-content table td {
        border: 1px solid #94a3b8 !important;
        padding: 10px 14px !important;
        line-height: 1.45 !important;
    }

    .dark .fi-ta table th,
    .dark .fi-ta table td,
    .dark .fi-ta-content table th,
    .dark .fi-ta-content table td {
        border-color: #64748b !important;
    }

    .fi-ta-header-cell {
        font-weight: 600 !important;
    }
    @endif
</style>
