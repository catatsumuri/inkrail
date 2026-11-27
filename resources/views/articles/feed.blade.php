{!! '<?xml version="1.0" encoding="UTF-8"?>' !!}
<rss version="2.0">
    <channel>
        <title>{!! $xml($title) !!}</title>
        <link>{!! $xml($url) !!}</link>
        <description>{!! $xml($description) !!}</description>
        <language>{!! $xml($locale) !!}</language>
        @foreach ($items as $item)
            <item>
                <title>{!! $xml($item['title']) !!}</title>
                <link>{!! $xml($item['url']) !!}</link>
                <description>{!! $xml($item['description']) !!}</description>
                <pubDate>{!! $xml($item['date']) !!}</pubDate>
                <guid isPermaLink="false">{!! $xml($item['guid']) !!}</guid>
            </item>
        @endforeach
    </channel>
</rss>
