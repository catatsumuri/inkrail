import {
    InkstreamMarkdown,
    type SyntaxProfile,
} from '@catatsumuri/inkstream/react';

export default function ArticleContent({
    content,
    syntax = 'all',
}: {
    content: string;
    syntax?: SyntaxProfile;
}) {
    return (
        <InkstreamMarkdown className="min-w-0" syntax={syntax}>
            {content}
        </InkstreamMarkdown>
    );
}
