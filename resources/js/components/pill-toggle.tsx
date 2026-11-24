import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { cn } from '@/lib/utils';

type Option<T extends string> = { value: T; label: string; title?: string };

type Props<T extends string> = {
    options: Option<T>[];
    value: T;
    onValueChange: (value: T) => void;
    label: string;
    className?: string;
};

/**
 * Sliding pill switcher, styled like the language toggle.
 */
export default function PillToggle<T extends string>({
    options,
    value,
    onValueChange,
    label,
    className,
}: Props<T>) {
    const selectedIndex = Math.max(
        0,
        options.findIndex((option) => option.value === value),
    );

    return (
        <ToggleGroup
            type="single"
            value={value}
            aria-label={label}
            onValueChange={(next) => {
                if (next && next !== value) {
                    onValueChange(next as T);
                }
            }}
            className={cn(
                'relative isolate grid rounded-full bg-muted p-1',
                className,
            )}
            style={{
                gridTemplateColumns: `repeat(${options.length}, minmax(0, 1fr))`,
            }}
        >
            <span
                aria-hidden="true"
                className="pointer-events-none absolute inset-y-1 left-1 -z-10 rounded-full bg-background shadow-sm transition-transform duration-200 ease-out motion-reduce:transition-none"
                style={{
                    width: `calc((100% - 0.5rem) / ${options.length})`,
                    transform: `translateX(${selectedIndex * 100}%)`,
                }}
            />
            {options.map((option) => (
                <ToggleGroupItem
                    key={option.value}
                    value={option.value}
                    title={option.title}
                    className="h-7 rounded-full px-3 text-xs text-muted-foreground first:rounded-full last:rounded-full hover:bg-transparent hover:text-foreground data-[state=on]:bg-transparent data-[state=on]:text-foreground"
                >
                    {option.label}
                </ToggleGroupItem>
            ))}
        </ToggleGroup>
    );
}
