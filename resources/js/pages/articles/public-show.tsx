import { Head, Link } from '@inertiajs/react';
import { home } from '@/routes';
import type { Article } from '@/types/article';

export default function PublicShow({
    article,
}: {
    article: Pick<Article, 'title' | 'content' | 'slug'>;
}) {
    return (
        <>
            <Head title={article.title} />
            <div className="min-h-screen bg-background text-foreground">
                <header className="border-b">
                    <div className="mx-auto max-w-3xl px-6 py-4">
                        <Link href={home()} className="font-semibold">
                            InkRail
                        </Link>
                    </div>
                </header>
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
