@extends('admin.layout')

@section('title', __('Edit Tag'))

@section('content')
<header class="my-4">
    <h1 class="title">@yield('title')</h1>
</header>

@if ($errors->any())
    <div class="notification is-danger">
        <button class="delete"></button>

        <ul>
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<form action="{{ route('admin.tags.update', $tag) }}" method="post">
    @method('PUT')
    @csrf

    <div class="field">
        <label for="name" class="is-sr-only">{{ __('Name') }}</label>
        <div class="control">
            <input class="input{{ $errors->has('name') ? ' is-danger': '' }}" type="text" id="name" name="name" value="{{ old('name', $tag->name ?? '') }}">
        </div>
    </div>

    <div class="field">
        <label for="slug">{{ __('Slug') }}</label>
        <div class="control">
            <input class="input{{ $errors->has('slug') ? ' is-danger': '' }}" type="text" id="slug" name="slug" value="{{ old('slug', $tag->slug ?? '') }}">
        </div>
    </div>

    <div class="field">
        <label for="description">{{ __('Description') }}</label>
        <div class="control">
            <textarea class="textarea" id="description" name="description">{{ old('description', $tag->description ?? '') }}</textarea>
        </div>
    </div>

    <div class="field">
        <div class="control">
            <button class="button is-success">{{ __('Update') }}</button>
        </div>
    </div>
</form>
@stop
