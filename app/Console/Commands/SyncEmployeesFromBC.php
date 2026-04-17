<?php

namespace App\Console\Commands;

use App\Models\SyncLog;
use App\Services\BusinessCentralSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncEmployeesFromBC extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bc:sync-employees {--full : Full sync instead of incremental}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync employees from Business Central';

    /**
     * Execute the console command.
     */
    public function handle(BusinessCentralSyncService $syncService)
    {
        $this->info('Starting employee sync from Business Central...');
        
        $startTime = now();
        $lastSync = $this->option('full') ? null : SyncLog::where('entity', 'employees')
            ->whereNotNull('completed_at')
            ->latest()
            ->first()?->completed_at?->toIso8601String();
        
        try {
            $result = $syncService->syncEmployeesFromBC($lastSync);
            
            SyncLog::create([
                'entity' => 'employees',
                'started_at' => $startTime,
                'completed_at' => now(),
                'total_records' => $result['processed'],
                'synced_records' => $result['synced'],
                'errors' => $result['errors'],
            ]);

            $this->info("Processed: {$result['processed']}");
            $this->info("Synced: {$result['synced']}");
            
            if (!empty($result['errors'])) {
                $this->error('Errors encountered:');
                foreach ($result['errors'] as $error) {
                    $this->error("  {$error['employee_no']}: {$error['error']}");
                }
            }
        } catch (\Exception $e) {
            $this->error("Sync failed: " . $e->getMessage());
            Log::error('Employee sync command failed', ['error' => $e->getMessage()]);
            return 1;
        }
        
        return 0;
    }
}
