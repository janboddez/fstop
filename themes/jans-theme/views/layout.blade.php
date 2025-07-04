<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    @hasSection('title')
        <title>@yield(strip_tags('title')) &ndash; {{ site_name() }}</title>
    @else
        <title>{{ site_name() }}</title>
    @endif

    <link rel="icon" href="{{ asset('vendor/jans-theme/favicon/favicon-192x192.png') }}" sizes="32x32">
    <link rel="icon" href="{{ asset('vendor/jans-theme/favicon/favicon-512x512.png') }}" sizes="192x192">
    <link rel="apple-touch-icon" href="{{ asset('vendor/jans-theme/favicon/favicon-512x512.png') }}">
    <meta name="msapplication-TileImage" content="{{ asset('vendor/jans-theme/favicon/favicon-512x512.png') }}">

    @if (request()->is('articles') || request()->is('users/*'))
        {{-- Copies of the homepage and stream, respectively. --}}
        <meta name="robots" content="noindex">
    @endif

    <link rel="webmention" href="/webmention">
    <link rel="authorization_endpoint" href="/indieauth">
    <link rel="token_endpoint" href="/indieauth/token">
    <link rel="micropub" href="/micropub">
    <link rel="micropub_media" href="/micropub/media">

    <link rel="stylesheet" href="/css/normalize.css?v={{ config('app.version') }}">
    <link rel="stylesheet" href="/css/highlight.css?v={{ config('app.version') }}" media="screen">
    <link rel="stylesheet" href="/css/fonts.css?v={{ config('app.version') }}">

    {{-- Echo additional stylesheets and whatnot. --}}
    @action('theme:layout:head')
</head>

