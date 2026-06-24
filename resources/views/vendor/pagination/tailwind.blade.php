@if ($paginator->hasPages())
    <nav role="navigation" aria-label="{{ __('Pagination Navigation') }}" class="flex justify-end">
        <span class="inline-flex items-center gap-2">
            @if ($paginator->onFirstPage())
                <span class="px-4 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-md cursor-not-allowed leading-5">
                    {!! __('pagination.previous') !!}
                </span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md leading-5 hover:bg-gray-100">
                    {!! __('pagination.previous') !!}
                </a>
            @endif

            <span class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-200 border border-gray-300 rounded-md leading-5">
                {{ $paginator->currentPage() }}
            </span>

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md leading-5 hover:bg-gray-100">
                    {!! __('pagination.next') !!}
                </a>
            @else
                <span class="px-4 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-md cursor-not-allowed leading-5">
                    {!! __('pagination.next') !!}
                </span>
            @endif
        </span>
    </nav>
@endif
