<span class="is-pulled-right mx-1 is-hidden-mobile">
    {{ __('Showing :first to :last of :total results', [
        'first' => $paginator->firstItem() ?? 0,
        'last' => $paginator->lastItem() ?? 0,
        'total' => $paginator->total(),
    ]) }}
</span>
