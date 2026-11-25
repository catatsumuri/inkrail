import ArticleCover from '@/components/article-cover';

export default function ArticleTitleHeader({
    title,
    src,
}: {
    title: string;
    src?: string | null;
}) {
    return (
        <div className="relative isolate overflow-hidden rounded-2xl border bg-muted/25">
            {src && (
                <>
                    <ArticleCover
                        src={src}
                        title={title}
                        presentation="backdrop"
                        className="absolute inset-0"
                    />
                    <div
                        aria-hidden="true"
                        className="absolute inset-0 bg-linear-to-t from-black/85 via-black/35 to-black/5"
                    />
                </>
            )}
            <div
                className={`relative flex flex-col justify-end p-6 sm:p-12 ${src ? 'min-h-60 sm:min-h-80' : ''}`}
            >
                <h1
                    className={`max-w-3xl text-3xl leading-tight font-semibold tracking-tight break-words sm:text-5xl ${src ? 'text-white' : ''}`}
                >
                    {title}
                </h1>
            </div>
        </div>
    );
}
