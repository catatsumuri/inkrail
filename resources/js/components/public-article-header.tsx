import { lang } from '@erag/lang-sync-inertia/react';
import { Link, usePage } from '@inertiajs/react';
import { edit, index } from '@/actions/App/Http/Controllers/ArticleController';
import { index as publicIndex } from '@/actions/App/Http/Controllers/PublicArticleController';
import { Button } from '@/components/ui/button';
import { home } from '@/routes';

export default function PublicArticleHeader({
    publicIndexEnabled,
    isIndexPage = false,
    editableArticleId = null,
}: {
    publicIndexEnabled: boolean;
    isIndexPage?: boolean;
    editableArticleId?: number | null;
}) {
    const { __ } = lang();
    const { auth } = usePage().props;

    return (
        <header className="border-b">
            <div className="mx-auto flex max-w-3xl flex-wrap items-center justify-between gap-3 px-6 py-4">
                <Link
                    href={
                        publicIndexEnabled && !isIndexPage
                            ? publicIndex()
                            : home()
                    }
                    className="font-semibold"
                >
                    InkRail
                </Link>
                {auth.user && (
                    <nav
                        aria-label={__('Article management')}
                        className="flex flex-wrap gap-2"
                    >
                        <Button asChild variant="ghost" size="sm">
                            <Link href={index()}>
                                {__('Back to my articles')}
                            </Link>
                        </Button>
                        {editableArticleId !== null && (
                            <Button asChild variant="outline" size="sm">
                                <Link href={edit(editableArticleId)}>
                                    {__('Edit article')}
                                </Link>
                            </Button>
                        )}
                    </nav>
                )}
            </div>
        </header>
    );
}
