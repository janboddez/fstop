@extends('theme::layout')

@section('title', $entry->name)

@section('content')
    @include('theme::entries.partials.content')
@stop
