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
        $date = Carbon::now()->format('Y-m-d');
        $logPath = storage_path("sync_projects/log_$date.log");
        $dbProd = DB::connection('mysql_crm_prod'); // Koneksi ke database Prod CRM
        $dbLooker = DB::connection('mysql_crm_looker'); // Koneksi ke database Looker CRM

        $updatedCount = 0;
        $insertedCount = 0;
        $batchNumber = 1; // Nomor batch

        try {
            file_put_contents($logPath, "[" . Carbon::now()->format('Y-m-d H:i:s') . "] Memulai proses sinkronisasi project.\n", FILE_APPEND);

            // Gunakan chunk untuk mengambil data dalam batch
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
                ->chunk(500, function ($projects) use ($dbLooker, &$updatedCount, &$insertedCount, &$batchNumber, $logPath) { 

                    $existingProjects = $dbLooker->table('projects')
                        ->whereIn('project_id', $projects->pluck('project_id')) // Ambil data yang sudah ada
                        ->pluck('project_id')
                        ->toArray();

                    $updates = [];
                    $inserts = [];

                    foreach ($projects as $row) {
                        $newData = [
                            'project_id' => $row->project_id,
                            'project_name' => $row->project_name,
                            'project_revenue' => $row->project_revenue,
                            'project_status' => $row->project_status,
                            'project_date' => $row->project_date,
                        ];

                        if (in_array($row->project_id, $existingProjects)) {
                            // Update hanya jika ada perubahan data
                            $updates[] = $newData;
                        } else {
                            $newData['created_at'] = now();
                            $newData['updated_at'] = now();
                            $inserts[] = $newData;
                        }
                    }

                    // Batch Insert & Update
                    if (!empty($inserts)) {
                        $dbLooker->table('projects')->insert($inserts);
                        $insertedCount += count($inserts);
                    }

                    if (!empty($updates)) {
                        foreach ($updates as $update) {
                            $dbLooker->table('projects')
                                ->where('project_id', $update['project_id'])
                                ->update($update);
                        }
                        $updatedCount += count($updates);
                    }

                    // Log progress setiap batch
                    file_put_contents($logPath, "[" . Carbon::now()->format('Y-m-d H:i:s') . "] Batch {$batchNumber} selesai. Total update: {$updatedCount}, insert: {$insertedCount}.\n", FILE_APPEND);

                    $batchNumber++; // Increment batch number
                });

            file_put_contents($logPath, "[" . Carbon::now()->format('Y-m-d H:i:s') . "] Sinkronisasi selesai. Total update: {$updatedCount}, insert: {$insertedCount}.\n", FILE_APPEND);
        } catch (\Exception $e) {
            file_put_contents($logPath, "[" . Carbon::now()->format('Y-m-d H:i:s') . "] Gagal sinkronisasi: " . $e->getMessage() . "\n", FILE_APPEND);
        }

        $this->info("Data revenue berhasil disimpan ke tabel project. Total update: {$updatedCount}, insert: {$insertedCount}.");
    }

}
