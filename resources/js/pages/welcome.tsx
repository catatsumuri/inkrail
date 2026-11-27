import SocialHead, { type SocialMetadata } from '@/components/social-head';
import LocaleSwitcher from '@/components/locale-switcher';
import { lang } from '@erag/lang-sync-inertia/react';
import { Link, usePage } from '@inertiajs/react';
import { ArrowRight, BookOpen, Languages, PenLine } from 'lucide-react';
import { index as myArticles } from '@/actions/App/Http/Controllers/ArticleController';
import { index as publicArticles } from '@/actions/App/Http/Controllers/PublicArticleController';
import { Button } from '@/components/ui/button';
import AppLogoIcon from '@/components/app-logo-icon';
import DemoNotice from '@/components/demo-notice';
import { login } from '@/routes';
/* @chisel-registration */
import { register } from '@/routes';
/* @end-chisel-registration */

export default function Welcome({
    publicIndexEnabled,
    social,
}: {
    publicIndexEnabled: boolean;
    social: SocialMetadata;
}) {
    const { auth, features } = usePage().props;
    const { __ } = lang();

    return (
        <>
            <SocialHead social={social} />
            <div className="min-h-screen bg-background text-foreground">
                <header className="mx-auto flex max-w-5xl flex-wrap items-center justify-between gap-4 px-6 py-6">
                    <span className="inline-flex items-center gap-2.5 text-xl font-semibold tracking-tight">
                        <AppLogoIcon className="size-8" />
                        InkRail
                    </span>
                    <nav
                        aria-label={__('Main navigation')}
                        className="flex flex-wrap items-center gap-2"
                    >
                        <LocaleSwitcher />
                        {publicIndexEnabled && (
                            <Button asChild variant="ghost">
                                <Link href={publicArticles()}>
                                    {__('Public articles')}
                                </Link>
                            </Button>
                        )}
                        {auth.user ? (
                            <Button asChild variant="outline">
                                <Link href={myArticles()}>
                                    {__('Back to my articles')}
                                </Link>
                            </Button>
                        ) : (
                            <>
                                <Button asChild variant="outline">
                                    <Link href={login()}>{__('Log in')}</Link>
                                </Button>
                                {/* @chisel-registration */}
                                {features.registration && (
                                    <Button asChild variant="ghost">
                                        <Link href={register()}>
                                            {__('Register')}
                                        </Link>
                                    </Button>
                                )}
                                {/* @end-chisel-registration */}
                            </>
                        )}
                    </nav>
                </header>
                <main className="mx-auto max-w-5xl px-6 pb-16">
                    <DemoNotice />
                    <section className="flex max-w-3xl flex-col items-start gap-7 py-16 sm:py-24">
                        <p className="text-sm font-medium tracking-widest text-muted-foreground">
                            {__('Writing and publishing')}
                        </p>
                        <h1 className="text-4xl leading-tight font-semibold tracking-tight text-balance sm:text-6xl">
                            {__('Give your ideas a place to grow.')}
                        </h1>
                        <p className="max-w-xl text-lg leading-relaxed text-pretty text-muted-foreground">
                            {__(
                                'Write in Markdown, keep your drafts private, and publish articles in English and Japanese when they are ready.',
                            )}
                        </p>
                        <div className="flex flex-wrap gap-3">
                            {auth.user ? (
                                <Button asChild size="lg">
                                    <Link href={myArticles()}>
                                        <PenLine aria-hidden="true" />
                                        {__('Manage my articles')}
                                        <ArrowRight aria-hidden="true" />
                                    </Link>
                                </Button>
                            ) : (
                                <>
                                    {/* @chisel-registration */}
                                    <Button asChild size="lg">
                                        <Link href={register()}>
                                            <PenLine aria-hidden="true" />
                                            {__('Start writing')}
                                            <ArrowRight aria-hidden="true" />
                                        </Link>
                                    </Button>
                                    {/* @end-chisel-registration */}
                                </>
                            )}
                            {publicIndexEnabled && (
                                <Button asChild size="lg" variant="outline">
                                    <Link href={publicArticles()}>
                                        <BookOpen aria-hidden="true" />
                                        {__('Explore public articles')}
                                    </Link>
                                </Button>
                            )}
                        </div>
                    </section>
                    <section
                        aria-label={__('Writing and publishing')}
                        className="grid gap-6 border-t pt-8 sm:grid-cols-3"
                    >
                        <div className="space-y-3">
                            <PenLine
                                aria-hidden="true"
                                className="size-5 text-muted-foreground"
                            />
                            <h2 className="font-semibold">
                                {__('A space for your drafts')}
                            </h2>
                            <p className="text-sm leading-relaxed text-muted-foreground">
                                {__(
                                    'Save your thoughts and return to edit them whenever you like.',
                                )}
                            </p>
                        </div>
                        <div className="space-y-3">
                            <Languages
                                aria-hidden="true"
                                className="size-5 text-muted-foreground"
                            />
                            <h2 className="font-semibold">
                                {__('Write in English and Japanese')}
                            </h2>
                            <p className="text-sm leading-relaxed text-muted-foreground">
                                {__(
                                    'Keep both language versions in one article, with a shared URL and publication status.',
                                )}
                            </p>
                        </div>
                        <div className="space-y-3">
                            <BookOpen
                                aria-hidden="true"
                                className="size-5 text-muted-foreground"
                            />
                            <h2 className="font-semibold">
                                {__('Share when you are ready')}
                            </h2>
                            <p className="text-sm leading-relaxed text-muted-foreground">
                                {__(
                                    'Publish an article to share its URL, or return it to a private draft at any time.',
                                )}
                            </p>
                        </div>
                    </section>
                </main>
            </div>
        </>
    );
}
