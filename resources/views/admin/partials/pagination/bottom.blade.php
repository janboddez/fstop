@if ($paginator->hasPages())
    <nav class="posts-navigation mt-4">
        <div class="is-hidden-tablet is-flex">
            @if ($paginator->onFirstPage())
                <span class="pagination-previous ml-0 is-flex-grow-0 is-disabled" aria-disabled="true">
                    {!! __('pagination.previous') !!}
                </span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" class="pagination-previous ml-0 is-flex-grow-0">
                    {!! __('pagination.previous') !!}
                </a>
            @endif

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" class="pagination-next mr-0 is-flex-grow-0">
                    {!! __('pagination.next') !!}
                </a>
            @else
                <span class="pagination-next mr-0 is-flex-grow-0 is-disabled">
                    {!! __('pagination.next') !!}
                </span>
            @endif
        </div>

        <div class="is-hidden-mobile">
            <div class="is-flex">
                {{-- Previous Page Link --}}
                @if ($paginator->onFirstPage())
                    <span class="pagination-previous ml-0 is-disabled" aria-disabled="true">
                        {!! __('pagination.previous') !!}
                    </span>
                @else
                    <a href="{{ $paginator->previousPageUrl() }}" class="pagination-previous ml-0">
                        {!! __('pagination.previous') !!}
                    </a>
                @endif

                {{-- Pagination Elements --}}
                @foreach ($elements as $element)
                    {{-- "Three Dots" Separator --}}
                    @if (is_string($element))
                        <span class="pagination-ellipsis" aria-disabled="true">
                            <span>{{ $element }}</span>
                        </span>
                    @endif

                    {{-- Array Of Links --}}
                    @if (is_array($element))
                        @foreach ($element as $page => $url)
                            @if ($page === $paginator->currentPage())
                                <span class="pagination-link is-disabled" aria-current="page">
                                    <span>{{ $page }}</span>
                                </span>
                            @else
                                <a href="{{ $url }}" class="pagination-link">
                                    {{ $page }}
                                </a>
                            @endif
                        @endforeach
                    @endif
                @endforeach

                {{-- Next Page Link --}}
                @if ($paginator->hasMorePages())
                    <a href="{{ $paginator->nextPageUrl() }}" class="pagination-next mr-0">
                        {!! __('pagination.next') !!}
                    </a>
                @else
                    <span class="pagination-next mr-0 is-disabled">
                        {!! __('pagination.next') !!}
                    </span>
                @endif
            </div>
        </div>
    </nav>
@endif
