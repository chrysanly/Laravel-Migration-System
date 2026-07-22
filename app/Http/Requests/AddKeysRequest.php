<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class AddKeysRequest extends FormRequest
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
            'modular' => ['boolean'],
            'migrate' => ['boolean'],
            'module' => ['nullable', 'required_if:modular,true', 'string', 'max:120', 'regex:/^[A-Za-z0-9_]+$/'],
            'primary_key' => ['array'],
            'primary_key.*' => ['string', 'regex:/^[A-Za-z0-9_]+$/'],
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
            $pk = (array) $this->input('primary_key', []);
            $fks = (array) $this->input('foreign_keys', []);
            if ($pk === [] && $fks === []) {
                $v->errors()->add('primary_key', 'Add at least a primary key or one foreign key.');
            }
        });
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'modular' => $this->boolean('modular'),
            'migrate' => $this->boolean('migrate'),
        ]);
    }
}
