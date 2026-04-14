@props(['maxWidth' => 'max-w-6xl'])
<div class="mx-auto w-full {{ $maxWidth }} space-y-4">
    @include('admin.partials.status-flash')
    <div class="rounded-[1.75rem] bg-gradient-to-br from-sky-100/60 via-white to-emerald-100/50 p-[3px] shadow-[0_12px_40px_-12px_rgba(14,165,233,0.2)] ring-1 ring-sky-200/50">
        <div class="rounded-[1.65rem] bg-gradient-to-b from-white/95 to-slate-50/90 px-3 py-4 sm:px-5 sm:py-6">
            {{ $slot }}
        </div>
    </div>
</div>
