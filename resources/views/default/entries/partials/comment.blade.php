@if (in_array($comment->author_url, ['https://mastodon.social/@janboddez', 'https://geekcompass.com/@jan', 'https://jan.boddez.net/'], true))
    <li id="comment-{{ $comment->id }}" class="h-cite p-comment by-author">
@else
    <li id="comment-{{ $comment->id }}" class="h-cite p-comment">
@endif

    <h3>
        {!! __('<span class="p-author h-card">:avatar<a class="u-url url fn" href=":author_url" rel="nofollow">:author</a></span> on <a href=":permalink" rel="bookmark"><time datetime=":created_at" class="dt-published">:created</time></a>', [
            'avatar' => ! empty($comment->avatar) ? '<span class="avatar">' . $comment->avatar . '</span> ' : '',
            'author_url' => e($comment->author_url ?? ''),
            'author' => e($comment->author ?? $comment->author_url ?? ''),
            'permalink' => route(Str::plural($entry->type) . '.show', $entry->slug) . "#comment-{$comment->id}",
            'created_at' => $comment->created_at->format('c'),
            'created' => $comment->created_at->format('M j, Y'),
        ]) !!}

        @auth
            &bull;
            <a href="{{ route('admin.comments.edit', $comment) }}">{{ __('Edit') }}</a>
        @endauth
    </h3>

    <div class="p-name p-content">
        <p>{!! $comment->content !!}</p>
    </div>

    @if (! empty($comment->source))
        <p><small>
            {!! __(
                'Via <a href=":source_url" class="u-url" rel="nofollow">:source</a><span class="sr-only">, in reply to <a href=":target_url" class="u-in-reply-to">:target</a></span>',
                [
                    'source_url' => e($comment->source),
                    'source' => e(parse_url($comment->source, PHP_URL_HOST)),
                    'target_url' => route(Str::plural($entry->type) . '.show', $entry->slug),
                    'target' => e($entry->name),
                ]
            ) !!}
        </small></p>
    @endif
</li>
