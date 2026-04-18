<?php

namespace App\Services;

use Illuminate\Support\Collection;

class IncomeStatementReport
{
    public function __construct(
        public Collection $rows,
        public array $summary,
        public string $period,
        public array $dimensions
    ) {}

    public function toArray(): array
    {
        return [
            'period' => $this->period,
            'dimensions' => $this->dimensions,
            'summary' => $this->summary,
            'rows' => $this->rows->toArray(),
        ];
    }

    public function toBcFormat(): array
    {
        // BC-style formatted output with indentation
        return [
            'report_name' => 'Income Statement',
            'company_name' => config('app.company_name'),
            'period' => $this->period,
            'printed_at' => now()->format('Y-m-d H:i'),
            'lines' => $this->rows->map(fn ($row) => [
                'account_no' => $row['account_no'],
                'indentation' => $row['indentation'],
                'description' => $row['account_name'],
                'amount' => number_format($row['net_change'], 2),
                'compare_amount' => isset($row['compare_amount']) ? number_format($row['compare_amount'], 2) : null,
                'variance' => isset($row['variance']) ? number_format($row['variance'], 2) : null,
                'variance_percent' => isset($row['variance_percent']) ? number_format($row['variance_percent'], 1).'%' : null,
                'bold' => $row['bold'],
            ]),
            'totals' => [
                'revenue' => number_format($this->summary['total_revenue'], 2),
                'cogs' => number_format($this->summary['total_cogs'], 2),
                'gross_profit' => number_format($this->summary['gross_profit'], 2),
                'operating_expenses' => number_format($this->summary['operating_expenses'], 2),
                'operating_income' => number_format($this->summary['operating_income'], 2),
                'net_income' => number_format($this->summary['net_income'], 2),
                // Comparative Totals
                'compare_revenue' => number_format($this->summary['compare_total_revenue'] ?? 0, 2),
                'compare_gross_profit' => number_format($this->summary['compare_gross_profit'] ?? 0, 2),
                'compare_operating_expenses' => number_format($this->summary['compare_operating_expenses'] ?? 0, 2),
                'compare_net_income' => number_format($this->summary['compare_net_income'] ?? 0, 2),
            ],
        ];
    }
}
