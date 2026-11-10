import { lang } from '@erag/lang-sync-inertia/react';
import { usePage } from '@inertiajs/react';

export default function ArticleLanguageNotice({
    resolvedLocale,
}: {
    resolvedLocale?: string;
}) {
    const { locale } = usePage().props;
    const { __ } = lang();
    return resolvedLocale && resolvedLocale !== locale ? (
        <p className="text-sm text-muted-foreground">
            {__('Translation unavailable. Showing:')}{' '}
            {resolvedLocale === 'ja' ? '日本語' : 'English'}
        </p>
    ) : null;
}
