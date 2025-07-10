<?php echo '<?xml version="1.0" encoding="UTF-8"?>'; ?>
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom" xmlns:dc="http://purl.org/dc/elements/1.1/">
<channel>
    <title>{{ site_name() }} &ndash; {{ ! empty($type) ? ucfirst(Str::plural($type)) : __('Feed') }}</title>

    @if (site_tagline() !== '')
        <description>{{ site_tagline() }}</description>
    @endif

    <link>{{ url('/') }}</link>

    @if (! empty($type))
        <atom:link href="{{ url('/' . Str::plural($type) . '/feed') }}" rel="self" type="application/rss+xml" />
    @else
        <atom:link href="{{ url('/feed') }}" rel="self" type="application/rss+xml" />
    @endif

    <language>{{ str_replace('_', '-', app()->getLocale()) }}</language>

    @foreach ($entries as $entry)
        @php
        if (! empty($entry->thumbnail)) {
            $image = '<p>' . $entry->thumbnail_html . '</p>' . PHP_EOL;
        } else {
            $image = '';
        }

        $content = $image . $entry->content;
        @endphp

    <item>
        @if (! blank($entry->user->name))
            <dc:creator><![CDATA[{{ $entry->user->name }}]]></dc:creator>
        @endif

        @if ($entry->type === 'article')
            {{-- @todo Either make this filterable, or have short-form entries baked in. --}}
            <title><![CDATA[{{ $entry->name }}]]></title>
        @endif

        <description><![CDATA[{!! $content !!}]]></description>
        <pubDate>{{ $entry->published->format('r') }}</pubDate>

        <link>{{ route(Str::plural($entry->type) . '.show', $entry->slug) }}</link>
        <guid>{{ route(Str::plural($entry->type) . '.show', $entry->slug) }}</guid>
    </item>
    @endforeach
</channel>
</rss>
