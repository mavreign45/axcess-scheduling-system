<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#0f172a">
    <title>@yield('title', 'Dashboard') · {{ config('axcess.brand.name', 'Axcess') }}</title>
    <meta name="description" content="{{ config('axcess.brand.full_title') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50">
    <a href="#main-content" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-[60] focus:rounded-lg focus:bg-white focus:px-4 focus:py-2 focus:text-sm focus:font-semibold focus:text-brand-700 focus:shadow-lg">Skip to content</a>

    <div class="min-h-screen lg:flex">
        <div id="sidebar-overlay" class="fixed inset-0 z-30 hidden bg-slate-950/60 backdrop-blur-sm lg:hidden"></div>

        <aside id="app-sidebar" class="fixed inset-y-0 left-0 z-40 flex w-72 -translate-x-full flex-col bg-slate-950 text-white transition-transform duration-200 ease-out lg:translate-x-0">
            <div class="flex h-[76px] items-center justify-between border-b border-white/10 px-5">
                <a href="{{ route('dashboard') }}" class="flex items-center gap-3" aria-label="Axcess dashboard">
                    <x-logo dark />
                </a>
                <button type="button" class="rounded-lg p-2 text-slate-400 hover:bg-white/5 hover:text-white lg:hidden" data-sidebar-toggle aria-label="Close navigation">
                    <x-icon name="x" class="h-5 w-5" />
                </button>
            </div>

            <nav class="ax-scrollbar flex-1 space-y-7 overflow-y-auto px-4 py-6" aria-label="Main navigation">
                <div>
                    <p class="px-3 pb-2 text-[10px] font-bold uppercase tracking-[0.18em] text-slate-500">Workspace</p>
                    <div class="space-y-1">
                        <a href="{{ route('dashboard') }}" class="ax-nav-link {{ request()->routeIs('dashboard') ? 'ax-nav-link-active' : '' }}">
                            <x-icon name="dashboard" class="h-[18px] w-[18px]" />
                            Dashboard
                        </a>
                        <a href="{{ route('schedule.index') }}" class="ax-nav-link {{ request()->routeIs('schedule.*') ? 'ax-nav-link-active' : '' }}">
                            <x-icon name="calendar" class="h-[18px] w-[18px]" />
                            Schedule
                        </a>
                        <a href="{{ route('leave.index') }}" class="ax-nav-link {{ request()->routeIs('leave.*') ? 'ax-nav-link-active' : '' }}">
                            <x-icon name="clock" class="h-[18px] w-[18px]" />
                            Leave &amp; absences
                        </a>
                        @can('view-staff-directory')
                            <a href="{{ route('staff.index') }}" class="ax-nav-link {{ request()->routeIs('staff.*') ? 'ax-nav-link-active' : '' }}">
                                <x-icon name="users" class="h-[18px] w-[18px]" />
                                Staff directory
                            </a>
                        @endcan
                    </div>
                </div>

                @if(auth()->user()?->canManageOperations())
                    <div>
                        <p class="px-3 pb-2 text-[10px] font-bold uppercase tracking-[0.18em] text-slate-500">Operations</p>
                        <div class="space-y-1">
                            <a href="{{ route('standby.index') }}" class="ax-nav-link {{ request()->routeIs('standby.*') ? 'ax-nav-link-active' : '' }}">
                                <x-icon name="phone" class="h-[18px] w-[18px]" />
                                Standby &amp; cover
                            </a>
                            <a href="{{ route('reports.index') }}" class="ax-nav-link {{ request()->routeIs('reports.*') ? 'ax-nav-link-active' : '' }}">
                                <x-icon name="chart" class="h-[18px] w-[18px]" />
                                Reports &amp; exports
                            </a>
                        </div>
                    </div>
                @endif

                <div class="rounded-2xl border border-white/10 bg-white/[0.04] p-4">
                    <div class="flex items-center gap-2 text-brand-300">
                        <x-icon name="shield" class="h-4 w-4" />
                        <span class="text-[10px] font-bold uppercase tracking-[0.14em]">Permission aware</span>
                    </div>
                    <p class="mt-2 text-xs leading-5 text-slate-400">
                        Exports and records follow your role and department scope.
                    </p>
                    @if(auth()->user()?->can('export-data'))
                        <a href="{{ route('reports.index') }}" class="mt-3 inline-flex items-center gap-1 text-xs font-semibold text-brand-300 hover:text-brand-200">
                            Open export centre
                            <x-icon name="arrow-right" class="h-3.5 w-3.5" />
                        </a>
                    @endif
                </div>
            </nav>

            <div class="border-t border-white/10 p-4">
                <div class="flex items-center gap-3 rounded-xl px-2 py-2">
                    <span class="ax-avatar bg-brand-700">{{ auth()->user()?->initials }}</span>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-semibold text-white">{{ auth()->user()?->full_name }}</p>
                        <p class="truncate text-[11px] text-slate-400">{{ auth()->user()?->roleLabel() }}@if(auth()->user()?->department) · {{ auth()->user()->department->name }}@endif</p>
                    </div>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="rounded-lg p-2 text-slate-400 transition hover:bg-white/5 hover:text-white" title="Sign out" aria-label="Sign out">
                            <x-icon name="logout" class="h-4 w-4" />
                        </button>
                    </form>
                </div>
            </div>
        </aside>

        <div class="min-w-0 flex-1 lg:pl-72">
            <header class="sticky top-0 z-20 border-b border-slate-200 bg-white/90 backdrop-blur-md">
                <div class="flex min-h-[76px] flex-wrap items-center gap-4 px-4 py-3 sm:px-6 lg:px-8">
                    <button type="button" class="rounded-lg border border-slate-200 p-2 text-slate-600 hover:bg-slate-50 lg:hidden" data-sidebar-toggle aria-label="Open navigation">
                        <x-icon name="menu" class="h-5 w-5" />
                    </button>

                    <div class="min-w-0 flex-1">
                        <p class="text-[10px] font-bold uppercase tracking-[0.16em] text-brand-700">@yield('eyebrow', config('axcess.brand.name'))</p>
                        <h1 class="truncate text-xl font-black tracking-tight text-slate-900 sm:text-2xl">@yield('title', 'Dashboard')</h1>
                        @hasSection('subtitle')
                            <p class="mt-0.5 hidden text-xs text-slate-500 sm:block">@yield('subtitle')</p>
                        @endif
                    </div>

                    <div class="flex shrink-0 items-center gap-2">
                        @yield('actions')
                        <div class="hidden items-center gap-2 rounded-xl border border-slate-200 bg-slate-50 py-1.5 pl-1.5 pr-3 sm:flex">
                            <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-slate-900 text-[11px] font-bold text-white">{{ auth()->user()?->initials }}</span>
                            <div>
                                <p class="max-w-[130px] truncate text-[11px] font-bold text-slate-800">{{ auth()->user()?->full_name }}</p>
                                <p class="text-[10px] text-slate-500">{{ auth()->user()?->roleLabel() }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </header>

            <main id="main-content" class="mx-auto w-full max-w-[1600px] px-4 py-6 sm:px-6 lg:px-8">
                @if(session('success'))
                    <div class="mb-5 flex items-start gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 shadow-sm" data-flash role="status">
                        <x-icon name="check-circle" class="mt-0.5 h-5 w-5 shrink-0 text-emerald-600" />
                        <p class="flex-1 font-medium">{{ session('success') }}</p>
                        <button type="button" class="rounded-md p-1 text-emerald-600 hover:bg-emerald-100" data-flash-close aria-label="Dismiss">
                            <x-icon name="x" class="h-4 w-4" />
                        </button>
                    </div>
                @endif

                @if(session('error'))
                    <div class="mb-5 flex items-start gap-3 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800 shadow-sm" data-flash role="alert">
                        <x-icon name="warning" class="mt-0.5 h-5 w-5 shrink-0 text-rose-600" />
                        <p class="flex-1 font-medium">{{ session('error') }}</p>
                        <button type="button" class="rounded-md p-1 text-rose-600 hover:bg-rose-100" data-flash-close aria-label="Dismiss">
                            <x-icon name="x" class="h-4 w-4" />
                        </button>
                    </div>
                @endif

                @if($errors->any())
                    <div class="mb-5 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800 shadow-sm" role="alert">
                        <div class="flex items-start gap-3">
                            <x-icon name="warning" class="mt-0.5 h-5 w-5 shrink-0 text-rose-600" />
                            <div>
                                <p class="font-bold">Please check the highlighted fields.</p>
                                <ul class="mt-1 list-inside list-disc space-y-0.5 text-xs">
                                    @foreach($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    </div>
                @endif

                @yield('content')
            </main>

            <footer class="mx-auto flex w-full max-w-[1600px] flex-col gap-2 border-t border-slate-200 px-4 py-5 text-[11px] text-slate-400 sm:flex-row sm:items-center sm:justify-between sm:px-6 lg:px-8">
                <p><span class="font-semibold text-slate-500">Axcess</span> · Staff Scheduling &amp; Workforce Management</p>
                <p>Planned hours are scheduling data, not attendance or payroll.</p>
            </footer>
        </div>
    </div>
</body>
</html>
