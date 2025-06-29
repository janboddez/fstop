<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('Log In') }} &ndash; {{ config('app.name') }}</title>

    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <meta name="robots" content="noindex">

    <link rel="stylesheet" href="{{ url('css/bulma.min.css') }}?v=0.9.4">
    <link rel="stylesheet" href="{{ url('css/materialdesignicons.min.css') }}?v=6.9.96">
    <link rel="stylesheet" href="{{ url('css/fonts.css') }}?v=0.1.0">

    <style>
    html {
        overflow-y: hidden;
    }
    body,
    button,
    input,
    select,
    textarea {
        font-family: "Inter", sans-serif;
    }
    a {
        text-decoration: underline;
    }
    .box {
        border-radius: 0;
    }
    .input,
    .textarea {
        border-radius: 0 !important;
    }
    </style>
</head>
<body>
    <div class="hero is-primary is-fullheight">
        <div class="hero-body">
            <div class="container">
                <div class="columns is-centered">
                    <div class="column is-3">
                        <form action="{{ route('login') }}" method="post" class="box">
                            @csrf

                            <div class="field">
                                <label for="email" class="label">{{ __('Email Address') }}</label>
                                <div class="control has-icons-left">
                                    <input class="input" type="email" name="email" id="email" required>
                                    <span class="icon is-small is-left">
                                        <i class="mdi mdi-email"></i>
                                    </span>
                                </div>
                            </div>

                            <div class="field">
                                <label for="password" class="label">{{ __('Password') }}</label>
                                <div class="control has-icons-left">
                                    <input class="input" type="password" name="password" id="password" required>
                                    <span class="icon is-small is-left">
                                        <i class="mdi mdi-lock"></i>
                                    </span>
                                </div>
                            </div>

                            <div class="field mt-5">
                                <button type="submit" class="button is-success">{{ __('Log In') }}</button>
                            </div>
                        </form>

                        <p class="has-text-centered has-text-dark">
                            <a href="{{ url('/') }}" class="mt-5">{{ __('Return to :site_name', ['site_name' => site_name()]) }}</a>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
