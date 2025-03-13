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

            // Ambil semua data dari database produksi
            $projects = $dbProd->table('project as p')
                ->join('sales_deal as sd', 'sd.id', '=', 'p.sales_deal_id')
                ->select(
                    'p.id as project_id',
                    'p.name as project_name',
                    'p.total_revenue as project_revenue',
                    'sd.status as project_status',
                    'p.created_at as project_date'
                )
                ->orderBy('p.id')
                ->get();

            $existingProjects = $dbLooker->table('projects')->pluck('project_id')->toArray(); // Ambil semua ID yg sudah ada

            $updates = [];
            $inserts = [];

            foreach ($projects as $row) {
                $existing = $dbLooker->table('projects')
                    ->where('project_id', $row->project_id)
                    ->first();

                $newData = [
                    'project_id' => $row->project_id,
                    'project_name' => $row->project_name,
                    'project_revenue' => $row->project_revenue,
                    'project_status' => $row->project_status,
                    'project_date' => $row->project_date,
                ];

                if ($existing) {
                    // Cek apakah ada perubahan data
                    $oldData = [
                        'project_id' => $existing->project_id,
                        'project_name' => $existing->project_name,
                        'project_revenue' => $existing->project_revenue,
                        'project_status' => $existing->project_status,
                        'project_date' => $existing->project_date,
                    ];

                    if (json_encode($newData) !== json_encode($oldData)) {
                        $newData['updated_at'] = now();
                        $updates[] = $newData;

                        file_put_contents($logPath, "[" . Carbon::now()->format('Y-m-d H:i:s') . "] Update project ID: " . $row->project_id . "\n", FILE_APPEND);
                    } else {
                        file_put_contents($logPath, "[" . Carbon::now()->format('Y-m-d H:i:s') . "] Skip project ID: " . $row->project_id . " (tidak ada perubahan)\n", FILE_APPEND);
                    }
                } else {
                    $newData['created_at'] = now();
                    $newData['updated_at'] = now();
                    $inserts[] = $newData;

                    file_put_contents($logPath, "[" . Carbon::now()->format('Y-m-d H:i:s') . "] Insert project ID: " . $row->project_id . "\n", FILE_APPEND);
                }
            }

            // Batch Insert & Update
            if (!empty($inserts)) {
                $dbLooker->table('projects')->insert($inserts);
            }

            if (!empty($updates)) {
                foreach ($updates as $update) {
                    $dbLooker->table('projects')
                        ->where('project_id', $update['project_id'])
                        ->update($update);
                }
            }

            file_put_contents($logPath, "[" . Carbon::now()->format('Y-m-d H:i:s') . "] Sinkronisasi project selesai.\n", FILE_APPEND);
        } catch (\Exception $e) {
            file_put_contents($logPath, "[" . Carbon::now()->format('Y-m-d H:i:s') . "] Gagal sinkronisasi project: " . $e->getMessage() . "\n", FILE_APPEND);
        }

        $this->info('Data revenue berhasil disimpan ke tabel project.');
    }

    
}
