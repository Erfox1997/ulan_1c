<x-guest-layout>
    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <div class="mb-6">
        <h1 class="text-lg font-semibold text-slate-900">{{ __('Log in') }}</h1>
        <p class="mt-1 text-sm text-slate-500">{{ __('Access your account') }}</p>
    </div>

    <form method="POST" action="{{ route('login') }}">
        @csrf

        <!-- Email Address -->
        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <!-- Password -->
        <div class="mt-4">
            <x-input-label for="password" :value="__('Password')" />

            <x-text-input id="password" class="block mt-1 w-full"
                            type="password"
                            name="password"
                            required autocomplete="current-password" />

            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <!-- Remember Me -->
        <div class="block mt-4">
            <label for="remember_me" class="inline-flex items-center">
                <input id="remember_me" type="checkbox" class="rounded border-slate-300 text-emerald-600 shadow-sm focus:ring-emerald-500" name="remember">
                <span class="ms-2 text-sm text-gray-600">{{ __('Remember me') }}</span>
            </label>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-3 mt-6">
            @if (Route::has('password.request'))
                <a class="text-sm font-medium text-emerald-700 hover:text-emerald-800 underline-offset-2 hover:underline focus:outline-none focus:ring-2 focus:ring-emerald-500/40 rounded" href="{{ route('password.request') }}">
                    {{ __('Forgot your password?') }}
                </a>
            @endif

            <x-primary-button class="ms-auto !bg-emerald-600 !tracking-normal !normal-case hover:!bg-emerald-700 focus:!ring-emerald-500">
                {{ __('Log in') }}
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>
