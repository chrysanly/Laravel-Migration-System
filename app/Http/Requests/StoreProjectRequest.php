<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Closure;
use Illuminate\Foundation\Http\FormRequest;

final class StoreProjectRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:120'],
            'root_path' => [
                'required',
                'string',
                'max:500',
                function (string $attribute, mixed $value, Closure $fail): void {
                    $path = is_string($value) ? rtrim(str_replace('\\', '/', $value), '/') : '';

                    if ($path === '' || ! is_dir($path)) {
                        $fail('The folder does not exist or is not a directory.');

                        return;
                    }

                    if (! is_file($path.'/artisan') && ! is_file($path.'/composer.json')) {
                        $fail('This folder does not look like a Laravel project (no artisan / composer.json).');
                    }
                },
            ],
            'php_binary' => ['nullable', 'string', 'max:500'],
            'use_env_credentials' => ['boolean'],
            'db_connection' => ['nullable', 'required_if:use_env_credentials,false', 'string', 'max:30'],
            'db_host' => ['nullable', 'string', 'max:190'],
            'db_port' => ['nullable', 'string', 'max:10'],
            'db_database' => ['nullable', 'required_if:use_env_credentials,false', 'string', 'max:190'],
            'db_username' => ['nullable', 'string', 'max:190'],
            'db_password' => ['nullable', 'string', 'max:190'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'use_env_credentials' => $this->boolean('use_env_credentials', true),
        ]);
    }
}
