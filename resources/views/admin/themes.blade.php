@extends('admin.layout')

@section('title', __('Themes'))

@section('content')
<header class="mt-4 mb-5 is-clearfix">
    <h1 class="title is-pulled-left mr-3">@yield('title')</h1>
</header>

@include('admin.partials.flash-message')

<div class="mx-1 my-3 is-clearfix">
    {{ __('Themes allow developers to override certain front-end templates, and publish assets (like CSS and JavaScript) of their own.') }}
</div>

<form action="{{ route('admin.themes.index') }}" method="POST">
    @csrf

    <div class="card">
        <table class="table is-fullwidth is-striped">
            <thead>
                <tr>
                    <th style="width: 2.5%;"><span class="is-hidden">{{ __('Enabled?') }}</span></th>
                    <th style="width: 20%;">{{ __('Name') }}</th>
                    <th>{{ __('Description') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($themes as $theme => $attributes)
                    <tr>
                        <td style="width: 2.5%;"><input type="radio" name="theme" id="theme_{{ $theme }}" value="{{ $theme }}"{{ old('theme_'.$theme, $attributes['active']) ? ' checked' : '' }}></td>
                        <td style="width: 20%;"><label for="theme_{{ $theme }}">{{ $theme }}</label></td>
                        <td>{{ $attributes['description'] ?? __('—') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3">{{ __('No themes found.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="field my-3">
        <div class="control">
            <button type="submit" class="button is-success">{{ __('Save Changes') }}</button>
        </div>
    </div>
</form>
@stop
