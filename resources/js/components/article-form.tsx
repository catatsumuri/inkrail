import { lang } from '@erag/lang-sync-inertia/react';
import { useState } from 'react';
import { Form, Link, usePage } from '@inertiajs/react';
import {
    store,
    update,
    index,
    show,
} from '@/actions/App/Http/Controllers/ArticleController';
import InputError from '@/components/input-error';
import LanguageToggle from '@/components/language-toggle';
import MarkdownEditor from '@/components/markdown-editor';
import TranslationStatus from '@/components/translation-status';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { Article } from '@/types/article';

export default function ArticleForm({ article }: { article?: Article }) {
    const { __ } = lang();
    const { auth, locale, articleLocales } = usePage().props;
    const [loadedVersion] = useState(article?.edit_version ?? 0);
    const [editingLocale, setEditingLocale] = useState(
        articleLocales.includes(locale) ? locale : articleLocales[0],
    );
    return (
        <Form
            {...(article ? update.form(article.id) : store.form())}
            data-article-form
            onError={(errors) => {
                const invalidLanguage = articleLocales.find((language) =>
                    Object.keys(errors).some((key) =>
                        key.startsWith(`translations.${language}.`),
                    ),
                );
                if (invalidLanguage) setEditingLocale(invalidLanguage);
            }}
            className="grid gap-6"
        >
            {({ errors, processing }) => (
                <>
                    {article && (
                        <>
                            <input
                                type="hidden"
                                name="edit_version"
                                value={loadedVersion}
                            />
                            <TranslationStatus
                                articleId={article.id}
                                states={article.translationStatus}
                            />
                        </>
                    )}
                    <InputError message={errors.edit_version} />
                    <LanguageToggle
                        locales={articleLocales}
                        value={editingLocale}
                        onValueChange={setEditingLocale}
                        label={__('Article language')}
                        states={article?.translationStatus}
                    />
                    <InputError message={errors.translations} />
                    {articleLocales.map((language) => (
                        <div
                            key={language}
                            hidden={editingLocale !== language}
                            className="space-y-6"
                        >
                            <div className="grid max-w-3xl gap-2">
                                <Label htmlFor={`title-${language}`}>
                                    {__('Title')}
                                </Label>
                                <Input
                                    id={`title-${language}`}
                                    name={`translations[${language}][title]`}
                                    defaultValue={
                                        article?.translations?.[language]
                                            ?.title ?? ''
                                    }
                                    maxLength={255}
                                />
                                <InputError
                                    message={
                                        errors[`translations.${language}.title`]
                                    }
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor={`content-${language}`}>
                                    {__('Content')}
                                </Label>
                                <MarkdownEditor
                                    id={`content-${language}`}
                                    name={`translations[${language}][content]`}
                                    defaultValue={
                                        article?.translations?.[language]
                                            ?.content ?? ''
                                    }
                                    invalid={Boolean(
                                        errors[
                                            `translations.${language}.content`
                                        ],
                                    )}
                                    describedBy={`content-${language}-error`}
                                    syntax={
                                        article?.slug === 'showcase-zenn'
                                            ? 'zenn'
                                            : 'all'
                                    }
                                />
                                <InputError
                                    id={`content-${language}-error`}
                                    message={
                                        errors[
                                            `translations.${language}.content`
                                        ]
                                    }
                                />
                            </div>
                        </div>
                    ))}
                    <div className="grid max-w-3xl gap-2">
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
