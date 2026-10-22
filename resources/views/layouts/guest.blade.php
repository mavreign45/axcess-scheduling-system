<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#0f172a">
    <title>@yield('title', 'Sign in') · {{ config('axcess.brand.name', 'Axcess') }}</title>
    <meta name="description" content="{{ config('axcess.brand.full_title') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-950">
    <div class="grid min-h-screen lg:grid-cols-[1.05fr_.95fr]">
        <section class="relative hidden overflow-hidden bg-slate-950 px-10 py-12 lg:flex lg:flex-col lg:justify-between">
            <div class="pointer-events-none absolute inset-0">
                <div class="absolute -left-28 top-16 h-96 w-96 rounded-full bg-brand-600/20 blur-3xl"></div>
                <div class="absolute -right-20 bottom-0 h-[28rem] w-[28rem] rounded-full bg-sky-600/10 blur-3xl"></div>
                <div class="absolute inset-0 opacity-[0.04]" style="background-image: linear-gradient(#fff 1px, transparent 1px), linear-gradient(90deg, #fff 1px, transparent 1px); background-size: 48px 48px;"></div>
            </div>

            <a href="{{ url('/') }}" class="relative z-10 inline-flex w-fit items-center gap-3">
                <x-logo dark />
            </a>

            <div class="relative z-10 max-w-xl">
                <span class="inline-flex items-center gap-2 rounded-full border border-brand-400/30 bg-brand-400/10 px-3 py-1.5 text-[10px] font-bold uppercase tracking-[0.16em] text-brand-200">
                    <x-icon name="sparkles" class="h-3.5 w-3.5" />
                    Workforce clarity, every shift
                </span>
                <h1 class="mt-6 text-5xl font-black leading-[1.03] tracking-tight text-white">
                    Every shift.<br>
                    Every handover.<br>
                    <span class="text-brand-300">Under control.</span>
                </h1>
                <p class="mt-6 max-w-md text-base leading-7 text-slate-400">
                    Axcess brings schedules, leave, standby cover, conflicts, and operational reporting into one dependable workspace for your team.
                </p>

                <div class="mt-9 grid gap-3 sm:grid-cols-2">
                    @foreach([
                        ['calendar', 'Weekly & monthly planning'],
                        ['users', 'Department-scoped access'],
                        ['warning', 'Conflict detection'],
                        ['download', 'Genuine Excel workbooks'],
                    ] as [$icon, $label])
                        <div class="flex items-center gap-3 rounded-xl border border-white/10 bg-white/[0.04] px-3.5 py-3">
                            <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-brand-500/15 text-brand-300">
                                <x-icon :name="$icon" class="h-4 w-4" />
                            </span>
                            <span class="text-xs font-semibold text-slate-300">{{ $label }}</span>
                        </div>
                    @endforeach
                </div>
            </div>

            <p class="relative z-10 text-[11px] text-slate-600">Axcess · Staff Scheduling &amp; Workforce Management</p>
        </section>

        <section class="flex min-h-screen items-center justify-center bg-slate-50 px-4 py-10 sm:px-8">
            <div class="w-full max-w-md">
                <div class="mb-8 flex items-center justify-between lg:hidden">
                    <x-logo />
                    <span class="rounded-full bg-brand-50 px-3 py-1 text-[10px] font-bold uppercase tracking-wider text-brand-700">Workforce</span>
                </div>
                @yield('content')
                <p class="mt-8 text-center text-[11px] text-slate-400">
                    © {{ now()->year }} Axcess · Secure workforce scheduling
                </p>
            </div>
        </section>
    </div>
</body>
</html>
