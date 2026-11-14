<?php

namespace App\Filament\Widgets;

use App\Models\Currency;
use App\Support\SalesFigures as F;
use Carbon\CarbonImmutable;
use Filament\Widgets\ChartWidget;

class SalesChart extends ChartWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    public ?string $filter = '30d';

    public function getHeading(): ?string
    {
        return 'Sales ('.Currency::base().')';
    }

    protected function getFilters(): ?array
    {
        return ['30d' => 'Last 30 days', '90d' => 'Last 90 days', '12m' => 'Last 12 months'];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        if ($this->filter === '12m') {
            $series = F::monthlySales(12);
            $labels = array_map(fn ($k) => CarbonImmutable::createFromFormat('Y-m', $k)->format('M Y'), array_keys($series));
        } else {
            $series = F::dailySales($this->filter === '90d' ? 90 : 30);
            $labels = array_map(fn ($k) => CarbonImmutable::parse($k)->format('j M'), array_keys($series));
        }

        return [
            'datasets' => [[
                'label' => 'Sales',
                'data' => array_values($series),
                'backgroundColor' => '#0F4C3A',
                'borderRadius' => 4,
            ]],
            'labels' => $labels,
        ];
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => ['legend' => ['display' => false]],
            'scales' => ['y' => ['beginAtZero' => true]],
        ];
    }
}
