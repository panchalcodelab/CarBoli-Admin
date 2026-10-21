@props([
    'heading' => null,
    'description' => null,
])

<section {{ $attributes->merge(['class' => 'rounded-xl border border-gray-200 bg-white shadow-sm']) }}>
    @if ($heading || $description)
        <div class="border-b border-gray-100 px-5 py-4">
            @if ($heading)
                <h2 class="text-base font-semibold text-gray-950">{{ $heading }}</h2>
            @endif
            @if ($description)
                <p class="mt-0.5 text-sm text-gray-500">{{ $description }}</p>
            @endif
        </div>
    @endif
    <div {{ $attributes->has('flush') ? '' : 'class=p-5' }}>
        {{ $slot }}
    </div>
</section>
