@extends('admin.layout')

@section('title', __('Profile'))

@section('content')
<header class="mt-4 mb-5 is-clearfix">
    <h1 class="title is-pulled-left mr-3">@yield('title')</h1>
</header>

@include('admin.partials.flash-message')

<form action="{{ route('admin.profile.update') }}" method="POST">
    @method('PUT')
    @csrf

    <div class="card">
        <table class="table is-fullwidth is-striped">
            <thead>
                <tr>
                    <th style="width: 20%;">{{ __('Name') }}</th>
                    <th>{{ __('Value') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($settings as $key => $value)
                    <tr>
                        <td style="width: 20%;"><label for="{{ $key }}">{{ Str::headline($key) }}</label></td>
                        <td>
                            <div class="field">
                                <div class="control">
                                    <textarea class="input{{ $errors->has($key) ? ' is-danger': '' }}" id="{{ $key }}" name="{{ $key }}">{{ old($key, $value ?? '') }}</textarea>
                                </div>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="2">{{ __('Nothing here.') }}</td>
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
