@extends('admin.layout')

@section('title', __('Edit Comment'))

@section('content')
<header class="my-4">
    <h1 class="title">@yield('title')</h1>
</header>

@include('admin.partials.flash-message')

<form action="{{ route('admin.comments.update', $comment) }}" method="post">
    @method('PUT')
    @csrf

    <div class="columns">
        <div class="column is-9">
                    <div class="field">
                        <label for="author" class="is-sr-only">{{ __('Author') }}</label>
                        <div class="control">
                            <input class="input{{ $errors->has('author') ? ' is-danger': '' }}" type="text" id="author" name="author" placeholder="{{ __('Author') }}" value="{{ old('author', $comment->author ?? '') }}">
                        </div>
                    </div>

                    <div class="field">
                        <label for="author_email" class="is-sr-only">{{ __('Email Address') }}</label>
                        <div class="control">
                            <input class="input" type="text" id="author_email" name="author_email" placeholder="{{ __('Email Address') }}" value="{{ old('author_email', $comment->author_email ?? '') }}">
                        </div>
                    </div>

                    <div class="field">
                        <label for="author_url" class="is-sr-only">{{ __('Website') }}</label>
                        <div class="control">
                            <input class="input" type="text" id="author_url" name="author_url" placeholder="{{ __('Website') }}" value="{{ old('author_url', $comment->author_url ?? '') }}">
                        </div>
                    </div>

                    <div class="field">
                        <label for="content" class="is-sr-only">{{ __('Content') }}</label>
                        <div class="control">
                            <textarea class="textarea{{ $errors->has('content') ? ' is-danger': '' }}" id="content" name="content" rows="18">{{ old('content', $comment->content ?? '') }}</textarea>
                       </div>
                    </div>


            <div class="card mt-5">
                <header class="card-header">
                    <h2 class="card-header-title">{{ __('Custom Fields') }}</h2>
                </header>

                <div class="card-content">
                    <div class="field">
                        @if (! empty($comment->meta))
                            @foreach ($comment->meta as $key => $value)
                                <div class="columns">
                                    <div class="column">
                                        <div class="control">
                                            <input type="text" class="input" name="meta_keys[]" value="{{ $key }}">
                                        </div>
                                    </div>

                                    <div class="column">
                                        <div class="control">
                                            <textarea class="textarea" id="summary" name="meta_values[]" rows="1">{{
                                                is_array($value) && count($value) > 1
                                                    ? json_encode($value)
                                                    : $value[0] ?? $value
                                            }}</textarea>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        @endif

                        <div class="columns">
                            <div class="column">
                                <div class="control">
                                    <input type="text" class="input" name="meta_keys[]">
                                </div>
                            </div>

                            <div class="column">
                                <div class="control">
                                    <textarea class="textarea" id="summary" name="meta_values[]" rows="1"></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="column is-3">
            <div class="card mb-5">
                <div class="card-content">
                    <label class="title is-6 mb-2 is-block" for="created_at">{{ __('Date') }}</label>
                    <div class="field is-grouped is-grouped-multiline mb-4">
                        <div class="control">
                            <input type="date" class="input" name="created_at" id="created_at" value="{{ old('created_at', isset($comment->created_at) ? $comment->created_at->format('Y-m-d') : '') }}">
                        </div>
                        <div class="control">
                            <label class="is-sr-only" for="time">{{ __('Time') }}</label>
                            <input type="time" class="input" name="time" id="time" value="{{ old('time', isset($comment->created_at) ? $comment->created_at->format('H:i') : '') }}">
                        </div>
                    </div>

                    @if (! empty($comment->entry))
                        <div class="field">
                            <div class="control">
                                {!! __('In reply to: <a href=":url">:name</a>', [
                                    'url' => route('admin.entries.edit', $comment->entry),
                                    'name' => $comment->entry->name,
                                ]) !!}
                            </div>
                        </div>
                    @endif

                    <div class="field">
                        <h3 class="title is-6 mb-1">{{ __('Status') }}</h3>
                        <div class="control">
                            <label class="radio"><input type="radio" name="status" value="pending" {{ in_array(old('status', $comment->status ?? null), [null, 'pending'], true) ? 'checked' : '' }}> {{ __('Pending') }}</label>
                        </div>
                        <div class="control">
                            <label class="radio"><input type="radio" name="status" value="approved" {{ old('approved', $comment->status ?? null) === 'approved' ? 'checked' : '' }}> {{ __('Approved') }}</label>
                        </div>
                    </div>

                    <div class="field is-grouped is-grouped-multiline">
                        <div class="control">
                            <button class="button is-success">{{ __('Update') }}</button>
                        </div>

                        @if ($comment->status === 'approved' && ! empty($comment->entry) && $comment->entry->status === 'published' && $comment->entry->visibility !== 'private')
                            <div class="control">
                                <a class="button" href="{{ route(Str::plural($comment->entry->type) . '.show', $comment->entry->slug) }}#comment-{{ $comment->id }}" rel="noopener noreferrer" target="_blank">{{ __('View Comment') }}</a>
                            </div>
                        @endif

                        <div class="control">
                            <button class="button is-danger" type="submit" form="delete-form">{{ __('Permanently Delete') }}</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

{{-- Wanting to avoid AJAX, we have the "Trash" button above submit this here form. --}}
<form action="{{ route('admin.comments.destroy', $comment) }}" method="post" id="delete-form">
    @method('DELETE')
    @csrf
</form>
@stop
