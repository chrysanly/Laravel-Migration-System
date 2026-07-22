<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class GenerateMigrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'table' => ['required', 'string', 'max:190', 'regex:/^[A-Za-z0-9_]+$/'],
            'modular' => ['boolean'],
            // Module folder name only — no separators, blocks path traversal.
            'module' => ['nullable', 'required_if:modular,true', 'string', 'max:120', 'regex:/^[A-Za-z0-9_]+$/'],
            'migrate' => ['boolean'],
            'include_existing_foreign_keys' => ['boolean'],
            'add_id_column' => ['boolean'],
            'apply_inferred_primary_key' => ['boolean'],
            'inferred_foreign_key_columns' => ['array'],
            'inferred_foreign_key_columns.*' => ['string', 'regex:/^[A-Za-z0-9_]+$/'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            // The route segment is the source of truth for which table to generate.
            'table' => (string) $this->route('table'),
            'modular' => $this->boolean('modular'),
            'migrate' => $this->boolean('migrate'),
            'include_existing_foreign_keys' => $this->boolean('include_existing_foreign_keys', true),
            'add_id_column' => $this->boolean('add_id_column'),
            'apply_inferred_primary_key' => $this->boolean('apply_inferred_primary_key'),
        ]);
    }
}