<body class="{{ body_class() }}" data-instant-allow-query-string>
    <header class="site-header">
        <div class="container">

            @if (request()->is('/'))
                <h1 class="site-branding">
                    <a href="/">{{ site_name() }}</a>
                </h1>
            @else
                <div class="site-branding">
                    <a href="/">{{ site_name() }}</a>
                </div>
            @endif

            <nav class="main-navigation">
                <ul>
                    <li{!! request()->is('/') && request()->input('page') < 2 ? ' class="active"' : '' !!}><a href="/">{{ __('Home') }}</a></li>
                    {{-- <li{!! request()->is('articles*') ? ' class="active"' : '' !!}><a href="/articles">{{ __('Articles') }}</a></li> --}}
                    <li{!! request()->is('about') ? ' class="active"' : '' !!}><a href="/about">{{ __('About') }}</a></li>
                    <li{!! request()->is('notes*') ? ' class="active"' : '' !!}><a href="/notes">{{ __('Notes') }}</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <main id="content">
        <div class="container">
            @section('content')
            @show
        </div>
    </main>

    <aside class="sidebar">
        <div class="container">
            <div class="column">
                <h2>{{ __('Browse by Content Type') }}</h2>
                <nav>
                    <ul>
                        <li><a href="/">{{ __('Articles') }}</a></li>
                        <li><a href="/notes">{{ __('Notes') }}</a></li>
                        <li><a href="/likes">{{ __('Likes') }}</a></li>
                        <li><a href="/stream">{{ __('“Stream”') }}</a></li>
                    </ul>
                </nav>
            </div>

            <div class="column">
                <h2>{{ __('Miscellaneous') }}</h2>
                <nav>
                    <ul>
                        <li><a href="/about">{{ __('About') }}</a></li>
                        <li><a href="/now">{{ __('/now') }}</a></li>
                        <li><a href="/uses">{{ __('/uses') }}</a></li>
                    </ul>
                </nav>
            </div>

            <div class="column">
                <form action="/search" method="GET">
                    <h2><label for="s">{{ __('Search This Site') }}</label></h2>
                    <input type="text" name="s" id="s">
                    <button type="submit" id="search-submit">{{ __('Search') }}</button>
                </form>
            </div>
        </div>
    </aside>

    <footer class="site-footer">
        <div class="container">
            <ul class="social-links">
                <li><a href="/feed"><svg class="icon icon-rss" aria-hidden="true" role="img" width="32" height="32"><use href="#icon-rss"></use></svg> <span class="sr-only">{{ __('RSS Feed') }}</span></a></li>
                <li><a href="https://indieweb.social/@janboddez" rel="me"><svg class="icon icon-mastodon" aria-hidden="true" role="img" width="32" height="32"><use href="#icon-mastodon"></use></svg> <span class="sr-only">{{ __('Mastodon') }}</span></a></li>
                <li><a href="https://pixelfed.social/janboddez" rel="me"><svg class="icon icon-pixelfed" aria-hidden="true" role="img" width="32" height="32"><use href="#icon-pixelfed"></use></svg> <span class="sr-only">{{ __('Pixelfed') }}</span></a></li>
                <li><a href="https://github.com/janboddez" rel="me"><svg class="icon icon-github" aria-hidden="true" role="img" width="32" height="32"><use href="#icon-github"></use></svg> <span class="sr-only">{{ __('Github') }}</span></a></li>
            </ul>
        </div>
    </footer>

    <svg style="position: absolute; width: 0; height: 0; overflow: hidden;" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink">
        <defs>
            <symbol id="icon-link" viewbox="0 0 24 24">
                <path d="M20.03 16.941c0-.334-.13-.643-.36-.875l-2.677-2.676a1.248 1.248 0 0 0-1.802.051c.425.425.927.785.927 1.441 0 .682-.554 1.236-1.236 1.236-.656 0-1.016-.502-1.44-.927-.271.258-.425.553-.425.94 0 .321.128.643.36.875l2.65 2.663c.232.232.554.348.876.348.321 0 .643-.116.875-.335l1.891-1.879c.232-.231.36-.54.36-.862zM10.982 7.87c0-.321-.128-.643-.36-.875l-2.65-2.663a1.248 1.248 0 0 0-.876-.36c-.321 0-.643.128-.875.347L4.331 6.197a1.226 1.226 0 0 0 0 1.737l2.676 2.676c.232.232.554.348.875.348.374 0 .67-.129.927-.4-.425-.424-.927-.784-.927-1.44 0-.682.554-1.236 1.236-1.236.656 0 1.016.502 1.44.927.271-.258.425-.553.425-.94zM22.5 16.941c0 .978-.399 1.93-1.094 2.612l-1.891 1.879a3.679 3.679 0 0 1-2.612 1.068c-.991 0-1.93-.386-2.625-1.094l-2.651-2.663a3.679 3.679 0 0 1-1.068-2.612c0-1.017.412-1.982 1.132-2.69L10.56 12.31a3.738 3.738 0 0 1-2.677 1.132c-.978 0-1.93-.386-2.625-1.08L2.581 9.683A3.667 3.667 0 0 1 1.5 7.059c0-.978.399-1.93 1.094-2.612l1.891-1.879A3.679 3.679 0 0 1 7.097 1.5c.991 0 1.93.386 2.625 1.094l2.651 2.663a3.679 3.679 0 0 1 1.068 2.612 3.767 3.767 0 0 1-1.132 2.69l1.132 1.132a3.738 3.738 0 0 1 2.677-1.132c.978 0 1.93.386 2.625 1.08l2.676 2.677a3.667 3.667 0 0 1 1.081 2.625z" />
            </symbol>
            <symbol id="icon-github" viewbox="0 0 24 24">
                <path d="M12 2.492c5.52 0 10 4.479 10 10 0 4.414-2.865 8.164-6.836 9.492-.508.09-.69-.221-.69-.482 0-.325.013-1.406.013-2.747 0-.938-.313-1.537-.677-1.85 2.226-.247 4.57-1.093 4.57-4.934 0-1.094-.39-1.98-1.028-2.682.104-.26.442-1.276-.105-2.657-.833-.26-2.747 1.029-2.747 1.029a9.417 9.417 0 0 0-5 0S7.586 6.37 6.753 6.632c-.547 1.38-.209 2.396-.105 2.657A3.873 3.873 0 0 0 5.62 11.97c0 3.828 2.33 4.687 4.557 4.935-.286.26-.547.703-.638 1.34-.573.261-2.031.704-2.904-.832-.546-.951-1.536-1.03-1.536-1.03-.977-.012-.065.613-.065.613.65.3 1.107 1.458 1.107 1.458.586 1.784 3.372 1.185 3.372 1.185 0 .833.013 1.615.013 1.862 0 .26-.182.573-.69.482C4.865 20.656 2 16.906 2 12.492c0-5.521 4.48-10 10-10zM5.79 16.854c.025-.052-.014-.118-.092-.157-.078-.026-.143-.013-.17.026-.025.052.014.118.092.157.065.039.143.026.17-.026zm.403.442c.052-.039.039-.13-.026-.208-.065-.065-.157-.091-.209-.039-.052.039-.039.13.026.208.065.065.157.091.209.04zm.39.586c.065-.052.065-.156 0-.247-.052-.091-.156-.13-.221-.078-.065.039-.065.143 0 .234.065.091.17.13.221.091zm.547.547c.052-.052.026-.17-.052-.247-.091-.091-.208-.104-.26-.04-.065.053-.04.17.052.248.09.091.208.104.26.04zm.742.326c.026-.078-.052-.17-.169-.209-.104-.026-.221.013-.247.091-.026.078.052.17.169.196.104.039.221 0 .247-.078zm.82.065c0-.091-.103-.157-.22-.144-.118 0-.209.065-.209.144 0 .09.091.156.221.143.118 0 .209-.065.209-.143zm.756-.13c-.013-.078-.117-.13-.234-.118-.118.026-.196.104-.183.196.013.078.117.13.235.104.117-.026.195-.104.182-.182z" />
            </symbol>
            <symbol id="icon-mastodon" viewbox="0 0 24 24">
                <path d="M21.377 14.59c-.288 1.48-2.579 3.102-5.21 3.416-1.372.164-2.723.314-4.163.248-2.356-.108-4.215-.562-4.215-.562 0 .23.014.448.042.652.306 2.325 2.306 2.464 4.2 2.529 1.91.065 3.612-.471 3.612-.471l.079 1.728s-1.337.718-3.718.85c-1.314.072-2.944-.033-4.844-.536-4.119-1.09-4.824-5.481-4.935-9.936-.033-1.323-.013-2.57-.013-3.613 0-4.556 2.985-5.891 2.985-5.891C6.702 2.313 9.284 2.022 11.969 2h.066c2.685.022 5.269.313 6.774 1.004 0 0 2.984 1.335 2.984 5.89 0 0 .038 3.362-.416 5.695zm-3.104-5.342c0-1.127-.277-2.032-.864-2.686-.594-.663-1.373-1.002-2.34-1.002-1.118 0-1.965.43-2.525 1.29L12 7.761l-.544-.913c-.56-.86-1.407-1.29-2.525-1.29-.967 0-1.746.34-2.34 1.003-.577.663-.864 1.559-.864 2.686v5.516h2.186V9.41c0-1.128.474-1.701 1.424-1.701 1.05 0 1.577.68 1.577 2.023v2.93h2.172v-2.93c0-1.344.527-2.023 1.577-2.023.95 0 1.424.573 1.424 1.701v5.354h2.186V9.248z" />
            </symbol>
            <symbol id="icon-pixelfed" viewbox="0 0 24 24">
                <path d="M12 22C6.477 22 2 17.523 2 12S6.477 2 12 2s10 4.477 10 10-4.477 10-10 10zm-.794-7.817h1.835c1.728 0 3.129-1.364 3.129-3.046 0-1.682-1.401-3.046-3.13-3.046h-2.647c-.997 0-1.805.787-1.805 1.757v6.84z" />
            </symbol>
            <symbol id="icon-rss" viewbox="0 0 24 24">
                <path d="M7.909 18.545a2.455 2.455 0 1 1-4.91 0 2.455 2.455 0 0 1 4.91 0zm6.545 1.573a.827.827 0 0 1-.218.613.778.778 0 0 1-.6.269H11.91a.807.807 0 0 1-.806-.742 8.18 8.18 0 0 0-7.363-7.363.808.808 0 0 1-.741-.806v-1.725c0-.23.09-.448.268-.601a.784.784 0 0 1 .55-.218h.064a11.481 11.481 0 0 1 7.222 3.35 11.482 11.482 0 0 1 3.35 7.223zm6.545.025a.779.779 0 0 1-.23.601.783.783 0 0 1-.588.256h-1.828a.814.814 0 0 1-.819-.767c-.421-7.428-6.34-13.347-13.767-13.781A.812.812 0 0 1 3 5.646V3.818c0-.23.09-.435.256-.588A.793.793 0 0 1 3.818 3h.038c4.475.23 8.68 2.11 11.85 5.292A18 18 0 0 1 21 20.143z" />
            </symbol>
            <symbol id="icon-bookmark" viewBox="0 0 24 24">
                <path d="M8.1 5a2 2 0 0 0-2 2v12.1L12 15l5.9 4.1V7a2 2 0 0 0-2-2H8.1z"/>
            </symbol>
            <symbol id="icon-like" viewBox="0 0 24 24">
                <path d="M7.785 5.49a4.536 4.536 0 0 0-4.535 4.537C3.25 14.564 9 17.25 12 19.75c3-2.5 8.75-5.186 8.75-9.723a4.536 4.536 0 0 0-4.535-4.537c-1.881 0-3.54 1.128-4.215 2.76-.675-1.632-2.334-2.76-4.215-2.76z"/>
            </symbol>
            <symbol id="icon-repost" viewBox="0 0 24 24">
                <path d="M7.25 6a2 2 0 0 0-2 2v6.1l-3-.1 4 4 4-4-3 .1V8h6.25l2-2zM16.75 9.9l-3 .1 4-4 4 4-3-.1V16a2 2 0 0 1-2 2H8.5l2-2h6.25z"/>
            </symbol>
        </defs>
    </svg>

    <script src="{{ url('js/blurhash_pure_js_port.min.js') }}?v={{ config('app.version') }}"></script>
    <script src="{{ url('js/blurhash.js') }}?v={{ config('app.version') }}"></script>

    <script src="{{ url('js/highlight.pack.js') }}"></script>
    <script>hljs.initHighlightingOnLoad();</script>

    <script src="{{ url('js/floating-ui/core.js') }}?v=1.6.9"></script>
    <script src="{{ url('js/floating-ui/dom.js') }}?v=1.6.13"></script>
    <script src="{{ url('js/popovers.js') }}?v={{ config('app.version') }}"></script>

    <script src="{{ url('js/instantpage.js') }}?v=5.2.0" type="module" defer></script>
</body>
</html>
