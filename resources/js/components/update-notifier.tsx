import { AlertTriangle, CheckCircle2, Download, Minus, RefreshCw } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';

interface CheckResult {
    current: string;
    latest: string;
    update_available: boolean;
}

interface UpdateStatus {
    running?: boolean;
    step?: number;
    total?: number;
    label?: string;
    done?: boolean;
    ok?: boolean;
    error?: string;
}

type Phase = 'idle' | 'available' | 'updating' | 'done' | 'error';

function xsrfToken(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);

    return match ? decodeURIComponent(match[1]) : '';
}

export function UpdateNotifier() {
    const [phase, setPhase] = useState<Phase>('idle');
    const [info, setInfo] = useState<CheckResult | null>(null);
    const [status, setStatus] = useState<UpdateStatus>({});
    const [minimized, setMinimized] = useState(false);
    const poll = useRef<ReturnType<typeof setInterval> | null>(null);

    const stopPolling = useCallback(() => {
        if (poll.current) {
            clearInterval(poll.current);
            poll.current = null;
        }
    }, []);

    const startPolling = useCallback(() => {
        stopPolling();
        poll.current = setInterval(async () => {
            try {
                const res = await fetch('/system/update/status', { headers: { Accept: 'application/json' } });
                const data: UpdateStatus = await res.json();
                setStatus(data);

                if (data.done) {
                    stopPolling();

                    if (data.ok) {
                        setPhase('done');
                        toast.success('Update complete.');
                    } else {
                        setPhase('error');
                    }
                }
            } catch {
                /* keep polling; transient */
            }
        }, 1500);
    }, [stopPolling]);

    // On mount: resume an in-progress update, else check for a newer version.
    useEffect(() => {
        let active = true;
        (async () => {
            try {
                const s: UpdateStatus = await fetch('/system/update/status', { headers: { Accept: 'application/json' } }).then((r) => r.json());

                if (active && s.running) {
                    setStatus(s);
                    setPhase('updating');
                    setMinimized(true);
                    startPolling();

                    return;
                }

                const c: CheckResult = await fetch('/system/update/check', { headers: { Accept: 'application/json' } }).then((r) => r.json());

                if (active && c.update_available) {
                    setInfo(c);
                    setPhase('available');
                }
            } catch {
                /* ignore */
            }
        })();

        return () => {
            active = false;
            stopPolling();
        };
    }, [startPolling, stopPolling]);

    async function startUpdate() {
        setPhase('updating');
        setMinimized(false);

        try {
            await fetch('/system/update/run', {
                method: 'POST',
                headers: { 'X-XSRF-TOKEN': xsrfToken(), Accept: 'application/json' },
                credentials: 'same-origin',
            });
            startPolling();
        } catch {
            setPhase('error');
            setStatus({ error: 'Could not start the update.' });
        }
    }

    const pct = status.total ? Math.round(((status.step ?? 0) / status.total) * 100) : 0;

    // "Update available" prompt.
    if (phase === 'available' && info) {
        return (
            <Dialog open onOpenChange={(o) => !o && setPhase('idle')}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle className="flex items-center gap-2">
                            <Download className="size-5" /> Update available
                        </DialogTitle>
                        <DialogDescription asChild>
                            <div>
                                A new version is available:{' '}
                                <span className="font-mono">v{info.current}</span> →{' '}
                                <span className="font-mono font-semibold text-foreground">v{info.latest}</span>.
                                It will download and apply in the background — you can keep working.
                            </div>
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setPhase('idle')}>Later</Button>
                        <Button onClick={startUpdate}>
                            <Download className="size-4" /> Update now
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        );
    }

    // Minimized progress pill.
    if ((phase === 'updating' || phase === 'error' || phase === 'done') && minimized) {
        return (
            <button
                type="button"
                onClick={() => setMinimized(false)}
                className="fixed bottom-6 start-6 z-50 flex items-center gap-2 rounded-full border bg-card px-4 py-2 text-xs shadow-lg"
            >
                {phase === 'updating' && <Spinner className="size-4" />}
                {phase === 'done' && <CheckCircle2 className="size-4 text-emerald-500" />}
                {phase === 'error' && <AlertTriangle className="size-4 text-destructive" />}
                <span>
                    {phase === 'updating' ? `Updating… ${status.step ?? 0}/${status.total ?? 6}` : phase === 'done' ? 'Update complete' : 'Update failed'}
                </span>
            </button>
        );
    }

    // Expanded progress card.
    if (phase === 'updating' || phase === 'error' || phase === 'done') {
        return (
            <div className="fixed bottom-6 start-6 z-50 w-80 rounded-xl border bg-card p-4 shadow-xl">
                <div className="mb-2 flex items-center justify-between">
                    <span className="flex items-center gap-2 text-sm font-medium">
                        {phase === 'updating' && <><Spinner className="size-4" /> Updating…</>}
                        {phase === 'done' && <><CheckCircle2 className="size-4 text-emerald-500" /> Update complete</>}
                        {phase === 'error' && <><AlertTriangle className="size-4 text-destructive" /> Update failed</>}
                    </span>
                    {phase === 'updating' && (
                        <Button variant="ghost" size="icon" className="size-6" onClick={() => setMinimized(true)} aria-label="Minimize">
                            <Minus className="size-4" />
                        </Button>
                    )}
                </div>

                {phase === 'updating' && (
                    <>
                        <div className="mb-1 h-2 w-full overflow-hidden rounded-full bg-muted">
                            <div className="h-full bg-primary transition-all" style={{ width: `${pct}%` }} />
                        </div>
                        <p className="text-xs text-muted-foreground">
                            {status.label ?? 'Working…'} ({status.step ?? 0}/{status.total ?? 6})
                        </p>
                    </>
                )}

                {phase === 'done' && (
                    <div className="flex items-center justify-between gap-2">
                        <p className="text-xs text-muted-foreground">Reload to use the latest version.</p>
                        <Button size="sm" onClick={() => window.location.reload()}>
                            <RefreshCw className="size-4" /> Reload
                        </Button>
                    </div>
                )}

                {phase === 'error' && (
                    <div className="flex flex-col gap-2">
                        <p className="text-xs break-words text-muted-foreground">
                            {status.label ? `Failed at: ${status.label}. ` : ''}Check the server/git output and try again.
                        </p>
                        <Button size="sm" variant="outline" onClick={() => setPhase('idle')}>Dismiss</Button>
                    </div>
                )}
            </div>
        );
    }

    return null;
}
