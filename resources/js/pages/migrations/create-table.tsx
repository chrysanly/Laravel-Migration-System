import { Head, Link, router, useForm } from '@inertiajs/react';
import { ArrowLeft, Plus, Trash2 } from 'lucide-react';
import { ConfirmButton } from '@/components/confirm-button';
import Heading from '@/components/heading';
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

interface ColumnRow {
    name: string;
    type: string;
    length: string;
    precision: string;
    scale: string;
    nullable: boolean;
    default: string;
    unsigned: boolean;
    unique: boolean;
}

interface FkRow {
    column: string;
    foreign_table: string;
    foreign_column: string;
    on_delete: string;
}

interface CreateTableProps {
    project: { id: string; name: string };
    existingTables: string[];
    modules: string[];
    columnTypes: string[];
    preview: string;
}

const ON_ACTIONS = ['cascade', 'restrict', 'set null', 'no action'];
const NEEDS_LENGTH = ['string'];
const NEEDS_PRECISION = ['decimal'];
const NEEDS_UNSIGNED = ['integer', 'bigInteger'];

function emptyColumn(type: string): ColumnRow {
    return { name: '', type, length: '', precision: '', scale: '', nullable: false, default: '', unsigned: false, unique: false };
}

export default function CreateTable({ project, existingTables, modules, columnTypes, preview }: CreateTableProps) {
    useFlashToast();

    const { data, setData, post, processing, errors } = useForm({
        table: '',
        modular: false,
        module: '',
        migrate: false,
        auto_increment_id: true,
        primary_key_columns: [] as string[],
        columns: [emptyColumn(columnTypes[0] ?? 'string')] as ColumnRow[],
        foreign_keys: [] as FkRow[],
    });

    // Replace whole form + refresh the server-rendered preview.
    function apply(patch: Partial<typeof data>) {
        const next = { ...data, ...patch };
        setData(next);
        router.get(`/projects/${project.id}/design`, next as unknown as Parameters<typeof router.get>[1], {
            only: ['preview'],
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    }

    function updateColumn(index: number, patch: Partial<ColumnRow>) {
        apply({ columns: data.columns.map((c, i) => (i === index ? { ...c, ...patch } : c)) });
    }

    function updateFk(index: number, patch: Partial<FkRow>) {
        apply({ foreign_keys: data.foreign_keys.map((f, i) => (i === index ? { ...f, ...patch } : f)) });
    }

    const definedColumns = data.columns.map((c) => c.name).filter(Boolean);
    const destination = data.modular ? `Modules/${data.module || '<Name>'}/Database/Migrations` : 'database/migrations';

    return (
        <div className="flex h-full flex-1 flex-col gap-6 p-4">
            <Head title="New table" />

            <Button asChild variant="ghost" size="sm" className="self-start">
                <Link href={`/projects/${project.id}`}>
                    <ArrowLeft className="size-4" /> {project.name}
                </Link>
            </Button>

            <Heading title="New table" description="Design a new table — it generates a create migration with up() and down()." />

            <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
                <div className="flex flex-col gap-6">
                    {/* Basics */}
                    <Card>
                        <CardContent className="flex flex-col gap-4 py-4">
                            <div className="grid gap-2">
                                <Label htmlFor="table">Table name</Label>
                                <Input
                                    id="table"
                                    value={data.table}
                                    onChange={(e) => setData('table', e.target.value)}
                                    onBlur={() => apply({})}
                                    placeholder="invoices"
                                    className="font-mono"
                                />
                                {errors.table && <p className="text-xs text-destructive">{errors.table}</p>}
                            </div>

                            <label className="flex items-center gap-2 text-sm">
                                <Checkbox
                                    checked={data.auto_increment_id}
                                    onCheckedChange={(v) => apply({ auto_increment_id: v === true })}
                                />
                                Auto-increment <span className="font-mono">id</span> primary key
                            </label>

                            {!data.auto_increment_id && (
                                <div className="grid gap-2 rounded-lg border p-3">
                                    <Label className="text-xs">Primary key column(s) — required</Label>
                                    <div className="grid grid-cols-2 gap-2">
                                        {definedColumns.length === 0 && (
                                            <p className="text-xs text-muted-foreground">Add columns first.</p>
                                        )}
                                        {definedColumns.map((name) => (
                                            <label key={name} className="flex items-center gap-2 text-sm">
                                                <Checkbox
                                                    checked={data.primary_key_columns.includes(name)}
                                                    onCheckedChange={(v) => {
                                                        const set = new Set(data.primary_key_columns);

                                                        if (v === true) {
                                                            set.add(name);
                                                        } else {
                                                            set.delete(name);
                                                        }

                                                        apply({ primary_key_columns: Array.from(set) });
                                                    }}
                                                />
                                                <span className="font-mono">{name}</span>
                                            </label>
                                        ))}
                                    </div>
                                    {errors.primary_key_columns && (
                                        <p className="text-xs text-destructive">{errors.primary_key_columns}</p>
                                    )}
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    {/* Columns */}
                    <Card>
                        <CardHeader className="flex-row items-center justify-between">
                            <CardTitle className="text-base">Columns</CardTitle>
                            <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                onClick={() => apply({ columns: [...data.columns, emptyColumn(columnTypes[0] ?? 'string')] })}
                            >
                                <Plus className="size-4" /> Add column
                            </Button>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-3">
                            {data.columns.map((c, i) => (
                                <div key={i} className="flex flex-col gap-2 rounded-lg border p-3">
                                    <div className="flex items-center gap-2">
                                        <Input
                                            value={c.name}
                                            onChange={(e) => setData('columns', data.columns.map((x, j) => (j === i ? { ...x, name: e.target.value } : x)))}
                                            onBlur={() => apply({})}
                                            placeholder="column_name"
                                            className="font-mono"
                                        />
                                        <Select value={c.type} onValueChange={(v) => updateColumn(i, { type: v })}>
                                            <SelectTrigger className="w-40"><SelectValue /></SelectTrigger>
                                            <SelectContent>
                                                {columnTypes.map((t) => <SelectItem key={t} value={t}>{t}</SelectItem>)}
                                            </SelectContent>
                                        </Select>
                                        <Button type="button" variant="ghost" size="icon" onClick={() => apply({ columns: data.columns.filter((_, j) => j !== i) })}>
                                            <Trash2 className="size-4 text-destructive" />
                                        </Button>
                                    </div>

                                    <div className="flex flex-wrap items-center gap-3 text-sm">
                                        {NEEDS_LENGTH.includes(c.type) && (
                                            <Input className="w-24" type="number" value={c.length} onChange={(e) => setData('columns', data.columns.map((x, j) => (j === i ? { ...x, length: e.target.value } : x)))} onBlur={() => apply({})} placeholder="length" />
                                        )}
                                        {NEEDS_PRECISION.includes(c.type) && (
                                            <>
                                                <Input className="w-24" type="number" value={c.precision} onChange={(e) => setData('columns', data.columns.map((x, j) => (j === i ? { ...x, precision: e.target.value } : x)))} onBlur={() => apply({})} placeholder="precision" />
                                                <Input className="w-24" type="number" value={c.scale} onChange={(e) => setData('columns', data.columns.map((x, j) => (j === i ? { ...x, scale: e.target.value } : x)))} onBlur={() => apply({})} placeholder="scale" />
                                            </>
                                        )}
                                        <Input className="w-32" value={c.default} onChange={(e) => setData('columns', data.columns.map((x, j) => (j === i ? { ...x, default: e.target.value } : x)))} onBlur={() => apply({})} placeholder="default" />
                                        <label className="flex items-center gap-1"><Checkbox checked={c.nullable} onCheckedChange={(v) => updateColumn(i, { nullable: v === true })} /> nullable</label>
                                        {NEEDS_UNSIGNED.includes(c.type) && (
                                            <label className="flex items-center gap-1"><Checkbox checked={c.unsigned} onCheckedChange={(v) => updateColumn(i, { unsigned: v === true })} /> unsigned</label>
                                        )}
                                        <label className="flex items-center gap-1"><Checkbox checked={c.unique} onCheckedChange={(v) => updateColumn(i, { unique: v === true })} /> unique</label>
                                    </div>
                                </div>
                            ))}
                            {errors.columns && <p className="text-xs text-destructive">{errors.columns}</p>}
                        </CardContent>
                    </Card>

                    {/* Foreign keys */}
                    <Card>
                        <CardHeader className="flex-row items-center justify-between">
                            <CardTitle className="text-base">Foreign keys</CardTitle>
                            <Button type="button" size="sm" variant="outline" onClick={() => apply({ foreign_keys: [...data.foreign_keys, { column: definedColumns[0] ?? '', foreign_table: '', foreign_column: 'id', on_delete: 'cascade' }] })}>
                                <Plus className="size-4" /> Add
                            </Button>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-3">
                            {data.foreign_keys.length === 0 && <p className="text-sm text-muted-foreground">No foreign keys.</p>}
                            {data.foreign_keys.map((fk, i) => (
                                <div key={i} className="grid grid-cols-[1fr_1fr_1fr_auto] items-end gap-2 rounded-lg border p-2">
                                    <div className="grid gap-1">
                                        <Label className="text-xs">Column</Label>
                                        <Select value={fk.column} onValueChange={(v) => updateFk(i, { column: v })}>
                                            <SelectTrigger><SelectValue placeholder="column" /></SelectTrigger>
                                            <SelectContent>
                                                {definedColumns.map((name) => <SelectItem key={name} value={name}>{name}</SelectItem>)}
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div className="grid gap-1">
                                        <Label className="text-xs">References</Label>
                                        <Select value={fk.foreign_table} onValueChange={(v) => updateFk(i, { foreign_table: v })}>
                                            <SelectTrigger><SelectValue placeholder="table" /></SelectTrigger>
                                            <SelectContent>
                                                {existingTables.map((t) => <SelectItem key={t} value={t}>{t}</SelectItem>)}
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div className="grid gap-1">
                                        <Label className="text-xs">On delete</Label>
                                        <Select value={fk.on_delete} onValueChange={(v) => updateFk(i, { on_delete: v })}>
                                            <SelectTrigger><SelectValue /></SelectTrigger>
                                            <SelectContent>
                                                {ON_ACTIONS.map((a) => <SelectItem key={a} value={a}>{a}</SelectItem>)}
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <Button type="button" variant="ghost" size="icon" onClick={() => apply({ foreign_keys: data.foreign_keys.filter((_, j) => j !== i) })}>
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
                                    <datalist id="modules-list">{modules.map((m) => <option key={m} value={m} />)}</datalist>
                                    {errors.module && <p className="text-xs text-destructive">{errors.module}</p>}
                                </div>
                            )}
                            <label className="flex items-center gap-2 text-sm">
                                <Checkbox checked={data.migrate} onCheckedChange={(v) => setData('migrate', v === true)} />
                                Run the migration immediately after generating
                            </label>
                            <ConfirmButton
                                className="w-full"
                                disabled={processing || data.table === '' || definedColumns.length === 0}
                                onConfirm={() => post(`/projects/${project.id}/design`)}
                                triggerLabel={processing ? 'Generating…' : data.migrate ? 'Generate & migrate' : 'Generate create migration'}
                                title={`Create table “${data.table || '…'}”?`}
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
                        {preview ? (
                            <pre className="max-h-[70vh] overflow-auto rounded-lg bg-muted p-4 text-xs">
                                <code className="font-mono">{preview}</code>
                            </pre>
                        ) : (
                            <p className="py-12 text-center text-sm text-muted-foreground">
                                Fill in a table name and at least one column to see the preview.
                            </p>
                        )}
                    </CardContent>
                </Card>
            </div>
        </div>
    );
}
