<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class RunMigrationRequest extends FormRequest
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
            // Migration file name only (no separators) — the path is rebuilt server-side.
            'file' => ['required', 'string', 'max:255', 'regex:/^[A-Za-z0-9_]+\.php$/'],
            'location' => ['required', 'in:root,module'],
            'module' => ['nullable', 'required_if:location,module', 'string', 'max:120', 'regex:/^[A-Za-z0-9_]+$/'],
        ];
    }
}
