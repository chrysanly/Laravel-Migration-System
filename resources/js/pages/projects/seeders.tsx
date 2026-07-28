import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Eye, Sprout } from 'lucide-react';
import { useMemo, useState } from 'react';
import Heading from '@/components/heading';
import { ScrollButtons } from '@/components/scroll-buttons';
import { SeedButton } from '@/components/seed-button';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { useFlashToast } from '@/hooks/use-flash-toast';
import type { SeederInfo } from '@/types/migration-system';

interface SeedersProps {
    project: { id: string; name: string };
    seeders: SeederInfo[];
}

export default function Seeders({ project, seeders }: SeedersProps) {
    useFlashToast();
    const [search, setSearch] = useState('');

    const filtered = useMemo(
        () =>
            seeders.filter(
                (s) =>
                    s.name.toLowerCase().includes(search.toLowerCase()) ||
                    (s.module ?? '').toLowerCase().includes(search.toLowerCase()),
            ),
        [seeders, search],
    );

    return (
        <div className="flex h-full flex-1 flex-col gap-6 p-4">
            <Head title={`Seeders: ${project.name}`} />

            <Button asChild variant="ghost" size="sm" className="self-start">
                <Link href={`/projects/${project.id}`}>
                    <ArrowLeft className="size-4" /> {project.name}
                </Link>
            </Button>

            <Heading
                title="Seeders"
                description="Seeder classes in the project. Review the code, then seed — runs artisan db:seed under the project's own environment."
            />

            {seeders.length === 0 ? (
                <Card className="border-dashed">
                    <CardContent className="py-12 text-center text-sm text-muted-foreground">
                        No seeders found in this project.
                    </CardContent>
                </Card>
            ) : (
                <>
                    <Input
                        placeholder="Filter seeders…"
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        className="max-w-sm"
                    />

                    <div className="overflow-x-auto rounded-xl border">
                        <table className="w-full text-sm">
                            <thead className="bg-muted/50 text-left">
                                <tr>
                                    <th className="px-3 py-2 font-medium">Seeder</th>
                                    <th className="px-3 py-2 font-medium">Location</th>
                                    <th className="px-3 py-2 font-medium">File</th>
                                    <th className="px-3 py-2 text-right font-medium">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                {filtered.map((seeder) => (
                                    <tr key={seeder.fqcn} className="border-t align-middle">
                                        <td className="px-3 py-2 font-mono">{seeder.name}</td>
                                        <td className="px-3 py-2">
                                            <Badge variant={seeder.location === 'module' ? 'outline' : 'secondary'}>
                                                {seeder.location === 'module' ? seeder.module : 'root'}
                                            </Badge>
                                        </td>
                                        <td className="max-w-[280px] truncate px-3 py-2 font-mono text-xs text-muted-foreground" title={seeder.file}>
                                            {seeder.file}
                                        </td>
                                        <td className="px-3 py-2">
                                            <div className="flex justify-end gap-2">
                                                <SeederView projectId={project.id} seeder={seeder} />
                                                <SeedButton projectId={project.id} fqcn={seeder.fqcn} />
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </>
            )}

            <ScrollButtons />
        </div>
    );
}

function SeederView({ projectId, seeder }: { projectId: string; seeder: SeederInfo }) {
    const [loading, setLoading] = useState(false);

    // Seeding straight from the review dialog — reviewing the code is the confirmation.
    function seed() {
        setLoading(true);
        router.post(
            `/projects/${projectId}/seed`,
            { class: seeder.fqcn },
            { preserveScroll: true, onFinish: () => setLoading(false) },
        );
    }

    return (
        <Dialog>
            <DialogTrigger asChild>
                <Button size="sm" variant="ghost">
                    <Eye className="size-4" /> View
                </Button>
            </DialogTrigger>
            <DialogContent className="max-w-3xl">
                <DialogHeader>
                    <DialogTitle className="font-mono">{seeder.name}</DialogTitle>
                    <DialogDescription className="font-mono break-all">{seeder.fqcn}</DialogDescription>
                </DialogHeader>
                <pre className="max-h-[60vh] overflow-auto rounded-lg bg-muted p-4 text-xs">
                    <code className="font-mono">{seeder.code}</code>
                </pre>
                <DialogFooter>
                    <Button onClick={seed} disabled={loading}>
                        {loading ? <Spinner className="size-4" /> : <Sprout className="size-4" />}
                        {loading ? 'Seeding…' : 'Seed this'}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
