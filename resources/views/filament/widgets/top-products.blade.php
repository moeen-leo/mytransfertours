@php
    $labels = ['transfer' => 'Transfer', 'tour' => 'Tour', 'attraction' => 'Ticket'];
    $max = collect($rows)->max('sales') ?: 1;
@endphp
<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">Best sellers</x-slot>
        <x-slot name="afterHeader">
            <select wire:model.live="period" style="border: 1px solid #D1D5DB; border-radius: 8px; padding: 4px 28px 4px 10px; font-size: 13px; background-color: transparent;">
                <option value="month">This month</option>
                <option value="last_month">Last month</option>
            </select>
        </x-slot>

        @if (empty($rows))
            <p style="color: #6B7280; font-size: 14px; margin: 0;">No sales in this period yet.</p>
        @else
            <div style="display: flex; flex-direction: column; gap: 12px;">
                @foreach ($rows as $r)
                    <div>
                        <div style="display: flex; justify-content: space-between; gap: 12px; font-size: 14px;">
                            <span style="min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                <span style="display: inline-block; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .03em; color: #0F4C3A; background: #E7F0EC; border-radius: 4px; padding: 1px 6px; margin-right: 6px;">{{ $labels[$r['type']] ?? $r['type'] }}</span>
                                {{ $r['title'] }}
                            </span>
                            <span style="white-space: nowrap; font-variant-numeric: tabular-nums;">
                                <span style="color: #6B7280;">{{ $r['qty'] }} sold ·</span>
                                <b>{{ \App\Support\SalesFigures::money($r['sales']) }}</b>
                            </span>
                        </div>
                        <div style="height: 6px; border-radius: 3px; background: #F3F4F6; margin-top: 6px;">
                            <div style="height: 6px; border-radius: 3px; background: #0F4C3A; width: {{ max(2, round($r['sales'] / $max * 100)) }}%;"></div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
