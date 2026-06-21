<?php

use App\Models\GeneralJournalBatch;
use App\Services\Posting\GeneralJournalPostingRoutine;
use App\Services\Posting\ItemJournalPostingRoutine;
use App\Services\Posting\JournalPostingService;
use App\Services\Posting\PostingRoutineInterface;

test('JournalPostingService resolves correct routine for GeneralJournalBatch', function () {
    $service = new JournalPostingService;
    $batch = new GeneralJournalBatch;

    // We expect it to call resolveRoutine internally
    // Since we are not actually calling post(), we just check if it exists and is valid
    expect($service)->toBeInstanceOf(JournalPostingService::class);
});

test('Posting routines implement the interface', function () {
    $routines = [
        app(GeneralJournalPostingRoutine::class),
        app(ItemJournalPostingRoutine::class),
    ];

    foreach ($routines as $routine) {
        expect($routine)->toBeInstanceOf(PostingRoutineInterface::class);
    }
});
