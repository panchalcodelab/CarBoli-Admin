<?php

use App\Models\AuditLog;
use App\Models\CustomerKyc;
use App\Services\KycReviewService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('panel.layouts.app')] class extends Component
{
    public int $kycId;
    public string $rejectReason = '';

    public bool $confirmingApprove = false;

    public function mount(int $kyc): void
    {
        $record = CustomerKyc::query()->findOrFail($kyc);
        Gate::authorize('view', $record);
        $this->kycId = $record->id;
    }

    public function confirmApprove(): void
    {
        $this->confirmingApprove = true;
    }

    public function cancelApprove(): void
    {
        $this->confirmingApprove = false;
    }

    public function record(): CustomerKyc
    {
        return CustomerKyc::query()->with(['customer', 'reviewer'])->findOrFail($this->kycId);
    }

    public function startReview(KycReviewService $service): void
    {
        $record = $this->record();
        Gate::authorize('review', $record);
        try {
            $service->markUnderReview($record);
            session()->flash('status', 'KYC marked under review.');
        } catch (ValidationException $e) {
            $this->addError('action', collect($e->errors())->flatten()->first());
        }
    }

    public function approve(KycReviewService $service): void
    {
        $record = $this->record();
        Gate::authorize('review', $record);
        try {
            $service->approve($record);
            session()->flash('status', 'KYC approved.');
        } catch (ValidationException $e) {
            $this->addError('action', collect($e->errors())->flatten()->first());
        } finally {
            $this->confirmingApprove = false;
        }
    }

    public function reject(KycReviewService $service): void
    {
        $record = $this->record();
        Gate::authorize('review', $record);
        $this->validate(['rejectReason' => ['required', 'string', 'max:2000']]);
        try {
            $service->reject($record, $this->rejectReason);
            $this->rejectReason = '';
            session()->flash('status', 'KYC rejected.');
        } catch (ValidationException $e) {
            $this->addError('action', collect($e->errors())->flatten()->first());
        }
    }

    /**
     * @return array<int, string>
     */
    public function history(): array
    {
        return AuditLog::query()
            ->with('user')
            ->where('entity_type', CustomerKyc::class)
            ->where('entity_id', $this->kycId)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (AuditLog $log): string => implode(' · ', array_filter([
                $log->created_at?->toDateTimeString(),
                $log->user?->name,
                $log->action,
                $log->new_values['rejection_reason'] ?? null,
            ])))
            ->all();
    }
}; ?>

