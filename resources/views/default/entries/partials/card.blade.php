<a class="card" href="{{ $entry->meta['card']['url'] }}" target="_blank" rel="nofollow noopener noreferrer">
    <div class="thumbnail">{!! $entry->card['thumbnail'] ?? '' !!}</div>
    <dl class="summary">
        <dt>{{ $entry->meta['card']['title'] }}</dt>
        <dd>
            <svg class="icon icon-link" aria-hidden="true" role="img" width="14" height="14"><use href="#icon-link"></use></svg>
            {{ parse_url($entry->meta['card']['url'], PHP_URL_HOST) }}
        </dd>
    </dl>
</a>
