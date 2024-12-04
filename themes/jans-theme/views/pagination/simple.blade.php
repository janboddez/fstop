@if ($paginator->hasPages())
    <nav class="posts-navigation">
        <ul class="pagination simple-pagination">
            @if ($paginator->onFirstPage())
                <li class="page-item disabled" aria-disabled="true">
                    <span class="page-link">{{ __('← Newer') }}</span>
                </li>
            @else
                <li class="page-item">
                    <a class="page-link" href="{{ $paginator->previousPageUrl() }}" rel="prev">{{ __('← Newer') }}</a>
                </li>
            @endif

            @if ($paginator->hasMorePages())
                <li class="page-item">
                    <a class="page-link" href="{{ $paginator->nextPageUrl() }}" rel="next">{{ __('Older →') }}</a>
                </li>
            @else
                <li class="page-item disabled" aria-disabled="true">
                    <span class="page-link">{{ __('Older →') }}</span>
                </li>
            @endif
        </ul>
    </nav>
@endif
