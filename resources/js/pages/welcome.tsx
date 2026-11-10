import LocaleSwitcher from '@/components/locale-switcher';
import { lang } from '@erag/lang-sync-inertia/react';
import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowRight, BookOpen, Code2, Layers } from 'lucide-react';
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
}: {
    publicIndexEnabled: boolean;
}) {
    const { auth } = usePage().props;
    const { __ } = lang();

    return (
        <>
            <Head title={__('Inkstream live demo')} />
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
                                <Button asChild variant="ghost">
                                    <Link href={register()}>
                                        {__('Register')}
                                    </Link>
                                </Button>
                                {/* @end-chisel-registration */}
                            </>
                        )}
                    </nav>
                </header>
                <main className="mx-auto max-w-5xl px-6 pb-16">
                    <DemoNotice />
                    <section className="flex max-w-3xl flex-col items-start gap-7 py-16 sm:py-24">
                        <p className="text-sm font-medium tracking-widest text-muted-foreground">
                            Inkstream · Live demo
                        </p>
                        <h1 className="text-4xl leading-tight font-semibold tracking-tight text-balance sm:text-6xl">
                            {__('See Markdown come to life.')}
                        </h1>
                        <p className="max-w-xl text-lg leading-relaxed text-pretty text-muted-foreground">
                            {__(
                                'InkRail is a live showcase of Inkstream. Explore Markdown, GitHub, Zenn, and Mintlify syntax alongside their rendered results.',
                            )}
                        </p>
                        <div className="flex flex-wrap gap-3">
                            {publicIndexEnabled && (
                                <Button asChild size="lg">
                                    <Link href={publicArticles()}>
                                        <BookOpen aria-hidden="true" />
                                        {__('Explore the demos')}
                                        <ArrowRight aria-hidden="true" />
                                    </Link>
                                </Button>
                            )}
                            {auth.user ? (
                                <Button
                                    asChild
                                    size="lg"
                                    variant={
                                        publicIndexEnabled
                                            ? 'outline'
                                            : 'default'
                                    }
                                >
                                    <Link href={myArticles()}>
                                        {__('Manage my articles')}
                                    </Link>
                                </Button>
                            ) : (
                                <Button asChild size="lg" variant="outline">
                                    <a href="https://github.com/catatsumuri/inkstream">
                                        {__('View Inkstream on GitHub')}
                                        <ArrowRight aria-hidden="true" />
                                    </a>
                                </Button>
                            )}
                        </div>
                    </section>
                    <section
                        aria-label={__('Inside the showcase')}
                        className="grid gap-6 border-t pt-8 sm:grid-cols-2"
                    >
                        <div className="space-y-3">
                            <Layers
                                aria-hidden="true"
                                className="size-5 text-muted-foreground"
                            />
                            <h2 className="font-semibold">
                                {__('One engine, multiple flavors')}
                            </h2>
                            <p className="text-sm leading-relaxed text-muted-foreground">
                                {__(
                                    'From everyday Markdown to Zenn disclosures and Mintlify components, see how different syntax works together.',
                                )}
                            </p>
                        </div>
                        <div className="space-y-3">
                            <Code2
                                aria-hidden="true"
                                className="size-5 text-muted-foreground"
                            />
                            <h2 className="font-semibold">
                                {__('Source and rendering, side by side')}
                            </h2>
                            <p className="text-sm leading-relaxed text-muted-foreground">
                                {__(
                                    'Each guide pairs copyable syntax examples with live previews, including diagrams, charts, trees, and quizzes.',
                                )}
                            </p>
                        </div>
                    </section>
                </main>
            </div>
        </>
    );
}
