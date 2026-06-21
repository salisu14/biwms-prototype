<?php

// app/Jobs/ImportPhysicalCount.php

namespace App\Jobs;

use App\Models\PhysicalInventoryJournal;
use App\Models\PhysicalInventoryLine;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class ImportPhysicalCount implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $journalId,
        public string $filePath
    ) {}

    public function handle(): void
    {
        $journal = PhysicalInventoryJournal::findOrFail($this->journalId);
        $fullPath = Storage::path($this->filePath);

        $handle = fopen($fullPath, 'r');
        $headers = fgetcsv($handle);

        while (($row = fgetcsv($handle)) !== false) {
            $data = array_combine($headers, $row);

            // Match by item_no or lot_no/serial_no
            $line = PhysicalInventoryLine::where('journal_id', $journal->id)
                ->whereHas('item', fn ($q) => $q->where('no', $data['item_no'] ?? ''))
                ->when(! empty($data['lot_no']), fn ($q) => $q->where('lot_no', $data['lot_no']))
                ->when(! empty($data['serial_no']), fn ($q) => $q->where('serial_no', $data['serial_no']))
                ->when(! empty($data['bin_code']), fn ($q) => $q->where('bin_code', $data['bin_code']))
                ->first();

            if ($line) {
                $line->update([
                    'qty_physical_inventory' => floatval($data['qty_counted'] ?? 0),
                ]);
            }
        }

        fclose($handle);
        Storage::delete($this->filePath);
    }
}
