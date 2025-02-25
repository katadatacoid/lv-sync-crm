<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class syncController extends Controller
{

    public function syncFromProduction()
    {
        $tables = ['invoice_realization', 'product']; 
    
        foreach ($tables as $table) {
            // Ambil data dari database production
            $data = DB::connection('mysql_crm_prod')->table($table)->get();
    
            // Kosongkan tabel di database lokal
            DB::connection('mysql_crm_sync')->table($table)->truncate();
    
            // Masukkan data dari production ke lokal
            DB::connection('mysql_crm_sync')->table($table)->insert(json_decode(json_encode($data), true));
        }
    
        return response()->json(['message' => 'Data dari production berhasil disalin ke lokal!']);
    }
    
}
