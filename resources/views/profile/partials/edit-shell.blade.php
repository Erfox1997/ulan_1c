@if (session('status'))
    <div class="mb-6 rounded-xl border border-emerald-200/80 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
        @if (session('status') === 'profile-updated')
            Данные профиля сохранены.
        @elseif (session('status') === 'password-updated')
            Пароль обновлён.
        @else
            {{ session('status') }}
        @endif
    </div>
@endif

<div class="mx-auto max-w-3xl space-y-6">
    <div class="rounded-2xl border border-slate-200/80 bg-white p-6 shadow-sm sm:p-8">
        <div class="max-w-xl">
            @include('profile.partials.update-profile-information-form')
        </div>
    </div>

    <div class="rounded-2xl border border-slate-200/80 bg-white p-6 shadow-sm sm:p-8">
        <div class="max-w-xl">
            @include('profile.partials.update-password-form')
        </div>
    </div>

    <div class="rounded-2xl border border-slate-200/80 bg-white p-6 shadow-sm sm:p-8">
        <div class="max-w-xl">
            @include('profile.partials.delete-user-form')
        </div>
    </div>
</div>
