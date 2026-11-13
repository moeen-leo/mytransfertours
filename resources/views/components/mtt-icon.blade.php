@props(['name', 'size' => 18])
{{-- Small stroke icons that take the text colour. Decorative: the text beside them says what they mean. --}}
<svg {{ $attributes->merge(['class' => 'icon']) }} width="{{ $size }}" height="{{ $size }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
@switch($name)
    @case('person')
        <circle cx="12" cy="7" r="3.5"/><path d="M5 20c0-5 3.5-7 7-7s7 2 7 7"/>
        @break
    @case('seat')
        <path d="M7 4v9h10"/><path d="M7 13l-1 7"/><path d="M17 13v7"/><path d="M7 9h7a2 2 0 0 1 2 2v2"/>
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
