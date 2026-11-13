<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Bookings\BookingResource;
use App\Support\SalesFigures as F;
use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** Top row of the dashboard. Profit is shown to admins only. */
class SalesOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    /** Refresh every 2 minutes rather than the default few seconds. */
    protected function getPollingInterval(): ?string
    {
        return '120s';
    }

    protected function getStats(): array
    {
        $isAdmin = Filament::auth()->user()?->isAdmin() ?? false;
        $daily = F::dailySales(14);

        $salesMonth = F::sales('month');
        $salesLastMonth = F::sales('last_month');
        $awaiting = F::awaitingSupplier();
        $awaitingCount = (clone $awaiting)->count();
        $declined = (clone $awaiting)->where('booking_items.status', 'rejected')->count();
        [$unpaid, $unpaidCount, $overdue] = F::unpaidOnAccount();
        $refundsMonth = F::refunds('month');

        $stats = [
            Stat::make('Sales today', F::money(F::sales('today')))
                ->description(F::bookings('today').' booking'.(F::bookings('today') === 1 ? '' : 's').' today')
                ->chart(array_values($daily))
                ->color('primary'),

            Stat::make('Sales this month', F::money($salesMonth))
                ->description(F::bookings('month').' bookings · last month '.F::money($salesLastMonth))
                ->color('primary'),
        ];

        if ($isAdmin) {
            $profit = F::profit('month');
            $stats[] = Stat::make('Profit this month', F::money($profit))
                ->description(($salesMonth > 0 ? round($profit / $salesMonth * 100).'% margin · ' : '').'after supplier cost, Stripe fees and refunds')
                ->color($profit >= 0 ? 'success' : 'danger');
        }

        $stats[] = Stat::make('Waiting for suppliers', (string) $awaitingCount)
            ->description($declined > 0 ? $declined.' declined, needs a new supplier' : ($awaitingCount ? 'Upcoming services not yet confirmed' : 'All upcoming services confirmed'))
            ->color($declined > 0 ? 'danger' : ($awaitingCount ? 'warning' : 'success'))
            ->url(BookingResource::getUrl('index'));

        $stats[] = Stat::make('Unpaid on account', F::money($unpaid))
            ->description($unpaidCount.' booking'.($unpaidCount === 1 ? '' : 's').($overdue ? ' · '.$overdue.' overdue' : ''))
            ->color($overdue ? 'danger' : 'gray');

        $stats[] = Stat::make('Refunds this month', F::money($refundsMonth))
            ->color($refundsMonth > 0 ? 'warning' : 'gray');

        return $stats;
    }
}
