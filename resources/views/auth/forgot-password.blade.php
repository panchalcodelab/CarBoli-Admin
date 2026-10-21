<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Reset password · {{ config('app.name', 'CarBoli') }}</title>

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
                <h2 class="text-3xl font-bold leading-tight">Locked out? We'll get you back in.</h2>
                <p class="mt-4 text-sm text-white/80">
                    Enter your account email and we'll send a secure link to reset your password.
                </p>
            </div>
            <p class="text-xs text-white/60">&copy; {{ date('Y') }} CarBoli. All rights reserved.</p>
        </div>

        {{-- Form panel --}}
        <div class="flex w-full flex-1 items-center justify-center px-6 py-12 lg:w-1/2">
            <div class="w-full max-w-sm">
                <div class="mb-8 flex flex-col items-center text-center">
                    <img src="{{ asset('img/Logo.png') }}" alt="CarBoli" class="h-14 w-auto" />
                    <h1 class="mt-6 text-2xl font-bold tracking-tight text-gray-950">Reset your password</h1>
                    <p class="mt-1 text-sm text-gray-500">
                        Enter your email and we'll send you a password reset link.
                    </p>
                </div>

                <x-auth-session-status class="mb-4" :status="session('status')" />

                <form method="POST" action="{{ route('password.email') }}" class="space-y-5">
                    @csrf

                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700">Email</label>
                        <input
                            id="email"
                            name="email"
                            type="email"
                            value="{{ old('email') }}"
                            required
                            autofocus
                            autocomplete="username"
                            @error('email') aria-invalid="true" aria-describedby="email-error" @enderror
                            class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-carboli-500 focus:ring-carboli-500"
                        />
                        <x-input-error :messages="$errors->get('email')" class="mt-2" />
                    </div>

                    <button type="submit"
                            class="flex w-full items-center justify-center rounded-lg bg-carboli-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-carboli-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-carboli-500 focus-visible:ring-offset-2">
                        Email password reset link
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
