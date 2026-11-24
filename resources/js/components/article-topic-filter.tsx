import { lang } from '@erag/lang-sync-inertia/react';
import { Link } from '@inertiajs/react';
import { cn } from '@/lib/utils';

export default function ArticleTopicFilter({
    topics,
    selectedTopic,
    topicUrl,
}: {
    topics: string[];
    selectedTopic: string | null;
    topicUrl: (topic: string | null) => string;
}) {
    const { __ } = lang();

    if (topics.length === 0 && selectedTopic === null) {
        return null;
    }

    return (
        <nav aria-label={__('Topics')} className="flex flex-col gap-3">
            <p className="text-xs font-medium text-muted-foreground">
                {__('Topics')}
            </p>
            <div className="flex flex-wrap gap-2">
                {[
                    null,
                    ...new Set([
                        ...topics,
                        ...(selectedTopic === null ? [] : [selectedTopic]),
                    ]),
                ].map((topic) => (
                    <Link
                        key={topic === null ? 'all-topics' : `topic:${topic}`}
                        href={topicUrl(topic)}
                        preserveScroll
                        aria-current={
                            selectedTopic === topic ? 'page' : undefined
                        }
                        className={cn(
                            'max-w-full rounded-full border px-3 py-1.5 text-xs break-words transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring',
                            selectedTopic === topic
                                ? 'border-foreground bg-foreground text-background'
                                : 'text-muted-foreground hover:border-foreground/40 hover:bg-muted hover:text-foreground',
                        )}
                    >
                        {topic ?? __('All topics')}
                    </Link>
                ))}
            </div>
        </nav>
    );
}
