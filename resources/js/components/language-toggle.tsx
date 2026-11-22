import {
    CircleCheck,
    CircleDashed,
    CircleMinus,
    Clock3,
    Languages,
} from 'lucide-react';
import { lang } from '@erag/lang-sync-inertia/react';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';
import type { TranslationState } from '@/types/article';

type Props = {
    locales: string[];
    value: string;
    onValueChange: (locale: string) => void;
    label: string;
    disabled?: boolean;
    compact?: boolean;
    states?: Record<string, TranslationState>;
};

export default function LanguageToggle({
    locales,
    value,
    onValueChange,
    label,
    disabled = false,
    compact = false,
    states,
}: Props) {
    const { __ } = lang();
    const statuses = {
        reviewed: {
            Icon: CircleCheck,
            label: __('Up to date'),
            color: 'text-emerald-600 dark:text-emerald-400',
        },
        other_changed: {
            Icon: Clock3,
            label: __('Out of date'),
            color: 'text-amber-600 dark:text-amber-400',
        },
        unreviewed: {
            Icon: Clock3,
            label: __('Not reviewed'),
            color: 'text-amber-600 dark:text-amber-400',
        },
        missing: {
            Icon: CircleDashed,
            label: __('Not created'),
            color: 'text-muted-foreground',
        },
        deleted: {
            Icon: CircleMinus,
            label: __('Deleted'),
            color: 'text-muted-foreground',
        },
    };
    const selectedIndex = Math.max(0, locales.indexOf(value));

    return (
        <div className="inline-flex w-fit shrink-0 items-center gap-2.5">
            <Languages
                aria-hidden="true"
                className={cn(
                    'size-4 shrink-0 text-muted-foreground',
                    compact && 'hidden lg:block',
                )}
            />
            <ToggleGroup
                type="single"
                value={value}
                disabled={disabled}
                aria-label={label}
                onValueChange={(language) => {
                    if (language && language !== value) onValueChange(language);
                }}
                className="relative isolate grid rounded-full bg-muted p-1"
                style={{
                    gridTemplateColumns: `repeat(${locales.length}, minmax(0, 1fr))`,
                }}
            >
                <span
                    aria-hidden="true"
                    className="pointer-events-none absolute inset-y-1 left-1 -z-10 rounded-full bg-background shadow-sm transition-transform duration-200 ease-out motion-reduce:transition-none"
                    style={{
                        width: `calc((100% - 0.5rem) / ${locales.length})`,
                        transform: `translateX(${selectedIndex * 100}%)`,
                    }}
                />
                {locales.map((language) => {
                    const state = states?.[language];
                    const status = state ? statuses[state.state] : null;
                    const languageName =
                        language === 'ja' ? '日本語' : 'English';
                    const item = (
                        <ToggleGroupItem
                            key={language}
                            value={language}
                            lang={language}
                            aria-label={
                                status
                                    ? `${languageName} · ${status.label}`
                                    : languageName
                            }
                            className={cn(
                                'h-8 min-w-20 rounded-full px-3 text-xs text-muted-foreground first:rounded-full last:rounded-full hover:bg-transparent hover:text-foreground data-[state=on]:bg-transparent data-[state=on]:text-foreground',
                                compact && 'min-w-9 px-2 sm:min-w-20 sm:px-3',
                            )}
                        >
                            {status && (
                                <status.Icon
                                    aria-hidden="true"
                                    className={cn(
                                        'size-3.5 shrink-0',
                                        status.color,
                                    )}
                                />
                            )}
                            {compact ? (
                                <>
                                    <span className="sm:hidden">
                                        {language === 'ja' ? 'JA' : 'EN'}
                                    </span>
                                    <span className="hidden sm:inline">
                                        {language === 'ja'
                                            ? '日本語'
                                            : 'English'}
                                    </span>
                                </>
                            ) : language === 'ja' ? (
                                '日本語'
                            ) : (
                                'English'
                            )}
                        </ToggleGroupItem>
                    );
                    return status ? (
                        <Tooltip key={language}>
                            <TooltipTrigger asChild>{item}</TooltipTrigger>
                            <TooltipContent>
                                {languageName} · {status.label}
                            </TooltipContent>
                        </Tooltip>
                    ) : (
                        item
                    );
                })}
            </ToggleGroup>
        </div>
    );
}
