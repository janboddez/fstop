@extends('admin.layout')

@section('title', __('Followers'))

@section('content')
<header class="mt-4 mb-5 is-clearfix">
    <h1 class="title is-pulled-left mr-3">@yield('title')</h1>
</header>

@include('admin.partials.flash-message')

<div class="card">
    <table class="table is-fullwidth is-striped">
        <thead>
            <tr>
                <th>{{ __('Actor URL') }}</th>
                <th style="width: 25%;">{{ __('Since') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($followers as $follower)
                <tr>
                    <td>
                        <img src="{{ $follower->avatar ?? asset('images/no-image.png') }}" class="avatar" width="40" height="40" alt=""
                            style="width: 20px; height: 20px; vertical-align: middle; border-radius: 50%; position: relative; top: -1px;"
                            > {{ $follower->url }}
                    </td>
                    <td><time>{{ $follower->pivot->created_at->format('M j, Y') }}<br><small>{{ $follower->pivot->created_at->format('h:i A') }}</small></time></td>
                </tr>
            @empty
                <tr>
                    <td colspan="2" style="text-align: center;">{{ __('Nothing here, yet.') }}</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

{{ $followers->onEachSide(3)->links('admin.partials.pagination.bottom') }}
@stop
