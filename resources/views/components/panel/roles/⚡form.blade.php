<?php

use App\Policies\RolePolicy;
use App\Services\RoleService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

new #[Layout('panel.layouts.app')] class extends Component
{
    public ?int $roleId = null;

    public string $name = '';

    /** @var array<int, int> */
    public array $selected = [];

    public bool $isProtected = false;

    public function mount(?int $role = null): void
    {
        if ($role) {
            $record = Role::query()
                ->where('guard_name', config('auth.defaults.guard', 'web'))
                ->with('permissions:id')
                ->findOrFail($role);

            Gate::authorize('update', $record);

            $this->roleId = $record->id;
            $this->name = (string) $record->name;
            $this->selected = $record->permissions->pluck('id')->map(fn ($id) => (int) $id)->all();
            $this->isProtected = in_array($record->name, RolePolicy::PROTECTED_ROLES, true);
        } else {
            Gate::authorize('create', Role::class);
        }
    }

    public function toggleGroup(string $groupKey): void
    {
        $groupIds = $this->permissionGroups()[$groupKey]['ids'] ?? [];
        $allSelected = ! array_diff($groupIds, $this->selected);

        if ($allSelected) {
            $this->selected = array_values(array_diff($this->selected, $groupIds));
        } else {
            $this->selected = array_values(array_unique(array_merge($this->selected, $groupIds)));
        }
    }

    public function save(RoleService $service): void
    {
        try {
            if ($this->roleId) {
                $role = Role::query()->findOrFail($this->roleId);
                $service->update($role, $this->name, $this->selected);
                session()->flash('status', 'Role updated.');
            } else {
                $service->create($this->name, $this->selected);
                session()->flash('status', 'Role created.');
            }

            $this->redirectRoute('panel.roles.index', navigate: true);
        } catch (ValidationException $e) {
            foreach ($e->errors() as $key => $messages) {
                $this->addError($key, $messages[0]);
            }
        } catch (AuthorizationException $e) {
            $this->addError('name', $e->getMessage());
        }
    }

    /**
     * Group every existing permission by the module it belongs to, derived from
     * its name. Permissions that match no known module fall into "Other" so
     * nothing is ever hidden. Grouping is presentation only — it never creates
     * or renames permissions.
     *
     * @return array<string, array{label: string, ids: array<int, int>, permissions: \Illuminate\Support\Collection<int, Permission>}>
     */
    public function permissionGroups(): array
    {
        /** @var array<string, array{label: string, matchers: array<int, string>}> $definitions */
        $definitions = [
            'customers' => ['label' => 'Customers / KYC', 'matchers' => ['customer', 'kyc']],
            'cars' => ['label' => 'Cars', 'matchers' => ['car']],
            'inspections' => ['label' => 'Inspections', 'matchers' => ['inspection']],
            'auctions' => ['label' => 'Auctions', 'matchers' => ['auction']],
            'payments' => ['label' => 'Payments / Settlements', 'matchers' => ['payment', 'financial', 'settlement']],
            'staff' => ['label' => 'Staff / Users', 'matchers' => ['staff']],
            'roles' => ['label' => 'Roles / Permissions', 'matchers' => ['role', 'permission']],
            'notifications' => ['label' => 'Notification Logs', 'matchers' => ['notification']],
            'audit' => ['label' => 'Audit Logs', 'matchers' => ['audit']],
            'settings' => ['label' => 'Settings', 'matchers' => ['setting']],
        ];

        $permissions = Permission::query()
            ->where('guard_name', config('auth.defaults.guard', 'web'))
            ->orderBy('name')
            ->get(['id', 'name']);

        $groups = [];
        foreach ($definitions as $key => $definition) {
            $groups[$key] = ['label' => $definition['label'], 'ids' => [], 'permissions' => collect()];
        }
        $groups['other'] = ['label' => 'Other', 'ids' => [], 'permissions' => collect()];

        foreach ($permissions as $permission) {
            $placed = false;
            foreach ($definitions as $key => $definition) {
                foreach ($definition['matchers'] as $matcher) {
                    if (str_contains($permission->name, $matcher)) {
                        $groups[$key]['permissions']->push($permission);
                        $groups[$key]['ids'][] = (int) $permission->id;
                        $placed = true;
                        break 2;
                    }
                }
            }

            if (! $placed) {
                $groups['other']['permissions']->push($permission);
                $groups['other']['ids'][] = (int) $permission->id;
            }
        }

        // Drop empty groups so the UI only renders modules that have permissions.
        return array_filter($groups, fn ($group) => $group['permissions']->isNotEmpty());
    }

    public function with(): array
    {
        return [
            'groups' => $this->permissionGroups(),
        ];
    }
}; ?>

<div>
    @section('title', $roleId ? 'Edit role' : 'Add role')
    @section('breadcrumb', 'System / Roles & Permissions / ' . ($roleId ? 'Edit' : 'Add'))

    <a href="{{ route('panel.roles.index') }}" wire:navigate class="mb-2 inline-flex items-center gap-1 text-sm text-gray-500 hover:text-carboli-600">← Back to Roles</a>
    <x-panel.page-header :title="$roleId ? 'Edit role' : 'Add role'" description="Set the role name and the permissions it grants." />

    @php($label = 'block text-sm font-medium text-gray-700')
    @php($control = 'mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-carboli-500 focus:ring-carboli-500')

    <x-panel.card>
        <div class="max-w-md">
            <label class="{{ $label }}">Role name <span class="text-red-500">*</span></label>
            <input type="text" wire:model="name" @disabled($isProtected)
                   class="{{ $control }} @if($isProtected) bg-gray-50 text-gray-500 @endif">
            @if ($isProtected)
                <p class="mt-1 text-xs text-gray-500">This is a protected system role. Its name cannot be changed.</p>
            @endif
            @error('name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>

        <div class="mt-6">
            <div class="flex items-center justify-between">
                <h3 class="text-sm font-semibold text-gray-900">Permissions</h3>
                <span class="text-xs text-gray-500">{{ count($selected) }} selected</span>
            </div>
            @error('permissions') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror

            <div class="mt-3 grid gap-4 sm:grid-cols-2">
                @foreach ($groups as $key => $group)
                    @php($groupSelected = count(array_diff($group['ids'], $selected)) === 0)
                    <div class="rounded-xl border border-gray-200 p-4">
                        <div class="mb-2 flex items-center justify-between">
                            <p class="text-sm font-semibold text-gray-800">{{ $group['label'] }}</p>
                            <button type="button" wire:click="toggleGroup('{{ $key }}')"
                                    class="text-xs font-medium text-carboli-600 hover:text-carboli-700">
                                {{ $groupSelected ? 'Clear all' : 'Select all' }}
                            </button>
                        </div>
                        <div class="space-y-2">
                            @foreach ($group['permissions'] as $permission)
                                <label class="flex items-center gap-2 text-sm text-gray-700">
                                    <input type="checkbox" value="{{ $permission->id }}" wire:model="selected"
                                           class="rounded border-gray-300 text-carboli-600 focus:ring-carboli-500">
                                    <span>{{ $permission->name }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="mt-6 flex items-center justify-end gap-2 border-t border-gray-100 pt-4">
            <a href="{{ route('panel.roles.index') }}" wire:navigate class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</a>
            <button wire:click="save" class="rounded-lg bg-carboli-500 px-4 py-2 text-sm font-semibold text-white hover:bg-carboli-600">{{ $roleId ? 'Save changes' : 'Create role' }}</button>
        </div>
    </x-panel.card>
</div>
