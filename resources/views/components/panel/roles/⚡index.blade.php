<?php

use App\Policies\RolePolicy;
use App\Services\RoleService;
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

    public ?int $confirmingDeleteId = null;

    public string $confirmingDeleteName = '';

    public function mount(): void
    {
        Gate::authorize('viewAny', Role::class);
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function confirmDelete(int $roleId): void
    {
        $role = Role::query()->findOrFail($roleId);
        $this->confirmingDeleteId = $roleId;
        $this->confirmingDeleteName = (string) $role->name;
    }

    public function cancelDelete(): void
    {
        $this->confirmingDeleteId = null;
        $this->confirmingDeleteName = '';
    }

    public function delete(int $roleId, RoleService $service): void
    {
        $role = Role::query()->findOrFail($roleId);

        try {
            $service->delete($role);
            session()->flash('status', "Role \"{$role->name}\" deleted.");
        } catch (ValidationException $e) {
            $this->addError('role', collect($e->errors())->flatten()->first());
        } finally {
            $this->confirmingDeleteId = null;
            $this->confirmingDeleteName = '';
        }
    }

    public function with(): array
    {
        $roles = Role::query()
            ->where('guard_name', config('auth.defaults.guard', 'web'))
            ->withCount(['users', 'permissions'])
            ->with('permissions:id,name')
            ->when($this->search !== '', fn ($q) => $q->where('name', 'like', '%'.$this->search.'%'))
            ->orderBy('name')
            ->paginate(10);

        return [
            'roles' => $roles,
            'canCreate' => Gate::allows('create', Role::class),
            'protectedRoles' => RolePolicy::PROTECTED_ROLES,
        ];
    }
}; ?>

<div>
    @section('title', 'Roles & Permissions')
    @section('breadcrumb', 'System / Roles & Permissions')

    <x-panel.page-header overline="System" title="Roles & Permissions"
        description="Define staff roles and the permissions granted to each.">
        <x-slot:actions>
            @if ($canCreate)
                <a href="{{ route('panel.roles.create') }}" wire:navigate
                   class="inline-flex items-center gap-2 rounded-lg bg-carboli-500 px-4 py-2 text-sm font-semibold text-white hover:bg-carboli-600">
                    <x-panel.icon name="plus" class="h-4 w-4" />Add role
                </a>
            @endif
        </x-slot:actions>
    </x-panel.page-header>

    @error('role')
        <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ $message }}</div>
    @enderror

    <x-panel.card heading="All roles" description="Search roles and review their permissions" flush>
        <div class="border-b border-gray-100 p-4">
            <div class="relative max-w-sm">
                <input type="search" wire:model.live.debounce.300ms="search" placeholder="Search role name..."
                       class="w-full rounded-lg border-gray-300 pl-9 text-sm focus:border-carboli-500 focus:ring-carboli-500">
                <x-panel.icon name="search" class="absolute left-3 top-2.5 h-4 w-4 text-gray-400" />
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-100 text-sm">
                <thead>
                    <tr class="text-left text-xs font-semibold uppercase tracking-wider text-gray-400">
                        <th class="px-4 py-3">Role</th>
                        <th class="px-4 py-3">Permissions</th>
                        <th class="px-4 py-3">Staff</th>
                        <th class="px-4 py-3 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($roles as $role)
                        @php
                            $isProtected = in_array($role->name, $protectedRoles, true);
                            $canEdit = Gate::allows('update', $role);
                            $canDelete = Gate::allows('delete', $role);
                        @endphp
                        <tr class="hover:bg-gray-50 align-top">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <span class="font-semibold text-gray-900">{{ $role->name }}</span>
                                    @if ($isProtected)
                                        <x-panel.badge color="warning">
                                            <x-panel.icon name="shield" class="h-3 w-3" />System
                                        </x-panel.badge>
                                    @endif
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                @if ($role->permissions->isEmpty())
                                    <span class="text-xs text-gray-400">No permissions</span>
                                @else
                                    <div class="flex max-w-xl flex-wrap gap-1">
                                        @foreach ($role->permissions->take(6) as $permission)
                                            <x-panel.badge>{{ $permission->name }}</x-panel.badge>
                                        @endforeach
                                        @if ($role->permissions_count > 6)
                                            <span class="text-xs text-gray-500">+{{ $role->permissions_count - 6 }} more</span>
                                        @endif
                                    </div>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-gray-700">{{ $role->users_count }}</td>
                            <td class="px-4 py-3 text-right">
                                <div class="inline-flex items-center gap-2">
                                    @if ($canEdit)
                                        <a href="{{ route('panel.roles.edit', $role) }}" wire:navigate
                                           class="rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50">Edit</a>
                                    @endif
                                    @if ($canDelete)
                                        <button wire:click="confirmDelete({{ $role->id }})"
                                                class="rounded-lg border border-red-300 px-3 py-1.5 text-xs font-medium text-red-700 hover:bg-red-50">Delete</button>
                                    @endif
                                    @if (! $canEdit && ! $canDelete)
                                        <span class="text-xs text-gray-400">Protected</span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-4 py-10 text-center text-sm text-gray-500">No roles found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-gray-100 p-4">{{ $roles->links() }}</div>
    </x-panel.card>

    <x-panel.confirm-modal
        :show="$confirmingDeleteId !== null"
        title="Delete role"
        confirm-label="Delete role"
        busy-label="Deleting…"
        :confirm-action="'delete('.(int) $confirmingDeleteId.')'"
        cancel-action="cancelDelete"
    >
        Are you sure you want to delete the role
        <span class="font-semibold text-gray-700">{{ $confirmingDeleteName }}</span>?
        This cannot be undone.
    </x-panel.confirm-modal>
</div>
