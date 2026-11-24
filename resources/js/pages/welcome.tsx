import LocaleSwitcher from '@/components/locale-switcher';
import { lang } from '@erag/lang-sync-inertia/react';
import { Head, Link, usePage } from '@inertiajs/react';
import { BookOpen, Github, Sparkles } from 'lucide-react';
import { index as myArticles } from '@/actions/App/Http/Controllers/ArticleController';
import { index as publicArticles } from '@/actions/App/Http/Controllers/PublicArticleController';
import { Button } from '@/components/ui/button';
import AppLogoIcon from '@/components/app-logo-icon';
import DemoNotice from '@/components/demo-notice';
import { demo, login } from '@/routes';

export default function Welcome({
    publicIndexEnabled,
}: {
    publicIndexEnabled: boolean;
}) {
    const { auth } = usePage().props;
    const { __ } = lang();

    return (
        <>
            <Head title={__('InkRail Live Demo')} />
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
                            <Button asChild variant="outline">
                                <Link href={login()}>{__('Log in')}</Link>
                            </Button>
                        )}
                    </nav>
                </header>
                <main className="mx-auto max-w-5xl px-6 pb-16">
                    <section className="flex max-w-3xl flex-col items-start gap-6 py-16 sm:py-24">
                        <p className="text-sm font-medium tracking-widest text-muted-foreground">
                            {__('Live demo')}
                        </p>
                        <h1 className="text-4xl leading-tight font-semibold tracking-tight text-balance sm:text-6xl">
                            {__('This is the live-demo branch of InkRail.')}
                        </h1>
                        <p className="text-lg text-muted-foreground">
                            {__('Built with')}{' '}
                            <a
                                href="https://github.com/catatsumuri/inkstream"
                                target="_blank"
                                rel="noopener noreferrer"
                                className="font-medium text-foreground underline underline-offset-4"
                            >
                                Inkstream v1
                            </a>
                        </p>
                        <div className="flex flex-wrap gap-3">
                            {publicIndexEnabled && (
                                <Button asChild size="lg">
                                    <Link href={publicArticles()}>
                                        <BookOpen aria-hidden="true" />
                                        {__('Explore public articles')}
                                    </Link>
                                </Button>
                            )}
                            <Button asChild size="lg" variant="outline">
                                <Link href={demo()}>
                                    <Sparkles aria-hidden="true" />
                                    {__('Generative UI demo')}
                                </Link>
                            </Button>
                            <Button asChild size="lg" variant="outline">
                                <a
                                    href="https://github.com/catatsumuri/inkrail/tree/live-demo"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                >
                                    <Github aria-hidden="true" />
                                    {__('View on GitHub')}
                                </a>
                            </Button>
                        </div>
                    </section>
                    <DemoNotice />
                </main>
            </div>
        </>
    );
}
