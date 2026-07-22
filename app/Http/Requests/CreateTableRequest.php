<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class CreateTableRequest extends FormRequest
{
    public const TYPES = [
        'string', 'text', 'integer', 'bigInteger', 'boolean', 'decimal',
        'float', 'double', 'dateTime', 'date', 'time', 'timestamp', 'json', 'uuid',
    ];

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
            'migrate' => ['boolean'],
            'module' => ['nullable', 'required_if:modular,true', 'string', 'max:120', 'regex:/^[A-Za-z0-9_]+$/'],
            'auto_increment_id' => ['boolean'],
            'primary_key_columns' => ['array'],
            'primary_key_columns.*' => ['string', 'regex:/^[A-Za-z0-9_]+$/'],

            'columns' => ['required', 'array', 'min:1'],
            'columns.*.name' => ['required', 'string', 'regex:/^[A-Za-z0-9_]+$/'],
            'columns.*.type' => ['required', 'string', 'in:'.implode(',', self::TYPES)],
            'columns.*.length' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'columns.*.precision' => ['nullable', 'integer', 'min:1', 'max:65'],
            'columns.*.scale' => ['nullable', 'integer', 'min:0', 'max:30'],
            'columns.*.nullable' => ['boolean'],
            'columns.*.default' => ['nullable', 'string', 'max:255'],
            'columns.*.unsigned' => ['boolean'],
            'columns.*.unique' => ['boolean'],

            'foreign_keys' => ['array'],
            'foreign_keys.*.column' => ['required', 'string', 'regex:/^[A-Za-z0-9_]+$/'],
            'foreign_keys.*.foreign_table' => ['required', 'string', 'regex:/^[A-Za-z0-9_]+$/'],
            'foreign_keys.*.foreign_column' => ['required', 'string', 'regex:/^[A-Za-z0-9_]+$/'],
            'foreign_keys.*.on_delete' => ['nullable', 'string', 'in:cascade,restrict,set null,no action'],
            'foreign_keys.*.on_update' => ['nullable', 'string', 'in:cascade,restrict,set null,no action'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            // Primary key is required: either an auto-increment id or explicit column(s).
            if (! $this->boolean('auto_increment_id') && (array) $this->input('primary_key_columns', []) === []) {
                $v->errors()->add('primary_key_columns', 'A primary key is required — enable the auto-increment id or choose column(s).');
            }
        });
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'modular' => $this->boolean('modular'),
            'migrate' => $this->boolean('migrate'),
            'auto_increment_id' => $this->boolean('auto_increment_id'),
        ]);
    }
}
