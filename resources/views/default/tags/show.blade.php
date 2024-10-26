@extends('theme::layout')

@section('title', __('Entries Tagged “:tag”', ['tag' => $tag->name]))
@section('body_class', 'tag')

@section('content')
    <h1>@yield('title')</h1>

    <div class="hfeed">
        @forelse ($entries as $entry)
            @include('theme::entries.partials.content')
        @empty
            <div class="hentry">
                <header class="entry-header">
                    <h2 class="entry-title">{{ __('Not Found') }}</h2>
                </header>

                <div class="entry-content">
                    <p>{!! __('Seems we couldn’t find what you’re looking for.') !!}</p>
                </div>
            </div>
        @endforelse
    </div>

    <nav class="posts-navigation">
        {{ $entries->links('theme::pagination.simple') }}
    </nav>
@stop
