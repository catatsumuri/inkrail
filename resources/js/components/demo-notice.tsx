import { lang } from '@erag/lang-sync-inertia/react';
import { Form, usePage } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { start } from '@/routes/sandbox';

export default function DemoNotice() {
    const { demoMode, auth } = usePage().props;
    const { __ } = lang();

    if (!demoMode) {
        return null;
    }

    return (
        <aside className="space-y-3 rounded-lg border bg-muted/40 p-4 text-sm">
            <p className="font-medium">
                {__(
                    auth.user?.is_sandbox
                        ? 'Your private sandbox'
                        : 'Live demo',
                )}
            </p>
            <p className="text-muted-foreground">
                {__(
                    auth.user?.is_sandbox
                        ? 'Edit your own copies freely. Your changes stay private and do not affect the public examples or other visitors.'
                        : 'Public examples are read-only. Start a private sandbox to edit your own copies without signing up.',
                )}
            </p>
            <p className="text-muted-foreground">
                {__(
                    'All demo data and sessions reset daily at 08:00 and 20:00 Japan time, and on every deployment. Do not store anything you want to keep.',
                )}
            </p>
            {!auth.user && (
                <Form {...start.form()}>
                    {({ processing }) => (
                        <Button disabled={processing}>
                            {__('Start my sandbox')}
                        </Button>
                    )}
                </Form>
            )}
        </aside>
    );
}
