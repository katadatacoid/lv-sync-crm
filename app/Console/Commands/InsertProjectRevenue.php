<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class InsertProjectRevenue extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'insert:project-revenue';
    protected $description = 'Mengambil data revenue dari sales_deal dan menyimpannya ke dalam tabel project';

    /**
     * The console command description.
     *
     * @var string
     */

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $logPath = storage_path('sync_logs/sync_projects.log');
        $dbProd = DB::connection('mysql_crm_prod'); // Koneksi ke database Prod CRM
        $dbLooker = DB::connection('mysql'); // Koneksi ke database Looker CRM
        
        try {
            file_put_contents($logPath, "[" . Carbon::now()->format('Y-m-d H:i:s') . "] Memulai proses sinkronisasi project.\n", FILE_APPEND);
            $dbProd->table('project as p')
            ->join('sales_deal as sd', 'sd.id', '=', 'p.sales_deal_id')
            ->select(
                'p.id as project_id',
                'p.name as project_name',
                'p.total_revenue as project_revenue',
                'sd.status as project_status',
                'p.created_at as project_date'
            )
            ->orderBy('p.id') 
            ->chunk(200, function ($data) use ($dbLooker) { 
    
                foreach ($data as $row) {
                    
                    $exists = $dbLooker->table('projects')
                        ->where('project_id', $row->project_id)
                        ->exists();
    
                    if (!$exists) {
                        $dbLooker->table('projects')->insert([
                            'project_id' => $row->project_id,
                            'project_name' => $row->project_name,
                            'project_revenue' => $row->project_revenue,
                            'project_status' => $row->project_status,
                            'project_date' => $row->project_date,
                            'created_at' => now(),
                            'updated_at' => now()
                        ]);
                    }
                }
            });
            file_put_contents($logPath, "[" . Carbon::now()->format('Y-m-d H:i:s') . "] Sinkronisasi project selesai.\n", FILE_APPEND);
        } catch (\Exception $e) {
            file_put_contents($logPath, "[" . Carbon::now()->format('Y-m-d H:i:s') . "] Gagal sinkronisasi project: " . $e->getMessage() . "\n", FILE_APPEND);
        }

        $this->info('Data revenue berhasil disimpan ke tabel project.');
    }
}
