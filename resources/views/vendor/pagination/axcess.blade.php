@if ($paginator->hasPages())
    <nav class="flex flex-col gap-3 border-t border-slate-200 px-4 py-4 sm:flex-row sm:items-center sm:justify-between" role="navigation" aria-label="Pagination">
        <p class="text-xs text-slate-500">
            Showing <span class="font-bold text-slate-700">{{ $paginator->firstItem() }}</span>
            to <span class="font-bold text-slate-700">{{ $paginator->lastItem() }}</span>
            of <span class="font-bold text-slate-700">{{ $paginator->total() }}</span> results
        </p>

        <div class="flex items-center gap-1">
            @if ($paginator->onFirstPage())
                <span class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200 text-slate-300" aria-disabled="true">
                    <x-icon name="chevron-left" class="h-4 w-4" />
                </span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-600 shadow-sm transition hover:border-brand-300 hover:text-brand-700" rel="prev" aria-label="Previous page">
                    <x-icon name="chevron-left" class="h-4 w-4" />
                </a>
            @endif

            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="inline-flex h-9 w-9 items-center justify-center text-xs text-slate-400">{{ $element }}</span>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span class="inline-flex h-9 min-w-9 items-center justify-center rounded-lg bg-slate-900 px-2 text-xs font-bold text-white" aria-current="page">{{ $page }}</span>
                        @else
                            <a href="{{ $url }}" class="inline-flex h-9 min-w-9 items-center justify-center rounded-lg border border-slate-200 bg-white px-2 text-xs font-semibold text-slate-600 shadow-sm transition hover:border-brand-300 hover:text-brand-700">{{ $page }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-600 shadow-sm transition hover:border-brand-300 hover:text-brand-700" rel="next" aria-label="Next page">
                    <x-icon name="chevron-right" class="h-4 w-4" />
                </a>
            @else
                <span class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200 text-slate-300" aria-disabled="true">
                    <x-icon name="chevron-right" class="h-4 w-4" />
                </span>
            @endif
        </div>
    </nav>
@endif
