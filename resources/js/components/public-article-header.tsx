import { lang } from '@erag/lang-sync-inertia/react';
import { Link, usePage } from '@inertiajs/react';
import { edit, index } from '@/actions/App/Http/Controllers/ArticleController';
import { Button } from '@/components/ui/button';
import AppLogoIcon from '@/components/app-logo-icon';
import DemoNotice from '@/components/demo-notice';
import { home } from '@/routes';

export default function PublicArticleHeader({
    editableArticleId = null,
}: {
    editableArticleId?: number | null;
}) {
    const { __ } = lang();
    const { auth } = usePage().props;

    return (
        <header className="border-b">
            <div className="mx-auto flex max-w-5xl flex-wrap items-center justify-between gap-3 px-6 py-4">
                <Link
                    href={home()}
                    className="inline-flex items-center gap-2.5 font-semibold tracking-tight"
                >
                    <AppLogoIcon className="size-7" />
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
            <div className="mx-auto max-w-5xl px-6 pb-4">
                <DemoNotice />
            </div>
        </header>
    );
}
