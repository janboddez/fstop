@extends('admin.layout')

@section('title', __('Edit Attachment'))

@section('content')
<header class="my-4">
    <h1 class="title">@yield('title')</h1>
</header>

@include('admin.partials.flash-message')

<form action="{{ route('admin.attachments.update', $attachment) }}" method="post">
    @method('PUT')
    @csrf

    <div class="columns">
        <div class="column is-8">
            <div class="field">
                @if (Str::endsWith($attachment->mime_type, 'pdf'))
                    <iframe class="file" src="{{ $attachment->url }}" style="width: 100%; min-height: 85vh;"></iframe>
                @else
                    <img class="file" src="{{ $attachment->large }}" alt="{{ $attachment->alt ?? '' }}" style="max-width: 100%; height: auto;">
                @endif
            </div>
        </div>

        <div class="column">
            <div class="card mb-5">
                <div class="card-content">
                    <div class="field">
                        <label for="featured">{{ __('Name') }}</label>
                        <div class="control">
                            <input class="input" type="text" id="name" name="name" value="{{ old('name', $attachment->name) }}">
                        </div>
                    </div>

                    <div class="field">
                        <label for="featured">{{ __('Alt') }}</label>
                        <div class="control">
                            <input class="input" type="text" id="alt" name="alt" value="{{ old('alt', $attachment->alt) }}">
                        </div>
                    </div>

                    <div class="field">
                        <label for="featured">{{ __('Caption') }}</label>
                        <div class="control">
                            <textarea class="textarea" id="caption" name="caption">{{ old('caption', $attachment->caption) }}</textarea>
                        </div>
                    </div>

                    <div class="field">
                        <div class="control">
                            <button class="button is-success">{{ __('Save') }}</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>
@stop
