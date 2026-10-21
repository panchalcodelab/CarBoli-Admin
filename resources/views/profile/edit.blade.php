@extends('panel.layouts.app')

@section('title', 'My Profile')
@section('breadcrumb', 'Workspace / My Profile')

@php
    $user = auth()->user();
    $initials = \Illuminate\Support\Str::of($user?->name ?? '')
        ->explode(' ')
        ->map(fn ($p) => \Illuminate\Support\Str::substr($p, 0, 1))
        ->take(2)
        ->implode('');
@endphp

@section('content')
    <x-panel.page-header
        overline="Account"
        title="My Profile"
        description="Manage your account details, password, and session." />

    {{-- Identity summary --}}
    <x-panel.card class="mb-6">
        <div class="flex flex-wrap items-center gap-4">
            <span class="flex h-14 w-14 items-center justify-center rounded-full bg-carboli-50 text-lg font-semibold text-carboli-700">
                {{ $initials }}
            </span>
            <div class="min-w-0">
                <p class="text-lg font-semibold text-gray-950">{{ $user?->name }}</p>
                <p class="truncate text-sm text-gray-500">{{ $user?->email }}</p>
            </div>
            @if ($role = $user?->getRoleNames()->first())
                <span class="ml-auto inline-flex items-center gap-1.5 rounded-full bg-carboli-50 px-3 py-1 text-xs font-semibold text-carboli-700">
                    <x-panel.icon name="shield" class="h-4 w-4" />
                    {{ $role }}
                </span>
            @endif
        </div>
    </x-panel.card>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <div class="space-y-6">
            <x-panel.card heading="Profile information" description="Update your account's name and email address.">
                @include('profile.partials.update-profile-information-form')
            </x-panel.card>
        </div>

        <div class="space-y-6">
            <x-panel.card id="update-password" heading="Change password" description="Use a long, random password to stay secure.">
                @include('profile.partials.update-password-form')
            </x-panel.card>

            <x-panel.card heading="Delete account" description="Permanently remove your account and its data.">
                @include('profile.partials.delete-user-form')
            </x-panel.card>
        </div>
    </div>
@endsection
