@props([
    'show' => false,
    'title' => 'Confirm',
    'confirmLabel' => 'Confirm',
    'confirmAction' => null,
    'cancelAction' => 'cancelConfirm',
    'tone' => 'danger',
    'busyLabel' => 'Working…',
])

@php
    $tones = [
        'danger' => [
            'badge' => 'bg-red-50 text-red-600',
            'button' => 'bg-red-600 hover:bg-red-700',
        ],
        'primary' => [
            'badge' => 'bg-carboli-50 text-carboli-600',
            'button' => 'bg-carboli-600 hover:bg-carboli-700',
        ],
    ];
    $toneClasses = $tones[$tone] ?? $tones['danger'];
@endphp

{{--
    Reusable confirmation dialog. Visibility is driven by a Livewire property
    (passed via :show). The Escape key and the backdrop both trigger the cancel
    action. The confirm button fires the given Livewire method. Authorization is
    always enforced server-side in the action handler — this dialog is only a
    UX guard. The backdrop colour is an inline style so the dimming can never be
    dropped by Tailwind's class purging.
--}}
<div
    x-data="{ show: @js((bool) $show) }"
    x-effect="show = @js((bool) $show); document.body.classList.toggle('overflow-hidden', show)"
    x-show="show"
    x-transition.opacity
    x-cloak
    @keydown.escape.window="$wire.{{ $cancelAction }}()"
    @click.self="$wire.{{ $cancelAction }}()"
    class="fixed inset-0 z-50 flex items-center justify-center p-4"
    style="background-color: rgba(17, 24, 39, 0.6);"
    role="dialog"
    aria-modal="true"
    aria-labelledby="confirm-modal-title"
>
    <div
        x-show="show"
        x-transition
        @click.stop
        class="relative w-full max-w-md rounded-2xl bg-white p-6 shadow-xl"
    >
        <div class="flex items-start gap-4">
            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full {{ $toneClasses['badge'] }}">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                </svg>
            </span>
            <div class="min-w-0">
                <h2 id="confirm-modal-title" class="text-base font-semibold text-gray-950">{{ $title }}</h2>
                <div class="mt-1 text-sm text-gray-500">{{ $slot }}</div>
            </div>
        </div>

        <div class="mt-6 flex items-center justify-end gap-2">
            <button type="button" wire:click="{{ $cancelAction }}"
                    class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                Cancel
            </button>
            <button type="button" wire:click="{{ $confirmAction }}" wire:loading.attr="disabled"
                    class="inline-flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-semibold text-white disabled:opacity-60 {{ $toneClasses['button'] }}">
                <span wire:loading.remove wire:target="{{ $confirmAction }}">{{ $confirmLabel }}</span>
                <span wire:loading wire:target="{{ $confirmAction }}">{{ $busyLabel }}</span>
            </button>
        </div>
    </div>
</div>
