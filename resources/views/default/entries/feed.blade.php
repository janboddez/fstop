<?php echo '<?xml version="1.0" encoding="UTF-8"?>'; ?>
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom" xmlns:dc="http://purl.org/dc/elements/1.1/">
<channel>
    <title>{{ config('app.name') }} {{ ! empty($type) ? ucfirst(Str::plural($type)) : __('Feed') }}</title>

    {{-- <description>{{ __('On Web Development, WordPress, and More') }}</description> --}}
    <link>{{ url('/') }}</link>

    @if (! empty($type))
        <atom:link href="{{ url('/' . Str::plural($type) . '/feed') }}" rel="self" type="application/rss+xml" />
    @else
        <atom:link href="{{ url('/feed') }}" rel="self" type="application/rss+xml" />
    @endif

    <language>en-us</language>
    {{-- <managingEditor>jan@janboddez.be (Jan Boddez)</managingEditor> --}}

    @foreach ($entries as $entry)
        @php
        if (! empty($entry->thumbnail)) {
            $image = '<p><img src="' . $entry->thumbnail . '" alt="' . $entry->title . '"></p>'.PHP_EOL;
        } else {
            $image = '';
        }

        $content = $image.$entry->content;
        @endphp

    <item>
        <dc:creator><![CDATA[Jan Boddez]]></dc:creator>

        @if ($type === 'article')
            <title><![CDATA[{{ html_entity_decode($entry->name) }}]]></title>
        @endif

        <description><![CDATA[{!! $content !!}]]></description>
        <pubDate>{{ $entry->created_at->format('r') }}</pubDate>

        <link>{{ route(Str::plural($entry->type) . '.show', $entry->slug) }}</link>
        <guid>{{ route(Str::plural($entry->type) . '.show', $entry->slug) }}</guid>
    </item>
    @endforeach
</channel>
</rss>
