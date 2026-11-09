<?php

namespace App\Filament\Widgets;

use App\Support\SalesFigures as F;
use Filament\Widgets\Widget;

class TopProducts extends Widget
{
    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.top-products';

    public string $period = 'month';

    protected function getViewData(): array
    {
        return [
            'rows' => F::topProducts($this->period === 'last_month' ? 'last_month' : 'month'),
        ];
    }
}
