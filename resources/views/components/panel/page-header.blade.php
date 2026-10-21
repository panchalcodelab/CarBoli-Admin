@props([
    'overline' => null,
    'title',
    'description' => null,
])

<div {{ $attributes->merge(['class' => 'mb-6 flex flex-wrap items-start justify-between gap-4']) }}>
    <div>
        @if ($overline)
            <p class="text-sm font-semibold uppercase tracking-wide text-carboli-600">{{ $overline }}</p>
        @endif
        <h1 class="mt-1 text-2xl font-bold tracking-tight text-gray-950">{{ $title }}</h1>
        @if ($description)
            <p class="mt-1 text-sm text-gray-500">{{ $description }}</p>
        @endif
    </div>

    @isset($actions)
        <div class="flex items-center gap-2">
            {{ $actions }}
        </div>
    @endisset
</div>
