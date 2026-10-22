@props(['status', 'label' => null])

@php
    $value = (string) $status;
    $classes = match (true) {
        in_array($value, ['published', 'approved', 'active', 'available', 'covered', 'confirmed', 'completed', 'resolved'], true) => 'bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-200',
        in_array($value, ['pending', 'draft', 'on_leave', 'warning', 'called_in', 'insufficient', 'info'], true) => 'bg-amber-50 text-amber-700 ring-1 ring-inset ring-amber-200',
        in_array($value, ['rejected', 'cancelled', 'inactive', 'error', 'missing', 'no_show', 'unauthorized'], true) => 'bg-rose-50 text-rose-700 ring-1 ring-inset ring-rose-200',
        in_array($value, ['released', 'open'], true) => 'bg-sky-50 text-sky-700 ring-1 ring-inset ring-sky-200',
        default => 'bg-slate-100 text-slate-600 ring-1 ring-inset ring-slate-200',
    };
@endphp

<span {{ $attributes->merge(['class' => 'ax-chip '.$classes]) }}>
    <span class="h-1.5 w-1.5 rounded-full bg-current opacity-70"></span>
    {{ $label ?? \App\Support\Axcess::label('assignment_status', $value) }}
</span>
