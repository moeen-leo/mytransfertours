@props(['name', 'size' => 18])
{{-- Small stroke icons that take the text colour. Decorative: the text beside them says what they mean. --}}
<svg {{ $attributes->merge(['class' => 'icon']) }} width="{{ $size }}" height="{{ $size }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
@switch($name)
    @case('person')
        <circle cx="12" cy="7" r="3.5"/><path d="M5 20c0-5 3.5-7 7-7s7 2 7 7"/>
        @break
    @case('seat')
        {{-- Car seat, side view: headrest, reclined backrest, cushion --}}
        <rect x="8.5" y="2.5" width="5" height="4" rx="1.5"/><path d="M10 7.5 8 16.5a1.5 1.5 0 0 0 1.5 1.8H18a1.5 1.5 0 0 0 1.5-1.5 2.6 2.6 0 0 0-2.6-2.6h-5.2l1.6-6.7"/><path d="M10 21h7"/>
        @break
    @case('bag')
        <rect x="5" y="7" width="14" height="13" rx="2"/><path d="M9 7V4h6v3"/>
        @break
    @case('swap')
        <path d="M4 8h14l-4-4"/><path d="M20 16H6l4 4"/>
        @break
    @case('clock')
        <circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>
        @break
@endswitch
</svg>
