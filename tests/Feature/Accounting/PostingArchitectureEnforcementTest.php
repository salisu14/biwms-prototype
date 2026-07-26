<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

it('allows direct gl entry persistence only inside the posting kernel and compatibility adapter', function (): void {
    $allowedDirectWriters = [
        'app/Services/Accounting/GeneralLedgerPostingKernel.php',
        'app/Services/PostingService.php',
    ];

    $offenders = collect(File::allFiles(app_path()))
        ->filter(fn (SplFileInfo $file): bool => $file->getExtension() === 'php')
        ->mapWithKeys(function (SplFileInfo $file): array {
            $relativePath = Str::after($file->getPathname(), base_path().DIRECTORY_SEPARATOR);
            $code = phpCodeWithoutComments($file->getContents());

            preg_match_all('/GlEntry::(?:query\(\)->)?create\(/', $code, $matches, PREG_OFFSET_CAPTURE);

            return [$relativePath => $matches[0] ?? []];
        })
        ->filter(fn (array $matches): bool => $matches !== [])
        ->keys()
        ->diff($allowedDirectWriters)
        ->values();

    expect($offenders->all())->toBe([]);
});

it('keeps direct gl_entries table access read-only outside reconcile and reporting code', function (): void {
    $allowedReaders = [
        'app/Console/Commands/BiwmsFinanceReconcile.php',
        'app/Services/Sales/ProfitLossService.php',
    ];

    $offenders = collect(File::allFiles(app_path()))
        ->filter(fn (SplFileInfo $file): bool => $file->getExtension() === 'php')
        ->mapWithKeys(function (SplFileInfo $file): array {
            $relativePath = Str::after($file->getPathname(), base_path().DIRECTORY_SEPARATOR);
            $code = phpCodeWithoutComments($file->getContents());

            preg_match_all('/DB::table\([\'"]gl_entries(?:\s+as\s+\w+)?[\'"]\)/', $code, $matches, PREG_OFFSET_CAPTURE);

            return [$relativePath => $matches[0] ?? []];
        })
        ->filter(fn (array $matches): bool => $matches !== [])
        ->keys()
        ->diff($allowedReaders)
        ->values();

    expect($offenders->all())->toBe([]);
});

function phpCodeWithoutComments(string $code): string
{
    return collect(token_get_all($code))
        ->reject(fn (string|array $token): bool => is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true))
        ->map(fn (string|array $token): string => is_array($token) ? $token[1] : $token)
        ->implode('');
}