<div>
    @php($kyc = $this->record())
    @php($canViewKyc = Gate::allows('view customer kyc'))
    @php($canReview = Gate::allows('review', $kyc))
    @section('title', 'KYC — ' . ($kyc->customer?->full_name ?? 'Record'))
    @section('breadcrumb', 'Workspace / KYC Verification / ' . ($kyc->customer?->full_name ?? 'Record'))

    <a href="{{ route('panel.kyc.index') }}" wire:navigate class="mb-2 inline-flex items-center gap-1 text-sm text-gray-500 hover:text-carboli-600">← Back to KYC Verification</a>

    <x-panel.page-header :title="$kyc->customer?->full_name ?? 'KYC record'" :description="$kyc->customer?->email">
        <x-slot:actions>
            <x-panel.badge :color="$kyc->status === 'approved' ? 'success' : ($kyc->status === 'rejected' ? 'danger' : ($kyc->status === 'under_review' ? 'warning' : 'gray'))">
                {{ \Illuminate\Support\Str::of($kyc->status)->replace('_',' ')->title() }}
            </x-panel.badge>
        </x-slot:actions>
    </x-panel.page-header>

    @if (session('status'))
        <div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('status') }}</div>
    @endif
    @error('action') <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ $message }}</div> @enderror

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <x-panel.card heading="Identity verification">
                <dl class="grid grid-cols-2 gap-4 text-sm">
                    <div><dt class="text-xs uppercase text-gray-400">Customer</dt><dd class="mt-0.5 text-gray-900">{{ $kyc->customer?->full_name ?? '—' }}</dd></div>
                    <div><dt class="text-xs uppercase text-gray-400">Mobile</dt><dd class="mt-0.5 text-gray-900">{{ $kyc->customer?->mobile_number ?? '—' }}</dd></div>
                    @if ($canViewKyc)
                        <div><dt class="text-xs uppercase text-gray-400">PAN</dt><dd class="mt-0.5 text-gray-900">{{ $kyc->pan ?: '—' }}</dd></div>
                        <div><dt class="text-xs uppercase text-gray-400">Document type</dt><dd class="mt-0.5 text-gray-900">{{ $kyc->identity_document_type ?: '—' }}</dd></div>
                        <div><dt class="text-xs uppercase text-gray-400">Document number</dt><dd class="mt-0.5 text-gray-900">{{ $kyc->identity_document_number ?: '—' }}</dd></div>
                        <div><dt class="text-xs uppercase text-gray-400">Address proof</dt><dd class="mt-0.5 text-gray-900">{{ $kyc->address_proof ?: '—' }}</dd></div>
                        @if ($kyc->rejection_reason)
                            <div class="col-span-2"><dt class="text-xs uppercase text-gray-400">Rejection reason</dt><dd class="mt-0.5 text-gray-900">{{ $kyc->rejection_reason }}</dd></div>
                        @endif
                    @else
                        <div class="col-span-2 rounded-lg bg-gray-50 px-3 py-2 text-xs text-gray-500">Sensitive KYC details are hidden. You do not have permission to view them.</div>
                    @endif
                </dl>

                @if ($canViewKyc)
                    <div class="mt-4 flex flex-wrap gap-2">
                        @if (filled($kyc->document_path))
                            <a href="{{ route('admin.kyc.documents', ['customerKyc' => $kyc, 'document' => 'identity']) }}" target="_blank" rel="noopener" class="rounded-lg border border-gray-300 px-3 py-2 text-xs font-medium text-gray-700 hover:bg-gray-50">Download identity document</a>
                        @endif
                        @if (filled($kyc->selfie_path))
                            <a href="{{ route('admin.kyc.documents', ['customerKyc' => $kyc, 'document' => 'selfie']) }}" target="_blank" rel="noopener" class="rounded-lg border border-gray-300 px-3 py-2 text-xs font-medium text-gray-700 hover:bg-gray-50">Download verification selfie</a>
                        @endif
                        @if (filled($kyc->address_proof))
                            <a href="{{ route('admin.kyc.documents', ['customerKyc' => $kyc, 'document' => 'address-proof']) }}" target="_blank" rel="noopener" class="rounded-lg border border-gray-300 px-3 py-2 text-xs font-medium text-gray-700 hover:bg-gray-50">Download address proof</a>
                        @endif
                    </div>
                @endif
            </x-panel.card>

            <x-panel.card heading="Review history">
                @php($history = $this->history())
                @forelse ($history as $line)
                    <p class="border-b border-gray-100 py-2 text-sm text-gray-700 last:border-0">{{ $line }}</p>
                @empty
                    <p class="text-sm text-gray-500">No status changes recorded.</p>
                @endforelse
            </x-panel.card>
        </div>

        <div class="space-y-6">
            <x-panel.card heading="Review">
                <dl class="mb-4 space-y-2 text-sm">
                    <div><dt class="text-gray-500">Last reviewer</dt><dd class="text-gray-900">{{ $kyc->reviewer?->name ?? 'Not reviewed' }}</dd></div>
                    <div><dt class="text-gray-500">Reviewed at</dt><dd class="text-gray-900">{{ $kyc->reviewed_at?->format('d M Y H:i') ?? 'Not reviewed' }}</dd></div>
                </dl>

                @if ($canReview)
                    <div class="space-y-3">
                        @if ($kyc->status === 'pending')
                            <button wire:click="startReview" class="w-full rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Start review</button>
                        @endif
                        @if (in_array($kyc->status, ['pending', 'under_review'], true))
                            <button wire:click="confirmApprove" class="w-full rounded-lg bg-carboli-500 px-4 py-2 text-sm font-semibold text-white hover:bg-carboli-600">Approve</button>
                            <div class="border-t border-gray-100 pt-3">
                                <textarea wire:model="rejectReason" rows="2" placeholder="Rejection reason" class="w-full rounded-lg border-gray-300 text-sm focus:border-carboli-500 focus:ring-carboli-500"></textarea>
                                @error('rejectReason') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                <button wire:click="reject" class="mt-2 w-full rounded-lg border border-red-300 px-4 py-2 text-sm font-semibold text-red-700 hover:bg-red-50">Reject</button>
                            </div>
                        @else
                            <p class="text-sm text-gray-500">This KYC record has been finalized.</p>
                        @endif
                    </div>
                @else
                    <p class="text-sm text-gray-500">You do not have permission to review KYC records.</p>
                @endif
            </x-panel.card>
        </div>
    </div>

    <x-panel.confirm-modal
        :show="$confirmingApprove"
        title="Approve KYC"
        confirm-label="Approve KYC"
        busy-label="Approving…"
        tone="primary"
        confirm-action="approve"
        cancel-action="cancelApprove"
    >
        Approve this KYC record? The customer's verification status will be updated.
    </x-panel.confirm-modal>
</div>
