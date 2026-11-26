import ArticlePublicationTime from '@/components/article-publication-time';
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
import { handleSaveShortcut } from '@/lib/save-shortcut';
import MarkdownEditor from '@/components/markdown-editor';
import TranslationStatus from '@/components/translation-status';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { Article } from '@/types/article';

export default function ArticleForm({
    article,
    namespace,
}: {
    article?: Article;
    namespace?: string | null;
}) {
    const isSandbox = article?.is_sandbox || namespace === 'sandbox';
    const { __ } = lang();
    const { auth, locale, articleLocales: configuredLocales } = usePage().props;
    const articleLocales = isSandbox ? ['ja'] : configuredLocales;
    const [publicationMode, setPublicationMode] = useState(
        article?.is_published
            ? article.published_at
                ? 'date'
                : 'now'
            : 'draft',
    );
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
    const modifierHeldOnSave = useRef(false);
    const saveButtonRef = useRef<HTMLButtonElement>(null);
    const [modifierHeld, setModifierHeld] = useState(false);
    const modifierKeyLabel = /Mac|iPhone|iPad/.test(navigator.platform)
        ? '⌘'
        : 'Ctrl';
    const formId = useId();
    const [saveButtonTarget, setSaveButtonTarget] = useState<Element | null>(
        null,
    );

    useEffect(() => {
        function saveWithKeyboard(event: KeyboardEvent) {
            modifierHeldOnSave.current = false;
            handleSaveShortcut(event, saveButtonRef.current);
        }

        window.addEventListener('keydown', saveWithKeyboard, true);

        return () =>
            window.removeEventListener('keydown', saveWithKeyboard, true);
    }, []);

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
    useEffect(() => {
        function syncModifier(event: KeyboardEvent) {
            setModifierHeld(event.ctrlKey || event.metaKey);
        }

        function resetModifier() {
            setModifierHeld(false);
        }

        window.addEventListener('keydown', syncModifier);
        window.addEventListener('keyup', syncModifier);
        window.addEventListener('blur', resetModifier);

        return () => {
            window.removeEventListener('keydown', syncModifier);
            window.removeEventListener('keyup', syncModifier);
            window.removeEventListener('blur', resetModifier);
        };
    }, []);
    const [{ jumpTo, jumpLocale, returnTo, returnHeading }] = useState(() => {
        const params = new URLSearchParams(window.location.search);
        const jump = params.get('jump');
        const returnPath = params.get('return_to');

        return {
            returnHeading: params.get('return_heading') ?? undefined,
            returnTo:
                returnPath !== null &&
                returnPath.startsWith('/') &&
                !returnPath.startsWith('//')
                    ? returnPath
                    : undefined,
            jumpTo: jump === null || jump === '' ? undefined : Number(jump),
            jumpLocale: params.get('locale') ?? undefined,
        };
    });
    const [editingLocale, setEditingLocale] = useState(() => {
        const requested = jumpLocale ?? locale;
        const preferred =
            article?.translations?.[requested] === undefined &&
            article?.resolvedLocale !== undefined
                ? article.resolvedLocale
                : requested;

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
                ...(namespace === 'sandbox' ? { namespace: 'sandbox' } : {}),
                ...(isSandbox
                    ? {
                          title: (
                              data.translations as Record<
                                  string,
                                  { title: string; content: string }
                              >
                          ).ja.title,
                          content: (
                              data.translations as Record<
                                  string,
                                  { title: string; content: string }
                              >
                          ).ja.content,
                          translations: undefined,
                      }
                    : {}),
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
                    (submitter.value === 'finish' || modifierHeldOnSave.current)
                );
                modifierHeldOnSave.current = false;
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
                    {returnTo && (
                        <input
                            type="hidden"
                            name="return_to"
                            value={returnTo}
                        />
                    )}
                    {returnTo && returnHeading && (
                        <input
                            type="hidden"
                            name="return_heading"
                            value={returnHeading}
                        />
                    )}
                    {article && (
                        <>
                            <input
                                type="hidden"
                                name="edit_version"
                                value={loadedVersion}
                            />
                            {!article.is_sandbox && (
                                <TranslationStatus
                                    articleId={article.id}
                                    states={article.translationStatus}
                                />
                            )}
                        </>
                    )}
                    <InputError message={errors.edit_version} />
                    {!isSandbox && (
                        <LanguageToggle
                            locales={articleLocales}
                            value={editingLocale}
                            onValueChange={setEditingLocale}
                            label={__('Article language')}
                            states={article?.translationStatus}
                        />
                    )}
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
                                    allowImages={!isSandbox}
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
                    {!isSandbox && (
                        <ArticleEyecatchInput
                            defaultValue={article?.eyecatch}
                            error={errors.eyecatch}
                            onUploadingChange={setUploadingEyecatch}
                        />
                    )}
                    {!auth.user?.is_sandbox && !isSandbox && (
                        <div className="grid gap-2">
                            <Label htmlFor="publication_mode">
                                {__('Publication')}
                            </Label>
                            <select
                                id="publication_mode"
                                value={publicationMode}
                                onChange={(event) =>
                                    setPublicationMode(event.target.value)
                                }
                                className="h-9 rounded-md border bg-background px-3"
                            >
                                <option value="draft">{__('Draft')}</option>
                                <option value="now">{__('Publish now')}</option>
                                <option value="date">
                                    {__('Choose publication date')}
                                </option>
                            </select>
                            <input
                                type="hidden"
                                name="is_published"
                                value={publicationMode === 'draft' ? '0' : '1'}
                            />
                            {publicationMode === 'date' ? (
                                <>
                                    <Label htmlFor="published_at">
                                        {__('Publication date (JST)')}
                                    </Label>
                                    <Input
                                        id="published_at"
                                        name="published_at"
                                        type="datetime-local"
                                        required
                                        defaultValue={
                                            article?.published_at
                                                ? new Date(
                                                      new Date(
                                                          article.published_at,
                                                      ).getTime() +
                                                          9 * 3600000,
                                                  )
                                                      .toISOString()
                                                      .slice(0, 16)
                                                : ''
                                        }
                                    />
                                </>
                            ) : (
                                <input
                                    type="hidden"
                                    name="published_at"
                                    value=""
                                />
                            )}
                            {article?.published_at &&
                                article.publication_status === 'scheduled' && (
                                    <ArticlePublicationTime
                                        date={article.published_at}
                                        scheduled
                                    />
                                )}
                            <InputError message={errors.published_at} />
                            <p className="text-sm text-muted-foreground">
                                {__(
                                    'Changing a published article to a future date hides it until that date.',
                                )}
                            </p>
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
                            <Link
                                href={
                                    (returnTo
                                        ? `${returnTo}${returnHeading ? `#${encodeURIComponent(returnHeading)}` : ''}`
                                        : undefined) ??
                                    (article
                                        ? show(article.id)
                                        : isSandbox
                                          ? index({
                                                query: { namespace: 'sandbox' },
                                            })
                                          : index())
                                }
                            >
                                <ArrowLeft aria-hidden="true" />
                                {__('Back without saving')}
                            </Link>
                        </Button>
                    </div>
                    {saveButtonTarget &&
                        createPortal(
                            <>
                                <span className="pointer-events-none fixed right-4 bottom-[4.75rem] z-40 hidden rounded-full bg-background/80 px-3 py-1 text-xs text-muted-foreground shadow-sm backdrop-blur sm:right-8 sm:bottom-[5.25rem] pointer-fine:block">
                                    {__(
                                        ':key+S to save · :key+click to save and return',
                                        {
                                            key: modifierKeyLabel,
                                        },
                                    )}
                                </span>
                                <Button
                                    type="submit"
                                    value="continue"
                                    ref={saveButtonRef}
                                    form={formId}
                                    onClick={(event) => {
                                        modifierHeldOnSave.current =
                                            event.ctrlKey || event.metaKey;
                                    }}
                                    disabled={processing || uploadingEyecatch}
                                    className="fixed right-4 bottom-4 z-40 h-12 min-w-40 rounded-full bg-blue-600 px-6 text-white shadow-lg shadow-blue-600/20 hover:bg-blue-700 focus-visible:ring-blue-500/50 sm:right-8 sm:bottom-6 dark:bg-blue-500 dark:hover:bg-blue-600"
                                    title={__(
                                        'Save and keep editing (Ctrl+S to save, Ctrl+click to save and return)',
                                    )}
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
                                    ) : modifierHeld ? (
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
                                                : modifierHeld
                                                  ? __('Save and return')
                                                  : __('Save')}
                                    </span>
                                </Button>
                            </>,
                            saveButtonTarget,
                        )}
                </>
            )}
        </Form>
    );
}
