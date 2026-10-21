<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Admin') · {{ config('app.name', 'CarBoli') }}</title>

    <link rel="icon" type="image/png" href="{{ asset('img/favicon.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('img/favicon.png') }}">

    @vite(['resources/css/app.css'])
    @livewireStyles
</head>
<body class="h-full bg-gray-50 font-poppins text-gray-900 antialiased">
    <div x-data="{ sidebarOpen: false }" class="min-h-full">
        {{-- Mobile sidebar backdrop --}}
        <div
            x-show="sidebarOpen"
            x-transition.opacity
            @click="sidebarOpen = false"
            class="fixed inset-0 z-30 bg-gray-900/40 lg:hidden"
            style="display: none;"
        ></div>

        {{-- Sidebar --}}
        <aside
            class="fixed inset-y-0 left-0 z-40 flex w-64 flex-col border-r border-gray-200 bg-white transition-transform duration-200 lg:translate-x-0"
            :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
        >
            <div class="flex h-16 items-center gap-3 border-b border-gray-100 px-5">
                <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-carboli-500 text-base font-bold text-white">C</span>
                <span class="text-lg font-bold tracking-tight">
                    Car<span class="text-carboli-500">Boli</span>
                </span>
            </div>

            <nav class="flex-1 overflow-y-auto px-3 py-4">
                @include('panel.layouts.navigation')
            </nav>
        </aside>

        {{-- Main column --}}
        <div class="lg:pl-64">
            {{-- Topbar --}}
            <header class="sticky top-0 z-20 flex h-16 items-center gap-4 border-b border-gray-200 bg-white px-4 sm:px-6">
                <button type="button" class="lg:hidden" @click="sidebarOpen = true" aria-label="Open sidebar">
                    <svg class="h-6 w-6 text-gray-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5M3.75 17.25h16.5" />
                    </svg>
                </button>

                <nav aria-label="Breadcrumb" class="flex-1 text-sm text-gray-500">
                    @yield('breadcrumb', 'Workspace')
                </nav>

                <div
                    x-data="{ open: false }"
                    @keydown.escape.window="open = false"
                    class="relative"
                >
                    <button
                        type="button"
                        @click="open = ! open"
                        :aria-expanded="open.toString()"
                        aria-haspopup="true"
                        class="flex items-center gap-3 rounded-full py-1 pl-1 pr-2 transition hover:bg-gray-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-carboli-500"
                    >
                        <span class="hidden text-right sm:block">
                            <span class="block text-sm font-semibold leading-tight">{{ auth()->user()?->name }}</span>
                            <span class="block text-xs text-gray-500">{{ auth()->user()?->getRoleNames()->first() }}</span>
                        </span>
                        <span class="flex h-9 w-9 items-center justify-center rounded-full bg-carboli-50 text-sm font-semibold text-carboli-700">
                            {{ \Illuminate\Support\Str::of(auth()->user()?->name ?? '')->explode(' ')->map(fn ($p) => \Illuminate\Support\Str::substr($p, 0, 1))->take(2)->implode('') }}
                        </span>
                        <svg class="h-4 w-4 text-gray-400 transition" :class="open && 'rotate-180'" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                        </svg>
                    </button>

                    <div
                        x-show="open"
                        x-transition.origin.top.right
                        @click.outside="open = false"
                        role="menu"
                        aria-orientation="vertical"
                        class="absolute right-0 z-30 mt-2 w-56 origin-top-right rounded-xl border border-gray-200 bg-white py-1 shadow-lg ring-1 ring-black/5 focus:outline-none"
                        style="display: none;"
                    >
                        <div class="border-b border-gray-100 px-4 py-3 sm:hidden">
                            <p class="text-sm font-semibold leading-tight">{{ auth()->user()?->name }}</p>
                            <p class="text-xs text-gray-500">{{ auth()->user()?->getRoleNames()->first() }}</p>
                        </div>

                        <a href="{{ route('profile.edit') }}" wire:navigate role="menuitem"
                           class="flex items-center gap-2.5 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 hover:text-carboli-700">
                            <x-panel.icon name="user" class="h-4 w-4 text-gray-400" />
                            My Profile
                        </a>

                        <a href="{{ route('profile.edit') }}#update-password" wire:navigate role="menuitem"
                           class="flex items-center gap-2.5 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 hover:text-carboli-700">
                            <x-panel.icon name="key" class="h-4 w-4 text-gray-400" />
                            Change Password
                        </a>

                        <div class="my-1 border-t border-gray-100"></div>

                        <form method="POST" action="{{ route('logout') }}" role="none">
                            @csrf
                            <button type="submit" role="menuitem"
                                    class="flex w-full items-center gap-2.5 px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-50 hover:text-red-600">
                                <x-panel.icon name="logout" class="h-4 w-4 text-gray-400" />
                                Log Out
                            </button>
                        </form>
                    </div>
                </div>
            </header>

            <main class="px-4 py-6 sm:px-6 lg:px-8">
                @if (session('status'))
                    <div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
                        {{ session('status') }}
                    </div>
                @endif

                {{ $slot ?? '' }}
                @yield('content')
            </main>
        </div>
    </div>

    @livewireScripts
</body>
</html>
