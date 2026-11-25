import { Button } from '@/components/ui/button';
import { show as publicShow } from '@/actions/App/Http/Controllers/PublicArticleController';
import { lang } from '@erag/lang-sync-inertia/react';
import { Head, setLayoutProps } from '@inertiajs/react';
import {
    edit,
    index,
    show,
} from '@/actions/App/Http/Controllers/ArticleController';
import ArticleForm from '@/components/article-form';
import type { Article } from '@/types/article';

export default function Edit({ article }: { article: Article }) {
    const { __ } = lang();
    setLayoutProps({
        breadcrumbs: [
            { title: __('Articles'), href: index() },
            { title: article.title, href: show(article.id) },
            { title: __('Edit'), href: edit(article.id) },
        ],
    });
    return (
        <>
            <Head title={__('Edit article')} />
            <div className="w-full space-y-6 px-4 py-6 sm:px-8 sm:py-10">
                <h1 className="text-xl font-semibold">{__('Edit article')}</h1>
                {article.publication_status === 'published' && (
                    <Button asChild variant="outline" size="sm">
                        <a
                            href={publicShow.url(article.slug)}
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            {__('View public page')}
                        </a>
                    </Button>
                )}
                <p className="text-sm text-muted-foreground">
                    {__('The public page shows saved changes only.')}
                </p>
                <ArticleForm key={article.id} article={article} />
            </div>
        </>
    );
}
