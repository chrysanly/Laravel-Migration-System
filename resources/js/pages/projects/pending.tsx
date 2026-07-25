import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, CheckCircle2, CornerDownRight, PlayCircle, Table2 } from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { MigrateButton } from '@/components/migrate-button';
import { ScrollButtons } from '@/components/scroll-buttons';
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
import { Spinner } from '@/components/ui/spinner';
import { useFlashToast } from '@/hooks/use-flash-toast';
import type { PendingGroup } from '@/types/migration-system';

interface PendingProps {
    project: { id: string; name: string };
    groups: PendingGroup[];
}

export default function Pending({ project, groups }: PendingProps) {
    useFlashToast();

    const total = groups.reduce((sum, g) => sum + g.count, 0);

    return (
        <div className="flex h-full flex-1 flex-col gap-6 p-4">
            <Head title={`Pending: ${project.name}`} />

            <div className="flex items-center justify-between gap-2">
                <Button asChild variant="ghost" size="sm">
                    <Link href={`/projects/${project.id}`}>
                        <ArrowLeft className="size-4" /> {project.name}
                    </Link>
                </Button>
                {total > 0 && <MigrateAllButton projectId={project.id} count={total} />}
            </div>

            <Heading
                title="Pending migrations"
                description="Migration files that haven't run yet — the create migration first, then its updates. Run them individually or all at once."
            />

            {groups.length === 0 ? (
                <Card className="border-dashed">
                    <CardContent className="flex flex-col items-center gap-3 py-16 text-center">
                        <CheckCircle2 className="size-10 text-emerald-500" />
                        <p className="font-medium">Everything is migrated</p>
                        <p className="text-sm text-muted-foreground">No pending migration files were found for this project.</p>
                    </CardContent>
                </Card>
            ) : (
                <div className="flex flex-col gap-4">
                    {groups.map((group) => (
                        <Card key={group.table}>
                            <CardHeader className="flex-row items-center justify-between gap-2 py-3">
                                <CardTitle className="flex items-center gap-2 text-base">
                                    <Table2 className="size-4 text-muted-foreground" />
                                    <span className="font-mono">{group.table}</span>
                                </CardTitle>
                                <Badge variant="secondary">{group.count} pending</Badge>
                            </CardHeader>
                            <CardContent className="flex flex-col gap-2">
                                {/* Parent: create migration */}
                                {group.create ? (
                                    <div className="flex flex-wrap items-center gap-2 rounded-lg border p-2 text-sm">
                                        <Badge className="uppercase">create</Badge>
                                        <span className="font-mono break-all">{group.create.file}</span>
                                        {group.create.module && (
                                            <span className="text-xs text-muted-foreground">({group.create.module})</span>
                                        )}
                                        <div className="ms-auto">
                                            <MigrateButton
                                                projectId={project.id}
                                                target={{ file: group.create.file, location: group.create.location, module: group.create.module }}
                                            />
                                        </div>
                                    </div>
                                ) : (
                                    group.create_migrated && (
                                        <p className="text-xs text-muted-foreground">
                                            <CheckCircle2 className="me-1 inline size-3.5 text-emerald-500" />
                                            Create migration already run — pending changes below.
                                        </p>
                                    )
                                )}

                                {/* Children: updates / drops / renames */}
                                {group.children.map((child) => (
                                    <div
                                        key={child.file}
                                        className="ms-4 flex flex-wrap items-center gap-2 rounded-lg border border-dashed p-2 text-sm"
                                    >
                                        <CornerDownRight className="size-4 text-muted-foreground" />
                                        <Badge variant="outline" className="uppercase">{child.kind}</Badge>
                                        <span className="font-mono break-all">{child.file}</span>
                                        {child.module && <span className="text-xs text-muted-foreground">({child.module})</span>}
                                        <div className="ms-auto">
                                            <MigrateButton
                                                projectId={project.id}
                                                variant="ghost"
                                                target={{ file: child.file, location: child.location, module: child.module }}
                                            />
                                        </div>
                                    </div>
                                ))}
                            </CardContent>
                        </Card>
                    ))}
                </div>
            )}

            <ScrollButtons />
        </div>
    );
}

function MigrateAllButton({ projectId, count }: { projectId: string; count: number }) {
    const [open, setOpen] = useState(false);
    const [loading, setLoading] = useState(false);

    function run() {
        setLoading(true);
        router.post(`/projects/${projectId}/migrate/all`, {}, {
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
                <Button size="sm" disabled={loading}>
                    {loading ? <Spinner className="size-4" /> : <PlayCircle className="size-4" />}
                    {loading ? 'Migrating…' : 'Migrate all pending'}
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Run all pending migrations?</DialogTitle>
                    <DialogDescription asChild>
                        <div>
                            This runs <span className="font-mono">artisan migrate --force</span> in the project, applying
                            all {count} pending migration{count === 1 ? '' : 's'} in order. This changes the live database.
                        </div>
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <DialogClose asChild>
                        <Button variant="outline">Cancel</Button>
                    </DialogClose>
                    <Button onClick={run} disabled={loading}>
                        {loading ? <Spinner className="size-4" /> : null}
                        Proceed &amp; migrate all
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
