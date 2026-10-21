<?php

use App\Models\Inspection;
use App\Services\InspectionService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('panel.layouts.app')] class extends Component
{
    public int $inspectionId;
    public string $newStatus = '';
    public string $statusNotes = '';

    public function mount(int $inspection): void
    {
        $record = Inspection::query()->findOrFail($inspection);
        Gate::authorize('view', $record);
        $this->inspectionId = $record->id;
    }

    public function inspection(): Inspection
    {
        return Inspection::query()->with(['car', 'customer', 'assignedStaff', 'documents'])->findOrFail($this->inspectionId);
    }

    public function updateStatus(InspectionService $service): void
    {
        $record = $this->inspection();
        Gate::authorize('updateStatus', $record);
        $this->validate(['newStatus' => ['required', 'string']]);

        try {
            $service->updateStatus($record, $this->newStatus, $this->statusNotes ?: null);
            $this->reset(['newStatus', 'statusNotes']);
            session()->flash('status', 'Inspection status updated.');
        } catch (ValidationException $e) {
            $this->addError('statusAction', collect($e->errors())->flatten()->first());
        }
    }
}; ?>

<div>
    @php($inspection = $this->inspection())
    @php($canUpdate = Gate::allows('updateStatus', $inspection))
    @section('title', 'Inspection #' . $inspection->id)
    @section('breadcrumb', 'Workspace / Inspections / #' . $inspection->id)

    <a href="{{ route('panel.inspections.index') }}" wire:navigate class="mb-2 inline-flex items-center gap-1 text-sm text-gray-500 hover:text-carboli-600">← Back to Inspections</a>

    <x-panel.page-header :title="'Inspection — '.($inspection->car?->stock_inventory_id ?? '#'.$inspection->id)" :description="$inspection->customer?->full_name">
        <x-slot:actions>
            @if (Gate::allows('update', $inspection))
                <a href="{{ route('panel.inspections.edit', $inspection) }}" wire:navigate class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Edit</a>
            @endif
            <x-panel.badge :color="$inspection->status === 'completed' ? 'success' : ($inspection->status === 'cancelled' ? 'danger' : 'gray')">{{ \Illuminate\Support\Str::of($inspection->status)->replace('_',' ')->title() }}</x-panel.badge>
        </x-slot:actions>
    </x-panel.page-header>

    @if (session('status'))
        <div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('status') }}</div>
    @endif
    @error('statusAction') <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ $message }}</div> @enderror

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <x-panel.card heading="Inspection details">
                <dl class="grid grid-cols-2 gap-4 text-sm">
                    @foreach ([
                        'Vehicle' => $inspection->car?->stock_inventory_id,
                        'Customer' => $inspection->customer?->full_name ?? 'Not specified',
                        'Assigned staff' => $inspection->assignedStaff?->name ?? 'Unassigned',
                        'Date' => $inspection->inspection_date?->format('d M Y'),
                        'Start time' => $inspection->start_time,
                        'End time' => $inspection->end_time,
                        'Location' => $inspection->location,
                        'Inspection score' => $inspection->inspection_score,
                    ] as $k => $v)
                        <div><dt class="text-xs uppercase tracking-wide text-gray-400">{{ $k }}</dt><dd class="mt-0.5 text-gray-900">{{ $v !== null && $v !== '' ? $v : '—' }}</dd></div>
                    @endforeach
                    <div class="col-span-2"><dt class="text-xs uppercase tracking-wide text-gray-400">Notes</dt><dd class="mt-0.5 text-gray-900">{{ $inspection->notes ?: '—' }}</dd></div>
                </dl>
            </x-panel.card>

            <x-panel.card heading="Documents" description="{{ $inspection->documents->count() }} file(s)">
                @forelse ($inspection->documents as $doc)
                    <div class="flex items-center justify-between border-b border-gray-100 py-2 text-sm last:border-0">
                        <span class="text-gray-900">{{ \Illuminate\Support\Str::of($doc->type)->replace('_',' ')->title() }}</span>
                        <span class="text-xs text-gray-500">{{ $doc->file_name }}</span>
                    </div>
                @empty
                    <p class="text-sm text-gray-500">No documents uploaded.</p>
                @endforelse
            </x-panel.card>
        </div>

        <div class="space-y-6">
            @if ($canUpdate)
                <x-panel.card heading="Update status">
                    <label class="block text-sm font-medium text-gray-700">New status</label>
                    <select wire:model="newStatus" class="mt-1 w-full rounded-lg border-gray-300 text-sm">
                        <option value="">Select status</option>
                        @foreach (\App\Models\Inspection::STATUSES as $s)<option value="{{ $s }}">{{ \Illuminate\Support\Str::of($s)->replace('_',' ')->title() }}</option>@endforeach
                    </select>
                    @error('newStatus') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    <textarea wire:model="statusNotes" rows="2" placeholder="Notes (optional)" class="mt-2 w-full rounded-lg border-gray-300 text-sm"></textarea>
                    <button wire:click="updateStatus" class="mt-2 w-full rounded-lg bg-carboli-500 px-4 py-2 text-sm font-semibold text-white hover:bg-carboli-600">Update status</button>
                    <p class="mt-2 text-xs text-gray-400">Only valid status transitions are allowed; the server enforces the inspection state machine.</p>
                </x-panel.card>
            @endif
        </div>
    </div>
</div>
