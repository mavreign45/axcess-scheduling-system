@extends('layouts.guest')

@section('title', 'Sign in')

@section('content')
    <div class="ax-card p-6 sm:p-8">
        <div class="mb-7">
            <span class="inline-flex items-center gap-1.5 rounded-full bg-brand-50 px-2.5 py-1 text-[10px] font-bold uppercase tracking-[0.14em] text-brand-700">
                <span class="h-1.5 w-1.5 rounded-full bg-brand-500"></span>
                Secure workspace
            </span>
            <h2 class="mt-4 text-2xl font-black tracking-tight text-slate-900">Sign in to Axcess</h2>
            <p class="mt-1.5 text-sm text-slate-500">Use your workforce account to access schedules and operations.</p>
        </div>

        @if($errors->any())
            <div class="mb-5 flex items-start gap-3 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800" role="alert">
                <x-icon name="warning" class="mt-0.5 h-5 w-5 shrink-0" />
                <div>
                    <p class="font-semibold">We could not sign you in.</p>
                    <p class="mt-0.5 text-xs">{{ $errors->first() }}</p>
                </div>
            </div>
        @endif

        <form method="POST" action="{{ route('login') }}" class="space-y-5">
            @csrf
            <div>
                <label for="email" class="ax-label">Work email</label>
                <div class="relative">
                    <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-slate-400">
                        <x-icon name="mail" class="h-4 w-4" />
                    </span>
                    <input id="email" name="email" type="email" autocomplete="email" required autofocus value="{{ old('email') }}" class="ax-input pl-10" placeholder="you@company.com">
                </div>
            </div>

            <div>
                <div class="flex items-center justify-between">
                    <label for="password" class="ax-label">Password</label>
                    <span class="text-[10px] font-medium text-slate-400">Contact your administrator to reset</span>
                </div>
                <div class="relative">
                    <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-slate-400">
                        <x-icon name="shield" class="h-4 w-4" />
                    </span>
                    <input id="password" name="password" type="password" autocomplete="current-password" required class="ax-input pl-10" placeholder="Enter your password">
                </div>
            </div>

            <label class="flex items-center gap-2.5 text-sm text-slate-600">
                <input type="checkbox" name="remember" value="1" class="h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500" @checked(old('remember'))>
                Keep me signed in on this device
            </label>

            <button type="submit" class="ax-btn-primary w-full">
                Sign in to Axcess
                <x-icon name="arrow-right" class="h-4 w-4" />
            </button>
        </form>

        @if(app()->environment('local'))
            <div class="mt-6 rounded-2xl border border-slate-200 bg-slate-50 p-4">
                <div class="flex items-center gap-2">
                    <x-icon name="sparkles" class="h-4 w-4 text-brand-600" />
                    <p class="text-[10px] font-bold uppercase tracking-[0.14em] text-slate-500">Demo accounts · password: password</p>
                </div>
                <div class="mt-3 grid gap-2 text-xs">
                    <div class="flex items-center justify-between gap-3 rounded-lg bg-white px-3 py-2 ring-1 ring-slate-200">
                        <span class="font-semibold text-slate-700">Administrator</span>
                        <code class="text-[11px] text-slate-500">admin@axcess.test</code>
                    </div>
                    <div class="flex items-center justify-between gap-3 rounded-lg bg-white px-3 py-2 ring-1 ring-slate-200">
                        <span class="font-semibold text-slate-700">Operations manager</span>
                        <code class="text-[11px] text-slate-500">manager@axcess.test</code>
                    </div>
                    <div class="flex items-center justify-between gap-3 rounded-lg bg-white px-3 py-2 ring-1 ring-slate-200">
                        <span class="font-semibold text-slate-700">Employee</span>
                        <code class="text-[11px] text-slate-500">liam.bennett@axcess.test</code>
                    </div>
                </div>
            </div>
        @endif
    </div>
@endsection
