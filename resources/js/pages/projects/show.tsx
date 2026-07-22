import { Head, Link, router } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowLeft,
    CheckCircle2,
    ChevronDown,
    ChevronRight,
    Database,
    KeyRound,
    Link2,
    Play,
    Plus,
    RefreshCw,
    ScrollText,
    Wand2,
    XCircle,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import Heading from '@/components/heading';
import { ScrollButtons } from '@/components/scroll-buttons';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
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
import { Spinner } from '@/components/ui/spinner';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { useFlashToast } from '@/hooks/use-flash-toast';
import type { Project, RelatedMigration, TableStatus } from '@/types/migration-system';

interface PhpInfo {
    binary: string;
    version: string;
    matched: boolean;
    driver: string;
}

interface ConnectionInfo {
    driver: string;
    host: string;
    port: string;
    database: string;
    username: string;
    has_password: boolean;
    auth: string;
}

interface ProjectShowProps {
    project: Project;
    tables: TableStatus[];
    connectionError: string | null;
    php: PhpInfo;
    connection: ConnectionInfo | null;
}

export default function ProjectShow({ project, tables, connectionError, php, connection }: ProjectShowProps) {
    useFlashToast();
    const [search, setSearch] = useState('');
    const [expanded, setExpanded] = useState<Set<string>>(new Set());

    const filtered = useMemo(
        () => tables.filter((t) => t.name.toLowerCase().includes(search.toLowerCase())),
        [tables, search],
    );

    const withMigration = tables.filter((t) => t.has_migration).length;
    const noPk = tables.filter((t) => !t.has_primary_key).length;

    function toggle(name: string) {
        setExpanded((prev) => {
            const next = new Set(prev);

            if (next.has(name)) {
                next.delete(name);
            } else {
                next.add(name);
            }

            return next;
        });
    }

    return (
        <div className="flex h-full flex-1 flex-col gap-6 p-4">
            <Head title={project.name} />

            <div className="flex items-center justify-between gap-2">
                <Button asChild variant="ghost" size="sm">
                    <Link href="/projects">
                        <ArrowLeft className="size-4" /> Projects
                    </Link>
                </Button>
                <div className="flex gap-2">
                    <Button asChild size="sm" variant="outline">
                        <Link href={`/projects/${project.id}/logs`}>
                            <ScrollText className="size-4" /> Logs
                        </Link>
                    </Button>
                    <Button asChild size="sm">
                        <Link href={`/projects/${project.id}/design`}>
                            <Plus className="size-4" /> New table
                        </Link>
                    </Button>
                </div>
            </div>

            <Heading title={project.name} description={project.root_path} />

            {/* Connection resolved from the PROJECT's own .env — commands run under the project path. */}
            <Card>
                <CardContent className="flex flex-wrap items-center gap-x-6 gap-y-2 py-4 text-sm">
                    <span className="text-xs font-medium text-muted-foreground">CONNECTION (from project .env)</span>
                    {connection ? (
                        <>
                            <span className="inline-flex items-center gap-1">
                                <Database className="size-4 text-muted-foreground" />
                                <Badge variant="secondary" className="uppercase">{connection.driver}</Badge>
                            </span>
                            {connection.driver !== 'sqlite' && (
                                <span className="font-mono text-xs">
                                    {connection.host}
                                    {connection.port ? `:${connection.port}` : ''}
                                </span>
                            )}
                            <span className="font-mono text-xs">db: {connection.database || '—'}</span>
                            <span className="text-xs text-muted-foreground">
                                auth: {connection.auth === 'windows' ? 'Windows (integrated)' : connection.auth === 'user' ? `${connection.username} ${connection.has_password ? '(password)' : ''}` : 'none'}
                            </span>
                        </>
                    ) : (
                        <span className="text-xs text-amber-600 dark:text-amber-400">
                            Could not read the project's .env — set credentials on the project or check the path.
                        </span>
                    )}
                </CardContent>
            </Card>

            <div className="flex flex-wrap items-center gap-2 text-xs">
                <Badge variant="outline" className="uppercase">{php.driver || 'db'}</Badge>
                {php.binary ? (
                    <span className="text-muted-foreground">
                        introspecting with PHP {php.version}
                        {!php.matched && (
                            <span className="ms-1 text-amber-600 dark:text-amber-400">
                                (no exact match to composer.json — closest with driver)
                            </span>
                        )}
                    </span>
                ) : (
                    <span className="text-amber-600 dark:text-amber-400">
                        No PHP binary with the “{php.driver}” driver was found — install it or set one on the project.
                    </span>
                )}
            </div>

            {connectionError ? (
                <Alert variant="destructive">
                    <AlertTriangle className="size-4" />
                    <AlertTitle>Cannot connect to this project's database</AlertTitle>
                    <AlertDescription>
                        <p>{connectionError}</p>
                        <p className="mt-2 text-sm">
                            Check that the database server is running and the project's{' '}
                            <span className="font-mono">.env</span> credentials are correct, then retry.
                        </p>
                        <Button variant="outline" size="sm" className="mt-3" onClick={() => router.reload()}>
                            <RefreshCw className="size-4" /> Retry connection
                        </Button>
                    </AlertDescription>
                </Alert>
            ) : (
                <>
                    <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
                        <SummaryTile label="Tables" value={tables.length} />
                        <SummaryTile label="With migration" value={withMigration} tone="success" />
                        <SummaryTile label="Without migration" value={tables.length - withMigration} tone="warning" />
                        <SummaryTile label="No primary key" value={noPk} tone="warning" />
                    </div>

                    <Input
                        placeholder="Filter tables…"
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        className="max-w-sm"
                    />

                    {filtered.length === 0 ? (
                        <Card className="border-dashed">
                            <CardContent className="py-12 text-center text-sm text-muted-foreground">
                                {tables.length === 0 ? 'This database has no tables.' : 'No tables match your filter.'}
                            </CardContent>
                        </Card>
                    ) : (
                        <div className="overflow-x-auto rounded-xl border">
                            <table className="w-full text-sm">
                                <thead className="bg-muted/50 text-left">
                                    <tr>
                                        <th className="px-3 py-2 font-medium">Table</th>
                                        <th className="px-3 py-2 font-medium">Migration</th>
                                        <th className="px-3 py-2 font-medium">PK</th>
                                        <th className="px-3 py-2 font-medium">FK</th>
                                        <th className="px-3 py-2 font-medium">Other</th>
                                        <th className="px-3 py-2 text-right font-medium">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {filtered.map((t) => (
                                        <TableRow
                                            key={t.name}
                                            table={t}
                                            projectId={project.id}
                                            expanded={expanded.has(t.name)}
                                            onToggle={() => toggle(t.name)}
                                        />
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </>
            )}

            <ScrollButtons />
        </div>
    );
}

function TableRow({
    table: t,
    projectId,
    expanded,
    onToggle,
}: {
    table: TableStatus;
    projectId: string;
    expanded: boolean;
    onToggle: () => void;
}) {
    return (
        <>
            <tr className="border-t align-middle">
                <td className="px-3 py-2 font-mono">{t.name}</td>
                <td className="px-3 py-2">
                    <MigrationStatus table={t} />
                </td>
                <td className="px-3 py-2">
                    {t.has_primary_key ? (
                        <Badge variant="secondary">
                            <KeyRound className="size-3" /> yes
                        </Badge>
                    ) : (
                        <Badge variant="outline" className="text-amber-600 dark:text-amber-400">
                            none
                        </Badge>
                    )}
                </td>
                <td className="px-3 py-2">
                    {t.foreign_key_count > 0 ? (
                        <Badge variant="secondary">
                            <Link2 className="size-3" /> {t.foreign_key_count}
                        </Badge>
                    ) : (
                        <Badge variant="outline" className="text-amber-600 dark:text-amber-400">
                            none
                        </Badge>
                    )}
                </td>
                <td className="px-3 py-2">
                    {t.related_count > 0 ? (
                        <button
                            type="button"
                            onClick={onToggle}
                            className="inline-flex items-center gap-1 text-muted-foreground hover:text-foreground"
                        >
                            {expanded ? <ChevronDown className="size-4" /> : <ChevronRight className="size-4" />}
                            {t.related_count} migration{t.related_count === 1 ? '' : 's'}
                        </button>
                    ) : (
                        <span className="text-muted-foreground">—</span>
                    )}
                </td>
                <td className="px-3 py-2">
                    <div className="flex justify-end gap-2">
                        {!t.has_migration && (
                            <Button asChild size="sm" variant="outline">
                                <Link href={`/projects/${projectId}/tables/${t.name}`}>
                                    <Wand2 className="size-4" /> Generate
                                </Link>
                            </Button>
                        )}
                        {t.has_migration && t.migrated === false && t.migration_file && t.location && (
                            <MigrateButton
                                projectId={projectId}
                                target={{ file: t.migration_file, location: t.location, module: t.module }}
                            />
                        )}
                        <Button asChild size="sm" variant="ghost">
                            <Link href={`/projects/${projectId}/tables/${t.name}/keys`}>
                                <KeyRound className="size-4" /> Keys
                            </Link>
                        </Button>
                    </div>
                </td>
            </tr>
            {expanded && t.related_count > 0 && (
                <tr className="border-t bg-muted/30">
                    <td colSpan={6} className="px-3 py-2">
                        <ul className="flex flex-col gap-1.5 text-xs">
                            {t.related.map((r, i) => (
                                <RelatedRow key={`${r.file}-${i}`} related={r} projectId={projectId} />
                            ))}
                        </ul>
                    </td>
                </tr>
            )}
        </>
    );
}

function RelatedRow({ related: r, projectId }: { related: RelatedMigration; projectId: string }) {
    return (
        <li className="flex flex-wrap items-center gap-2">
            <Badge variant="outline" className="uppercase">{r.kind}</Badge>
            {r.migrated ? (
                <CheckCircle2 className="size-4 text-emerald-600 dark:text-emerald-400" />
            ) : (
                <XCircle className="size-4 text-amber-600 dark:text-amber-400" />
            )}
            <span className="font-mono">{r.file}</span>
            {r.module && <span className="text-muted-foreground">({r.module})</span>}
            {!r.migrated && (
                <MigrateButton
                    projectId={projectId}
                    variant="ghost"
                    target={{ file: r.file, location: r.location, module: r.module }}
                />
            )}
        </li>
    );
}

interface MigrateTarget {
    file: string;
    location: string;
    module: string | null;
}

function MigrateButton({
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

function MigrationStatus({ table: t }: { table: TableStatus }) {
    let icon: React.ReactNode;
    let label: string;
    let info: string;

    if (!t.has_migration) {
        icon = <XCircle className="size-5 text-muted-foreground" />;
        label = 'no file';
        info = 'No create-migration file was found for this table.';
    } else if (t.migrated) {
        icon = <CheckCircle2 className="size-5 text-emerald-600 dark:text-emerald-400" />;
        label = 'migrated';
        info = `Migrated — "${t.migration_file}" is recorded in the migrations table.`;
    } else {
        icon = <XCircle className="size-5 text-amber-600 dark:text-amber-400" />;
        label = 'not migrated';
        info = `Not migrated — the file "${t.migration_file}" exists but has not been run yet.`;
    }

    return (
        <Tooltip>
            <TooltipTrigger asChild>
                <span className="inline-flex cursor-default items-center gap-1.5">
                    {icon}
                    <span className="text-xs text-muted-foreground">{label}</span>
                </span>
            </TooltipTrigger>
            <TooltipContent className="max-w-xs">{info}</TooltipContent>
        </Tooltip>
    );
}

function SummaryTile({
    label,
    value,
    tone = 'default',
}: {
    label: string;
    value: number;
    tone?: 'default' | 'success' | 'warning';
}) {
    const toneClass =
        tone === 'success'
            ? 'text-emerald-600 dark:text-emerald-400'
            : tone === 'warning'
              ? 'text-amber-600 dark:text-amber-400'
              : 'text-foreground';

    return (
        <Card>
            <CardContent className="py-4">
                <p className="text-xs text-muted-foreground">{label}</p>
                <p className={`text-2xl font-semibold ${toneClass}`}>{value}</p>
            </CardContent>
        </Card>
    );
}
