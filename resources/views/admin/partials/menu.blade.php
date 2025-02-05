<ul class="menu-list">
    <li class="my-3">
        <a{!! request()->is('admin') ? ' class="is-active"' : '' !!} href="{{ url('admin') }}">
            <span class="icon-text"><span class="icon"><i class="mdi mdi-home-outline"></i></span> <span>{{ __('Dashboard') }}</span></span>
        </a>
    </li>

    @forelse(get_registered_entry_types('object') as $type => $attributes)
        @if ($type === 'page')
            {{-- Sqeeuze in tags. --}}
            <li>
                <a{!! request()->is('admin/tags*') ? ' class="is-active"' : '' !!} href="{{ route('admin.tags.index') }}">
                    <span class="icon-text"><span class="icon"><i class="mdi mdi-tag-outline"></i></span> <span>{{ __('Tags') }}</span></span>
                </a>
            </li>
            <li class="mt-3">
        @else
            <li>
        @endif
            <a{!! request()->is('admin/entries*') && (request()->input('type') === $type || (isset($entry) && $entry->type === $type)) ? ' class="is-active"' : '' !!} href="{{ route('admin.entries.index') }}?type={{ $type }}">
                @if (! empty($attributes['icon']))
                    <span class="icon-text"><span class="icon"><i class="{{ $attributes['icon'] }}"></i></span>
                @endif
                <span>{{ ucfirst(Str::plural($type)) }}</span></span>
            </a>
        </li>
        @empty
    @endforelse
    <li>
        <a{!! request()->is('admin/comments*') ? ' class="is-active"' : '' !!} href="{{ route('admin.comments.index') }}">
            <span class="icon-text"><span class="icon"><i class="mdi mdi-forum"></i></span> <span>{{ __('Comments') }} ({{ \App\Models\Comment::pending()->count() }})</span></span>
        </a>
    </li>
    <li>
        <a{!! request()->is('admin/attachments*') ? ' class="is-active"' : '' !!} href="{{ route('admin.attachments.index') }}">
            <span class="icon-text"><span class="icon"><i class="mdi mdi-image-multiple-outline"></i></span> <span>{{ __('Media') }}</span></span>
        </a>
    </li>
    <li class="mt-3">
        <a{!! request()->is('admin/profile*') ? ' class="is-active"' : '' !!} href="{{ route('admin.profile.index') }}">
            <span class="icon-text"><span class="icon"><i class="mdi mdi-account"></i></span> <span>{{ __('Profile') }}</span></span>
        </a>
    </li>

    @if (config('app.activitypub', env('ACTIVITYPUB_ENABLED', false)))
        <li>
            <a{!! request()->is('admin/followers*') ? ' class="is-active"' : '' !!} href="{{ route('admin.followers.index') }}">
                <span class="icon-text"><span class="icon"><i class="mdi mdi-account-multiple"></i></span> <span>{{ __('Followers (beta)') }}</span></span>
            </a>
        </li>
    @endif

    <li class="mt-3">
        <a{!! request()->is('admin/themes*') ? ' class="is-active"' : '' !!} href="{{ route('admin.themes.index') }}">
            <span class="icon-text"><span class="icon"><i class="mdi mdi-palette-outline"></i></span> <span>{{ __('Themes') }}</span></span>
        </a>
    </li>
    <li>
        <a{!! request()->is('admin/plugins*') ? ' class="is-active"' : '' !!} href="{{ route('admin.plugins.index') }}">
            <span class="icon-text"><span class="icon"><i class="mdi mdi-connection"></i></span> <span>{{ __('Plugins') }}</span></span>
        </a>
    </li>
    <li>
        <a{!! request()->is('admin/settings*') ? ' class="is-active"' : '' !!} href="{{ route('admin.settings.index') }}">
            <span class="icon-text"><span class="icon"><i class="mdi mdi-cog"></i></span> <span>{{ __('Settings') }}</span></span>
        </a>
    </li>

    <li class="my-3">
        <a href="/logout" class="logout" data-no-instant><span class="icon-text">
            <span class="icon"><i class="mdi mdi-logout"></i></span> <span>{{ __('Log Out') }}</span></span>
        </a>
    </li>
</ul>
