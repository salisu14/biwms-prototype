<?php

namespace App\Filament\Widgets\Reports;

use Filament\Widgets\ChartWidget;

class SalesChartWidget extends ChartWidget
{
    protected static ?int $sort = 2;
    protected int|string|array $columnSpan = 'full';
    protected static string $height = '400px';

    public array $data = [];

    protected function getType(): string
    {
        return 'bar'; // or 'pie'
    }

    protected function getData(): array
    {
        if (empty($this->data)) {
            return [];
        }

        return [
            'datasets' => [
                [
                    'label' => 'Revenue',
                    'data' => array_column($this->data, 'total_revenue'),
                    'backgroundColor' => [
                        '#f59e0b', # amber - DOMESTIC
                        '#10b981', # emerald - EXPORT
                        '#3b82f6', # blue - FOREIGN
                        '#8b5cf6', # violet - MANUFACTURING
                        '#ec4899', # pink
                        '#06b6d4', # sky
                        '#84cc16', # lime
                        '#f97316', # orange
                        '#ef4444', # red
                    ],
                    'borderWidth' => 1,
                    'borderRadius' => 4,
                ],
            ],
            'labels' => array_map(fn($item) => "{$item['group_code']} - {$item['group_name']}",
                $this->data
            ),
        ];
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => ['position' => 'bottom'],
                'tooltip' => [
                    'callbacks' => [
                        'label' => "function(context) {
                            const label = context.dataset.label || '';
                            const value = context.parsed.y;
                            return label + ': ₦' + value.toLocaleString();
                        }",
                    ],
                ],
            ],
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'ticks' => [
                        'callback' => "function(value) {
                            if (value >= 1000000) return (value/1000000).toFixed(1) + 'M';
                            if (value >= 1000) return (value/1000).toFixed(1) + 'K';
                            return '₦' + value.toLocaleString();
                        }",
                    ],
                    'grid' => ['display' => true],
                ],
                'x' => [
                    'grid' => ['display' => false],
                    'ticks' => [
                        'maxRotation' => 45,
                        'minRotation' => 0,
                    ],
                ],
            ],
            'responsive' => true,
            'maintainAspectRatio' => false,
        ];
    }
}
