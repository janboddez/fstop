@extends('admin.layout')

@section('title', isset($entry) ? __('Edit :Type', ['type' => $type]) : __('Create :Type', ['type' => $type]))

@section('content')
<header class="my-4 is-clearfix">
    <h1 class="title is-pulled-left mr-3">@yield('title')</h1>

    @if (isset($entry))
        <a class="button is-small" href="{{ route('admin.entries.create', ['type' => $type]) }}">{{ __('New :Type', ['type' => $type]) }}</a>
    @endif
</header>

@include('admin.partials.flash-message')

<form action="{{ isset($entry) ? route('admin.entries.update', $entry) : route('admin.entries.store') }}" method="post">
    @if (isset($entry))
        @method('PUT')
    @endif

    @csrf

    <div class="columns is-desktop">
        <div class="column is-9-desktop">
            <div class="field">
                <label for="name" class="is-sr-only">{{ __('Name') }}</label>
                <div class="control">
                    <input class="input{{ $errors->has('name') ? ' is-danger': '' }}" type="text" id="name" name="name" placeholder="{{ __('Name') }}" value="{{ old('name', $entry->name ?? '') }}">
                </div>
            </div>

            <div class="field is-grouped is-align-items-center">
                <label for="slug" class="is-hidden-mobile"><span class="is-sr-only">{{ __('Slug:') }}</span> {{ $type === 'page' ? url('/') : url(Str::plural($type)) }}/</label>
                <div class="control is-expanded">
                    <input class="input{{ $errors->has('slug') ? ' is-danger': '' }}" type="text" id="slug" name="slug" placeholder="{{ __('slug') }}" value="{{ old('slug', $entry->slug ?? '') }}">
                </div>
            </div>

            <div class="field">
                <label for="content" class="is-sr-only">{{ __('Content') }}</label>
                <div class="control">
                    <textarea class="textarea{{ $errors->has('content') ? ' is-danger': '' }}" id="content" name="content" rows="18">{{ old('content', $entry->content ?? '') }}</textarea>
               </div>
            </div>

            <div class="card mt-5">
                <header class="card-header">
                    <h2 class="card-header-title"><label for="summary">{{ __('Summary') }}</label></h2>
                </header>

                <div class="card-content">
                    <div class="field">
                        <div class="control">
                            <textarea class="textarea" id="summary" name="summary" rows="3">{{ old('summary', $entry->summary ?? '') }}</textarea>
                       </div>
                    </div>
                </div>
            </div>

            <div class="card mt-5">
                <header class="card-header">
                    <h2 class="card-header-title">{{ __('Custom Fields') }}</h2>
                </header>

                <div class="card-content">
                    <div class="field">
                        @if (! empty($entry->meta))
                            @foreach ($entry->meta as $key => $value)
                                <div class="columns">
                                    <div class="column">
                                        <div class="control">
                                            <input type="text" class="input" name="meta_keys[]" value="{{ $key }}">
                                        </div>
                                    </div>

                                    <div class="column">
                                        <div class="control">
                                            <textarea class="textarea" id="summary" name="meta_values[]" rows="1">{{
                                                ! empty($value[0]) && is_string($value[0])
                                                    ? $value[0] /* Even single values should always be cast to an array! */
                                                    : json_encode($value, JSON_UNESCAPED_SLASHES)
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

        <div class="column is-3-desktop">
            <div class="card mb-5">
                <div class="card-content">
                    <label class="title is-6 is-block mb-2" for="created_at">{{ __('Date') }}</label>
                    <div class="field is-grouped is-grouped-multiline mb-4">
                        <div class="control">
                            <input type="date" class="input" name="created_at" id="created_at" value="{{ old('created_at', isset($entry) ? $entry->created_at->format('Y-m-d') : '') }}">
                        </div>
                        <div class="control">
                            <label class="is-sr-only" for="time">{{ __('Time') }}</label>
                            <input type="time" class="input" name="time" id="time" value="{{ old('time', isset($entry) ? $entry->created_at->format('H:i') : '') }}">
                        </div>
                    </div>

                    <div class="field">
                        <h3 class="title is-6 mb-1">{{ __('Status') }}</h3>
                        <div class="control">
                            <label class="radio"><input type="radio" name="status" value="draft"{{ in_array(old('status', $entry->status ?? null), [null, 'draft'], true) ? ' checked' : '' }}> {{ __('Draft') }}</label>
                        </div>
                        <div class="control">
                            <label class="radio"><input type="radio" name="status" value="published"{{ old('published', $entry->status ?? null) === 'published' ? ' checked' : '' }}> {{ __('Published') }}</label>
                        </div>
                    </div>

                    <div class="field">
                        <h3 class="title is-6 mb-1">{{ __('Visibility') }}</h3>
                        <div class="control">
                            <label class="radio"><input type="radio" name="visibility" value="public"{{ in_array(old('visibility', $entry->visibility ?? null), [null, 'public'], true) ? ' checked' : '' }}> {{ __('Public') }}</label>
                        </div>
                        <div class="control">
                            <label class="radio"><input class="form-check-input" type="radio" name="visibility" value="unlisted"{{ old('visibility', $entry->visibility ?? null) === 'unlisted' ? ' checked' : '' }}> {{ __('Unlisted') }}</label>
                        </div>
                        <div class="control">
                            <label class="radio"><input class="form-check-input" type="radio" name="visibility" value="private"{{ old('visibility', $entry->visibility ?? null) === 'private' ? ' checked' : '' }}> {{ __('Private') }}</label>
                        </div>
                    </div>

                    <div class="field">
                        <label class="title is-6 is-block mb-2" for="type">{{ __('Type') }}</label>
                        <div class="select">
                            <select type="text" name="type" id="type">
                                @foreach (array_keys(App\Models\Entry::getRegisteredTypes()) as $possibleType)
                                    <option value="{{ $possibleType }}"{{ $possibleType === $type ? ' selected' : '' }}>{{ ucfirst($possibleType) }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="field is-grouped is-grouped-multiline mt-4">
                        @if (isset($entry))
                            <div class="control">
                                <button class="button is-success">{{ __('Update') }}</button>
                            </div>

                            @if ($entry->status === 'published')
                                <div class="control">
                                    <a class="button" href="{{ route(Str::plural($type) . '.show', $entry->slug) }}" rel="noopener noreferrer" target="_blank">{{ __('View :Type', ['type' => $type]) }}</a>
                                </div>
                            @endif
                        @else
                            <div class="control">
                                <button class="button is-success">{{ __('Create') }}</button>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="card mb-5">
                <header class="card-header">
                    <h2 class="card-header-title"><label for="tags">{{ __('Tags') }}</label></h2>
                </header>

                <div class="card-content">
                    <div class="control">
                        <textarea class="textarea" id="tags" name="tags" rows="1">{{
                            old('tags', ! empty($entry->tags) ? $entry->tags->map(fn ($tag) => $tag->name)->implode(', ') : '')
                        }}</textarea>
                    </div>
                </div>
            </div>

            <div class="card">
                <header class="card-header">
                    <h2 class="card-header-title"><label for="featured">{{ __('Featured Image') }}</label></h2>
                </header>

                <div class="card-content">
                    <div class="control">
                        <input class="input" type="text" id="featured" name="featured" value="{{ old('featured', $entry->featured->url ?? '') }}">
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>
@stop
