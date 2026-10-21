<?php

use App\Models\NotificationLog;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('panel.layouts.app')] class extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url]
    public string $status = '';

    #[Url]
    public string $channel = '';

    public function mount(): void
    {
        Gate::authorize('viewAny', NotificationLog::class);
    }

    public function updating($name): void
    {
        if (in_array($name, ['search', 'status', 'channel'], true)) {
            $this->resetPage();
        }
    }

    public function with(): array
    {
        $logs = NotificationLog::query()
            ->with('customer')
            ->when($this->search !== '', function ($q) {
                $term = '%'.$this->search.'%';
                $q->where('event', 'like', $term)
                    ->orWhere('template_identifier', 'like', $term)
                    ->orWhereHas('customer', fn ($c) => $c->where('full_name', 'like', $term));
            })
            ->when($this->status !== '', fn ($q) => $q->where('status', $this->status))
            ->when($this->channel !== '', fn ($q) => $q->where('channel', $this->channel))
            ->latest()
            ->paginate(15);

        return [
            'logs' => $logs,
            'statuses' => NotificationLog::STATUSES,
            'channels' => NotificationLog::query()->distinct()->orderBy('channel')->pluck('channel'),
        ];
    }

    public function statusColor(string $status): string
    {
        return match ($status) {
            'sent' => 'success',
            'failed' => 'danger',
            'queued' => 'warning',
            default => 'gray',
        };
    }
}; ?>

<div>
    @section('title', 'Notification Log')
    @section('breadcrumb', 'System / Notification Log')

    <x-panel.page-header overline="System" title="Notification Log"
        description="Read-only record of notifications queued, sent and failed across channels." />

    <x-panel.card heading="Notifications" description="Search and filter delivery records" flush>
        <div class="flex flex-wrap items-center gap-3 border-b border-gray-100 p-4">
            <div class="relative flex-1 min-w-[220px]">
                <input type="search" wire:model.live.debounce.300ms="search" placeholder="Search event, template or customer..."
                       class="w-full rounded-lg border-gray-300 pl-9 text-sm focus:border-carboli-500 focus:ring-carboli-500">
                <svg class="absolute left-3 top-2.5 h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/></svg>
            </div>
            <select wire:model.live="status" class="rounded-lg border-gray-300 text-sm">
                <option value="">All statuses</option>
                @foreach ($statuses as $s)<option value="{{ $s }}">{{ ucfirst($s) }}</option>@endforeach
            </select>
            <select wire:model.live="channel" class="rounded-lg border-gray-300 text-sm">
                <option value="">All channels</option>
                @foreach ($channels as $c)<option value="{{ $c }}">{{ ucfirst($c) }}</option>@endforeach
            </select>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-100 text-sm">
                <thead>
                    <tr class="text-left text-xs font-semibold uppercase tracking-wider text-gray-400">
                        <th class="px-4 py-3">Time</th>
                        <th class="px-4 py-3">Customer</th>
                        <th class="px-4 py-3">Event</th>
                        <th class="px-4 py-3">Channel</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Provider ref</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($logs as $log)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 text-gray-700">{{ $log->created_at?->format('d M Y H:i') }}</td>
                            <td class="px-4 py-3 text-gray-900">{{ $log->customer?->full_name ?? 'System event' }}</td>
                            <td class="px-4 py-3 text-gray-700">{{ $log->event }}</td>
                            <td class="px-4 py-3"><x-panel.badge>{{ ucfirst($log->channel) }}</x-panel.badge></td>
                            <td class="px-4 py-3"><x-panel.badge :color="$this->statusColor($log->status)">{{ ucfirst($log->status) }}</x-panel.badge></td>
                            <td class="px-4 py-3 text-gray-500">{{ $log->provider_reference ?: '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-10 text-center text-sm text-gray-500">No notifications found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-gray-100 p-4">{{ $logs->links() }}</div>
    </x-panel.card>
</div>
