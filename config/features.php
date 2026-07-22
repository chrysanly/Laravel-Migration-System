<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Frontend stack (resolved by STARTUP.md gate)
    |--------------------------------------------------------------------------
    | 'blade' | 'react' | 'vue'. This project was scaffolded with the official
    | Laravel React starter kit (Inertia + React 19 + TypeScript + Tailwind 4).
    */
    'stack' => env('APP_STACK', 'react'),

    /*
    |--------------------------------------------------------------------------
    | RBAC (Spatie Permission) — see SETUP.md §6
    |--------------------------------------------------------------------------
    | When false, AppServiceProvider grants all Gate checks so can()/@can
    | degrade gracefully. Flip on before shipping real authorization.
    */
    'spatie' => env('ENABLE_SPATIE', false),

    /*
    |--------------------------------------------------------------------------
    | Authentication (managed by `php artisan auth:setup`, when scaffolded)
    |--------------------------------------------------------------------------
    | NOTE: this app uses the starter kit's Fortify-based auth, not Breeze.
    | These keys are kept for boilerplate parity / documentation.
    */
    'auth' => [
        'mode' => env('AUTH_MODE', 'login-register'),
        'registration' => env('AUTH_REGISTRATION', true),
        'social' => array_filter(explode(',', (string) env('AUTH_SOCIAL', ''))),
        'allowed_domains' => array_filter(explode(',', (string) env('AUTH_ALLOWED_DOMAINS', ''))),
    ],

    /*
    |--------------------------------------------------------------------------
    | Env-toggle feature modules (see FEATURES.md)
    |--------------------------------------------------------------------------
    | Flip ENABLE_* in .env, then run `php artisan features:install` to install
    | the package, publish its config, and drop the generated classes into app/.
    | The installer command itself is scaffolded on demand (deferred for now).
    */
    'modules' => [

        'pdf' => [
            'enabled' => env('ENABLE_PDF', false),
            'packages' => ['barryvdh/laravel-dompdf'],
            'publish' => ['provider' => 'Barryvdh\DomPDF\ServiceProvider'],
            'stubs' => [
                'stubs/features/pdf/PdfService.stub' => 'app/Services/Pdf/PdfService.php',
                'stubs/features/pdf/GeneratePdfJob.stub' => 'app/Jobs/GeneratePdfJob.php',
            ],
        ],

        'excel' => [
            'enabled' => env('ENABLE_EXCEL', false),
            'packages' => ['maatwebsite/excel'],
            'publish' => ['provider' => 'Maatwebsite\Excel\ExcelServiceProvider'],
            'stubs' => [
                'stubs/features/excel/ExcelService.stub' => 'app/Services/Excel/ExcelService.php',
                'stubs/features/excel/ArrayExport.stub' => 'app/Services/Excel/Exports/ArrayExport.php',
                'stubs/features/excel/GenerateExcelJob.stub' => 'app/Jobs/GenerateExcelJob.php',
            ],
        ],

    ],

];
