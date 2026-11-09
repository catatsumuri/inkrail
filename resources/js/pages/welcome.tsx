import { lang } from '@erag/lang-sync-inertia/react';
import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowRight, BookOpen, FileText, Send } from 'lucide-react';
import { index as myArticles } from '@/actions/App/Http/Controllers/ArticleController';
import { index as publicArticles } from '@/actions/App/Http/Controllers/PublicArticleController';
import { Button } from '@/components/ui/button';
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
            <Head title={__('Welcome to InkRail')} />
            <div className="min-h-screen bg-background text-foreground">
                <header className="mx-auto flex max-w-5xl flex-wrap items-center justify-between gap-4 px-6 py-6">
                    <span className="text-xl font-semibold tracking-tight">
                        InkRail
                    </span>
                    <nav
                        aria-label={__('Main navigation')}
                        className="flex flex-wrap items-center gap-2"
                    >
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
                    <section className="flex max-w-3xl flex-col items-start gap-7 py-16 sm:py-24">
                        <p className="text-sm font-medium tracking-widest text-muted-foreground">
                            InkRail
                        </p>
                        <h1 className="text-4xl leading-tight font-semibold tracking-tight text-balance sm:text-6xl">
                            {__('Give your ideas a place to grow.')}
                        </h1>
                        <p className="max-w-xl text-lg leading-relaxed text-pretty text-muted-foreground">
                            {__(
                                'Write at your own pace, keep drafts close, and share articles when they are ready.',
                            )}
                        </p>
                        <div className="flex flex-wrap gap-3">
                            {publicIndexEnabled && (
                                <Button asChild size="lg">
                                    <Link href={publicArticles()}>
                                        <BookOpen aria-hidden="true" />
                                        {__('Explore public articles')}
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
                                <Button
                                    asChild
                                    size="lg"
                                    variant={
                                        publicIndexEnabled
                                            ? 'outline'
                                            : 'default'
                                    }
                                >
                                    <Link href={login()}>
                                        {__('Start writing')}
                                    </Link>
                                </Button>
                            )}
                        </div>
                    </section>
                    <section
                        aria-label={__('Writing and publishing')}
                        className="grid gap-6 border-t pt-8 sm:grid-cols-2"
                    >
                        <div className="space-y-3">
                            <FileText
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
                            <Send
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
