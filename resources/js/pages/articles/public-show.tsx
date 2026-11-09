import { Head } from '@inertiajs/react';
import PublicArticleHeader from '@/components/public-article-header';
import type { Article } from '@/types/article';

export default function PublicShow({
    article,
    editableArticleId,
    publicIndexEnabled,
}: {
    article: Pick<Article, 'title' | 'content' | 'slug'>;
    editableArticleId: number | null;
    publicIndexEnabled: boolean;
}) {
    return (
        <>
            <Head title={article.title} />
            <div className="min-h-screen bg-background text-foreground">
                <PublicArticleHeader
                    publicIndexEnabled={publicIndexEnabled}
                    editableArticleId={editableArticleId}
                />
                <main className="mx-auto max-w-3xl px-6 py-10">
                    <article className="space-y-8">
                        <h1 className="text-3xl font-semibold break-words">
                            {article.title}
                        </h1>
                        <div className="break-words whitespace-pre-wrap">
                            {article.content}
                        </div>
                    </article>
                </main>
            </div>
        </>
    );
}
