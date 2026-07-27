import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, CheckCircle2, Layers, Undo2 } from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { ScrollButtons } from '@/components/scroll-buttons';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useFlashToast } from '@/hooks/use-flash-toast';
import type { MigratedBatch } from '@/types/migration-system';

interface RollbackProps {
    project: { id: string; name: string };
    batches: MigratedBatch[];
}

export default function Rollback({ project, batches }: RollbackProps) {
    useFlashToast();
    const [steps, setSteps] = useState('');

    const latest = batches[0];

    return (
        <div className="flex h-full flex-1 flex-col gap-6 p-4">
            <Head title={`Rollback: ${project.name}`} />

            <Button asChild variant="ghost" size="sm" className="self-start">
                <Link href={`/projects/${project.id}`}>
                    <ArrowLeft className="size-4" /> {project.name}
                </Link>
            </Button>

            <Heading
                title="Rollback migrations"
                description="Based on the project's migrations table. Roll back the last batch, or the last N migrations — each runs the migration's down()."
            />

            <Alert>
                <Undo2 className="size-4" />
                <AlertTitle>Rollback reverses migrations on the live database</AlertTitle>
                <AlertDescription>
                    It runs <span className="font-mono">artisan migrate:rollback</span> in the project. Make sure the
                    affected migrations have a correct <span className="font-mono">down()</span>, and keep a backup.
                </AlertDescription>
            </Alert>

            {batches.length === 0 ? (
                <Card className="border-dashed">
                    <CardContent className="flex flex-col items-center gap-3 py-16 text-center">
                        <CheckCircle2 className="size-10 text-muted-foreground" />
                        <p className="font-medium">Nothing to roll back</p>
                        <p className="text-sm text-muted-foreground">The migrations table has no recorded migrations.</p>
                    </CardContent>
                </Card>
            ) : (
                <>
                    {/* Actions */}
                    <div className="flex flex-wrap items-end gap-4">
                        <RollbackButton
                            projectId={project.id}
                            triggerLabel="Rollback last batch"
                            title={`Roll back batch ${latest.batch}?`}
                            description={
                                <>
                                    This reverses the {latest.count} migration{latest.count === 1 ? '' : 's'} in the most
                                    recent batch (batch {latest.batch}). This changes the live database.
                                </>
                            }
                            body={{}}
                        />

                        <div className="flex items-end gap-2">
                            <div className="grid gap-1">
                                <Label htmlFor="steps" className="text-xs">Or roll back last N migrations</Label>
                                <Input
                                    id="steps"
                                    type="number"
                                    min={1}
                                    value={steps}
                                    onChange={(e) => setSteps(e.target.value)}
                                    placeholder="e.g. 3"
                                    className="w-40"
                                />
                            </div>
                            <RollbackButton
                                projectId={project.id}
                                disabled={!steps || Number(steps) < 1}
                                variant="outline"
                                triggerLabel="Rollback N"
                                title={`Roll back the last ${steps || 'N'} migrations?`}
                                description={
                                    <>
                                        This reverses the last {steps || 'N'} migrations individually. This changes the
                                        live database.
                                    </>
                                }
                                body={{ steps: Number(steps) }}
                            />
                        </div>
                    </div>

                    {/* Batches */}
                    <div className="flex flex-col gap-4">
                        {batches.map((batch, i) => {
                            // Rolling back an older batch also rolls back every newer batch,
                            // so the step count is the cumulative migrations from the top.
                            const steps = batches.slice(0, i + 1).reduce((sum, b) => sum + b.count, 0);

                            return (
                                <Card key={batch.batch} className={i === 0 ? 'border-primary/50' : undefined}>
                                    <CardHeader className="flex-row items-center justify-between gap-2 py-3">
                                        <CardTitle className="flex items-center gap-2 text-base">
                                            <Layers className="size-4 text-muted-foreground" />
                                            Batch {batch.batch}
                                            {i === 0 && <Badge>latest</Badge>}
                                        </CardTitle>
                                        <div className="flex items-center gap-2">
                                            <Badge variant="secondary">{batch.count} migration{batch.count === 1 ? '' : 's'}</Badge>
                                            <RollbackButton
                                                projectId={project.id}
                                                size="sm"
                                                variant="outline"
                                                triggerLabel={i === 0 ? 'Rollback' : 'Roll back to here'}
                                                title={i === 0 ? `Roll back batch ${batch.batch}?` : `Roll back to batch ${batch.batch}?`}
                                                description={
                                                    i === 0 ? (
                                                        <>
                                                            This reverses the {batch.count} migration{batch.count === 1 ? '' : 's'} in
                                                            batch {batch.batch}. This changes the live database.
                                                        </>
                                                    ) : (
                                                        <>
                                                            Rolling back batch {batch.batch} also rolls back the {i} newer batch
                                                            {i === 1 ? '' : 'es'} — <strong>{steps} migrations total</strong>. This
                                                            changes the live database.
                                                        </>
                                                    )
                                                }
                                                body={{ steps }}
                                            />
                                        </div>
                                    </CardHeader>
                                    <CardContent>
                                        <ul className="flex flex-col gap-1 text-xs">
                                            {batch.migrations.map((m) => (
                                                <li key={m} className="font-mono break-all text-muted-foreground">{m}</li>
                                            ))}
                                        </ul>
                                    </CardContent>
                                </Card>
                            );
                        })}
                    </div>
                </>
            )}

            <ScrollButtons />
        </div>
    );
}

function RollbackButton({
    projectId,
    triggerLabel,
    title,
    description,
    body,
    variant = 'default',
    size,
    disabled,
}: {
    projectId: string;
    triggerLabel: string;
    title: string;
    description: React.ReactNode;
    body: Record<string, number>;
    variant?: 'default' | 'outline';
    size?: 'sm';
    disabled?: boolean;
}) {
    const [open, setOpen] = useState(false);
    const [loading, setLoading] = useState(false);

    function run() {
        setLoading(true);
        router.post(`/projects/${projectId}/rollback`, body, {
            preserveScroll: true,
            onFinish: () => {
                setLoading(false);
                setOpen(false);
            },
        });
    }

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant={variant} size={size} disabled={disabled || loading}>
                    {loading ? <Spinner className="size-4" /> : <Undo2 className="size-4" />}
                    {loading ? 'Rolling back…' : triggerLabel}
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{title}</DialogTitle>
                    <DialogDescription asChild>
                        <div>{description}</div>
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <DialogClose asChild>
                        <Button variant="outline">Cancel</Button>
                    </DialogClose>
                    <Button variant="destructive" onClick={run} disabled={loading}>
                        {loading ? <Spinner className="size-4" /> : null}
                        Proceed &amp; roll back
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
