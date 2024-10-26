<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>@yield(strip_tags('title')) &ndash; {{ config('app.name') }}</title>

    <link href="{{ url('css/bulma.min.css') }}?v=0.9.4" rel="stylesheet">
    <link href="{{ url('css/materialdesignicons.min.css') }}?v=6.9.96" rel="stylesheet">
    <link href="{{ url('css/admin.css') }}?v=0.1.0" rel="stylesheet">
</head>
<body data-instant-allow-query-string>
    <div class="columns is-desktop is-gapless my-0">
        <div class="column is-2-desktop">
            <nav id="menu">
                <div class="navbar-brand py-1">
                    <div class="field is-grouped is-grouped-multiline mx-3 mt-3">
                        <div class="control">
                            <a class="button is-link" href="{{ url('admin') }}">{{ config('app.name') }}</a>
                        </div>
                        <div class="control">
                            <a class="button is-link" href="{{ url('/') }}" target="_blank" rel="noopener noreferrer">{{ __('Visit Site') }}</a>
                        </div>
                    </div>

                    <button class="navbar-burger is-align-self-center" aria-expanded="false" data-target="nav-menu">
                        <span aria-hidden="true"></span>
                        <span aria-hidden="true"></span>
                        <span aria-hidden="true"></span>
                    </button>
                </div>

                <div class="navbar-menu" id="nav-menu">
                    {{-- Rather than include this partial directly, we call an action that allows it to be filtered and then echoes the outcome. --}}
                    @action('admin.partials.menu')
                </div>
            </nav>
        </div>

        <div class="column is-10-desktop">
            <main class="px-5 pt-4 pb-5">
                @section('content')
                @show
            </main>
        </div>
    </div>

    <script>
    document.querySelectorAll('.navbar-burger').forEach(function (el) {
        el.addEventListener('click', function (event) {
            var target = document.getElementById(el.dataset.target);

            el.classList.toggle('is-active');
            el.setAttribute('aria-expanded', el.getAttribute('aria-expanded') === 'true' ? 'false' : 'true');

            target.classList.toggle('is-active');
        });
    });

    // Prevent accidental logout.
    document.querySelectorAll('a.logout').forEach(function (el) {
        el.addEventListener('click', function (event) {
            if (! confirm('{{ __('Are you sure?') }}')) {
                // Cancel.
                event.preventDefault();
            }
        });
    });

    // Hide notifications. (Or rather, remove them from the DOM.)
    document.querySelectorAll('.notification .delete').forEach(function (el) {
        el.addEventListener('click', function (event) {
            event.target.parentNode.remove();
        });
    });
    </script>

    @section('scripts')
    @show

    <script src="{{ url('js/instantpage-5.1.1.js') }}?v=5.1.1" type="module" defer></script>
</body>
</html>
