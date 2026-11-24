import {
    InkstreamMarkdown,
    type GenerativeUiEvent,
} from '@catatsumuri/inkstream/react';
import { lang } from '@erag/lang-sync-inertia/react';
import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { stream } from '@/actions/App/Http/Controllers/GenerativeUiDemoController';
import AppLogoIcon from '@/components/app-logo-icon';
import LocaleSwitcher from '@/components/locale-switcher';
import { Button } from '@/components/ui/button';
import { home } from '@/routes';

type Segment = { text: string; streaming: boolean };

type FormValues = { period: string; threshold: number; archived: boolean };

const defaultValues: FormValues = {
    period: 'week',
    threshold: 65,
    archived: false,
};

function xsrfToken(): string {
    const token = document.cookie
        .split('; ')
        .find((cookie) => cookie.startsWith('XSRF-TOKEN='))
        ?.slice('XSRF-TOKEN='.length);

    return decodeURIComponent(token ?? '');
}

/**
 * Reads a Server-Sent Events response and calls onChunk with each `chunk`
 * event's text. Multi-line data fields are rejoined with newlines.
 */
async function readEventStream(
    body: Record<string, unknown>,
    onChunk: (text: string) => void,
): Promise<void> {
    const response = await fetch(stream.url(), {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'text/event-stream',
            'X-XSRF-TOKEN': xsrfToken(),
        },
        body: JSON.stringify(body),
    });

    if (!response.ok || response.body === null) {
        throw new Error(`Stream failed with status ${response.status}`);
    }

    const reader = response.body
        .pipeThrough(new TextDecoderStream())
        .getReader();
    let buffer = '';

    for (;;) {
        const { done, value } = await reader.read();

        if (done) {
            break;
        }

        buffer += value;
        let boundary = buffer.indexOf('\n\n');

        while (boundary !== -1) {
            const raw = buffer.slice(0, boundary);
            buffer = buffer.slice(boundary + 2);
            boundary = buffer.indexOf('\n\n');

            let event = 'message';
            const data: string[] = [];

            for (const line of raw.split('\n')) {
                if (line.startsWith('event:')) {
                    event = line.slice(6).trim();
                } else if (line.startsWith('data:')) {
                    data.push(line.slice(5).replace(/^ /, ''));
                }
            }

            if (event === 'chunk') {
                onChunk(data.join('\n'));
            }
        }
    }
}

export default function Demo() {
    const { __ } = lang();
    const [intro, setIntro] = useState<Segment>({ text: '', streaming: true });
    const [report, setReport] = useState<Segment | null>(null);
    const [values, setValues] = useState<FormValues>(defaultValues);
    const [failed, setFailed] = useState(false);
    const started = useRef(false);
    const busy = useRef(false);

    useEffect(() => {
        if (started.current) {
            return;
        }

        started.current = true;
        busy.current = true;

        readEventStream({ action: 'intro' }, (text) =>
            setIntro((segment) => ({ ...segment, text: segment.text + text })),
        )
            .catch(() => setFailed(true))
            .finally(() => {
                busy.current = false;
                setIntro((segment) => ({ ...segment, streaming: false }));
            });
    }, []);

    const handleEvent = useCallback((event: GenerativeUiEvent) => {
        if (event.type === 'change') {
            setValues((current) => ({
                ...current,
                [event.id]: event.value,
            }));

            return;
        }

        if (
            event.type !== 'submit' ||
            event.action !== 'build-report' ||
            busy.current
        ) {
            return;
        }

        busy.current = true;
        setReport({ text: '', streaming: true });

        readEventStream(
            { action: 'build-report', values: event.values },
            (text) =>
                setReport((segment) =>
                    segment === null
                        ? segment
                        : { ...segment, text: segment.text + text },
                ),
        )
            .catch(() => setFailed(true))
            .finally(() => {
                busy.current = false;
                setReport((segment) =>
                    segment === null
                        ? segment
                        : { ...segment, streaming: false },
                );
            });
    }, []);

    return (
        <>
            <Head title={__('Generative UI demo')} />
            <div className="min-h-screen bg-background text-foreground">
                <header className="mx-auto flex max-w-3xl flex-wrap items-center justify-between gap-4 px-6 py-6">
                    <Link
                        href={home()}
                        className="inline-flex items-center gap-2.5 text-xl font-semibold tracking-tight"
                    >
                        <AppLogoIcon className="size-8" />
                        InkRail
                    </Link>
                    <nav className="flex items-center gap-2">
                        <LocaleSwitcher />
                        <Button asChild variant="ghost">
                            <Link href={home()}>
                                <ArrowLeft aria-hidden="true" />
                                {__('Back')}
                            </Link>
                        </Button>
                    </nav>
                </header>
                <main className="mx-auto max-w-3xl px-6 pb-24">
                    <p className="text-sm font-medium tracking-widest text-muted-foreground">
                        {__('Live demo')}
                    </p>
                    <h1 className="mt-2 mb-8 text-3xl font-semibold tracking-tight sm:text-4xl">
                        {__('Generative UI demo')}
                    </h1>
                    {failed && (
                        <p
                            role="alert"
                            className="mb-6 text-sm text-destructive"
                        >
                            {__(
                                'The stream was interrupted. Reload to try again.',
                            )}
                        </p>
                    )}
                    <InkstreamMarkdown
                        generativeUi
                        streaming={intro.streaming}
                        streamingMode="block"
                        streamingUpdateInterval={50}
                        generativeUiValues={values}
                        onGenerativeUiEvent={handleEvent}
                    >
                        {intro.text}
                    </InkstreamMarkdown>
                    {report !== null && (
                        <InkstreamMarkdown
                            generativeUi
                            streaming={report.streaming}
                            streamingMode="block"
                            streamingUpdateInterval={50}
                        >
                            {report.text}
                        </InkstreamMarkdown>
                    )}
                </main>
            </div>
        </>
    );
}
