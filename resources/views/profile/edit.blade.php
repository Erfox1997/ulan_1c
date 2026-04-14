@php
    $profileUser = $user ?? auth()->user();
@endphp

@if ($profileUser->isSuperAdmin())
    <x-superadmin-layout pageTitle="Профиль">
        @include('profile.partials.edit-shell')
    </x-superadmin-layout>
@elseif ($profileUser->branch_id)
    <x-admin-layout pageTitle="Профиль">
        @include('profile.partials.edit-shell')
    </x-admin-layout>
@else
    <x-app-layout>
        <x-slot name="header">
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                {{ __('Profile') }}
            </h2>
        </x-slot>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                @include('profile.partials.edit-shell')
            </div>
        </div>
    </x-app-layout>
@endif
