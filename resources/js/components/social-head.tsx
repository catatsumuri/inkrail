import { Head } from '@inertiajs/react';

export type SocialMetadata = {
    title: string;
    url: string;
    meta: Record<string, string>;
};

export default function SocialHead({ social }: { social: SocialMetadata }) {
    return (
        <Head title={social.title}>
            <link head-key="canonical" rel="canonical" href={social.url} />
            {Object.entries(social.meta).map(([key, content]) =>
                key.startsWith('og:') ? (
                    <meta
                        key={key}
                        head-key={key}
                        property={key}
                        content={content}
                    />
                ) : (
                    <meta
                        key={key}
                        head-key={key}
                        name={key}
                        content={content}
                    />
                ),
            )}
        </Head>
    );
}
