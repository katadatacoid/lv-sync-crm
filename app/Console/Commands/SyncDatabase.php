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
        $date = Carbon::now()->format('Y-m-d');
        $logPath = storage_path("sync_logs/sync_truncate_insert_$date.log");

        file_put_contents($logPath, "[" . Carbon::now()->format('Y-m-d H:i:s') . "] Memulai sinkronisasi database dengan truncate & insert...\n", FILE_APPEND);

        $tables = [
            'project',
            'project_revenue_item',
            'sales_deal',
            'target',
            'invoice_realization',
            'product',
            'product_variant',
            'sub_product'
        ];

        $dbProd = DB::connection('mysql_crm_prod');
        $dbLooker = DB::connection('mysql_crm_looker');

        foreach ($tables as $table) {
            try {
                $startTime = microtime(true); 
                $dbLooker->beginTransaction();

                $dbLooker->table($table)->truncate();

                $data = collect($dbProd->table($table)->get())->map(function ($item) {
                    return (array) $item;
                })->toArray();

                if (!empty($data)) {
                    $batchSize = 500; 
                    $chunks = array_chunk(json_decode(json_encode($data), true), $batchSize);
                
                    foreach ($chunks as $index => $chunk) {
                        try {
                            $startTime = microtime(true); 
                    
                            $dbLooker->table($table)->insert($chunk);
                            $insertedCount = count($chunk);
                    
                            $endTime = microtime(true); 
                            $duration = round($endTime - $startTime, 4); 
                    
                            // Log sukses
                            $log = "[" . Carbon::now()->format('Y-m-d H:i:s') . "] [Batch " . ($index + 1) . "] Table " . $table . " Berhasil insert " . $insertedCount . " data dalam " . $duration . " detik\n";
                        } catch (\Exception $e) {
                            // Log gagal
                            $log = "[" . date('Y-m-d H:i:s') . "] [Batch " . ($index + 1) . "] Table " . $table . " Gagal: " . $e->getMessage() . "\n";
                        }
                    
                        file_put_contents($logPath, $log, FILE_APPEND); 
                    }
                    
                } else {
                    $insertedCount = 0;
                }              

                $dbLooker->commit();
                $endTime = microtime(true);
                $duration = round($endTime - $startTime, 2);

                file_put_contents($logPath, "[" . Carbon::now()->format('Y-m-d H:i:s') . "] Sinkronisasi tabel $table berhasil. Data baru ditambahkan: $insertedCount. Waktu proses: {$duration} detik\n", FILE_APPEND);
            } catch (\Exception $e) {
                // $dbLooker->rollBack();
                file_put_contents($logPath, "[" . Carbon::now()->format('Y-m-d H:i:s') . "] Gagal sinkronisasi tabel $table: " . $e->getMessage() . "\n", FILE_APPEND);
            }
        }

        file_put_contents($logPath, "[" . Carbon::now()->format('Y-m-d H:i:s') . "] Sinkronisasi selesai.\n", FILE_APPEND);
        $this->info("Sinkronisasi truncate & insert selesai.");
    }


}
