@extends('admin.layout')

@section('title', __('Comments'))

@section('content')
<header class="mt-4 mb-5 is-clearfix">
    <h1 class="title is-pulled-left mr-3">@yield('title')</h1>

    <form class="is-pulled-right is-hidden-mobile" action="{{ url()->current() }}" method="get">
        <div class="field has-addons" style="margin-bottom: -1rem;">
            <div class="control">
                <input class="input" type="text" name="s" id="s" value="{{ request()->input('s') }}">
            </div>
            <div class="control">
                <button class="button" type="submit">{{ __('Search') }}</button>
            </div>
        </div>
    </form>
</header>

@include('admin.partials.flash-message')

<div class="mx-1 my-3 is-clearfix">
    <a{!! ! request()->input('pending') && ! request()->input('s') && ! request()->input('entry') ? ' class="is-active"' : '' !!} href="{{ route('admin.comments.index') }}">{{ __('Approved (:count)', ['count' => $approved]) }}</a>
    |
    <a{!! request()->input('pending') ? ' class="is-active"' : '' !!} href="{{ route('admin.comments.index', ['pending' => true]) }}">{{ __('Pending (:count)', ['count' => $pending]) }}</a>

    {{ $comments->links('admin.partials.pagination.top') }}
</div>

<div class="card">
    <table class="table is-fullwidth is-striped">
        <thead>
            <tr>
                <th style="width: 20%;">{{ __('Author') }}</th>
                <th>{{ __('Comment') }}</th>
                <th class="is-hidden-mobile" style="width: 10%;">{{ __('Status') }}</th>
                <th class="column-date" style="width: 25%;">{{ __('Date') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($comments as $comment)
                <tr>
                    <td>
                        <a href="{{ route('admin.comments.edit', $comment) }}">{{ $comment->author }}</a>
                        <br>
                        <small>
                            @if ($comment->status === 'pending')
                                <form class="is-inline" action="{{ route('admin.comments.approve', $comment) }}" method="post">
                                    @csrf
                                    <button class="button is-ghost" type="submit">{{ __('Approve') }}</button>
                                </form>
                            @else
                                <form class="is-inline" action="{{ route('admin.comments.unapprove', $comment) }}" method="post">
                                    @csrf
                                    <button class="button is-ghost" type="submit">{{ __('Unapprove') }}</button>
                                </form>
                            @endif
                            |
                            <a href="{{ route('admin.comments.edit', $comment) }}">{{ __('Edit') }}</a>
                            |
                            <form class="is-inline" action="{{ route('admin.comments.destroy', $comment) }}" method="post">
                                @method('DELETE')
                                @csrf
                                <button class="button is-ghost" type="submit">{{ __('Permanently delete') }}</button>
                            </form>
                        </small>
                    </td>
                    <td>{!! Str::words(strip_tags($comment->content), 10, ' […]') !!}</td>
                    <td class="is-hidden-mobile">{{ ucfirst($comment->status) }}</td>
                    <td><time>{{ $comment->created_at->format('M j, Y') }}<br><small>{{ $comment->created_at->format('h:i A') }}</small></time></td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" style="text-align: center;">{{ __('Nothing here, yet.') }}</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

{{ $comments->onEachSide(3)->links('admin.partials.pagination.bottom') }}
@stop
