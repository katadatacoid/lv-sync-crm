<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class InsertTargetRevenue extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:insert-target-revenue';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Mengambil data target dari db lokal crm_prod tabel target';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $logPath = storage_path('sync_logs/sync_target.log'); // Path file log
        $dbProd = DB::connection('mysql_crm_prod'); // Koneksi ke database prod CRM
        $dbLooker = DB::connection('mysql'); // Koneksi ke database public looker

        try {
            file_put_contents($logPath, "[" . Carbon::now()->format('Y-m-d H:i:s') . "] Memulai proses sinkronisasi database.\n", FILE_APPEND);
    
            $dbLooker->table('target')->truncate();
    
            $data = $dbProd->table('target')->get();
    
            foreach ($data as $row) {
                $dbLooker->table('target')->insert([
                    'id' => $row->id,
                    'created_at' => $row->created_at,
                    'updated_at' => $row->updated_at,
                    'created_by' => $row->created_by,
                    'year' => $row->year, 
                    'month' => $row->month, 
                    'entity' => $row->entity, 
                    'type' => $row->type, 
                    'amount' => $row->amount, 
                    'value' => $row->value
                ]);
            }
    
            file_put_contents($logPath, "[" . Carbon::now()->format('Y-m-d H:i:s') . "] Sinkronisasi database selesai.\n", FILE_APPEND);
        
        } catch (\Exception $e) {
            file_put_contents($logPath, "[" . Carbon::now()->format('Y-m-d H:i:s') . "] Gagal sinkronisasi: " . $e->getMessage() . "\n", FILE_APPEND);
        }
    }
}
