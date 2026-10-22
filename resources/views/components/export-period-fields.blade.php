@props(['mode' => 'week'])

@php
    $activeMode = in_array($mode, ['date', 'week', 'month', 'custom'], true) ? $mode : 'week';
@endphp

<div class="grid gap-4 sm:grid-cols-2">
    <div>
        <label class="ax-label" for="{{ $attributes->get('id', 'export-mode') }}">Reporting period</label>
        <select id="{{ $attributes->get('id', 'export-mode') }}" name="mode" class="ax-input" data-export-mode>
            <option value="date" @selected($activeMode === 'date')>Single date</option>
            <option value="week" @selected($activeMode === 'week')>Week</option>
            <option value="month" @selected($activeMode === 'month')>Month</option>
            <option value="custom" @selected($activeMode === 'custom')>Custom range</option>
        </select>
    </div>

    <div data-period-field="date" class="{{ $activeMode === 'date' ? '' : 'hidden' }}">
        <label class="ax-label" for="export-date-{{ $attributes->get('id', 'default') }}">Date</label>
        <input id="export-date-{{ $attributes->get('id', 'default') }}" type="date" name="date" value="{{ now()->toDateString() }}" class="ax-input">
    </div>

    <div data-period-field="week" class="{{ $activeMode === 'week' ? '' : 'hidden' }}">
        <label class="ax-label" for="export-week-{{ $attributes->get('id', 'default') }}">Week beginning</label>
        <input id="export-week-{{ $attributes->get('id', 'default') }}" type="date" name="week" value="{{ now()->startOfWeek()->toDateString() }}" class="ax-input">
        <p class="mt-1 text-[11px] text-slate-500">Axcess exports Monday through Sunday.</p>
    </div>

    <div data-period-field="month" class="{{ $activeMode === 'month' ? '' : 'hidden' }}">
        <label class="ax-label" for="export-month-{{ $attributes->get('id', 'default') }}">Month</label>
        <input id="export-month-{{ $attributes->get('id', 'default') }}" type="month" name="month" value="{{ now()->format('Y-m') }}" class="ax-input">
    </div>

    <div data-period-field="custom" class="sm:col-span-2 {{ $activeMode === 'custom' ? '' : 'hidden' }}">
        <div class="grid gap-3 sm:grid-cols-2">
            <div>
                <label class="ax-label" for="export-from-{{ $attributes->get('id', 'default') }}">From</label>
                <input id="export-from-{{ $attributes->get('id', 'default') }}" type="date" name="from" value="{{ now()->startOfWeek()->toDateString() }}" class="ax-input">
            </div>
            <div>
                <label class="ax-label" for="export-to-{{ $attributes->get('id', 'default') }}">To</label>
                <input id="export-to-{{ $attributes->get('id', 'default') }}" type="date" name="to" value="{{ now()->endOfWeek()->toDateString() }}" class="ax-input">
            </div>
        </div>
    </div>
</div>
