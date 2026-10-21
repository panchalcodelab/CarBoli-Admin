@props([
    'color' => 'gray',
])

@php
    $classes = [
        'gray' => 'bg-gray-100 text-gray-700',
        'success' => 'bg-green-100 text-green-700',
        'warning' => 'bg-amber-100 text-amber-700',
        'info' => 'bg-sky-100 text-sky-700',
        'danger' => 'bg-red-100 text-red-700',
        'primary' => 'bg-carboli-50 text-carboli-700',
    ][$color] ?? 'bg-gray-100 text-gray-700';
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-medium {$classes}"]) }}>
    <span class="h-1.5 w-1.5 rounded-full bg-current opacity-70"></span>
    {{ $slot }}
</span>
