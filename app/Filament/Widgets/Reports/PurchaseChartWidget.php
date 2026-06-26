<?php

namespace App\Filament\Widgets\Reports;

use Filament\Widgets\ChartWidget;

class PurchaseChartWidget extends ChartWidget
{
    protected static ?int $sort = 2;
    protected int|string|array $columnSpan = 'full';
    protected static string $height = '350px';
    protected ?string $heading = 'Cost Distribution';
    protected ?string $description = 'Breakdown by posting group';

    public array $data = [];

    protected function getType(): string
    {
        return 'doughnut'; // Try: 'bar', 'pie', 'polarArea'
    }

    protected function getData(): array
    {
        if (empty($this->data)) {
            return [
                'datasets' => [],
                'labels' => [],
            ];
        }

        // ✅ Dynamic colors based on data length
        $colors = $this->generateColors(count($this->data));

        return [
            'datasets' => [
                [
                    'data' => array_column($this->data, 'total_cost'),
                    'backgroundColor' => $colors,
                    'borderWidth' => 3,
                    'borderColor' => '#ffffff',
                    'hoverBorderWidth' => 4,
                    'hoverBorderColor' => '#ffffff',
                ],
            ],
            'labels' => array_map(
                fn($item) => "{$item['group_code']} ({$item['percentage_of_total']}%)",
                $this->data
            ),
        ];
    }

    protected function getOptions(): array
    {
        return [
            'responsive' => true,
            'maintainAspectRatio' => false,
            'cutout' => '65%', // Larger center hole for doughnut
            'plugins' => [
                'legend' => [
                    'position' => 'right',
                    'labels' => [
                        'padding' => 20,
                        'usePointStyle' => true,
                        'pointStyle' => 'circle',
                        'font' => [
                            'size' => 12,
                            'family' => 'inherit',
                        ],
                    ],
                ],
                'tooltip' => [
                    'backgroundColor' => 'rgba(0, 0, 0, 0.8)',
                    'titleFont' => ['size' => 14],
                    'bodyFont' => ['size' => 13],
                    'padding' => 12,
                    'callbacks' => [
                        'label' => "function(context) {
                            const value = context.parsed;
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const pct = ((value / total) * 100).toFixed(1);
                            return ' ₦' + value.toLocaleString('en-NG', {minimumFractionDigits: 2}) + ' (' + pct + '%)';
                        }",
                    ],
                ],
            ],
            'animation' => [
                'animateRotate' => true,
                'animateScale' => true,
                'duration' => 1000,
                'easing' => 'easeOutQuart',
            ],
        ];
    }

    /**
     * Generate vibrant distinct colors
     */
    private function generateColors(int $count): array
    {
        $baseColors = [
            '#ef4444', '#f97316', '#f59e0b', '#eab308',
            '#84cc16', '#22c55e', '#14b8a6', '#06b6d4',
            '#3b82f6', '#6366f1', '#8b5cf6', '#a855f7',
            '#ec4899', '#f43f5e',
        ];

        // If more colors needed, generate them
        if ($count <= count($baseColors)) {
            return array_slice($baseColors, 0, $count);
        }

        // Generate additional colors using HSL rotation
        $colors = $baseColors;
        for ($i = count($baseColors); $i < $count; $i++) {
            $hue = ($i * 137.508) % 360; // Golden angle for distribution
            $colors[] = "hsl({$hue}, 70%, 55%)";
        }

        return $colors;
    }
}
