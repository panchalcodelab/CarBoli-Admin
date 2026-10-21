<?php

use App\Models\AuditLog;
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
    public string $module = '';

    public function mount(): void
    {
        Gate::authorize('viewAny', AuditLog::class);
    }

    public function updating($name): void
    {
        if (in_array($name, ['search', 'module'], true)) {
            $this->resetPage();
        }
    }

    public function with(): array
    {
        $logs = AuditLog::query()
            ->with('user')
            ->when($this->search !== '', function ($q) {
                $term = '%'.$this->search.'%';
                $q->where('action', 'like', $term)
                    ->orWhere('module', 'like', $term)
                    ->orWhere('entity_type', 'like', $term);
            })
            ->when($this->module !== '', fn ($q) => $q->where('module', $this->module))
            ->orderByDesc('created_at')
            ->paginate(20);

        return [
            'logs' => $logs,
            'modules' => AuditLog::query()->distinct()->orderBy('module')->pluck('module'),
        ];
    }
}; ?>

<div>
    @section('title', 'Audit Log')
    @section('breadcrumb', 'System / Audit Log')

    <x-panel.page-header overline="System" title="Audit Log"
        description="Append-only record of sensitive administrative actions. Read-only." />

    <x-panel.card heading="Audit records" description="Search and filter the audit trail" flush>
        <div class="flex flex-wrap items-center gap-3 border-b border-gray-100 p-4">
            <div class="relative flex-1 min-w-[220px]">
                <input type="search" wire:model.live.debounce.300ms="search" placeholder="Search action, module or entity..."
                       class="w-full rounded-lg border-gray-300 pl-9 text-sm focus:border-carboli-500 focus:ring-carboli-500">
                <svg class="absolute left-3 top-2.5 h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/></svg>
            </div>
            <select wire:model.live="module" class="rounded-lg border-gray-300 text-sm">
                <option value="">All modules</option>
                @foreach ($modules as $m)<option value="{{ $m }}">{{ \Illuminate\Support\Str::of($m)->replace('_',' ')->title() }}</option>@endforeach
            </select>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-100 text-sm">
                <thead>
                    <tr class="text-left text-xs font-semibold uppercase tracking-wider text-gray-400">
                        <th class="px-4 py-3">Time</th>
                        <th class="px-4 py-3">Actor</th>
                        <th class="px-4 py-3">Module</th>
                        <th class="px-4 py-3">Action</th>
                        <th class="px-4 py-3">Entity</th>
                        <th class="px-4 py-3">IP</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($logs as $log)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 text-gray-700">{{ $log->created_at?->format('d M Y H:i:s') }}</td>
                            <td class="px-4 py-3 text-gray-900">{{ $log->user?->name ?? 'System' }}</td>
                            <td class="px-4 py-3"><x-panel.badge>{{ \Illuminate\Support\Str::of($log->module)->replace('_',' ')->title() }}</x-panel.badge></td>
                            <td class="px-4 py-3 text-gray-700">{{ $log->action }}</td>
                            <td class="px-4 py-3 text-gray-500">{{ $log->entity_type ? class_basename($log->entity_type).'#'.$log->entity_id : '—' }}</td>
                            <td class="px-4 py-3 text-gray-500">{{ $log->ip_address ?: '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-10 text-center text-sm text-gray-500">No audit records found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-gray-100 p-4">{{ $logs->links() }}</div>
    </x-panel.card>
</div>
