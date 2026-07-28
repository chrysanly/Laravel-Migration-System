export interface Project {
    id: string;
    name: string;
    root_path: string;
    use_env_credentials: boolean;
    db_database?: string | null;
    created_at?: string | null;
}

export interface RelatedMigration {
    file: string;
    kind: 'update' | 'drop' | 'rename' | string;
    module: string | null;
    location: 'root' | 'module' | string;
    migrated: boolean;
}

export interface TableStatus {
    name: string;
    has_migration: boolean;
    migration_file: string | null;
    location: 'root' | 'module' | null;
    module: string | null;
    has_primary_key: boolean;
    foreign_key_count: number;
    migrated: boolean | null;
    related: RelatedMigration[];
    related_count: number;
}

export interface SeederInfo {
    name: string;
    fqcn: string;
    file: string;
    module: string | null;
    location: 'root' | 'module' | string;
    code: string;
}

export interface MigratedBatch {
    batch: number;
    migrations: string[];
    count: number;
}

export interface PendingChild {
    file: string;
    kind: string;
    module: string | null;
    location: string;
}

export interface PendingGroup {
    table: string;
    create: { file: string; module: string | null; location: string } | null;
    create_migrated: boolean;
    children: PendingChild[];
    count: number;
}

export interface ColumnInfo {
    name: string;
    type: string;
    type_name: string;
    nullable: boolean;
    default: string | number | null;
    auto_increment: boolean;
    unsigned: boolean;
}

export interface ForeignKeyInfo {
    columns: string[];
    foreign_table: string;
    foreign_columns: string[];
    on_update: string | null;
    on_delete: string | null;
    inferred: boolean;
}

export interface IndexInfo {
    name: string;
    columns: string[];
    unique: boolean;
    primary: boolean;
}

export interface InferredInfo {
    primary_key_columns: string[];
    add_id_column: boolean;
    foreign_keys: ForeignKeyInfo[];
    has_any: boolean;
}

export interface TableSchemaInfo {
    table: string;
    primary_key: string[];
    columns: ColumnInfo[];
    foreign_keys: ForeignKeyInfo[];
    indexes: IndexInfo[];
    inferred: InferredInfo;
}

export interface GenerationSelections {
    include_existing_foreign_keys: boolean;
    add_id_column: boolean;
    apply_inferred_primary_key: boolean;
    inferred_foreign_key_columns: string[];
}
