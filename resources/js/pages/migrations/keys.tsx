import { Head, Link, router, useForm } from '@inertiajs/react';
import { ArrowLeft, KeyRound, Plus, Trash2 } from 'lucide-react';
import { ConfirmButton } from '@/components/confirm-button';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useFlashToast } from '@/hooks/use-flash-toast';
import type { TableSchemaInfo } from '@/types/migration-system';

interface FkRow {
    column: string;
    foreign_table: string;
    foreign_column: string;
    on_delete: string;
}

interface KeysProps {
    project: { id: string; name: string };
    table: string;
    schema: TableSchemaInfo;
    existingTables: string[];
    modules: string[];
    preview: string;
}

const ON_ACTIONS = ['cascade', 'restrict', 'set null', 'no action'];

export default function AddKeys({ project, table, schema, existingTables, modules, preview }: KeysProps) {
    useFlashToast();

    const { data, setData, post, processing, errors } = useForm({
        modular: false,
        module: '',
        migrate: false,
        primary_key: schema.inferred.primary_key_columns as string[],
        foreign_keys: schema.inferred.foreign_keys.map(
            (fk): FkRow => ({
                column: fk.columns[0],
                foreign_table: fk.foreign_table,
                foreign_column: fk.foreign_columns[0] ?? 'id',
                on_delete: fk.on_delete ?? 'cascade',
            }),
        ),
    });

    // Replace the relevant form fields + re-render the server preview on every change.
    function apply(patch: Partial<Pick<typeof data, 'primary_key' | 'foreign_keys'>>) {
        const next = { ...data, ...patch };
        setData(next);
        router.get(`/projects/${project.id}/tables/${table}/keys`, next as unknown as Parameters<typeof router.get>[1], {
            only: ['preview'],
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    }

    function togglePk(column: string, checked: boolean) {
        const set = new Set(data.primary_key);

        if (checked) {
            set.add(column);
        } else {
            set.delete(column);
        }

        apply({ primary_key: Array.from(set) });
    }

    function updateFk(index: number, patch: Partial<FkRow>) {
        apply({ foreign_keys: data.foreign_keys.map((fk, i) => (i === index ? { ...fk, ...patch } : fk)) });
    }

    function addFk() {
        apply({
            foreign_keys: [
                ...data.foreign_keys,
                { column: schema.columns[0]?.name ?? '', foreign_table: '', foreign_column: 'id', on_delete: 'cascade' },
            ],
        });
    }

    function removeFk(index: number) {
        apply({ foreign_keys: data.foreign_keys.filter((_, i) => i !== index) });
    }

    const destination = data.modular ? `Modules/${data.module || '<Name>'}/Database/Migrations` : 'database/migrations';

    return (
        <div className="flex h-full flex-1 flex-col gap-6 p-4">
            <Head title={`Keys: ${table}`} />

            <Button asChild variant="ghost" size="sm" className="self-start">
                <Link href={`/projects/${project.id}`}>
                    <ArrowLeft className="size-4" /> {project.name}
                </Link>
            </Button>

            <Heading
                title={`Add keys: ${table}`}
                description="Generate a migration that adds a primary key and/or foreign keys to this existing table."
            />

            <div className="flex flex-wrap gap-2 text-xs">
                <Badge variant={schema.primary_key.length ? 'secondary' : 'outline'}>
                    current PK: {schema.primary_key.length ? schema.primary_key.join(', ') : 'none'}
                </Badge>
                <Badge variant={schema.foreign_keys.length ? 'secondary' : 'outline'}>
                    current FKs: {schema.foreign_keys.length || 'none'}
                </Badge>
            </div>

            <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
                <div className="flex flex-col gap-6">
                    {/* Primary key */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-base">
                                <KeyRound className="size-4" /> Primary key
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="grid grid-cols-2 gap-2 sm:grid-cols-3">
                            {schema.columns.map((c) => (
                                <label key={c.name} className="flex items-center gap-2 text-sm">
                                    <Checkbox
                                        checked={data.primary_key.includes(c.name)}
                                        onCheckedChange={(v) => togglePk(c.name, v === true)}
                                    />
                                    <span className="truncate font-mono" title={c.name}>{c.name}</span>
                                </label>
                            ))}
                        </CardContent>
                    </Card>

                    {/* Foreign keys */}
                    <Card>
                        <CardHeader className="flex-row items-center justify-between">
                            <CardTitle className="text-base">Foreign keys</CardTitle>
                            <Button type="button" size="sm" variant="outline" onClick={addFk}>
                                <Plus className="size-4" /> Add
                            </Button>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-3">
                            {data.foreign_keys.length === 0 && (
                                <p className="text-sm text-muted-foreground">No foreign keys.</p>
                            )}
                            {data.foreign_keys.map((fk, i) => (
                                <div key={i} className="grid grid-cols-[1fr_1fr_1fr_auto] items-end gap-2 rounded-lg border p-2">
                                    <div className="grid gap-1">
                                        <Label className="text-xs">Column</Label>
                                        <Select value={fk.column} onValueChange={(v) => updateFk(i, { column: v })}>
                                            <SelectTrigger><SelectValue placeholder="column" /></SelectTrigger>
                                            <SelectContent>
                                                {schema.columns.map((c) => (
                                                    <SelectItem key={c.name} value={c.name}>{c.name}</SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div className="grid gap-1">
                                        <Label className="text-xs">References table</Label>
                                        <Select value={fk.foreign_table} onValueChange={(v) => updateFk(i, { foreign_table: v })}>
                                            <SelectTrigger><SelectValue placeholder="table" /></SelectTrigger>
                                            <SelectContent>
                                                {existingTables.map((t) => (
                                                    <SelectItem key={t} value={t}>{t}</SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div className="grid gap-1">
                                        <Label className="text-xs">On delete</Label>
                                        <Select value={fk.on_delete} onValueChange={(v) => updateFk(i, { on_delete: v })}>
                                            <SelectTrigger><SelectValue /></SelectTrigger>
                                            <SelectContent>
                                                {ON_ACTIONS.map((a) => (
                                                    <SelectItem key={a} value={a}>{a}</SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <Button type="button" variant="ghost" size="icon" onClick={() => removeFk(i)}>
                                        <Trash2 className="size-4 text-destructive" />
                                    </Button>
                                </div>
                            ))}
                        </CardContent>
                    </Card>

                    {/* Destination + generate */}
                    <Card>
                        <CardContent className="flex flex-col gap-3 py-4">
                            <label className="flex items-center gap-2 text-sm">
                                <Checkbox checked={data.modular} onCheckedChange={(v) => setData('modular', v === true)} />
                                This is a module migration
                            </label>
                            {data.modular && (
                                <div className="grid gap-1">
                                    <Label htmlFor="module" className="text-xs">Module name</Label>
                                    <Input id="module" list="modules-list" value={data.module} onChange={(e) => setData('module', e.target.value)} placeholder="Customer" />
                                    <datalist id="modules-list">
                                        {modules.map((m) => <option key={m} value={m} />)}
                                    </datalist>
                                    {errors.module && <p className="text-xs text-destructive">{errors.module}</p>}
                                </div>
                            )}
                            {errors.primary_key && <p className="text-xs text-destructive">{errors.primary_key}</p>}
                            <label className="flex items-center gap-2 text-sm">
                                <Checkbox checked={data.migrate} onCheckedChange={(v) => setData('migrate', v === true)} />
                                Run the migration immediately after generating
                            </label>
                            <ConfirmButton
                                className="w-full"
                                disabled={processing || (data.primary_key.length === 0 && data.foreign_keys.length === 0)}
                                onConfirm={() => post(`/projects/${project.id}/tables/${table}/keys`)}
                                triggerLabel={processing ? 'Generating…' : data.migrate ? 'Generate & migrate' : 'Generate keys migration'}
                                title={`Add keys to “${table}”?`}
                                confirmLabel={data.migrate ? 'Proceed, write & migrate' : 'Proceed & write file'}
                                description={
                                    <>
                                        This writes a new migration to <span className="font-mono">{destination}</span>.
                                        {data.migrate && ' It will then be run immediately (this changes the live database).'}
                                    </>
                                }
                            />
                        </CardContent>
                    </Card>
                </div>

                <Card className="xl:sticky xl:top-4 xl:self-start">
                    <CardHeader><CardTitle className="text-base">Preview</CardTitle></CardHeader>
                    <CardContent>
                        <pre className="max-h-[70vh] overflow-auto rounded-lg bg-muted p-4 text-xs">
                            <code className="font-mono">{preview}</code>
                        </pre>
                    </CardContent>
                </Card>
            </div>
        </div>
    );
}
