import LocaleSwitcher from '@/components/locale-switcher';
import { lang } from '@erag/lang-sync-inertia/react';
import { Form, Head, Link, usePage } from '@inertiajs/react';
import { ArrowRight, Check, Copy, Github, ArrowUpRight } from 'lucide-react';
import { index as myArticles } from '@/actions/App/Http/Controllers/ArticleController';
import {
    index as publicArticles,
    show as publicArticle,
} from '@/actions/App/Http/Controllers/PublicArticleController';
import { Button } from '@/components/ui/button';
import AppLogoIcon from '@/components/app-logo-icon';
import { start } from '@/routes/sandbox';
import { useClipboard } from '@/hooks/use-clipboard';
import { demo, login } from '@/routes';

const repositoryUrl = 'https://github.com/catatsumuri/inkrail';

const quickStart = `git clone -b live-demo \\
  ${repositoryUrl}.git
cd inkrail
cp .env.example .env
composer install
vendor/bin/sail up -d
vendor/bin/sail composer setup
vendor/bin/sail npm run dev`;

const links = {
    laravel: 'https://laravel.com/',
    inertia: 'https://inertiajs.com/',
    inkstream: 'https://github.com/catatsumuri/inkstream',
    branch: `${repositoryUrl}/tree/live-demo`,
};

const coreStack = [
    { name: 'Laravel 13', href: links.laravel },
    { name: 'Inertia.js 3', href: links.inertia },
    { name: 'Inkstream 1', href: links.inkstream },
];

function TextLink({ href, children }: { href: string; children: string }) {
    return (
        <a
            href={href}
            target="_blank"
            rel="noopener noreferrer"
            className="font-medium text-foreground underline underline-offset-4"
        >
            {children}
        </a>
    );
}

const supportingStack = [
    'React 19',
    'TypeScript',
    'Tailwind CSS 4',
    'Vite',
    'SQLite',
];

