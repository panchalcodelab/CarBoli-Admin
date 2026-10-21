<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Choose a new password · {{ config('app.name', 'CarBoli') }}</title>

    <link rel="icon" type="image/png" href="{{ asset('img/favicon.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('img/favicon.png') }}">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full bg-gray-50 font-poppins text-gray-900 antialiased">
    <div class="flex min-h-full flex-col lg:flex-row">
        {{-- Brand panel --}}
        <div class="relative hidden w-full bg-carboli-600 lg:flex lg:w-1/2 lg:flex-col lg:justify-between lg:p-12">
            <div class="flex items-center gap-3 text-white">
                <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-white/15 text-lg font-bold">C</span>
                <span class="text-xl font-bold tracking-tight">CarBoli</span>
            </div>
            <div class="max-w-md text-white">
                <h2 class="text-3xl font-bold leading-tight">Set a new password.</h2>
                <p class="mt-4 text-sm text-white/80">
                    Choose a strong password you don't use anywhere else to keep your admin account secure.
                </p>
            </div>
            <p class="text-xs text-white/60">&copy; {{ date('Y') }} CarBoli. All rights reserved.</p>
        </div>

        {{-- Form panel --}}
        <div class="flex w-full flex-1 items-center justify-center px-6 py-12 lg:w-1/2">
            <div class="w-full max-w-sm">
                <div class="mb-8 flex flex-col items-center text-center">
                    <img src="{{ asset('img/Logo.png') }}" alt="CarBoli" class="h-14 w-auto" />
                    <h1 class="mt-6 text-2xl font-bold tracking-tight text-gray-950">Choose a new password</h1>
                    <p class="mt-1 text-sm text-gray-500">Enter and confirm your new password below.</p>
                </div>

                <form method="POST" action="{{ route('password.store') }}" class="space-y-5" x-data="{ show: false }">
                    @csrf

                    <input type="hidden" name="token" value="{{ $request->route('token') }}">

                    {{-- Email --}}
                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700">Email</label>
                        <input
                            id="email"
                            name="email"
                            type="email"
                            value="{{ old('email', $request->email) }}"
                            required
                            autofocus
                            autocomplete="username"
                            @error('email') aria-invalid="true" aria-describedby="email-error" @enderror
                            class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-carboli-500 focus:ring-carboli-500"
                        />
                        <x-input-error :messages="$errors->get('email')" class="mt-2" />
                    </div>

                    {{-- New password --}}
                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-700">New password</label>
                        <div class="relative mt-1">
                            <input
                                id="password"
                                name="password"
                                :type="show ? 'text' : 'password'"
                                required
                                autocomplete="new-password"
                                @error('password') aria-invalid="true" aria-describedby="password-error" @enderror
                                class="block w-full rounded-lg border-gray-300 pr-11 shadow-sm focus:border-carboli-500 focus:ring-carboli-500"
                            />
                            <button
                                type="button"
                                @click="show = ! show"
                                :aria-label="show ? 'Hide password' : 'Show password'"
                                :aria-pressed="show.toString()"
                                class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-gray-600 focus:outline-none focus-visible:text-carboli-600"
                            >
                                <svg x-show="! show" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                </svg>
                                <svg x-show="show" x-cloak class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.243 4.243L9.88 9.88" />
                                </svg>
                            </button>
                        </div>
                        <x-input-error :messages="$errors->get('password')" class="mt-2" />
                    </div>

                    {{-- Confirm password --}}
                    <div>
                        <label for="password_confirmation" class="block text-sm font-medium text-gray-700">Confirm password</label>
                        <input
                            id="password_confirmation"
                            name="password_confirmation"
                            :type="show ? 'text' : 'password'"
                            required
                            autocomplete="new-password"
                            @error('password_confirmation') aria-invalid="true" aria-describedby="password-confirmation-error" @enderror
                            class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-carboli-500 focus:ring-carboli-500"
                        />
                        <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
                    </div>

                    <button type="submit"
                            class="flex w-full items-center justify-center rounded-lg bg-carboli-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-carboli-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-carboli-500 focus-visible:ring-offset-2">
                        Reset password
                    </button>
                </form>

                <p class="mt-6 text-center text-sm text-gray-500">
                    <a href="{{ route('login') }}" class="font-medium text-carboli-600 hover:text-carboli-700">
                        &larr; Back to sign in
                    </a>
                </p>
            </div>
        </div>
    </div>
</body>
</html>
