import { router } from '@inertiajs/react';
import { Play } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';

export interface MigrateTarget {
    file: string;
    location: string;
    module: string | null;
}

/**
 * Runs a single migration file on the target project (with confirmation + loading).
 */
export function MigrateButton({
    projectId,
    target,
    label = 'Migrate',
    variant = 'outline',
}: {
    projectId: string;
    target: MigrateTarget;
    label?: string;
    variant?: 'outline' | 'ghost';
}) {
    const [open, setOpen] = useState(false);
    const [loading, setLoading] = useState(false);

    function run() {
        setLoading(true);
        router.post(
            `/projects/${projectId}/migrate`,
            { file: target.file, location: target.location, module: target.module },
            {
                preserveScroll: true,
                onFinish: () => {
                    setLoading(false);
                    setOpen(false);
                },
            },
        );
    }

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button size="sm" variant={variant} disabled={loading}>
                    {loading ? <Spinner className="size-4" /> : <Play className="size-4" />}
                    {loading ? 'Migrating…' : label}
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Run this migration?</DialogTitle>
                    <DialogDescription asChild>
                        <div className="break-words">
                            This runs <span className="font-mono break-all">{target.file}</span>
                            {target.module ? ` (module ${target.module})` : ''} against the target database via{' '}
                            <span className="font-mono">artisan migrate --force</span>. This changes the live database.
                        </div>
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <DialogClose asChild>
                        <Button variant="outline">Cancel</Button>
                    </DialogClose>
                    <Button onClick={run} disabled={loading}>
                        {loading ? <Spinner className="size-4" /> : null}
                        Proceed &amp; migrate
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
