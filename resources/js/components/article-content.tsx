import {
    extractRenderedHeadingText,
    headingComponents,
    InkstreamMarkdown,
    type SyntaxProfile,
} from '@catatsumuri/inkstream/react';
import { lang } from '@erag/lang-sync-inertia/react';
import { Pencil } from 'lucide-react';
import { useMemo, type ComponentType, type ReactNode } from 'react';
import type { Components } from 'react-markdown';

export type EditHeadingPayload = { level: 2 | 3; text: string };

function editableHeading(
    level: 2 | 3,
    onEditHeading: (payload: EditHeadingPayload) => void,
    label: (text: string) => string,
): ComponentType<{ children?: ReactNode }> {
    const Base = headingComponents[`h${level}`] as ComponentType<{
        children?: ReactNode;
    }>;

    return function EditableHeading(props) {
        const text = extractRenderedHeadingText(props.children);

        return (
            <Base {...props}>
                <button
                    type="button"
                    onClick={() => onEditHeading({ level, text })}
                    aria-label={label(text)}
                    title={label(text)}
                    className="float-right rounded p-1 text-muted-foreground/60 opacity-0 transition-opacity hover:bg-accent hover:text-foreground focus-visible:opacity-100 focus-visible:outline-none [.ink-heading:hover_&]:opacity-100 [@media(hover:none)]:opacity-100"
                >
                    <Pencil aria-hidden="true" className="size-3.5" />
                </button>
                {props.children}
            </Base>
        );
    };
}

export default function ArticleContent({
    content,
    syntax = 'all',
    onEditHeading,
}: {
    content: string;
    syntax?: SyntaxProfile;
    onEditHeading?: (payload: EditHeadingPayload) => void;
}) {
    const { __ } = lang();
    const components = useMemo<Components | undefined>(() => {
        if (!onEditHeading) {
            return undefined;
        }

        const label = (text: string) => `${__('Edit this section')}: ${text}`;

        return {
            h2: editableHeading(2, onEditHeading, label),
            h3: editableHeading(3, onEditHeading, label),
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [onEditHeading]);

    return (
        <InkstreamMarkdown
            className="min-w-0"
            syntax={syntax}
            components={components}
        >
            {content}
        </InkstreamMarkdown>
    );
}
