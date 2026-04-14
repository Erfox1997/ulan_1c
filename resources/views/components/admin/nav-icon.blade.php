@props(['name' => 'home'])
@php
    $class = 'h-5 w-5 shrink-0 text-current opacity-80';
@endphp
@switch($name)
    @case('home')
        <svg {{ $attributes->merge(['class' => $class]) }} fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25"/></svg>
        @break
    @case('chart')
        <svg {{ $attributes->merge(['class' => $class]) }} fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z"/></svg>
        @break
    @case('bank')
        <svg {{ $attributes->merge(['class' => $class]) }} fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 013 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 00-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 01-1.125-1.125V15m1.5 0v-.75A.75.75 0 003 15h-.75M15 10.5a3 3 0 11-6 0 3 3 0 016 0zm3 0h.008v.008H18V10.5zm-12 0h.008v.008H6V10.5z"/></svg>
        @break
    @case('cart')
        <svg {{ $attributes->merge(['class' => $class]) }} fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 00-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 00-16.536-1.84M7.5 14.25L5.106 5.272M6 20.25a.75.75 0 11-1.5 0 .75.75 0 011.5 0zm12.75 0a.75.75 0 11-1.5 0 .75.75 0 011.5 0z"/></svg>
        @break
    @case('box')
        <svg {{ $attributes->merge(['class' => $class]) }} fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z"/></svg>
        @break
    @case('users')
        <svg {{ $attributes->merge(['class' => $class]) }} fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z"/></svg>
        @break
    @case('cog')
        <svg {{ $attributes->merge(['class' => $class]) }} fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.09.543-.364 1.02-.99 1.114-.636.1-1.28.216-1.92.332-.65-.136-1.305-.248-1.96-.273a31.52 31.52 0 00-1.92 1.732c-.28.216-.52.475-.68.76-.16.288-.24.6-.24.92 0 .32.08.632.24.92.16.285.4.544.68.76.592.472 1.224.88 1.88 1.216a31.52 31.52 0 001.92 1.732c.64.024 1.31.137 1.96.273.64-.116 1.284-.232 1.92-.332.626-.094.99-.571.99-1.114l-.213-1.281c-.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.09.543-.364 1.02-.99 1.114-.636.1-1.28.216-1.92.332-.65-.136-1.305-.248-1.96-.273a31.52 31.52 0 00-1.92 1.732c-.28.216-.52.475-.68.76-.16.288-.24.6-.24.92 0 .32.08.632.24.92.16.285.4.544.68.76.592.472 1.224.88 1.88 1.216a31.52 31.52 0 001.92 1.732c.64.024 1.31.137 1.96.273.64-.116 1.284-.232 1.92-.332.626-.094.99-.571.99-1.114l-.213-1.281z"/></svg>
        @break
    @case('briefcase')
        <svg {{ $attributes->merge(['class' => $class]) }} fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 14.15v4.125c0 1.036-.84 1.875-1.875 1.875H5.625c-1.036 0-1.875-.84-1.875-1.875v-4.125m16.5 0c0 1.036-.84 1.875-1.875 1.875H5.625c-1.036 0-1.875-.84-1.875-1.875m16.5 0V8.25c0-1.036-.84-1.875-1.875-1.875H5.625c-1.036 0-1.875.84-1.875 1.875v5.875m17.25 0h-1.5M4.125 8.25h15.75"/></svg>
        @break
    @default
        <svg {{ $attributes->merge(['class' => $class]) }} fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6z"/></svg>
        @break
@endswitch
