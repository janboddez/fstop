@extends('admin.layout')

@section('title', __('Dashboard'))

@section('content')
<header class="mt-4 mb-5">
    <h1 class="title">@yield('title')</h1>
</header>

<div class="mx-1 my-3 is-clearfix">
    <a href="{{ route('admin.entries.index', ['type' => 'article']) }}">{{ trans_choice('{0} :count articles|{1} :count article|[2,*] :count articles', $articles_count) }}</a>
    |
    <a href="{{ route('admin.entries.index', ['type' => 'page']) }}">{{ trans_choice('{0} :count pages|{1} :count page|[2,*] :count pages', $pages_count) }}</a>
    |
    <a href="{{ route('admin.comments.index', ['pending' => true]) }}">{{ trans_choice('{0} :count pending comments|{1} :count pending comment|[2,*] :count pending comments', $comments_count) }}</a>
</div>

<div class="columns">
    <div class="column is-4">
        <div class="card mb-5">
            <table class="table is-fullwidth is-striped">
                <thead>
                    <tr>
                        <th>{{ __('Awaiting Moderation') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($pending as $comment)
                        <tr>
                            <td>
                                <a href="{{ route('admin.comments.edit', $comment) }}">{{ $comment->author }} &ndash; {!! Str::words(strip_tags($comment->content), 10, ' […]') !!}</a>

                                @if (! blank($comment->entry))
                                    <br>
                                    <small class="is-block mt-2">On <a href="{{ route('admin.entries.edit', $comment->entry) }}">{!! $comment->entry->name !!}</a></small>
                                @endif

                                <small class="is-block mt-2">
                                    @if ($comment->status === 'pending')
                                        <form class="is-inline" action="{{ route('admin.comments.approve', $comment) }}" method="post">
                                            @csrf
                                            <button class="button is-ghost on-hover-success" type="submit">{{ __('Approve') }}</button>
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
                                        <button class="button is-ghost on-hover-danger" type="submit">{{ __('Delete') }}</button>
                                    </form>
                                </small>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td>{{ __('Nothing here, yet.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="card">
            <table class="table is-fullwidth is-striped">
                <thead>
                    <tr>
                        <th colspan="2">{{ __('Recently Sent Webmentions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($webmentions as $webmention)
                        @forelse ($webmention->meta['webmention'] as $data)
                            <tr>
                                <td style="width: 67%;"><a href="{{ route('admin.entries.edit', $webmention) }}">{{ preg_replace('~^https?://~', '', $data['endpoint']) }}</a></td>
                                <td class="has-text-right-desktop"><time{!! ! empty($data['status']) ? ' title="' . $data['status'] . '"' : '' !!}>{{ Carbon\Carbon::parse($data['sent'])->format('M j, Y') }}</td>
                            </tr>
                        @empty
                        @endforelse
                    @empty
                        <tr>
                            <td colspan="2">{{ __('Nothing here, yet.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="column is-4">
        <div class="card mb-5">
            <table class="table is-fullwidth is-striped">
                <thead>
                    <tr>
                        <th colspan="2">{{ __('Recent Drafts') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($drafts as $draft)
                        <tr>
                            <td style="width: 67%;"><a href="{{ route('admin.entries.edit', $draft) }}">{{ $draft->name }}</a></td>
                            <td class="has-text-right-desktop"><time>{{ $draft->created_at->format('M j, Y') }}</time></td>
                        </li>
                    @empty
                        <tr>
                            <td colspan="2">{{ __('Nothing here, yet.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="card">
            <table class="table is-fullwidth is-striped">
                <thead>
                    <tr>
                        <th colspan="2">{{ __('Recently Published') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($articles as $article)
                        <tr>
                            <td style="width: 67%;"><a href="{{ route('admin.entries.edit', $article) }}">{{ $article->name }}</a></td>
                            <td class="has-text-right-desktop"><time>{{ $article->created_at->format('M j, Y') }}</time></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="2">{{ __('Nothing here, yet.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="column is-4">
        <div class="card">
            <table class="table is-fullwidth is-striped">
                <thead>
                    <tr>
                        <th>{{ __('Latest Comments') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($comments as $comment)
                        <tr>
                            <td>
                                <a href="{{ route('admin.comments.edit', $comment) }}">{{ $comment->author }} &ndash; {!! Str::words(strip_tags($comment->content), 10, ' […]') !!}</a>
                                <br>
                                <small class="is-block mt-1">On <a href="{{ route('admin.entries.edit', $comment->entry) }}">{!! $comment->entry->name !!}</a></small>

                                <small class="is-block mt-2">
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
                                        <button class="button is-ghost on-hover-danger" type="submit">{{ __('Delete') }}</button>
                                    </form>
                                </small>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td>{{ __('Nothing here, yet.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@stop
