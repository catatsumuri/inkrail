import { Form, Link } from '@inertiajs/react';
import {
    store,
    update,
    index,
    show,
} from '@/actions/App/Http/Controllers/ArticleController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { Article } from '@/types/article';

export default function ArticleForm({ article }: { article?: Article }) {
    return (
        <Form
            {...(article ? update.form(article.id) : store.form())}
            className="grid max-w-3xl gap-6"
        >
            {({ errors, processing }) => (
                <>
                    <div className="grid gap-2">
                        <Label htmlFor="title">タイトル</Label>
                        <Input
                            id="title"
                            name="title"
                            defaultValue={article?.title}
                            required
                            maxLength={255}
                            autoFocus
                            aria-invalid={Boolean(errors.title)}
                        />
                        <InputError message={errors.title} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="content">本文</Label>
                        <textarea
                            id="content"
                            name="content"
                            defaultValue={article?.content}
                            className="min-h-80 w-full rounded-md border bg-background p-3 font-mono text-sm"
                            aria-invalid={Boolean(errors.content)}
                        />
                        <InputError message={errors.content} />
                    </div>
                    <div className="flex items-center gap-4">
                        <Button disabled={processing}>保存</Button>
                        <Link href={article ? show(article.id) : index()}>
                            キャンセル
                        </Link>
                    </div>
                </>
            )}
        </Form>
    );
}
