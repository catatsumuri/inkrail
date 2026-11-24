import { useState } from 'react';
import { cn } from '@/lib/utils';

const coverPalettes = [
    'bg-amber-50 text-amber-800 dark:bg-stone-900 dark:text-amber-400',
    'bg-teal-50 text-teal-800 dark:bg-teal-950 dark:text-teal-400',
    'bg-indigo-50 text-indigo-800 dark:bg-indigo-950 dark:text-indigo-400',
    'bg-rose-50 text-rose-800 dark:bg-stone-900 dark:text-rose-400',
];

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
    const paletteIndex = Array.from(title).reduce(
        (index, character) =>
            (index + character.codePointAt(0)!) % coverPalettes.length,
        0,
    );

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
                    className={cn(
                        'absolute inset-0 overflow-hidden',
                        coverPalettes[paletteIndex],
                    )}
                >
                    <div className="absolute inset-0 bg-[radial-gradient(currentColor_0.6px,transparent_0.6px)] bg-size-[18px_18px] opacity-[0.08]" />
                    <div className="absolute -top-1/4 -right-1/4 aspect-square w-3/4 rounded-full border border-current opacity-15" />
                    <div className="absolute top-1/2 left-1/2 aspect-square w-1/2 -translate-x-1/2 -translate-y-1/2 rounded-full bg-current opacity-[0.08]" />
                    <div className="absolute top-1/2 left-1/2 h-3/5 w-2/5 -translate-x-1/2 -translate-y-1/2 -rotate-12 rounded-sm border border-current/15 bg-white/60 shadow-xl shadow-current/5 dark:bg-black/20">
                        <div className="absolute inset-x-1/5 top-1/4 h-px bg-current/30" />
                        <div className="absolute inset-x-1/5 top-[38%] h-px bg-current/15" />
                        <div className="absolute inset-x-1/5 top-[48%] h-px bg-current/15" />
                        <div className="absolute top-[58%] left-1/5 h-px w-1/3 bg-current/15" />
                        <div className="absolute right-1/5 bottom-1/6 size-5 rounded-full bg-current/60" />
                    </div>
                    <div className="absolute bottom-1/6 left-1/6 h-1/2 w-2 -rotate-[-35deg] rounded-full bg-current/60" />
                </div>
            )}
        </div>
    );
}
