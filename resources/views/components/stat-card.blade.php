@props(['label', 'value', 'meta' => null, 'icon' => 'chart', 'tone' => 'brand'])

@php
    $tones = [
        'brand' => 'bg-brand-50 text-brand-700 ring-brand-100',
        'slate' => 'bg-slate-100 text-slate-700 ring-slate-200',
        'amber' => 'bg-amber-50 text-amber-700 ring-amber-100',
        'rose' => 'bg-rose-50 text-rose-700 ring-rose-100',
        'sky' => 'bg-sky-50 text-sky-700 ring-sky-100',
        'violet' => 'bg-violet-50 text-violet-700 ring-violet-100',
    ];
@endphp

<div {{ $attributes->merge(['class' => 'ax-card p-5']) }}>
    <div class="flex items-start justify-between gap-4">
        <div class="min-w-0">
            <p class="text-[11px] font-bold uppercase tracking-wider text-slate-500">{{ $label }}</p>
            <p class="mt-2 text-2xl font-black tracking-tight text-slate-900">{{ $value }}</p>
            @if($meta)
                <p class="mt-1 text-xs text-slate-500">{{ $meta }}</p>
            @endif
        </div>
        <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl ring-1 {{ $tones[$tone] ?? $tones['brand'] }}">
            <x-icon :name="$icon" class="h-5 w-5" />
        </span>
    </div>
</div>
