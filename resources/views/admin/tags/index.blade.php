@extends('admin.layout')

@section('title', __('Tags'))

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

<div class="mx-1 my-3 is-clearfix">
    {{ $tags->links('admin.partials.pagination.top') }}
</div>

<div class="card">
    <table class="table is-fullwidth is-striped">
        <thead>
            <tr>
                <th>{{ __('Name') }}</th>
                <th>{{ __('Slug') }}</th>
                <th>{{ __('Count') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($tags as $tag)
                <tr>
                    <td>
                        <a href="{{ route('admin.tags.edit', $tag) }}">{{ $tag->name }}</a>
                        <br>
                        <small>
                            <a href="{{ route('tags.show', $tag) }}" target="_blank" rel="noopener noreferrer">{{ __('View') }}</a>
                            |
                            <a href="{{ route('admin.tags.edit', $tag) }}">{{ __('Edit') }}</a>
                            |
                            <form class="is-inline" action="{{ route('admin.tags.destroy', $tag) }}" method="post">
                                @method('DELETE')
                                @csrf
                                <button class="button is-ghost" type="submit">{{ __('Delete') }}</button>
                            </form>
                        </small>
                    </td>
                    <td>{{ $tag->slug }}</td>
                    <td>{{ $tag->entries_count }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="3" style="text-align: center;">{{ __('Nothing here, yet.') }}</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

{{ $tags->onEachSide(2)->links('admin.partials.pagination.bottom') }}
@stop
