import { lang } from '@erag/lang-sync-inertia/react';
import { Link } from '@inertiajs/react';
import {
    CircleCheck,
    CircleDashed,
    CircleMinus,
    Clock3,
    History,
} from 'lucide-react';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';
import { toast } from 'sonner';
import { index } from '@/actions/App/Http/Controllers/ArticleHistoryController';
import type { TranslationState } from '@/types/article';

export default function TranslationStatus({
    articleId,
    states,
    compact = false,
}: {
    articleId: number;
    states?: Record<string, TranslationState>;
    compact?: boolean;
}) {
    const { __ } = lang();
    function canOpenHistory() {
        const dirty = Array.from(
            document.querySelectorAll<HTMLInputElement | HTMLTextAreaElement>(
                '[data-article-form] input, [data-article-form] textarea',
            ),
        ).some((input) =>
            input instanceof HTMLInputElement && input.type === 'checkbox'
                ? input.checked !== input.defaultChecked
                : input.value !== input.defaultValue,
        );
        if (dirty)
            toast.error(
                __('Save or discard your edits before opening history.'),
            );
        return !dirty;
    }
    if (!states) return null;
    const labels = {
        missing: __('Not created'),
        deleted: __('Deleted'),
        unreviewed: __('Not reviewed'),
        reviewed: __('Up to date'),
        other_changed: __('Out of date'),
    };
    return (
        <div className="flex flex-wrap items-center gap-x-4 gap-y-2 text-xs text-muted-foreground">
            {Object.entries(states).map(([locale, status]) => {
                const other = locale === 'en' ? 'ja' : 'en';
                const baseline = states[other]?.baselineId;
                const current = states[other]?.revisionId;
                const Icon = {
                    reviewed: CircleCheck,
                    other_changed: Clock3,
                    unreviewed: Clock3,
                    missing: CircleDashed,
                    deleted: CircleMinus,
                }[status.state];
                const languageName = locale === 'ja' ? '日本語' : 'English';
                return (
                    <Tooltip key={locale}>
                        <TooltipTrigger asChild>
                            <Link
                                aria-label={`${languageName} · ${labels[status.state]}`}
                                onBefore={canOpenHistory}
                                href={index(
                                    { article: articleId },
                                    {
                                        query:
                                            status.state === 'other_changed' &&
                                            baseline &&
                                            current
                                                ? {
                                                      locale: other,
                                                      from: baseline,
                                                      to: current,
                                                  }
                                                : { locale },
                                    },
                                )}
                                className="inline-flex items-center gap-1.5 rounded-full bg-muted/60 px-2.5 py-1.5 transition-colors hover:bg-muted hover:text-foreground focus-visible:outline-2 focus-visible:outline-ring"
                            >
                                <Icon
                                    aria-hidden="true"
                                    className={cn(
                                        'size-3.5',
                                        status.state === 'reviewed' &&
                                            'text-emerald-600 dark:text-emerald-400',
                                        (status.state === 'other_changed' ||
                                            status.state === 'unreviewed') &&
                                            'text-amber-600 dark:text-amber-400',
                                    )}
                                />
                                {languageName}
                            </Link>
                        </TooltipTrigger>
                        <TooltipContent>
                            {languageName} · {labels[status.state]}
                        </TooltipContent>
                    </Tooltip>
                );
            })}
            <Link
                href={index({ article: articleId })}
                onBefore={canOpenHistory}
                className="inline-flex items-center gap-1 hover:text-foreground"
                title={__('History and correspondence')}
            >
                <History className="size-3.5" aria-hidden="true" />
                <span className={compact ? 'sr-only' : undefined}>
                    {__('History and correspondence')}
                </span>
            </Link>
        </div>
    );
}
