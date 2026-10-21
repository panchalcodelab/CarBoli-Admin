@props([
    'label',
    'value',
    'trend' => null,
    'trendUp' => true,
])

<div {{ $attributes->merge(['class' => 'rounded-xl border border-gray-200 bg-white p-5 shadow-sm']) }}>
    <div class="flex items-start justify-between">
        @isset($icon)
            <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-carboli-50 text-carboli-600">
                {{ $icon }}
            </span>
        @endisset
        @if (! is_null($trend))
            <span @class([
                'text-xs font-semibold',
                'text-green-600' => $trendUp,
                'text-red-600' => ! $trendUp,
            ])>{{ $trend }}</span>
        @endif
    </div>
    <p class="mt-4 text-2xl font-bold text-gray-950">{{ $value }}</p>
    <p class="mt-1 text-sm text-gray-500">{{ $label }}</p>
</div>
