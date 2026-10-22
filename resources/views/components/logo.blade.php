@props(['dark' => false, 'compact' => false])

<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-3']) }}>
    <span class="relative inline-flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-gradient-to-br from-brand-400 via-brand-600 to-brand-800 shadow-lg shadow-brand-900/20 ring-1 ring-white/20">
        <svg class="h-6 w-6 text-white" viewBox="0 0 32 32" fill="none" aria-hidden="true">
            <path d="M7 24 15.2 7h2.5L26 24h-4.2l-1.7-4H12.7l-1.6 4H7Zm7-7.5h4.5L16.3 10 14 16.5Z" fill="currentColor"/>
            <path d="M23.5 7.5h3v3h-3z" fill="currentColor" opacity=".55"/>
        </svg>
    </span>
    @unless($compact)
        <span class="flex min-w-0 flex-col">
            <span class="text-[17px] font-black leading-5 tracking-tight {{ $dark ? 'text-white' : 'text-slate-900' }}">Axcess</span>
            <span class="text-[9px] font-semibold uppercase tracking-[0.16em] {{ $dark ? 'text-slate-400' : 'text-slate-500' }}">Workforce scheduling</span>
        </span>
    @endunless
</span>
