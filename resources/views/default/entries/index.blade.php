@extends('theme::layout')

@section('title', request()->is('/') ? site_tagline() : (isset($type) ? ucfirst(Str::plural($type)) : __('Stream')))

@section('content')
<div class="h-feed">
    @if (! request()->is('/'))
        <h1>@yield('title')</h1>
    @endif

    @forelse ($entries as $entry)
        @include('theme::entries.partials.content')
    @empty
        <div class="h-entry">
            <header class="entry-header">
                <h2 class="entry-title p-name">{{ __('Not Found') }}</h2>
            </header>

            <div class="e-content">
                <p>{!! __('Seems we couldn’t find what you’re looking for.') !!}</p>
            </div>
        </div>
    @endforelse

    <nav class="posts-navigation">
        {{ $entries->links('theme::pagination.simple') }}
    </nav>
</div>
@stop
