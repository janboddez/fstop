@extends('admin.layout')

@section('title', __('Attachments'))

@section('content')
<header class="mt-4 mb-5 is-clearfix">
    <h1 class="title is-pulled-left mr-3">@yield('title')</h1>
    <button class="button is-small upload-modal-trigger" data-target="upload-modal">{{ __('Upload File') }}</button>
</header>

@include('admin.partials.flash-message')

<div class="mx-1 my-3 is-clearfix">
    {{ $attachments->links('admin.partials.pagination.top') }}
</div>

<div class="card">
    <table class="table is-fullwidth is-striped">
        <thead>
            <tr>
                <th class="is-hidden-mobile" style="width: 20%;">{{ __('Name') }}</th>
                <th>{{ __('Preview') }}</th>
                <th class="is-hidden-mobile" style="width: 20%;">{{ __('Uploaded To') }}</th>
                <th style="width: 50%;">{{ __('URL') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($attachments as $attachment)
                <tr>
                    <td class="is-hidden-mobile" style="width: 20%;">
                        <a href="{{ route('admin.attachments.edit', $attachment) }}">{{ $attachment->name ?? '—' }}</a>
                        <br>
                        <small>
                            <a href="{{ $attachment->url }}" target="_blank" rel="noopener noreferrer">{{ __('View') }}</a>
                            |
                            <a href="{{ route('admin.attachments.edit', $attachment) }}">{{ __('Edit') }}</a>
                            |
                            <form class="is-inline" action="{{ route('admin.attachments.destroy', $attachment) }}" method="post">
                                @method('DELETE')
                                @csrf
                                <button class="button is-ghost" type="submit">{{ __('Delete') }}</button>
                            </form>
                        </small>
                    </td>
                    <td style="width: 8%;">{!! sprintf(
                            '<img src="%s" width="%d" alt="%s"%s>',
                            $attachment->url,
                            '90',
                            $attachment->alt,
                            ! empty($attachment->srcset) ? ' srcset="' . $attachment->srcset . '"' : ''
                        ) !!}</td>

                    @if (! blank($attachment->entry) && ! $attachment->entry->trashed())
                        <td class="is-hidden-mobile" style="width: 20%;"><a href="{{ route('admin.entries.edit', $attachment->entry) }}">{{ $attachment->entry->name ?? __('(No Title)') }}</a></td>
                    @else
                        <td class="is-hidden-mobile" style="width: 20%;">{{ __('—') }}</td>
                    @endif

                    <td style="width: 50%;">
                        <input class="input is-clickable copy-trigger" type="text" value="{{ $attachment->url }}" readonly>
                        <input class="input is-clickable copy-trigger mt-2" type="text" value="{{ sprintf(
                            '<img src="%s" width="%d"%s alt="%s"%s>',
                            $attachment->url,
                            $attachment->width,
                            ! empty($attachment->height) ? ' height="' . $attachment->height . '"' : '',
                            $attachment->alt,
                            ! empty($attachment->srcset) ? ' srcset="' . $attachment->srcset . '"' : ''
                        ) }}" readonly>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" style="text-align: center;">{{ __('Nothing here, yet.') }}</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

{{ $attachments->onEachSide(2)->links('admin.partials.pagination.bottom') }}

<div class="modal" id="upload-modal">
    <div class="modal-background"></div>

    <div class="modal-card">
        <header class="modal-card-head">
            <h2 class="modal-card-title">{{ __('Upload File') }}</h2>
            <button class="delete" aria-label="close"></button>
        </header>

        <div class="modal-card-body">
            <form action="{{ route('admin.attachments.store') }}" method="post" enctype="multipart/form-data">
                @csrf
                <div class="field">
                    <div class="control">
                        <div class="file has-name">
                            <label class="file-label">
                                <input class="file-input" type="file" name="file">
                                <span class="file-cta">
                                    <span class="file-icon"><i class="mdi mdi-upload"></i></span>
                                    <span class="file-label">{{ __('Browse…') }}</span>
                                </span>
                                <span class="file-name">{{ __('…') }}</span>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="field">
                    <div class="control">
                        <button class="button is-link">{{ __('Upload File') }}</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <button class="modal-close is-large" aria-label="close"></button>
</div>
@stop

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {

    (document.querySelectorAll('.copy-trigger') || []).forEach(($trigger) => {
        var text = '';
        $trigger.addEventListener('click', () => {
            text = $trigger.value;
            navigator.clipboard.writeText(text).then(() => {
                alert('{{ __('Copied to clipboard!') }}');
            });
        });
    });

    // Functions to open and close a modal
    function openModal($el) {
        $el.classList.add('is-active');
    }

    function closeModal($el) {
        $el.classList.remove('is-active');
    }

    function closeAllModals() {
        (document.querySelectorAll('.modal') || []).forEach(($modal) => {
            closeModal($modal);
        });
    }

    // Add a click event on buttons to open a specific modal
    (document.querySelectorAll('.upload-modal-trigger') || []).forEach(($trigger) => {
        const modal = $trigger.dataset.target;
        const $target = document.getElementById(modal);

        $trigger.addEventListener('click', () => {
            openModal($target);
        });
    });

    // Add a click event on various child elements to close the parent modal
    (document.querySelectorAll('.modal-background, .modal-close, .modal-card-head .delete, .modal-card-foot .button') || []).forEach(($close) => {
        const $target = $close.closest('.modal');

        $close.addEventListener('click', () => {
            closeModal($target);
        });
    });

    // Add a keyboard event to close all modals
    document.addEventListener('keydown', (event) => {
        const e = event || window.event;

        if (e.keyCode === 27) { // Escape key
            closeAllModals();
        }
    });
});
</script>
@stop
