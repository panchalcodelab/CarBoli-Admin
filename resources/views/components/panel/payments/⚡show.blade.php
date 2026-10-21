<?php

use App\Models\Payment;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('panel.layouts.app')] class extends Component
{
    public int $paymentId;

    public function mount(int $payment): void
    {
        $record = Payment::query()->findOrFail($payment);
        Gate::authorize('view', $record);
        $this->paymentId = $record->id;
    }

    public function payment(): Payment
    {
        return Payment::query()->with(['customer', 'auction'])->findOrFail($this->paymentId);
    }
}; ?>

<div>
    @php($payment = $this->payment())
    @section('title', 'Payment #' . $payment->id)
    @section('breadcrumb', 'Workspace / Payments & Settlements / #' . $payment->id)

    <a href="{{ route('panel.payments.index') }}" wire:navigate class="mb-2 inline-flex items-center gap-1 text-sm text-gray-500 hover:text-carboli-600">← Back to Payments & Settlements</a>

    <x-panel.page-header :title="'Payment #'.$payment->id" :description="$payment->customer?->full_name">
        <x-slot:actions>
            <x-panel.badge :color="$payment->status === 'captured' ? 'success' : ($payment->status === 'failed' ? 'danger' : 'gray')">{{ \Illuminate\Support\Str::of($payment->status)->replace('_',' ')->title() }}</x-panel.badge>
        </x-slot:actions>
    </x-panel.page-header>

    <x-panel.card heading="Transaction details">
        <dl class="grid grid-cols-2 gap-4 text-sm">
            @foreach ([
                'Customer' => $payment->customer?->full_name,
                'Auction' => $payment->auction?->title,
                'Type' => \Illuminate\Support\Str::of($payment->payment_type)->replace('_',' ')->title(),
                'Amount' => number_format((float) $payment->amount, 2).' '.$payment->currency,
                'Gateway' => $payment->gateway,
                'Gateway reference' => $payment->gateway_reference_id,
                'Successful at' => $payment->successful_at?->format('d M Y H:i'),
                'Captured at' => $payment->captured_at?->format('d M Y H:i'),
                'Released at' => $payment->released_at?->format('d M Y H:i'),
                'Refunded at' => $payment->refunded_at?->format('d M Y H:i'),
                'Failure reason' => $payment->failure_reason,
                'Created' => $payment->created_at?->format('d M Y H:i'),
            ] as $k => $v)
                <div><dt class="text-xs uppercase tracking-wide text-gray-400">{{ $k }}</dt><dd class="mt-0.5 text-gray-900">{{ $v !== null && $v !== '' ? $v : '—' }}</dd></div>
            @endforeach
        </dl>
        <p class="mt-4 rounded-lg bg-gray-50 px-3 py-2 text-xs text-gray-500">This is a read-only financial record. Payment state changes are performed through the controlled payment workflow, not from this screen.</p>
    </x-panel.card>
</div>
