@props(['id', 'title', 'description' => null, 'url', 'action' => 'Export'])

<dialog id="{{ $id }}" class="ax-dialog">
    <form data-export-form data-export-url="{{ $url }}" class="flex min-h-full flex-col">
        <div class="flex items-start justify-between gap-4 border-b border-slate-200 bg-slate-50/80 px-6 py-5">
            <div class="flex items-start gap-3">
                <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-brand-700 text-white shadow-sm">
                    <x-icon name="download" class="h-5 w-5" />
                </span>
                <div>
                    <h2 class="text-base font-black text-slate-900">{{ $title }}</h2>
                    @if($description)
                        <p class="mt-1 max-w-xl text-xs leading-5 text-slate-500">{{ $description }}</p>
                    @endif
                </div>
            </div>
            <button type="button" class="rounded-lg p-2 text-slate-400 transition hover:bg-white hover:text-slate-700" data-export-close aria-label="Close export dialog">
                <x-icon name="x" class="h-5 w-5" />
            </button>
        </div>

        <div class="grid gap-5 px-6 py-5">
            <div class="hidden rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-700" data-export-error role="alert"></div>
            <div class="hidden rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700" data-export-success role="status"></div>

            {{ $slot }}
        </div>

        <div class="mt-auto flex flex-col gap-3 border-t border-slate-200 bg-slate-50/80 px-6 py-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-3">
                <label class="text-xs font-bold uppercase tracking-wider text-slate-500" for="{{ $id }}-format">Format</label>
                <select id="{{ $id }}-format" name="format" class="rounded-lg border border-slate-300 bg-white px-2.5 py-2 text-xs font-semibold text-slate-700" data-export-format>
                    <option value="xlsx">Excel workbook (.xlsx)</option>
                    <option value="csv">CSV (single sheet)</option>
                </select>
            </div>
            <div class="flex items-center justify-end gap-2">
                <button type="button" class="ax-btn-secondary ax-btn-sm" data-export-close>Cancel</button>
                <button type="submit" class="ax-btn-primary ax-btn-sm" data-export-submit>
                    <x-icon name="download" class="h-4 w-4" />
                    {{ $action }}
                </button>
            </div>
        </div>

        <p class="hidden border-t border-amber-200 bg-amber-50 px-6 py-2.5 text-[11px] leading-5 text-amber-800" data-csv-note>
            CSV exports contain only the primary worksheet. Choose Excel to include every selected worksheet, formatting, formulas, and filters.
        </p>
    </form>
</dialog>
