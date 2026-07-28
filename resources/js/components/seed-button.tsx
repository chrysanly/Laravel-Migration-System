import { router } from '@inertiajs/react';
import { Sprout } from 'lucide-react';
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

/**
 * Runs a seeder class on the target project (with confirmation + loading).
 */
export function SeedButton({
    projectId,
    fqcn,
    label = 'Seed',
    variant = 'outline',
    size = 'sm',
}: {
    projectId: string;
    fqcn: string;
    label?: string;
    variant?: 'default' | 'outline' | 'ghost';
    size?: 'sm' | 'default';
}) {
    const [open, setOpen] = useState(false);
    const [loading, setLoading] = useState(false);

    function run() {
        setLoading(true);
        router.post(
            `/projects/${projectId}/seed`,
            { class: fqcn },
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
                <Button size={size} variant={variant} disabled={loading}>
                    {loading ? <Spinner className="size-4" /> : <Sprout className="size-4" />}
                    {loading ? 'Seeding…' : label}
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Run this seeder?</DialogTitle>
                    <DialogDescription asChild>
                        <div className="break-words">
                            This runs <span className="font-mono break-all">{fqcn}</span> via{' '}
                            <span className="font-mono">artisan db:seed --force</span> against the target database. It may
                            insert or overwrite data.
                        </div>
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <DialogClose asChild>
                        <Button variant="outline">Cancel</Button>
                    </DialogClose>
                    <Button onClick={run} disabled={loading}>
                        {loading ? <Spinner className="size-4" /> : null}
                        Proceed &amp; seed
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
