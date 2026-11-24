import { lang } from '@erag/lang-sync-inertia/react';
import { useEffect, useId, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { ArrowLeft, Check, LoaderCircle, Save } from 'lucide-react';
import { Form, Link, usePage } from '@inertiajs/react';
import {
    store,
    update,
    index,
    show,
} from '@/actions/App/Http/Controllers/ArticleController';
import InputError from '@/components/input-error';
import ArticleEyecatchInput from '@/components/article-eyecatch-input';
import LanguageToggle from '@/components/language-toggle';
import MarkdownEditor from '@/components/markdown-editor';
import TranslationStatus from '@/components/translation-status';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { Article } from '@/types/article';

export default function ArticleForm({ article }: { article?: Article }) {
    const { __ } = lang();
    const { auth, locale, articleLocales } = usePage().props;
    const [uploadingEyecatch, setUploadingEyecatch] = useState(false);
    const [topicsInput, setTopicsInput] = useState(
        article?.topics?.join(', ') ?? '',
    );
    const topics = topicsInput
        .split(',')
        .map((topic) => topic.trim())
        .filter(Boolean);
    const [loadedVersion, setLoadedVersion] = useState(
        article?.edit_version ?? 0,
    );
    const continueEditing = useRef(true);
    const formId = useId();
    const [saveButtonTarget, setSaveButtonTarget] = useState<Element | null>(
        null,
    );

    useEffect(() => {
        function updateSaveButtonTarget() {
            setSaveButtonTarget(document.fullscreenElement ?? document.body);
        }

        updateSaveButtonTarget();
        document.addEventListener('fullscreenchange', updateSaveButtonTarget);

        return () =>
            document.removeEventListener(
                'fullscreenchange',
                updateSaveButtonTarget,
            );
    }, []);
    const [{ jumpTo, jumpLocale }] = useState(() => {
        const params = new URLSearchParams(window.location.search);
        const jump = params.get('jump');

        return {
            jumpTo: jump === null || jump === '' ? undefined : Number(jump),
            jumpLocale: params.get('locale') ?? undefined,
        };
    });
    const [editingLocale, setEditingLocale] = useState(() => {
        const preferred = jumpLocale ?? locale;

        return articleLocales.includes(preferred)
            ? preferred
            : articleLocales[0];
    });
    return (
        <Form
            id={formId}
            {...(article ? update.form(article.id) : store.form())}
            transform={({ topics_input, ...data }) => ({
                ...data,
                continue_editing: continueEditing.current,
                topics: String(topics_input ?? '')
                    .split(',')
                    .map((topic) => topic.trim())
                    .filter(Boolean),
            })}
            data-article-form
            options={{ preserveScroll: () => continueEditing.current }}
            onSubmitCapture={(event) => {
                const submitter = (event.nativeEvent as SubmitEvent).submitter;
                continueEditing.current = !(
                    submitter instanceof HTMLButtonElement &&
                    submitter.value === 'finish'
                );
            }}
            onSuccess={(page) => {
                const savedArticle = page.props.article as Article | undefined;
                if (
                    savedArticle?.id === article?.id &&
                    savedArticle?.edit_version !== undefined
                ) {
                    setLoadedVersion(savedArticle.edit_version);
                }
            }}
            onError={(errors) => {
                const invalidLanguage = articleLocales.find((language) =>
                    Object.keys(errors).some((key) =>
                        key.startsWith(`translations.${language}.`),
                    ),
                );
                if (invalidLanguage) setEditingLocale(invalidLanguage);
            }}
            className="grid gap-6 pb-24"
        >
            {({ errors, processing, recentlySuccessful }) => (
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
                                    jumpTo={
                                        language === jumpLocale
                                            ? jumpTo
                                            : undefined
                                    }
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
                    <div className="grid max-w-3xl gap-2">
                        <Label htmlFor="topics">{__('Topics')}</Label>
                        <Input
                            id="topics"
                            name="topics_input"
                            value={topicsInput}
                            onChange={(event) =>
                                setTopicsInput(event.target.value)
                            }
                            placeholder="zenn, github"
                            aria-invalid={
                                Boolean(errors.topics) ||
                                Object.keys(errors).some((key) =>
                                    key.startsWith('topics.'),
                                )
                            }
                            aria-describedby="topics-help topics-error"
                        />
                        <p
                            id="topics-help"
                            className="text-sm text-muted-foreground"
                        >
                            {__(
                                'Separate topics with commas. Add up to 5 topics, shared across article languages.',
                            )}
                        </p>
                        {topics.length > 0 && (
                            <div className="flex flex-wrap gap-2">
                                {topics.map((topic, position) => (
                                    <Badge key={position} variant="secondary">
                                        {topic}
                                    </Badge>
                                ))}
                            </div>
                        )}
                        <InputError
                            id="topics-error"
                            message={
                                errors.topics ??
                                Object.entries(errors).find(([key]) =>
                                    key.startsWith('topics.'),
                                )?.[1]
                            }
                        />
                    </div>
                    <ArticleEyecatchInput
                        defaultValue={article?.eyecatch}
                        error={errors.eyecatch}
                        onUploadingChange={setUploadingEyecatch}
                    />
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
                    <div className="flex flex-wrap items-center gap-3 border-t pt-6">
                        <Button
                            type="submit"
                            value="finish"
                            disabled={processing || uploadingEyecatch}
                        >
                            <Check aria-hidden="true" />
                            {__('Save and view article')}
                        </Button>
                        <Button asChild variant="outline">
                            <Link href={article ? show(article.id) : index()}>
                                <ArrowLeft aria-hidden="true" />
                                {__('Back without saving')}
                            </Link>
                        </Button>
                    </div>
                    {saveButtonTarget &&
                        createPortal(
                            <Button
                                type="submit"
                                value="continue"
                                form={formId}
                                disabled={processing || uploadingEyecatch}
                                className="fixed right-4 bottom-4 z-40 h-12 min-w-40 rounded-full bg-blue-600 px-6 text-white shadow-lg shadow-blue-600/20 hover:bg-blue-700 focus-visible:ring-blue-500/50 sm:right-8 sm:bottom-6 dark:bg-blue-500 dark:hover:bg-blue-600"
                                title={__('Save and keep editing')}
                            >
                                {processing ? (
                                    <LoaderCircle
                                        aria-hidden="true"
                                        className="animate-spin"
                                    />
                                ) : Object.keys(errors).length > 0 ? (
                                    <Save aria-hidden="true" />
                                ) : recentlySuccessful ? (
                                    <Check aria-hidden="true" />
                                ) : (
                                    <Save aria-hidden="true" />
                                )}
                                <span aria-live="polite">
                                    {processing
                                        ? __('Saving…')
                                        : Object.keys(errors).length > 0
                                          ? __('Save failed')
                                          : recentlySuccessful
                                            ? __('Saved')
                                            : __('Save')}
                                </span>
                            </Button>,
                            saveButtonTarget,
                        )}
                </>
            )}
        </Form>
    );
}
