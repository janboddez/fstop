<article class="h-entry {{ $entry->type }}{{ ! empty($entry->thumbnail) ? ' has-thumbnail' : '' }}">
    {{-- Show short-form entries' thumbnail also on singular pages. --}}
    @if (is_archive() && ! empty($entry->thumbnail))
        <div class="post-thumbnail">
            {{-- @todo: Make responsive. --}}
            <a class="u-url" href="{{ $entry->permalink }}" rel="bookmark"><img class="u-featured" src="{{ $entry->thumbnail }}" width="1600" height="720" alt="" loading="lazy"></a>
        </div>
    @elseif (in_array($entry->type, ['note', 'like'], true) && ! empty($entry->thumbnail))
        <div class="post-thumbnail">
            {{-- @todo: Make responsive. --}}
            <a href="{{ $entry->thumbnail }}"><img class="u-featured" src="{{ $entry->thumbnail }}" width="1600" height="720" alt="" loading="lazy"></a>
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
                    <a class="u-url" href="{{ $entry->permalink }}" rel="bookmark"><time class="dt-published" datetime="{{ $entry->created_at->format('c') }}">{{ $entry->created_at->format('M j, Y') }}</time></a>
                    @auth
                        |
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
        </header>
    @else
        {{-- One of the short-form entry formats. --}}
        <header class="entry-header">
            @if (is_singular())
                <h1 class="sr-only">{{ $entry->name }}</h1>
            @else
                <h2 class="sr-only"><a class="u-url" href="{{ $entry->permalink }}" rel="bookmark">{{ $entry->name }}</a></h2>
            @endif
        </header>
    @endif

    @if (is_archive() && in_array($entry->type, ['article', 'page'], true))
        <div class="p-summary">
            <p>
                @if (! empty($entry->summary))
                    {{ $entry->summary }}
                @else
                    {{ Str::limit(strip_tags($entry->content), 150, '…') }}
                @endif

                <a class="u-url" href="{{ $entry->permalink }}" rel="bookmark">{!! __('Continue reading :title', ['title' => '<span class="sr-only">' . $entry->name . '</span>']) !!} →</a>
            </p>
        </div>
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

    @if (! empty($entry->meta['card']['title']) && ! empty($entry->meta['card']['url']))
        {{-- Preview card, if any. --}}
        @include('theme::entries.partials.card')
    @endif

    @if (! in_array($entry->type, ['article', 'page'], true))
        {{-- Date, location or client info, and syndication links. --}}
        <footer class="entry-footer">
            <div class="entry-meta">
                <a class="u-url" href="{{ route(Str::plural($entry->type) . '.show', $entry->slug) }}" rel="bookmark"><time class="dt-published" datetime="{{ $entry->created_at->format('c') }}">{{ $entry->created_at->format('M j, Y') }}</time></a>
                @auth
                    |
                    <a href="{{ route('admin.entries.edit', $entry) }}">{{ __('Edit :type', ['type' => $entry->type]) }}</a>
                @endauth
            </div>
            <div class="client">
                @if (! empty($entry->meta['geo']['address']))
                    <small class="h-geo">
                        <span class="p-name">{{ $entry->meta['geo']['address'] }}</span>

                        @if (isset($entry->meta['weather']['temperature']))
                            @php
                                $temperature = $entry->meta['weather']['temperature'];
                                if ($temperature > 273.15) {
                                    $temperature -= 273.15;
                                }
                            @endphp
                            &bull; {{ round($temperature) }}&nbsp;&deg;C
                        @endif

                        @if (isset($entry->meta['weather']['description']))
                            &bull; {{ $entry->meta['weather']['description'] }}
                        @endif
                    </small>
                @elseif (! empty($entry->meta['client']))
                    <small>{{ $entry->meta['client'] }}</small>
                @endif
            </div>
            <div class="also-on">{!! (! blank($entry->syndication) ? __('Also on :syndication', ['syndication' => $entry->syndication]) : '') !!}</div>
        </footer>
    @endif

    @if (is_singular() && $entry->comments->count() > 0)
        {{-- Comments. --}}
        <h2 id="comments">{{ __('Reactions') }}</h2>
        <ol class="comments">
            @foreach ($entry->comments as $comment)
                @include('theme::entries.partials.comment')
            @endforeach
        </ol>
    @endif
</article>