export default function Welcome({
    publicIndexEnabled,
}: {
    publicIndexEnabled: boolean;
}) {
    const { auth, demoMode } = usePage().props;
    const { __ } = lang();
    const [copiedText, copy] = useClipboard();
    const copied = copiedText === quickStart;

    const features = [
        {
            title: __('Write in Markdown'),
            body: __(
                'Drafts stay private. Publish an article to get a shareable URL, or take it back to a draft at any time.',
            ),
        },
        {
            title: __('Two languages in one article'),
            body: __(
                'Edit both language versions side by side (English and Japanese in this demo). They share one URL and one publication status, and the header switch changes the display language.',
            ),
        },
        {
            title: __('More than plain Markdown'),
            body: __(
                'GFM tables, Zenn and Mintlify syntax, Mermaid diagrams, charts, file trees and sandboxed HTML embeds, all rendered by Inkstream.',
            ),
            link: publicIndexEnabled
                ? {
                      href: publicArticle('showcase-extensions'),
                      label: __('See the syntax showcase'),
                  }
                : undefined,
        },
    ];

    const requirements = [
        __('PHP 8.4 or later and Composer'),
        __('Docker, because the project runs on Laravel Sail (PHP 8.5)'),
        __('Node.js and npm, which Vite uses to build the React app'),
        __('SQLite works out of the box, so no database server is needed'),
    ];

    return (
        <>
            <Head title={__('InkRail Live Demo')} />
            <div className="min-h-screen bg-[#f6f5f0] text-foreground dark:bg-[#191a18]">
                <header className="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-4 border-b px-6 py-5">
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
                <main className="mx-auto max-w-6xl px-6 pb-16">
                    <section className="grid gap-12 py-12 lg:grid-cols-[1fr_1.1fr] lg:items-center lg:gap-16 lg:py-24">
                        <div className="flex flex-col items-start gap-7">
                            <p className="flex items-center gap-2 font-mono text-xs text-muted-foreground">
                                <span
                                    className="size-1.5 rounded-full bg-emerald-700 dark:bg-emerald-400"
                                    aria-hidden="true"
                                />
                                {__('InkRail Live Demo')}
                            </p>
                            <h1 className="text-4xl leading-[1.3] font-medium tracking-tight text-balance sm:text-5xl">
                                {__('A place for your next article.')}
                            </h1>
                            <p className="max-w-md text-base leading-8 text-muted-foreground">
                                {__(
                                    'Start with a few lines of Markdown. Add a diagram, write a second language, and see your article take shape.',
                                )}
                            </p>
                            <div className="flex flex-wrap items-center gap-4">
                                {auth.user ? (
                                    <Button
                                        asChild
                                        size="lg"
                                        className="rounded-sm"
                                    >
                                        <Link href={myArticles()}>
                                            {__('Back to my articles')}
                                            <ArrowRight aria-hidden="true" />
                                        </Link>
                                    </Button>
                                ) : demoMode ? (
                                    <Form {...start.form()}>
                                        {({ processing }) => (
                                            <Button
                                                size="lg"
                                                className="rounded-sm"
                                                disabled={processing}
                                            >
                                                {__('Start my sandbox')}
                                                <ArrowRight aria-hidden="true" />
                                            </Button>
                                        )}
                                    </Form>
                                ) : (
                                    <Button
                                        asChild
                                        size="lg"
                                        className="rounded-sm"
                                    >
                                        <Link href={login()}>
                                            {__('Log in')}
                                            <ArrowRight aria-hidden="true" />
                                        </Link>
                                    </Button>
                                )}
                                {publicIndexEnabled && (
                                    <Link
                                        href={publicArticles()}
                                        className="text-sm underline decoration-border underline-offset-4 hover:decoration-current"
                                    >
                                        {__('Read the example articles')}
                                    </Link>
                                )}
                            </div>
                            {demoMode && (
                                <p className="max-w-md text-xs leading-6 text-muted-foreground">
                                    {auth.user?.is_sandbox
                                        ? __('Your private sandbox')
                                        : __(
                                              'No sign-up. Your own copy to edit.',
                                          )}
                                    <br />
                                    {__(
                                        'Demo data resets daily at 08:00 and 20:00 Japan time, and on deployment.',
                                    )}
                                </p>
                            )}
                        </div>
                        <div className="min-w-0 border border-stone-300 bg-background shadow-[8px_8px_0_0_#e7e5df] sm:shadow-[14px_14px_0_0_#e7e5df] dark:border-stone-700 dark:shadow-[8px_8px_0_0_#292724]">
                            <div className="flex items-center justify-between border-b px-5 py-3 font-mono text-xs text-muted-foreground">
                                <span>my-first-article.md</span>
                                <span>{__('Preview')}</span>
                            </div>
                            <div
                                className="grid grid-cols-[2rem_1fr] gap-x-3 border-b bg-stone-50 px-5 py-6 font-mono text-xs leading-7 sm:text-sm dark:bg-stone-900/40"
                                aria-label={__('Markdown example')}
                            >
                                <div
                                    className="text-right text-muted-foreground/50"
                                    aria-hidden="true"
                                >
                                    1<br />2<br />3<br />4<br />5
                                </div>
                                <pre className="min-w-0 overflow-x-auto text-muted-foreground">
                                    <code>{`# ${__('Notes from today')}

${__('A small idea, worth writing down.')}

> ${__('Start here. See where it goes.')}`}</code>
                                </pre>
                            </div>
                            <div className="flex flex-col gap-5 px-7 py-8 sm:px-10 sm:py-10">
                                <p className="font-mono text-[10px] tracking-wider text-muted-foreground">
                                    INKRAIL / {__('Preview')}
                                </p>
                                <h2 className="font-serif text-3xl leading-tight sm:text-4xl">
                                    {__('Notes from today')}
                                </h2>
                                <p className="text-sm leading-7 text-muted-foreground">
                                    {__('A small idea, worth writing down.')}
                                </p>
                                <blockquote className="border-l-2 border-emerald-800 pl-4 font-serif text-lg italic dark:border-emerald-500">
                                    {__('Start here. See where it goes.')}
                                </blockquote>
                                {publicIndexEnabled && (
                                    <Link
                                        href={publicArticle(
                                            'showcase-extensions',
                                        )}
                                        className="mt-3 inline-flex items-center gap-2 self-start text-xs font-medium underline underline-offset-4"
                                    >
                                        {__('See what Markdown can do')}
                                        <ArrowUpRight
                                            className="size-3.5"
                                            aria-hidden="true"
                                        />
                                    </Link>
                                )}
                            </div>
                        </div>
                    </section>

                    <section className="grid gap-6 border-y border-foreground/15 py-8 sm:grid-cols-[1fr_auto] sm:items-center sm:gap-12">
                        <div className="flex max-w-2xl flex-col gap-2">
                            <h2 className="text-lg font-medium">
                                {__('Watch Markdown become interactive.')}
                            </h2>
                            <p className="text-sm leading-7 text-muted-foreground">
                                {__(
                                    'Cards, forms, and charts appear as the response streams in. Try the Generative UI demo to see it happen.',
                                )}
                            </p>
                        </div>
                        <Button
                            asChild
                            variant="outline"
                            className="justify-self-start rounded-sm"
                        >
                            <Link href={demo()}>
                                {__('Try the demo')}
                                <ArrowUpRight aria-hidden="true" />
                            </Link>
                        </Button>
                    </section>

                    <section
                        aria-labelledby="what-it-does"
                        className="grid gap-8 py-12 md:grid-cols-[14rem_1fr] md:gap-16"
                    >
                        <h2
                            id="what-it-does"
                            className="text-lg font-medium tracking-tight"
                        >
                            {__('What can you do with it?')}
                        </h2>
                        <div className="grid gap-8 divide-y divide-foreground/10">
                            {features.map((feature) => (
                                <div
                                    key={feature.title}
                                    className="flex flex-col gap-2 pb-8 last:pb-0"
                                >
                                    <h3 className="font-semibold">
                                        {feature.title}
                                    </h3>
                                    <p className="text-sm leading-relaxed text-muted-foreground">
                                        {feature.body}
                                    </p>
                                    {feature.link && (
                                        <Link
                                            href={feature.link.href}
                                            className="inline-flex items-center gap-1.5 text-sm font-medium underline underline-offset-4"
                                        >
                                            {feature.link.label}
                                            <ArrowRight
                                                aria-hidden="true"
                                                className="size-4"
                                            />
                                        </Link>
                                    )}
                                </div>
                            ))}
                        </div>
                    </section>
                    <section className="flex flex-col gap-5 border-t border-foreground/15 py-10">
                        <div className="flex flex-wrap items-center justify-between gap-4">
                            <h2 className="text-lg font-medium">
                                {__('Make it your own.')}
                            </h2>
                            <a
                                href={repositoryUrl}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="inline-flex items-center gap-2 text-sm underline underline-offset-4"
                            >
                                <Github className="size-4" aria-hidden="true" />
                                {__('Source code on GitHub')}
                                <ArrowUpRight
                                    className="size-3.5"
                                    aria-hidden="true"
                                />
                            </a>
                        </div>
                        <p className="max-w-2xl text-sm leading-7 text-muted-foreground">
                            {__('InkRail is a')}{' '}
                            <TextLink href={links.laravel}>Laravel</TextLink> +{' '}
                            <TextLink href={links.inertia}>Inertia.js</TextLink>{' '}
                            +{' '}
                            <TextLink href={links.inkstream}>
                                Inkstream
                            </TextLink>{' '}
                            {__('app. This is its ')}
                            <TextLink href={links.branch}>
                                {__('live-demo branch')}
                            </TextLink>
                            {__('. Try it now, then clone it.')}
                        </p>
                        <ul
                            aria-label={__('Technology stack')}
                            className="flex flex-wrap gap-x-5 gap-y-2 font-mono text-xs text-muted-foreground"
                        >
                            {coreStack.map((item) => (
                                <li key={item.name}>
                                    <a
                                        href={item.href}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        className="hover:underline"
                                    >
                                        {item.name}
                                    </a>
                                </li>
                            ))}
                            {supportingStack.map((name) => (
                                <li key={name}>{name}</li>
                            ))}
                        </ul>
                    </section>
                    <section
                        aria-labelledby="run-it"
                        className="grid gap-8 border-t py-10 md:grid-cols-[14rem_1fr] md:gap-12"
                    >
                        <h2
                            id="run-it"
                            className="text-lg font-medium tracking-tight"
                        >
                            {__('Run it yourself')}
                        </h2>
                        <div className="grid min-w-0 gap-6">
                            <ul className="grid gap-2 text-sm leading-relaxed text-muted-foreground">
                                {requirements.map((requirement) => (
                                    <li
                                        key={requirement}
                                        className="border-l-2 pl-3"
                                    >
                                        {requirement}
                                    </li>
                                ))}
                            </ul>
                            <div className="min-w-0 space-y-3">
                                <div className="relative rounded-md border bg-muted/30">
                                    <pre className="overflow-x-auto p-4 pr-14 font-mono text-[13px] leading-relaxed">
                                        <code>{quickStart}</code>
                                    </pre>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        className="absolute top-2 right-2"
                                        aria-label={
                                            copied
                                                ? __('Copied')
                                                : __('Copy the commands')
                                        }
                                        onClick={() => copy(quickStart)}
                                    >
                                        {copied ? (
                                            <Check aria-hidden="true" />
                                        ) : (
                                            <Copy aria-hidden="true" />
                                        )}
                                    </Button>
                                </div>
                                <p className="text-sm leading-relaxed text-muted-foreground">
                                    {__(
                                        'To load the showcase articles, set DEMO_MODE=true and run vendor/bin/sail artisan migrate:fresh --seed.',
                                    )}
                                </p>
                            </div>
                        </div>
                    </section>
                </main>
            </div>
        </>
    );
}
