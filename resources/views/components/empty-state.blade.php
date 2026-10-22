@props(['title' => 'Nothing here yet', 'message' => null, 'icon' => 'inbox'])

<div {{ $attributes->merge(['class' => 'flex flex-col items-center justify-center rounded-2xl border border-dashed border-slate-300 bg-slate-50/60 px-6 py-12 text-center']) }}>
    <span class="inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-white text-slate-400 shadow-sm ring-1 ring-slate-200">
        <x-icon :name="$icon" class="h-6 w-6" />
    </span>
    <h3 class="mt-4 text-sm font-bold text-slate-800">{{ $title }}</h3>
    @if($message)
        <p class="mt-1 max-w-md text-sm text-slate-500">{{ $message }}</p>
    @endif
    @if(trim((string) $slot) !== '')
        <div class="mt-4">{{ $slot }}</div>
    @endif
</div>
