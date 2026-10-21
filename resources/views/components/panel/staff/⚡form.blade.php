<?php

use App\Models\User;
use App\Services\StaffUserService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Spatie\Permission\Models\Role;

new #[Layout('panel.layouts.app')] class extends Component
{
    public ?int $staffId = null;

    public string $name = '';
    public string $email = '';
    public string $mobile = '';
    public string $status = 'active';
    public ?int $role_id = null;
    public string $password = '';

    public function mount(?int $staff = null): void
    {
        if ($staff) {
            $record = User::query()->with('roles')->findOrFail($staff);
            Gate::authorize('update', $record);
            $this->staffId = $record->id;
            $this->name = (string) $record->name;
            $this->email = (string) $record->email;
            $this->mobile = (string) $record->mobile;
            $this->status = (string) $record->status;
            $this->role_id = $record->roles->first()?->id;
        } else {
            Gate::authorize('create', User::class);
        }
    }

    public function save(StaffUserService $service): void
    {
        $data = [
            'name' => $this->name,
            'email' => $this->email,
            'mobile' => $this->mobile ?: null,
            'status' => $this->status,
            'role_id' => $this->role_id,
            'password' => $this->password ?: null,
        ];

        try {
            if ($this->staffId) {
                $service->update(User::query()->findOrFail($this->staffId), $data);
                session()->flash('status', 'Staff user updated.');
            } else {
                $service->create($data);
                session()->flash('status', 'Staff user created.');
            }
            $this->redirectRoute('panel.staff.index', navigate: true);
        } catch (ValidationException $e) {
            foreach ($e->errors() as $key => $messages) {
                $this->addError($key, $messages[0]);
            }
        } catch (AuthorizationException $e) {
            $this->addError('role_id', $e->getMessage());
        }
    }

    public function with(): array
    {
        return [
            // All roles are selectable, including Super Admin. Server-side rules
            // in StaffUserService enforce that only an acting Super Admin may
            // actually assign the Super Admin role.
            'roles' => Role::query()
                ->where('guard_name', config('auth.defaults.guard', 'web'))
                ->orderBy('name')
                ->get(['id', 'name']),
            'statuses' => User::STAFF_STATUSES,
        ];
    }
}; ?>

<div>
    @section('title', $staffId ? 'Edit staff user' : 'Add staff user')
    @section('breadcrumb', 'System / Staff Users / ' . ($staffId ? 'Edit' : 'Add'))

    <a href="{{ route('panel.staff.index') }}" wire:navigate class="mb-2 inline-flex items-center gap-1 text-sm text-gray-500 hover:text-carboli-600">← Back to Staff Users</a>
    <x-panel.page-header :title="$staffId ? 'Edit staff user' : 'Add staff user'" description="Account details, role and access." />

    @php($label = 'block text-sm font-medium text-gray-700')
    @php($control = 'mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-carboli-500 focus:ring-carboli-500')

    <x-panel.card>
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="{{ $label }}">Name <span class="text-red-500">*</span></label>
                <input type="text" wire:model="name" class="{{ $control }}">
                @error('name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="{{ $label }}">Email <span class="text-red-500">*</span></label>
                <input type="email" wire:model="email" class="{{ $control }}">
                @error('email') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="{{ $label }}">Mobile</label>
                <input type="text" wire:model="mobile" class="{{ $control }}">
                @error('mobile') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="{{ $label }}">Status <span class="text-red-500">*</span></label>
                <select wire:model="status" class="{{ $control }}">
                    @foreach ($statuses as $s)<option value="{{ $s }}">{{ ucfirst($s) }}</option>@endforeach
                </select>
                @error('status') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="{{ $label }}">Role <span class="text-red-500">*</span></label>
                <select wire:model="role_id" class="{{ $control }}">
                    <option value="">Select role</option>
                    @foreach ($roles as $r)<option value="{{ $r->id }}">{{ $r->name }}</option>@endforeach
                </select>
                @error('role_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="{{ $label }}">Password {!! $staffId ? '<span class="text-gray-400">(leave blank to keep current)</span>' : '<span class="text-red-500">*</span>' !!}</label>
                <input type="password" wire:model="password" class="{{ $control }}">
                @error('password') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="mt-6 flex items-center justify-end gap-2 border-t border-gray-100 pt-4">
            <a href="{{ route('panel.staff.index') }}" wire:navigate class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</a>
            <button wire:click="save" class="rounded-lg bg-carboli-500 px-4 py-2 text-sm font-semibold text-white hover:bg-carboli-600">{{ $staffId ? 'Save changes' : 'Create user' }}</button>
        </div>
    </x-panel.card>
</div>
