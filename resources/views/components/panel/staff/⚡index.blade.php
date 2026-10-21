<?php

use App\Models\User;
use App\Services\StaffUserService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Spatie\Permission\Models\Role;

new #[Layout('panel.layouts.app')] class extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url]
    public string $status = '';

    #[Url]
    public string $role = '';

    public ?int $confirmingDeleteId = null;

    public string $confirmingDeleteName = '';

    public function mount(): void
    {
        Gate::authorize('viewAny', User::class);
    }

    public function updating($name): void
    {
        if (in_array($name, ['search', 'status', 'role'], true)) {
            $this->resetPage();
        }
    }

    public function confirmDelete(int $id): void
    {
        $staff = User::query()->findOrFail($id);
        $this->confirmingDeleteId = $id;
        $this->confirmingDeleteName = (string) $staff->name;
    }

    public function cancelDelete(): void
    {
        $this->confirmingDeleteId = null;
        $this->confirmingDeleteName = '';
    }

    public function delete(int $id, StaffUserService $service): void
    {
        $staff = User::query()->findOrFail($id);

        try {
            $service->delete($staff);
            session()->flash('status', "Staff user \"{$staff->name}\" deleted.");
        } catch (ValidationException $e) {
            $this->addError('staff', collect($e->errors())->flatten()->first());
        } finally {
            $this->confirmingDeleteId = null;
            $this->confirmingDeleteName = '';
        }
    }

    public function with(): array
    {
        $staff = User::query()
            ->with('roles')
            ->when($this->search !== '', function ($q) {
                $term = '%'.$this->search.'%';
                $q->where('name', 'like', $term)->orWhere('email', 'like', $term)->orWhere('mobile', 'like', $term);
            })
            ->when($this->status !== '', fn ($q) => $q->where('status', $this->status))
            ->when($this->role !== '', fn ($q) => $q->whereHas('roles', fn ($r) => $r->where('name', $this->role)))
            ->latest()
            ->paginate(10);

        return [
            'staff' => $staff,
            'statuses' => User::STAFF_STATUSES,
            'roles' => Role::query()->where('guard_name', 'web')->orderBy('name')->pluck('name'),
            'canManage' => Gate::allows('create', User::class),
        ];
    }

    public function statusColor(string $status): string
    {
        return match ($status) {
            'active' => 'success',
            'suspended' => 'danger',
            default => 'gray',
        };
    }
}; ?>

<div>
    @section('title', 'Staff Users')
    @section('breadcrumb', 'System / Staff Users')

    <x-panel.page-header overline="System" title="Staff Users" description="Manage admin and sub-admin accounts, roles and access.">
        <x-slot:actions>
            @can('create', App\Models\User::class)
                <a href="{{ route('panel.staff.create') }}" wire:navigate class="inline-flex items-center gap-2 rounded-lg bg-carboli-500 px-4 py-2 text-sm font-semibold text-white hover:bg-carboli-600"><x-panel.icon name="plus" class="h-4 w-4" />Add staff user</a>
            @endcan
        </x-slot:actions>
    </x-panel.page-header>

    @error('staff')
        <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ $message }}</div>
    @enderror

    <x-panel.card heading="All staff users" description="Search and filter staff accounts" flush>
        <div class="flex flex-wrap items-center gap-3 border-b border-gray-100 p-4">
            <div class="relative flex-1 min-w-[220px]">
                <input type="search" wire:model.live.debounce.300ms="search" placeholder="Search name, email, mobile..."
                       class="w-full rounded-lg border-gray-300 pl-9 text-sm focus:border-carboli-500 focus:ring-carboli-500">
                <svg class="absolute left-3 top-2.5 h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/></svg>
            </div>
            <select wire:model.live="status" class="rounded-lg border-gray-300 text-sm">
                <option value="">All statuses</option>
                @foreach ($statuses as $s)<option value="{{ $s }}">{{ ucfirst($s) }}</option>@endforeach
            </select>
            <select wire:model.live="role" class="rounded-lg border-gray-300 text-sm">
                <option value="">All roles</option>
                @foreach ($roles as $r)<option value="{{ $r }}">{{ $r }}</option>@endforeach
            </select>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-100 text-sm">
                <thead>
                    <tr class="text-left text-xs font-semibold uppercase tracking-wider text-gray-400">
                        <th class="px-4 py-3">Name</th>
                        <th class="px-4 py-3">Email</th>
                        <th class="px-4 py-3">Role</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Last login</th>
                        <th class="px-4 py-3 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($staff as $member)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 font-semibold text-gray-900">{{ $member->name }}</td>
                            <td class="px-4 py-3 text-gray-700">{{ $member->email }}</td>
                            <td class="px-4 py-3">
                                @foreach ($member->roles as $r)<x-panel.badge color="primary">{{ $r->name }}</x-panel.badge> @endforeach
                            </td>
                            <td class="px-4 py-3"><x-panel.badge :color="$this->statusColor($member->status)">{{ ucfirst($member->status) }}</x-panel.badge></td>
                            <td class="px-4 py-3 text-gray-700">{{ $member->last_login_at?->format('d M Y H:i') ?? 'Never' }}</td>
                            <td class="px-4 py-3 text-right">
                                @php
                                    $canEdit = Gate::allows('update', $member);
                                    $canDelete = Gate::allows('delete', $member);
                                @endphp
                                <div class="inline-flex items-center gap-2">
                                    @if ($canEdit)
                                        <a href="{{ route('panel.staff.edit', $member) }}" wire:navigate class="rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50">Edit</a>
                                    @endif
                                    @if ($canDelete)
                                        <button wire:click="confirmDelete({{ $member->id }})" class="rounded-lg border border-red-300 px-3 py-1.5 text-xs font-medium text-red-700 hover:bg-red-50">Delete</button>
                                    @endif
                                    @if (! $canEdit && ! $canDelete)
                                        <span class="text-xs text-gray-400">{{ auth()->id() === $member->id ? 'You' : '—' }}</span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-10 text-center text-sm text-gray-500">No staff users found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-gray-100 p-4">{{ $staff->links() }}</div>
    </x-panel.card>

    <x-panel.confirm-modal
        :show="$confirmingDeleteId !== null"
        title="Delete staff user"
        confirm-label="Delete user"
        :confirm-action="'delete('.(int) $confirmingDeleteId.')'"
        cancel-action="cancelDelete"
    >
        Are you sure you want to permanently delete
        <span class="font-semibold text-gray-700">{{ $confirmingDeleteName }}</span>?
        This removes the account and all related records and cannot be undone.
    </x-panel.confirm-modal>
</div>
