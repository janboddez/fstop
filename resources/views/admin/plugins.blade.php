@extends('admin.layout')

@section('title', __('Plugins'))

@section('content')
<header class="mt-4 mb-5 is-clearfix">
    <h1 class="title is-pulled-left mr-3">@yield('title')</h1>
</header>

@include('admin.partials.flash-message')

<form action="{{ route('admin.plugins.update') }}" method="POST">
    @method('PUT')
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
                @forelse ($plugins as $slug => $attributes)
                    <tr>
                        <td style="width: 2.5%;"><input type="checkbox" name="plugins[]" id="plugin_{{ $slug }}" value="{{ $slug }}"{{ $attributes['active'] || in_array($slug, old('plugins', []), true) ? ' checked' : '' }}></td>
                        <td style="width: 20%;"><label for="plugin_{{ $slug }}">{{ Str::headline($slug) }}</label></td>
                        <td>{{ $attributes['description'] ?? __('—') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3">{{ __('No plugins found.') }}</td>
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
