@extends('admin.layout')

@section('title', ucfirst(Str::plural($type)))

@section('content')
<header class="mt-4 mb-5 is-clearfix">
    <h1 class="title is-pulled-left mr-3">@yield('title')</h1>
    <a class="button is-small" href="{{ route('admin.entries.create', ['type' => $type]) }}">{{ __('New :Type', ['type' => $type]) }}</a>

    <form class="is-pulled-right is-hidden-mobile" action="{{ url()->current() }}" method="get">
        <input type="hidden" name="type" value="{{ $type }}">
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
    <a{!! ! request()->input('draft') && ! request()->input('trashed') && ! request()->input('s') ? ' class="is-active"' : '' !!} href="{{ route('admin.entries.index', ['type' => $type]) }}">{{ __('Published (:count)', ['count' => $published]) }}</a>
    |
    <a{!! request()->input('draft') ? ' class="is-active"' : '' !!} href="{{ route('admin.entries.index', ['type' => $type, 'draft' => true]) }}">{{ __('Draft (:count)', ['count' => $draft]) }}</a>
    |
    <a{!! request()->input('trashed') ? ' class="is-active"' : '' !!} href="{{ route('admin.entries.index', ['type' => $type, 'trashed' => true]) }}">{{ __('Trash (:count)', ['count' => $trashed]) }}</a>

    {{ $entries->links('admin.partials.pagination.top') }}
</div>

<div class="card">
    <table class="table is-fullwidth is-striped">
        <thead>
            <tr>
                <th style="width: {{ $type === 'page' ? '30%' : '50%' }};">{{ __('Name') }}</th>

                @if ($type === 'page')
                    <th class="is-hidden-mobile" style="width: 30%;">{{ __('Slug') }}</th>
                @endif

                <th class="is-hidden-mobile">{{ __('Status') }}</th>
                <th class="is-hidden-mobile">{{ __('Visibility') }}</th>

                @if ($type !== 'page')
                    <th style="text-align: center;">{{ __('Comments') }}</th>
                    <th class="is-hidden-mobile">{{ __('Tags') }}</th>
                @endif

                <th>{{ __('Date') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($entries as $entry)
                <tr>
                    <td>
                        @if ($entry->trashed())
                            @if ($entry->type === 'page')
                                {{ (count(explode('/', $entry->slug)) > 1 ? '— ': '').$entry->name }}
                            @else
                                {{ $entry->name ?? __('(No Title)') }}
                            @endif

                            <br>

                            <small>
                                <form class="is-inline" action="{{ route('admin.entries.restore', $entry) }}" method="post">
                                    @csrf
                                    <button class="button is-ghost" type="submit">{{ __('Restore') }}</button>
                                </form>
                                |
                                <form class="is-inline" action="{{ route('admin.entries.destroy', $entry) }}" method="post">
                                    @method('DELETE')
                                    @csrf
                                    <button class="button is-ghost" type="submit">{{ __('Delete permanently') }}</button>
                                </form>
                            </small>
                        @else
                            @if ($entry->type === 'page')
                                <a href="{{ route('admin.entries.edit', $entry) }}">{{ (count(explode('/', $entry->slug)) > 1 ? '— ': '').$entry->name }}</a>
                            @else
                                <a href="{{ route('admin.entries.edit', $entry) }}">{{ $entry->name ?? __('(No Title)') }}</a>
                            @endif

                            <br>

                            <small>
                                @if ($entry->status === 'published')
                                    <a href="{{ route(Str::plural($entry->type) . '.show', $entry->slug) }}" target="_blank" rel="noopener noreferrer">{{ __('View') }}</a>
                                    |
                                @endif
                                <a href="{{ route('admin.entries.edit', $entry) }}">{{ __('Edit') }}</a>
                                |
                                <form class="is-inline" action="{{ route('admin.entries.destroy', $entry) }}" method="post">
                                    @method('DELETE')
                                    @csrf
                                    <button class="button is-ghost" type="submit">{{ __('Delete') }}</button>
                                </form>
                            </small>
                        @endif
                    </td>

                    @if ($type === 'page')
                        <td class="is-hidden-mobile" style="width: 30%;">{{ $entry->slug }}</td>
                    @endif

                    <td class="is-hidden-mobile">{{ ucfirst($entry->status) }}</td>
                    <td class="is-hidden-mobile">{{ ucfirst($entry->visibility) }}</td>

                    @if ($type !== 'page')
                        <td style="text-align: center;">
                            <a href="{{ route('admin.comments.index', ['entry' => $entry->id]) }}">{{ $entry->comments_count }}</a>
                        </td>
                        <td class="is-hidden-mobile">
                            <small>{!! ! blank($entry->tags)
                                ? $entry->tags->map(fn ($tag) => '<a href="' . route('admin.tags.edit', $tag) . '">' . $tag->name . '</a>')->implode(', ')
                                : '—' !!}</small>
                        </td>
                    @endif

                    <td><time>{{ $entry->created_at->format('M j, Y') }}<br><small>{{ $entry->created_at->format('h:i A') }}</small></time></td>
                </tr>
            @empty
                <tr>
                    @if ($type === 'page')
                        <td colspan="5" style="text-align: center;">{{ __('Nothing here, yet.') }}</td>
                    @else
                        <td colspan="6" style="text-align: center;">{{ __('Nothing here, yet.') }}</td>
                    @endif
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

{{ $entries->onEachSide(3)->links('admin.partials.pagination.bottom') }}
@stop
