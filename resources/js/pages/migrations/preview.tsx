import { Head, Link, router, useForm } from '@inertiajs/react';
import { ArrowLeft, Copy, KeyRound, Link2, Wand2 } from 'lucide-react';
import { toast } from 'sonner';
import { ConfirmButton } from '@/components/confirm-button';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useFlashToast } from '@/hooks/use-flash-toast';
import type { GenerationSelections, TableSchemaInfo } from '@/types/migration-system';

interface PreviewProps {
    project: { id: string; name: string };
    schema: TableSchemaInfo;
    preview: string;
    modules: string[];
    selectedOptions: GenerationSelections;
}

export default function MigrationPreview({ project, schema, preview, modules, selectedOptions }: PreviewProps) {
    useFlashToast();

    const { data, setData, post, processing, errors, transform } = useForm({
        modular: false,
        module: '',
        migrate: false,
        include_existing_foreign_keys: selectedOptions.include_existing_foreign_keys,
        add_id_column: selectedOptions.add_id_column,
        apply_inferred_primary_key: selectedOptions.apply_inferred_primary_key,
        inferred_foreign_key_columns: selectedOptions.inferred_foreign_key_columns as string[],
    });

    // Re-request the server-rendered code preview when a code-affecting option changes.
    function refreshPreview(patch: Partial<typeof data>) {
        const next = { ...data, ...patch };
        setData(next);
        router.get(
            `/projects/${project.id}/tables/${schema.table}`,
            {
                include_existing_foreign_keys: next.include_existing_foreign_keys,
                add_id_column: next.add_id_column,
                apply_inferred_primary_key: next.apply_inferred_primary_key,
                inferred_foreign_key_columns: next.inferred_foreign_key_columns,
            },
            { only: ['preview', 'selectedOptions'], preserveState: true, preserveScroll: true, replace: true },
        );
    }

    function toggleInferredFk(column: string, checked: boolean) {
        const set = new Set(data.inferred_foreign_key_columns);

        if (checked) {
            set.add(column);
        } else {
            set.delete(column);
        }

        refreshPreview({ inferred_foreign_key_columns: Array.from(set) });
    }

    function generate() {
        transform((d) => ({ ...d, table: schema.table }));
        post(`/projects/${project.id}/tables/${schema.table}/generate`);
    }

    const destination = data.modular
        ? `Modules/${data.module || '<Name>'}/Database/Migrations`
        : 'database/migrations';

    const inferred = schema.inferred;

    return (
        <div className="flex h-full flex-1 flex-col gap-6 p-4">
            <Head title={`Generate: ${schema.table}`} />

            <div className="flex items-center gap-2">
                <Button asChild variant="ghost" size="sm">
                    <Link href={`/projects/${project.id}`}>
                        <ArrowLeft className="size-4" /> {project.name}
                    </Link>
                </Button>
            </div>

            <Heading
                title={`Generate migration: ${schema.table}`}
                description="Review the reverse-engineered schema, choose options, then write the migration file."
            />

            <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
                {/* Left: schema + options */}
                <div className="flex flex-col gap-6">
                    {/* Columns */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                Columns <span className="text-muted-foreground">({schema.columns.length})</span>
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="overflow-x-auto rounded-lg border">
                                <table className="w-full text-xs">
                                    <thead className="bg-muted/50 text-left">
                                        <tr>
                                            <th className="px-3 py-1.5 font-medium">Column</th>
                                            <th className="px-3 py-1.5 font-medium">Type</th>
                                            <th className="px-3 py-1.5 font-medium">Flags</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {schema.columns.map((c) => (
                                            <tr key={c.name} className="border-t">
                                                <td className="px-3 py-1.5 font-mono">
                                                    {c.name}
                                                    {schema.primary_key.includes(c.name) && (
                                                        <KeyRound className="ml-1 inline size-3 text-amber-500" />
                                                    )}
                                                </td>
                                                <td className="px-3 py-1.5 font-mono text-muted-foreground">{c.type}</td>
                                                <td className="px-3 py-1.5">
                                                    <div className="flex flex-wrap gap-1">
                                                        {c.nullable && <Badge variant="outline">nullable</Badge>}
                                                        {c.auto_increment && <Badge variant="secondary">auto</Badge>}
                                                        {c.default !== null && (
                                                            <Badge variant="outline">default: {String(c.default)}</Badge>
                                                        )}
                                                    </div>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Options */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Options</CardTitle>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-4">
                            {schema.foreign_keys.length > 0 && (
                                <label className="flex items-center gap-2 text-sm">
                                    <Checkbox
                                        checked={data.include_existing_foreign_keys}
                                        onCheckedChange={(v) =>
                                            refreshPreview({ include_existing_foreign_keys: v === true })
                                        }
                                    />
                                    Include existing foreign keys ({schema.foreign_keys.length})
                                </label>
                            )}

                            {inferred.has_any && (
                                <div className="flex flex-col gap-2 rounded-lg border border-dashed p-3">
                                    <p className="flex items-center gap-1 text-xs font-medium text-muted-foreground">
                                        <Link2 className="size-3" /> Inferred (opt-in) — not present in the live table
                                    </p>

                                    {inferred.add_id_column && (
                                        <label className="flex items-center gap-2 text-sm">
                                            <Checkbox
                                                checked={data.add_id_column}
                                                onCheckedChange={(v) => refreshPreview({ add_id_column: v === true })}
                                            />
                                            Add a new <span className="font-mono">id</span> primary key
                                        </label>
                                    )}

                                    {inferred.primary_key_columns.length > 0 && (
                                        <label className="flex items-center gap-2 text-sm">
                                            <Checkbox
                                                checked={data.apply_inferred_primary_key}
                                                onCheckedChange={(v) =>
                                                    refreshPreview({ apply_inferred_primary_key: v === true })
                                                }
                                            />
                                            Set primary key on{' '}
                                            <span className="font-mono">
                                                {inferred.primary_key_columns.join(', ')}
                                            </span>
                                        </label>
                                    )}

                                    {inferred.foreign_keys.map((fk) => (
                                        <label key={fk.columns[0]} className="flex items-center gap-2 text-sm">
                                            <Checkbox
                                                checked={data.inferred_foreign_key_columns.includes(fk.columns[0])}
                                                onCheckedChange={(v) => toggleInferredFk(fk.columns[0], v === true)}
                                            />
                                            <span className="font-mono">
                                                {fk.columns[0]} → {fk.foreign_table}.{fk.foreign_columns[0]}
                                            </span>
                                        </label>
                                    ))}
                                </div>
                            )}

                            {/* Destination */}
                            <div className="flex flex-col gap-2 border-t pt-3">
                                <label className="flex items-center gap-2 text-sm">
                                    <Checkbox
                                        checked={data.modular}
                                        onCheckedChange={(v) => setData('modular', v === true)}
                                    />
                                    This is a module migration
                                </label>

                                {data.modular && (
                                    <div className="grid gap-1">
                                        <Label htmlFor="module" className="text-xs">
                                            Module name
                                        </Label>
                                        <Input
                                            id="module"
                                            list="modules-list"
                                            value={data.module}
                                            onChange={(e) => setData('module', e.target.value)}
                                            placeholder="Customer"
                                        />
                                        <datalist id="modules-list">
                                            {modules.map((m) => (
                                                <option key={m} value={m} />
                                            ))}
                                        </datalist>
                                        <InputError message={errors.module} />
                                        <p className="text-xs text-muted-foreground">
                                            Writes to <span className="font-mono">Modules/{data.module || '<Name>'}/Database/Migrations</span>
                                        </p>
                                    </div>
                                )}

                                {!data.modular && (
                                    <p className="text-xs text-muted-foreground">
                                        Writes to <span className="font-mono">database/migrations</span> (project root).
                                    </p>
                                )}
                            </div>

                            <label className="flex items-center gap-2 text-sm">
                                <Checkbox checked={data.migrate} onCheckedChange={(v) => setData('migrate', v === true)} />
                                Run the migration immediately after generating
                            </label>

                            <ConfirmButton
                                className="w-full"
                                disabled={processing}
                                onConfirm={generate}
                                triggerLabel={
                                    <>
                                        <Wand2 className="size-4" />
                                        {processing ? 'Generating…' : data.migrate ? 'Generate & migrate' : 'Generate migration file'}
                                    </>
                                }
                                title={`Create migration for “${schema.table}”?`}
                                confirmLabel={data.migrate ? 'Proceed, write & migrate' : 'Proceed & write file'}
                                description={
                                    <>
                                        This writes a new migration file to{' '}
                                        <span className="font-mono">{destination}</span> in the target project.
                                        {data.migrate && ' It will then be run immediately (this changes the live database).'}
                                    </>
                                }
                            />
                        </CardContent>
                    </Card>
                </div>

                {/* Right: code preview */}
                <Card className="xl:sticky xl:top-4 xl:self-start">
                    <CardHeader className="flex-row items-center justify-between">
                        <CardTitle className="text-base">Preview</CardTitle>
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={() => {
                                void navigator.clipboard.writeText(preview);
                                toast.success('Copied to clipboard');
                            }}
                        >
                            <Copy className="size-4" /> Copy
                        </Button>
                    </CardHeader>
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
