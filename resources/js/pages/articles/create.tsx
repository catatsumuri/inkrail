import { lang } from '@erag/lang-sync-inertia/react';
import { Head, setLayoutProps } from '@inertiajs/react';
import {
    create,
    index,
} from '@/actions/App/Http/Controllers/ArticleController';
import ArticleForm from '@/components/article-form';

export default function Create({ namespace }: { namespace?: string | null }) {
    const { __ } = lang();
    const isSandbox = namespace === 'sandbox';
    const title = __(isSandbox ? 'Write a sandbox note' : 'Create article');
    setLayoutProps({
        breadcrumbs: [
            {
                title: __(isSandbox ? 'Sandbox' : 'My articles'),
                href: index({ query: { namespace } }),
            },
            {
                title,
                href: create({ query: { namespace } }),
            },
        ],
    });
    return (
        <>
            <Head title={title} />
            <div className="w-full space-y-6 px-4 py-6 sm:px-8 sm:py-10">
                <h1 className="text-xl font-semibold">{title}</h1>
                {isSandbox && (
                    <p className="text-sm text-muted-foreground">
                        {__(
                            'Only you can view these notes. Publishing is disabled.',
                        )}
                    </p>
                )}
                <ArticleForm namespace={namespace} />
            </div>
        </>
    );
}
