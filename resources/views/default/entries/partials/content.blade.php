<article class="h-entry {{ $entry->type }}{{ ! empty($entry->thumbnail) ? ' has-thumbnail' : '' }}">
    @if (is_archive() && ! empty($entry->thumbnail))
        <div class="post-thumbnail">
            {{-- @todo: Make responsive. --}}
            <a class="u-url" href="{{ $entry->permalink }}" rel="bookmark">{!! $entry->thumbnail_html !!}</a>
        </div>
    @endif

    @if (in_array($entry->type, ['article', 'page'], true))
        <header class="entry-header">
            @if (is_singular())
                <h1 class="entry-title p-name">{{ $entry->name }}</h1>
            @else
                <h2 class="entry-title p-name"><a class="u-url" href="{{ $entry->permalink }}" rel="bookmark">{{ $entry->name }}</a></h2>
            @endif

            @if ($entry->type === 'article')
                <div class="entry-meta">
                    <a class="u-url" href="{{ $entry->permalink }}" rel="bookmark"><time class="dt-published" datetime="{{ $entry->published ? $entry->published->format('c') : $entry->created_at->format('c') }}">{{ $entry->published ? $entry->published->format('M j, Y') : $entry->created_at->format('M j, Y') }}</time></a>

                    @auth
                        &bull;
                        <a href="{{ route('admin.entries.edit', $entry) }}">{{ __('Edit :type', ['type' => $entry->type]) }}</a>
                    @endauth
                </div>
            @else
                @auth
                    <div class="entry-meta">
                        <a href="{{ route('admin.entries.edit', $entry) }}">{{ __('Edit :type', ['type' => $entry->type]) }}</a>
                    </div>
                @endauth
            @endif

            <span class="sr-only p-author h-card">
                @if ($avatar = $entry->user->meta->firstWhere('key', 'avatar'))
                    <img class="u-photo" src="{{ $avatar->value[0] }}" alt="{{ $entry->user->name }}">
                @endif

                <a href="{{ $entry->author_url }}" class="u-url p-name fn" rel="author">{{ $entry->user->name }}</a>
            </span>
        </header>
    @else
        {{-- One of the short-form entry formats. --}}
        <header class="entry-header">
            @if (is_singular())
                <h1 class="sr-only">{{ $entry->name }}</h1>
            @else
                <h2 class="sr-only"><a class="u-url" href="{{ $entry->permalink }}" rel="bookmark">{{ $entry->name }}</a></h2>
            @endif

            <span class="sr-only p-author h-card">
                @if ($avatar = $entry->user->meta->firstWhere('key', 'avatar'))
                    <img class="u-photo" src="{{ $avatar->value[0] }}" alt="{{ $entry->user->name }}">
                @endif

                <a href="{{ $entry->author_url }}" class="u-url p-name fn" rel="author">{{ $entry->user->name }}</a>
            </span>
        </header>
    @endif

    <div class="entry-content">
        @if (is_archive())
            <p class="p-summary">{{ $entry->summary }}</p>
            <a class="u-url" href="{{ $entry->permalink }}" rel="bookmark">{!! __('Continue reading :title →', ['title' => '<span class="sr-only">' . e($entry->name) . '</span>']) !!}</a>
        @else
            {{-- Only define `e-content` if it doesn't already exist. --}}
            @if (preg_match('~class=("|\')?e-content("|\')?~', $entry->content))
                {!! $entry->content !!}
            @else
                <div class="e-content">
                    {!! $entry->content !!}
                </div>
            @endif
        @endif
    </div>

    @if (! blank($entry->tags))
        <p class="entry-meta">
            {{ __('Tagged:') }}

            @foreach ($entry->tags as $tag)
                @if (! $loop->last)
                    <a class="p-category" href="{{ route('tags.show', $tag) }}" rel="tag">{{ $tag->name }}</a>,
                @else
                    <a class="p-category" href="{{ route('tags.show', $tag) }}" rel="tag">{{ $tag->name }}</a>
                @endif
            @endforeach
        </p>
    @endif

    @if (is_singular() && ! blank($entry->comments))
        <h2 id="comments">{{ __('Reactions') }}</h2>
        <ol class="comments">
            @foreach ($entry->comments as $comment)
                @include('theme::entries.partials.comment')
            @endforeach
        </ol>
    @endif
</article>
