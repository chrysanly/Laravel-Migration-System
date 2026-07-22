import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, CheckCircle2, Eye, XCircle } from 'lucide-react';
import Heading from '@/components/heading';
import { ScrollButtons } from '@/components/scroll-buttons';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { useFlashToast } from '@/hooks/use-flash-toast';

interface LogEntry {
    id: string;
    action: string;
    target: string | null;
    status: string;
    php_version: string | null;
    command: string | null;
    output: string | null;
    created_at: string | null;
}

interface LogsProps {
    project: { id: string; name: string };
    logs: LogEntry[];
}

function formatTime(iso: string | null): string {
    if (!iso) {
        return '—';
    }

    const d = new Date(iso);

    return `${d.toLocaleDateString()} ${d.toLocaleTimeString()}`;
}

export default function ProjectLogs({ project, logs }: LogsProps) {
    useFlashToast();

    return (
        <div className="flex h-full flex-1 flex-col gap-6 p-4">
            <Head title={`Logs: ${project.name}`} />

            <Button asChild variant="ghost" size="sm" className="self-start">
                <Link href={`/projects/${project.id}`}>
                    <ArrowLeft className="size-4" /> {project.name}
                </Link>
            </Button>

            <Heading title="Operation logs" description="History of generate / keys / create / migrate actions on this project." />

            {logs.length === 0 ? (
                <Card className="border-dashed">
                    <CardContent className="py-12 text-center text-sm text-muted-foreground">
                        No operations logged yet.
                    </CardContent>
                </Card>
            ) : (
                <div className="overflow-x-auto rounded-xl border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-left">
                            <tr>
                                <th className="px-3 py-2 font-medium">When</th>
                                <th className="px-3 py-2 font-medium">Action</th>
                                <th className="px-3 py-2 font-medium">Target</th>
                                <th className="px-3 py-2 font-medium">Status</th>
                                <th className="px-3 py-2 font-medium">PHP</th>
                                <th className="px-3 py-2 text-right font-medium">Details</th>
                            </tr>
                        </thead>
                        <tbody>
                            {logs.map((log) => (
                                <tr key={log.id} className="border-t align-middle">
                                    <td className="whitespace-nowrap px-3 py-2 text-muted-foreground">{formatTime(log.created_at)}</td>
                                    <td className="px-3 py-2">
                                        <Badge variant="outline" className="uppercase">{log.action}</Badge>
                                    </td>
                                    <td className="max-w-[280px] truncate px-3 py-2 font-mono text-xs" title={log.target ?? ''}>
                                        {log.target ?? '—'}
                                    </td>
                                    <td className="px-3 py-2">
                                        {log.status === 'success' ? (
                                            <span className="inline-flex items-center gap-1 text-emerald-600 dark:text-emerald-400">
                                                <CheckCircle2 className="size-4" /> success
                                            </span>
                                        ) : (
                                            <span className="inline-flex items-center gap-1 text-destructive">
                                                <XCircle className="size-4" /> failed
                                            </span>
                                        )}
                                    </td>
                                    <td className="px-3 py-2 text-muted-foreground">{log.php_version ?? '—'}</td>
                                    <td className="px-3 py-2 text-right">
                                        <LogDetail log={log} />
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}

            <ScrollButtons />
        </div>
    );
}

function LogDetail({ log }: { log: LogEntry }) {
    return (
        <Dialog>
            <DialogTrigger asChild>
                <Button size="sm" variant="ghost">
                    <Eye className="size-4" /> View
                </Button>
            </DialogTrigger>
            <DialogContent className="max-w-2xl overflow-hidden">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        <Badge variant="outline" className="uppercase">{log.action}</Badge>
                        {log.status === 'success' ? (
                            <span className="text-sm text-emerald-600 dark:text-emerald-400">success</span>
                        ) : (
                            <span className="text-sm text-destructive">failed</span>
                        )}
                    </DialogTitle>
                </DialogHeader>
                <div className="flex min-w-0 flex-col gap-3">
                    {log.target && (
                        <p className="min-w-0 text-sm break-all">
                            <span className="text-muted-foreground">Target:</span>{' '}
                            <span className="font-mono">{log.target}</span>
                        </p>
                    )}
                    {log.command && (
                        <div className="min-w-0">
                            <p className="mb-1 text-xs font-medium text-muted-foreground">Command (ran under the project path)</p>
                            <pre className="max-w-full overflow-x-auto rounded-lg bg-muted p-3 text-xs">
                                <code className="font-mono whitespace-pre-wrap break-all">{log.command}</code>
                            </pre>
                        </div>
                    )}
                    <div className="min-w-0">
                        <p className="mb-1 text-xs font-medium text-muted-foreground">Output</p>
                        <pre className="max-h-[50vh] max-w-full overflow-auto rounded-lg bg-muted p-3 text-xs">
                            <code className="font-mono break-all whitespace-pre-wrap">{log.output || 'No output.'}</code>
                        </pre>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    );
}
