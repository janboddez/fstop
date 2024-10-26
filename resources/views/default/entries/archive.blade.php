@extends('theme::layout')

@section('title', 'Articles')

@section('content')
<h1>@yield('title')</h1>

@if (! blank($entries))
    {{-- "Current" month. --}}
    <h2>{{ $current = $entries[0]->created_at->format('F Y') }}</h2>
    <ul>
        @foreach ($entries as $entry)
            @if ($current !== $entry->created_at->format('F Y'))
                </ul>

                <h2>{{ $current = $entry->created_at->format('F Y') }}</h2>
                <ul>
            @endif

            <li><a href="{{ route('articles.show', $entry->slug) }}" rel="bookmark">{{ $entry->name }}</a></li>
        @endforeach
    </ul>
@else
    <h2>{{ __('Not Found') }}</h2>
    <p>{!! __('Seems we couldn’t find what you’re looking for.') !!}</p>
@endif
@stop
