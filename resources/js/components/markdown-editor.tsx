import { lang } from '@erag/lang-sync-inertia/react';
import { Code2, Eye } from 'lucide-react';
import { useDeferredValue, useState } from 'react';
import ArticleContent from '@/components/article-content';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

type Props = {
    id: string;
    name: string;
    defaultValue: string;
    invalid?: boolean;
    describedBy?: string;
    syntax?: 'all' | 'zenn';
};

export default function MarkdownEditor({
    id,
    name,
    defaultValue,
    invalid = false,
    describedBy,
    syntax = 'all',
}: Props) {
    const { __ } = lang();
    const [content, setContent] = useState(defaultValue);
    const preview = useDeferredValue(content);
    const [activePanel, setActivePanel] = useState<'write' | 'preview'>(
        'write',
    );

    return (
        <div className="overflow-hidden rounded-xl border bg-background">
            <div className="flex gap-1 border-b p-2 lg:hidden">
                <Button
                    type="button"
                    variant={activePanel === 'write' ? 'secondary' : 'ghost'}
                    size="sm"
                    aria-pressed={activePanel === 'write'}
                    aria-controls={`${id}-write`}
                    onClick={() => setActivePanel('write')}
                >
                    <Code2 aria-hidden="true" />
                    {__('Markdown')}
                </Button>
                <Button
                    type="button"
                    variant={activePanel === 'preview' ? 'secondary' : 'ghost'}
                    size="sm"
                    aria-pressed={activePanel === 'preview'}
                    aria-controls={`${id}-preview`}
                    onClick={() => setActivePanel('preview')}
                >
                    <Eye aria-hidden="true" />
                    {__('Preview')}
                </Button>
            </div>
            <div className="grid min-w-0 lg:grid-cols-2">
                <div
                    id={`${id}-write`}
                    className={cn(
                        'min-w-0 lg:border-r',
                        activePanel !== 'write' && 'hidden lg:block',
                    )}
                >
                    <div className="hidden items-center gap-2 border-b bg-muted/30 px-5 py-3 text-xs font-medium text-muted-foreground lg:flex">
                        <Code2 aria-hidden="true" className="size-3.5" />
                        {__('Markdown')}
                    </div>
                    <textarea
                        id={id}
                        name={name}
                        defaultValue={defaultValue}
                        onChange={(event) => setContent(event.target.value)}
                        aria-invalid={invalid}
                        aria-describedby={describedBy}
                        spellCheck={false}
                        className="block h-[60vh] min-h-80 w-full resize-none bg-transparent p-5 font-mono text-sm leading-7 outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-inset sm:p-6"
                    />
                </div>
                <section
                    id={`${id}-preview`}
                    aria-label={__('Preview')}
                    className={cn(
                        'min-w-0',
                        activePanel !== 'preview' && 'hidden lg:block',
                    )}
                >
                    <div className="hidden items-center gap-2 border-b bg-muted/30 px-5 py-3 text-xs font-medium text-muted-foreground lg:flex">
                        <Eye aria-hidden="true" className="size-3.5" />
                        {__('Preview')}
                    </div>
                    <div
                        tabIndex={0}
                        className="h-[60vh] min-h-80 overflow-auto p-5 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none focus-visible:ring-inset sm:p-6"
                    >
                        {content.trim() ? (
                            <ArticleContent content={preview} syntax={syntax} />
                        ) : (
                            <p className="text-sm text-muted-foreground">
                                {__('Your Markdown preview will appear here.')}
                            </p>
                        )}
                    </div>
                </section>
            </div>
        </div>
    );
}
