import { lang } from '@erag/lang-sync-inertia/react';
import { Form, Head, Link, setLayoutProps } from '@inertiajs/react';
import {
    destroy,
    edit,
    index,
    show,
} from '@/actions/App/Http/Controllers/ArticleController';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import type { Article } from '@/types/article';

export default function Show({ article }: { article: Article }) {
    const { __ } = lang();
    setLayoutProps({
        breadcrumbs: [
            { title: '記事', href: index() },
            { title: article.title, href: show(article.id) },
        ],
    });
    return (
        <>
            <Head title={article.title} />
            <main className="space-y-6 p-4">
                <div className="flex items-center gap-4">
                    <Link href={index()}>一覧へ</Link>
                    <Button asChild variant="outline">
                        <Link href={edit(article.id)}>編集</Link>
                    </Button>
                    <Dialog>
                        <DialogTrigger asChild>
                            <Button type="button" variant="destructive">
                                {__('Delete article')}
                            </Button>
                        </DialogTrigger>
                        <DialogContent>
                            <DialogTitle>
                                {__('Delete this article?')}
                            </DialogTitle>
                            <DialogDescription>
                                {__(
                                    'This article will be permanently deleted. This cannot be undone.',
                                )}
                            </DialogDescription>
                            <Form {...destroy.form(article.id)}>
                                {({ processing }) => (
                                    <DialogFooter className="gap-2">
                                        <DialogClose asChild>
                                            <Button
                                                type="button"
                                                variant="secondary"
                                            >
                                                {__('Cancel')}
                                            </Button>
                                        </DialogClose>
                                        <Button
                                            type="submit"
                                            variant="destructive"
                                            disabled={processing}
                                        >
                                            {__('Delete article')}
                                        </Button>
                                    </DialogFooter>
                                )}
                            </Form>
                        </DialogContent>
                    </Dialog>
                </div>
                <h1 className="text-xl font-semibold break-all">
                    {article.title}
                </h1>
                <div className="max-w-3xl break-words whitespace-pre-wrap">
                    {article.content}
                </div>
            </main>
        </>
    );
}
