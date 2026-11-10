import { lang } from '@erag/lang-sync-inertia/react';
import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { index } from '@/actions/App/Http/Controllers/PublicArticleController';
import ArticleContent from '@/components/article-content';
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
    const { __ } = lang();

    return (
        <>
            <Head title={article.title} />
            <div className="min-h-screen bg-background text-foreground">
                <PublicArticleHeader editableArticleId={editableArticleId} />
                <main className="mx-auto max-w-5xl space-y-6 px-6 py-10">
                    {publicIndexEnabled && (
                        <Link
                            href={index()}
                            className="inline-flex items-center gap-2 text-sm text-muted-foreground hover:text-foreground"
                        >
                            <ArrowLeft aria-hidden="true" className="size-4" />
                            {__('Public articles')}
                        </Link>
                    )}
                    <article className="space-y-8">
                        <h1 className="text-3xl font-semibold break-words">
                            {article.title}
                        </h1>
                        <ArticleContent
                            content={article.content}
                            syntax={
                                article.slug === 'showcase-zenn'
                                    ? 'zenn'
                                    : 'all'
                            }
                        />
                    </article>
                </main>
            </div>
        </>
    );
}
