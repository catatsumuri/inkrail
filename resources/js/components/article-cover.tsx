import { BookOpen } from 'lucide-react';
import { useState } from 'react';
import { cn } from '@/lib/utils';

export default function ArticleCover({
    src,
    title,
    className,
    presentation = 'image',
}: {
    src?: string | null;
    title: string;
    className?: string;
    presentation?: 'image' | 'backdrop';
}) {
    const [failedSrc, setFailedSrc] = useState<string | null>(null);

    return (
        <div
            className={cn(
                'relative isolate overflow-hidden bg-muted',
                className,
            )}
        >
            {src && failedSrc !== src ? (
                <img
                    src={src}
                    alt=""
                    loading={presentation === 'backdrop' ? 'eager' : 'lazy'}
                    decoding="async"
                    onError={() => setFailedSrc(src)}
                    className={cn(
                        'absolute inset-0 size-full object-cover',
                        presentation === 'image' &&
                            'transition-transform duration-500 group-hover:scale-[1.025]',
                    )}
                />
            ) : (
                <div
                    aria-hidden="true"
                    className="absolute inset-0 flex flex-col justify-between overflow-hidden bg-stone-100 p-7 text-stone-600 dark:bg-stone-900 dark:text-stone-400"
                >
                    <div className="absolute -top-16 -right-10 size-64 rounded-full border border-current opacity-10" />
                    <div className="absolute -right-20 -bottom-28 size-80 rounded-full border border-current opacity-10" />
                    <BookOpen className="size-6 stroke-1" />
                    <span className="relative max-w-xs font-serif text-3xl leading-tight tracking-tight text-balance sm:text-4xl">
                        {title.split(' — ')[0]}
                    </span>
                    <span className="font-mono text-[10px] tracking-[0.25em] uppercase opacity-60">
                        InkRail
                    </span>
                </div>
            )}
        </div>
    );
}
