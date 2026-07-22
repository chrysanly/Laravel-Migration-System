import { Head, Link, router, useForm } from '@inertiajs/react';
import { Database, FolderPlus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
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
import { useFlashToast } from '@/hooks/use-flash-toast';
import type { Project } from '@/types/migration-system';

interface ProjectsIndexProps {
    projects: Project[];
}

export default function ProjectsIndex({ projects }: ProjectsIndexProps) {
    useFlashToast();

    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
        root_path: '',
        php_binary: '',
        use_env_credentials: true as boolean,
        db_connection: 'mysql',
        db_host: '127.0.0.1',
        db_port: '3306',
        db_database: '',
        db_username: 'root',
        db_password: '',
    });

    function submit(e: React.FormEvent) {
        e.preventDefault();
        post('/projects', { onSuccess: () => reset() });
    }

    return (
        <div className="flex h-full flex-1 flex-col gap-6 p-4">
            <Head title="Projects" />

            <Heading
                title="Projects"
                description="Register a Laravel project folder, then inspect its database and generate migrations for tables that don't have one."
            />

            <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_380px]">
                {/* Project list */}
                <div className="order-2 lg:order-1">
                    {projects.length === 0 ? (
                        <Card className="border-dashed">
                            <CardContent className="flex flex-col items-center justify-center gap-3 py-16 text-center">
                                <Database className="size-10 text-muted-foreground" />
                                <div>
                                    <p className="font-medium">No projects registered yet</p>
                                    <p className="text-sm text-muted-foreground">
                                        Add a project folder using the form to get started.
                                    </p>
                                </div>
                            </CardContent>
                        </Card>
                    ) : (
                        <div className="grid gap-4 sm:grid-cols-2">
                            {projects.map((project) => (
                                <Card key={project.id} className="flex flex-col">
                                    <CardHeader>
                                        <div className="flex items-start justify-between gap-2">
                                            <CardTitle className="truncate">{project.name}</CardTitle>
                                            <Badge variant={project.use_env_credentials ? 'secondary' : 'outline'}>
                                                {project.use_env_credentials ? '.env' : 'manual'}
                                            </Badge>
                                        </div>
                                        <CardDescription className="break-all font-mono text-xs">
                                            {project.root_path}
                                        </CardDescription>
                                    </CardHeader>
                                    <CardContent className="mt-auto flex items-center gap-2">
                                        <Button asChild size="sm">
                                            <Link href={`/projects/${project.id}`}>Open</Link>
                                        </Button>
                                        <DeleteProjectButton project={project} />
                                    </CardContent>
                                </Card>
                            ))}
                        </div>
                    )}
                </div>

                {/* Register form */}
                <div className="order-1 lg:order-2">
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <FolderPlus className="size-5" /> Register a project
                            </CardTitle>
                            <CardDescription>
                                Point at a Laravel project's root folder (the one containing{' '}
                                <span className="font-mono">artisan</span>).
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <form onSubmit={submit} className="flex flex-col gap-4">
                                <div className="grid gap-2">
                                    <Label htmlFor="name">Name</Label>
                                    <Input
                                        id="name"
                                        value={data.name}
                                        onChange={(e) => setData('name', e.target.value)}
                                        placeholder="Alqalam ERP"
                                        autoComplete="off"
                                    />
                                    <InputError message={errors.name} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="root_path">Project folder (absolute path)</Label>
                                    <Input
                                        id="root_path"
                                        value={data.root_path}
                                        onChange={(e) => setData('root_path', e.target.value)}
                                        placeholder="C:\laragon\www\alqalam_erp_prototype"
                                        className="font-mono text-xs"
                                        autoComplete="off"
                                    />
                                    <InputError message={errors.root_path} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="php_binary" className="text-xs">
                                        PHP binary (optional)
                                    </Label>
                                    <Input
                                        id="php_binary"
                                        value={data.php_binary}
                                        onChange={(e) => setData('php_binary', e.target.value)}
                                        placeholder="auto-detect from composer.json + driver"
                                        className="font-mono text-xs"
                                        autoComplete="off"
                                    />
                                    <p className="text-xs text-muted-foreground">
                                        Leave blank to auto-pick a PHP that matches the project and has the DB driver.
                                    </p>
                                    <InputError message={errors.php_binary} />
                                </div>

                                <label className="flex items-center gap-2 text-sm">
                                    <Checkbox
                                        checked={data.use_env_credentials}
                                        onCheckedChange={(v) => setData('use_env_credentials', v === true)}
                                    />
                                    Use the project's own <span className="font-mono">.env</span> credentials
                                </label>

                                {!data.use_env_credentials && (
                                    <div className="grid gap-3 rounded-lg border p-3">
                                        <div className="grid grid-cols-2 gap-2">
                                            <div className="grid gap-1">
                                                <Label htmlFor="db_connection" className="text-xs">Driver</Label>
                                                <Input id="db_connection" value={data.db_connection} onChange={(e) => setData('db_connection', e.target.value)} />
                                            </div>
                                            <div className="grid gap-1">
                                                <Label htmlFor="db_database" className="text-xs">Database</Label>
                                                <Input id="db_database" value={data.db_database} onChange={(e) => setData('db_database', e.target.value)} />
                                            </div>
                                            <div className="grid gap-1">
                                                <Label htmlFor="db_host" className="text-xs">Host</Label>
                                                <Input id="db_host" value={data.db_host} onChange={(e) => setData('db_host', e.target.value)} />
                                            </div>
                                            <div className="grid gap-1">
                                                <Label htmlFor="db_port" className="text-xs">Port</Label>
                                                <Input id="db_port" value={data.db_port} onChange={(e) => setData('db_port', e.target.value)} />
                                            </div>
                                            <div className="grid gap-1">
                                                <Label htmlFor="db_username" className="text-xs">Username</Label>
                                                <Input id="db_username" value={data.db_username} onChange={(e) => setData('db_username', e.target.value)} />
                                            </div>
                                            <div className="grid gap-1">
                                                <Label htmlFor="db_password" className="text-xs">Password</Label>
                                                <Input id="db_password" type="password" value={data.db_password} onChange={(e) => setData('db_password', e.target.value)} />
                                            </div>
                                        </div>
                                        <InputError message={errors.db_database} />
                                    </div>
                                )}

                                <Button type="submit" disabled={processing}>
                                    {processing ? 'Registering…' : 'Register project'}
                                </Button>
                            </form>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </div>
    );
}

function DeleteProjectButton({ project }: { project: Project }) {
    const [open, setOpen] = useState(false);

    function remove() {
        router.delete(`/projects/${project.id}`, { onFinish: () => setOpen(false) });
    }

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant="ghost" size="sm" className="text-destructive hover:text-destructive">
                    <Trash2 className="size-4" />
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Remove “{project.name}”?</DialogTitle>
                    <DialogDescription>
                        This only removes it from this tool's registry. The project folder and its files are not touched.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <DialogClose asChild>
                        <Button variant="outline">Cancel</Button>
                    </DialogClose>
                    <Button variant="destructive" onClick={remove}>
                        Remove
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
