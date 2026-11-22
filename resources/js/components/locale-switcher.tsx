import { useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import { lang } from '@erag/lang-sync-inertia/react';
import LanguageToggle from '@/components/language-toggle';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogClose,
} from '@/components/ui/dialog';
import { update } from '@/routes/locale';

export default function LocaleSwitcher({
    compact = false,
}: {
    compact?: boolean;
}) {
    const { locale, articleLocales } = usePage().props;
    const { __ } = lang();
    const [pending, setPending] = useState<string | null>(null);
    const [processing, setProcessing] = useState(false);

    function change(language: string) {
        setProcessing(true);
        router.post(
            update.url(),
            { locale: language },
            {
                preserveState: false,
                onFinish: () => {
                    setProcessing(false);
                    setPending(null);
                },
            },
        );
    }

    function select(language: string) {
        const dirty = Array.from(
            document.querySelectorAll<HTMLInputElement | HTMLTextAreaElement>(
                '[data-article-form] input, [data-article-form] textarea',
            ),
        ).some((input) =>
            input instanceof HTMLInputElement && input.type === 'checkbox'
                ? input.checked !== input.defaultChecked
                : input.value !== input.defaultValue,
        );
        if (dirty) {
            setPending(language);
        } else {
            change(language);
        }
    }

    return (
        <>
            <LanguageToggle
                compact={compact}
                locales={articleLocales}
                value={locale}
                onValueChange={select}
                label={__('Language')}
                disabled={processing}
            />
            <Dialog
                open={pending !== null}
                onOpenChange={(open) => {
                    if (!open) setPending(null);
                }}
            >
                <DialogContent>
                    <DialogTitle>{__('Discard unsaved changes?')}</DialogTitle>
                    <DialogDescription>
                        {__(
                            'Changing the display language will discard your unsaved article changes.',
                        )}
                    </DialogDescription>
                    <DialogFooter>
                        <DialogClose asChild>
                            <Button variant="outline" disabled={processing}>
                                {__('Cancel')}
                            </Button>
                        </DialogClose>
                        <Button
                            disabled={processing}
                            onClick={() => {
                                if (pending) change(pending);
                            }}
                        >
                            {__('Discard and switch')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
