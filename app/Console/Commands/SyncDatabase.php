<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class SyncDatabase extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:database';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sinkronisasi database production ke lokal';

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $tables = [
            'commitment',
            'commitment_logs',
            'project',
            'project_cogs_item',
            'project_cogs_item_realization',
            'project_logs',
            'project_revenue_item',
            'sales_deal',
            'sales_deal_activity',
            'sales_deal_activity_tags',
            'flagship_project',
            'flagship_project_cogs_item',
            'flagship_project_revenue_item',
            'invoice_payment_terms',
            'invoice_realization',
            'meeting_expenses_item',
            'product',
            'product_variant',
            'sub_product'
        ]; 
        $date = Carbon::now()->format('Y-m-d'); 
        $logPath = storage_path("sync_logs/sync_$date.log"); 

        foreach ($tables as $table) {
            try {
                $data = DB::connection('mysql_crm_prod')->table($table)->get();
        
                if ($table === 'project') {
                    foreach ($data as $row) {
                        $exists = DB::connection('mysql_crm_sync')->table($table)->where('id', $row->id)->exists();
                        
                        if (!$exists) {
                            DB::connection('mysql_crm_sync')->table($table)->insert((array) $row);
                        }
                    }
                } else {
                    DB::connection('mysql_crm_sync')->table($table)->truncate();
                    if ($data->isNotEmpty()) {
                        DB::connection('mysql_crm_sync')->table($table)->insert(json_decode(json_encode($data), true));
                    }
                }
        
                file_put_contents($logPath, "[" . Carbon::now()->format('Y-m-d H:i:s') . "] Sinkronisasi tabel $table berhasil.\n", FILE_APPEND);
            } catch (\Exception $e) {
                file_put_contents($logPath, "[" . Carbon::now()->format('Y-m-d H:i:s') . "] Gagal sinkronisasi tabel $table: " . $e->getMessage() . "\n", FILE_APPEND);
            }
        }
        
    
        // Catat log setelah semua proses selesai
        file_put_contents($logPath, "[" . Carbon::now()->format('Y-m-d H:i:s') . "] Sinkronisasi database selesai.\n", FILE_APPEND);
    
        $this->info('Sinkronisasi database berhasil dilakukan!');
    }
}
