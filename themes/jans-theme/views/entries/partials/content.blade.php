<article class="h-entry {{ $entry->type }}{{ ! empty($entry->thumbnail) ? ' has-thumbnail' : '' }}">
    {{-- Show short-form entries' thumbnail also on singular pages. --}}
    @if (is_archive() && ! empty($entry->thumbnail))
        <div class="post-thumbnail">
            {{-- @todo: Make responsive. --}}
            <a class="u-url" href="{{ $entry->permalink }}" rel="bookmark">{!! $entry->thumbnail_html !!}</a>
        </div>
    @elseif (in_array($entry->type, ['note', 'like'], true) && ! empty($entry->thumbnail))
        <div class="post-thumbnail">
            {{-- @todo: Make responsive. --}}
            <a href="{{ $entry->thumbnail }}">{!! $entry->thumbnail_html !!}</a>
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
                    <a class="u-url" href="{{ $entry->permalink }}" rel="bookmark"><time class="dt-published" datetime="{{ $entry->published->format('c') }}">{{ $entry->published->format('M j, Y') }}</time></a>

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
                <a class="u-url" href="{{ route(Str::plural($entry->type) . '.show', $entry->slug) }}" rel="bookmark"><time class="dt-published" datetime="{{ $entry->published->format('c') }}">{{ $entry->published->format('M j, Y') }}</time></a>

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

    @if (is_singular())
        @if  (! blank($entry->likes) || ! blank($entry->reposts) || ! blank($entry->bookmarks))
            <div class="reactions">
                @if (! blank($entry->likes))
                    <p><button class="link" popovertarget="likes"><svg class="icon icon-like" aria-hidden="true" role="img" width="18" height="18" style="position: relative; top: 0.125rem;"><use href="#icon-like"></use></svg> {{ $entry->likes->count() }}<span class="sr-only"> {{ __('Likes') }}</span></button></p>
                    <ul id="likes" popover>
                        @foreach ($entry->likes as $reaction)
                            <li class="h-cite p-like">
                                @if (! empty($reaction->author_url))
                                    <a href="{{ $reaction->author_url }}" target="_blank" rel="noopener noreferrer"><span class="h-card p-author"><img src="{{ $reaction->avatar ?? asset('images/no-image.png') }}" width="40" height="40" alt="{{ $reaction->author ?? '' }}"></span></a>
                                @else
                                    <span class="h-card p-author"><img src="{{ $reaction->avatar ?? asset('images/no-image.png') }}" width="40" height="40" alt="{{ $reaction->author ?? '' }}"></span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif

                @if (! blank($entry->reposts))
                    <p><button class="link" popovertarget="reposts"><svg class="icon icon-repost" aria-hidden="true" role="img" width="18" height="18" style="position: relative; top: 0.125rem;"><use href="#icon-repost"></use></svg> {{ $entry->reposts->count() }}<span class="sr-only"> {{ __('Reposts') }}</span></button></p>
                    <ul id="reposts" popover>
                        @foreach ($entry->reposts as $reaction)
                            <li class="h-cite p-repost">
                                @if (! empty($reaction->author_url))
                                    <a href="{{ $reaction->author_url }}" target="_blank" rel="noopener noreferrer"><span class="h-card p-author"><img src="{{ $reaction->avatar ?? asset('images/no-image.png') }}" width="40" height="40" alt="{{ $reaction->author ?? '' }}"></span></a>
                                @else
                                    <span class="h-card p-author"><img src="{{ $reaction->avatar ?? asset('images/no-image.png') }}" width="40" height="40" alt="{{ $reaction->author ?? '' }}"></span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif

                @if (! blank($entry->bookmarks))
                    <p><button class="link" popovertarget="bookmarks"><svg class="icon icon-bookmark" aria-hidden="true" role="img" width="18" height="18" style="position: relative; top: 0.125rem;"><use href="#icon-bookmark"></use></svg> {{ $entry->bookmarks->count() }}<span class="sr-only"> {{ __('Likes') }}</span></button></p>
                    <ul id="bookmarks" popover>
                        @foreach ($entry->bookmarks as $reaction)
                            <li class="h-cite p-bookmark">
                                @if (! empty($reaction->author_url))
                                    <a href="{{ $reaction->author_url }}" target="_blank" rel="noopener noreferrer"><span class="h-card p-author"><img src="{{ $reaction->avatar ?? asset('images/no-image.png') }}" width="40" height="40" alt="{{ $reaction->author ?? '' }}"></span></a>
                                @else
                                    <span class="h-card p-author"><img src="{{ $reaction->avatar ?? asset('images/no-image.png') }}" width="40" height="40" alt="{{ $reaction->author ?? '' }}"></span>
                                @endif

                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        @endif

        @if (! blank($entry->comments))
            @php
                $comments = $entry->comments->groupBy('parent_id');
            @endphp
            <h2 id="comments">{{ __('Comments') }}</h2>
            <ol class="comments">
                @foreach ($comments as $parent)
                    @foreach ($parent as $comment)
                        @include('theme::entries.partials.comment')
                    @endforeach
                @endforeach
            </ol>
        @endif
    @endif
</article>
