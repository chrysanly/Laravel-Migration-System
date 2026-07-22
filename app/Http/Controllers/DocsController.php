<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

final class DocsController extends Controller
{
    /**
     * Render the in-app documentation from a lightweight markdown file.
     */
    public function index(): Response
    {
        $path = base_path('docs/documentation.md');
        $markdown = is_file($path) ? (string) file_get_contents($path) : '# Documentation\n\nNot found.';

        return Inertia::render('docs', [
            'html' => Str::markdown($markdown),
        ]);
    }
}
