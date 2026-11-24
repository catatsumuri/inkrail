import { useEffect, useState } from 'react';
import { usePage } from '@inertiajs/react';

export default function ArticlePublicationTime({
    date,
    scheduled = false,
}: {
    date: string;
    scheduled?: boolean;
}) {
    const { locale } = usePage().props;
    const [now, setNow] = useState(Date.now);

    useEffect(() => {
        if (!scheduled) return;
        const timer = window.setInterval(() => setNow(Date.now()), 30_000);
        return () => window.clearInterval(timer);
    }, [scheduled]);

    const timestamp = new Date(date);
    const minutes = Math.max(
        0,
        Math.ceil((timestamp.getTime() - now) / 60_000),
    );
    const unit = minutes >= 1440 ? 'day' : minutes >= 60 ? 'hour' : 'minute';
    const amount =
        unit === 'day'
            ? Math.ceil(minutes / 1440)
            : unit === 'hour'
              ? Math.ceil(minutes / 60)
              : minutes;
    const relative = new Intl.RelativeTimeFormat(locale, {
        numeric: 'always',
    }).format(amount, unit);

    return (
        <time dateTime={date} className="text-sm text-muted-foreground">
            {new Intl.DateTimeFormat(locale, {
                timeZone: 'Asia/Tokyo',
                dateStyle: 'medium',
                timeStyle: 'short',
            }).format(timestamp)}{' '}
            JST
            {scheduled && <> · {relative}</>}
        </time>
    );
}
