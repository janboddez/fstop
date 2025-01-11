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

                    @if (($shortUrl = $entry->meta->firstWhere('key', 'short_url')) && ($parts = parse_url($shortUrl->value[0])) !== false)
                        &bull;
                        <a href="{{ $shortUrl->value[0] }}">{{ $parts['host'] . $parts['path'] }}</a>
                    @endif

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
        @if (is_archive() && in_array($entry->type, ['article', 'page'], true))
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

    @if (! in_array($entry->type, ['article', 'page'], true))
        {{-- Date, location or client info, and syndication links. --}}
        <footer class="entry-footer">
            <div class="entry-meta">
                <a class="u-url" href="{{ route(Str::plural($entry->type) . '.show', $entry->slug) }}" rel="bookmark"><time class="dt-published" datetime="{{ $entry->created_at->format('c') }}">{{ $entry->created_at->format('M j, Y') }}</time></a>

                @if (($shortUrl = $entry->meta->firstWhere('key', 'short_url')) && ($parts = parse_url($shortUrl->value[0])) !== false)
                    &bull;
                    <a href="{{ $shortUrl->value[0] }}">{{ $parts['host'] . $parts['path'] }}</a>
                @endif

                @auth
                    &bull;
                    <a href="{{ route('admin.entries.edit', $entry) }}">{{ __('Edit :type', ['type' => $entry->type]) }}</a>
                @endauth
            </div>

            <div class="client">
                @if (($geo = $entry->meta->firstWhere('key', 'geo')) && ! empty($geo->value['address']))
                    <span class="h-geo">
                        <span class="p-name">{{ $geo->value['address'] }}</span>

                        @if (($weather = $entry->meta->firstWhere('key', 'weather')) && isset($weather->value['temperature']))
                            @php
                                $temperature = $weather->value['temperature'];
                                if ($temperature > 100) {
                                    // Anything above 100 must be in Kelvin.
                                    $temperature -= 273.15;
                                }
                            @endphp
                            &bull; {{ round($temperature) }}&nbsp;&deg;C
                        @endif

                        @if (isset($weather->value['description']))
                            &bull; {{ $weather->value['description'] }}
                        @endif
                    </span>
                @elseif (($client = $entry->meta->firstWhere('key', 'client')) && ! empty($client->value[0]))
                    <span>{{ $client->value[0] }}</span>
                @endif
            </div>

            <div class="also-on">{!! (! blank($entry->syndication) ? __('Also on :syndication', ['syndication' => $entry->syndication]) : '') !!}</div>
        </footer>

        @if (($card = $entry->meta->firstWhere('key', 'preview_card')) && ! empty($card->value['url']))
            <a class="card{{ (! empty($card->value['thumbnail']) ? ' has-thumbnail' : '' ) }}" href="{{ $card->value['url'] }}" target="_blank" rel="nofollow noopener noreferrer">
                <div class="thumbnail">
                    @if (! empty($card->value['thumbnail']))
                        <img src="{{ $card->value['thumbnail'] }}" width="100" height="100" alt="">
                    @endif
                </div>

                <dl class="summary">
                    @if (! empty($card->value['title']))
                        <dt>{!! \Michelf\SmartyPants::defaultTransform(Str::limit($card->value['title'], 120, '&hellip;'), \Michelf\SmartyPants::ATTR_LONG_EM_DASH_SHORT_EN) !!}</dt>
                    @endif

                    <dd><svg class="icon icon-link" aria-hidden="true" role="img" width="14" height="14"><use href="#icon-link"></use></svg> {{ parse_url($card->value['url'] , PHP_URL_HOST) }}</dd>
                </dl>
            </a>
        @endif
    @endif

    @if (is_singular() && ! blank($entry->likes))
        <h2>{{ __('Likes') }}</h2>
        <ol class="comments">
            @foreach ($entry->likes as $comment)
                @include('theme::entries.partials.comment')
            @endforeach
        </ol>
    @endif

    @if (is_singular() && ! blank($entry->reposts))
        <h2>{{ __('Reposts') }}</h2>
        <ol class="comments">
            @foreach ($entry->reposts as $comment)
                @include('theme::entries.partials.comment')
            @endforeach
        </ol>
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
