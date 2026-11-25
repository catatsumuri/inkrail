import { lang } from '@erag/lang-sync-inertia/react';
import { Head, setLayoutProps } from '@inertiajs/react';
import {
    create,
    index,
} from '@/actions/App/Http/Controllers/ArticleController';
import ArticleForm from '@/components/article-form';

export default function Create() {
    const { __ } = lang();
    setLayoutProps({
        breadcrumbs: [
            { title: __('Articles'), href: index() },
            { title: __('Create article'), href: create() },
        ],
    });
    return (
        <>
            <Head title={__('Create article')} />
            <div className="w-full space-y-6 px-4 py-6 sm:px-8 sm:py-10">
                <h1 className="text-xl font-semibold">
                    {__('Create article')}
                </h1>
                <ArticleForm />
            </div>
        </>
    );
}
