import { lang } from '@erag/lang-sync-inertia/react';
import { Form, Link, usePage } from '@inertiajs/react';
import {
    store,
    update,
    index,
    show,
} from '@/actions/App/Http/Controllers/ArticleController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { Article } from '@/types/article';

export default function ArticleForm({ article }: { article?: Article }) {
    const { __ } = lang();
    const { auth } = usePage().props;
    return (
        <Form
            {...(article ? update.form(article.id) : store.form())}
            className="grid max-w-3xl gap-6"
        >
            {({ errors, processing }) => (
                <>
                    <div className="grid gap-2">
                        <Label htmlFor="title">{__('Title')}</Label>
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
                        <Label htmlFor="slug">{__('Slug')}</Label>
                        <Input
                            id="slug"
                            name="slug"
                            defaultValue={article?.slug}
                            maxLength={255}
                            pattern="[a-z0-9]+(-[a-z0-9]+)*"
                            aria-invalid={Boolean(errors.slug)}
                            aria-describedby="slug-help"
                        />
                        <p
                            id="slug-help"
                            className="text-sm text-muted-foreground"
                        >
                            {__(
                                'Use lowercase letters, numbers, and hyphens. Leave blank to keep the current slug or generate one for a new article.',
                            )}
                        </p>
                        <InputError message={errors.slug} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="content">{__('Content')}</Label>
                        <textarea
                            id="content"
                            name="content"
                            defaultValue={article?.content}
                            className="min-h-80 w-full rounded-md border bg-background p-3 font-mono text-sm"
                            aria-invalid={Boolean(errors.content)}
                        />
                        <InputError message={errors.content} />
                    </div>
                    {!auth.user?.is_sandbox && (
                        <div className="grid gap-2">
                            <div className="flex items-center gap-2">
                                <input
                                    type="hidden"
                                    name="is_published"
                                    value="0"
                                />
                                <Checkbox
                                    id="is_published"
                                    name="is_published"
                                    value="1"
                                    defaultChecked={
                                        article?.is_published ?? false
                                    }
                                    aria-invalid={Boolean(errors.is_published)}
                                />
                                <Label htmlFor="is_published">
                                    {__('Published')}
                                </Label>
                            </div>
                            <InputError message={errors.is_published} />
                        </div>
                    )}
                    <div className="flex items-center gap-4">
                        <Button disabled={processing}>{__('Save')}</Button>
                        <Link href={article ? show(article.id) : index()}>
                            {__('Cancel')}
                        </Link>
                    </div>
                </>
            )}
        </Form>
    );
}
