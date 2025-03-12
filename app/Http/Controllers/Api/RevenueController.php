<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class RevenueController extends Controller
{
    protected $db;

    public function __construct()
    {
        $this->db = DB::connection('mysql');
    }

    public function allSBU(){
        $data = $this->db->table('invoice_realization')->sum('revenue');
        $result = [
            'status' => 'success',
            'code' => 200,
            'Total_Revenue' => $data,
            'Description' => 'Revenue All SBU'
        ];
        return response()->json($result);
    }

     public function revenueSBU(Request $request) {
        
        $sbu_id = $request->query('sbu'); 
        $query = DB::table('invoice_realization')
            ->join('project', 'invoice_realization.project_id', '=', 'project.id')
            ->join('project_revenue_item', 'project.id', '=', 'project_revenue_item.project_id')
            ->join('product', 'project_revenue_item.product_id', '=', 'product.id')
            ->join('sbu', 'product.sbu_id', '=', 'sbu.id')
            ->select('sbu.name as sbu_name', DB::raw('SUM(project_revenue_item.price * project_revenue_item.quantity) as total_revenue'))
            ->groupBy('sbu.name')
            ->orderByDesc('total_revenue');

        if (!empty($sbu_id)) {
            $query->where('sbu.id', $sbu_id);
        }
    
        $data = $query->get();
    
        return response()->json([
            'status' => 'success',
            'code' => 200,
            'Total_Revenue' => $data,
            'Description' => empty($sbu_id) ? 'Revenue All SBU' : 'Revenue for SBU ID ' . $sbu_id
        ]);
    }
    

    public function getRevenueSbu(Request $request){
        // dd($request->query('sbu'));
        $sbu_id = $request->query('sbu') ?? null;


        $revenues = DB::table('sbu as s')
            ->joinSub(
                DB::table('project as pr')
                    ->join('project_revenue_item as pri', 'pr.id', '=', 'pri.project_id')
                    ->join('product as p', 'p.id', '=', 'pri.product_id')
                    ->select('pr.id as project_id', DB::raw('MIN(p.sbu_id) as sbu_id'))
                    ->groupBy('pr.id'),
                'proj_sbu',
                's.id',
                '=',
                'proj_sbu.sbu_id'
            )
            ->joinSub(
                DB::table('invoice_realization')
                    ->select('project_id', DB::raw('SUM(revenue) as total_revenue'))
                    ->groupBy('project_id'),
                'ir',
                'proj_sbu.project_id',
                '=',
                'ir.project_id'
            )
            ->select(
                's.id as sbu_id',
                's.name as sbu_name',
                DB::raw('SUM(ir.total_revenue) as total_realized_revenue')
            )
            ->when($sbu_id, function ($query) use ($sbu_id) {
                return $query->where('s.id', $sbu_id);
            })
            ->groupBy('s.id', 's.name')
            ->orderByDesc('total_realized_revenue')
            ->get();

            // dd($revenues);

        return response()->json([
            'status' => 'success',
            'data' => $revenues
        ]);
    }

    // public function getRevenueProject(Request $request){
    //     $project_id = $request->query('project_id') ?? null;
    //     $get = DB::Connection('mysql_crm_prod');

    //     $selectdata = $get->table('project_revenue_item as pri')
    //     ->select(
    //         // 'p.name as project_name',
    //         // 'prod.name as product_name',
    //         // 'pri.price as product_price',
    //         // 's.name as sbu_name',
    //         // 'pri.created_at as revenue_date'
    //         'p.name as project_name',
    //         's.name as sbu_name',
    //         's.id as sbu_id',
    //         DB::raw('SUM(pri.price) as total_product_price')
    //         )
    //     ->join('project as p', 'p.id','=','pri.project_id')
    //     ->join('product as prod','prod.id','=','pri.product_id')
    //     ->join('sbu as s','s.id','=','prod.sbu_id')
    //     // ->where('p.id',$project_id) 
    //     ->groupBy('p.id', 'p.name', 's.id', 's.name')
    //     ->orderBy('p.name')
    //     ->get();

    //     $result = [];

    //     foreach ($selectdata as $row) {
    //         $projectName = $row->project_name;
        
    //         if (!isset($result[$projectName])) {
    //             $result[$projectName] = [
    //                 'project_name' => $projectName,
    //                 'revenue_eta' => 0, // Akan dihitung ulang di bawah
    //                 'revenue_act' => 0,
    //                 'total_product_price_sum' => 0, // Digunakan untuk perhitungan total
    //                 'revenue' => []
    //             ];
    //         }
        
    //         // Tambahkan total harga produk ke total project
    //         $result[$projectName]['total_product_price_sum'] += $row->total_product_price;
        
    //         // Simpan data per SBU
    //         $result[$projectName]['revenue'][] = [
    //             'sbu_name' => $row->sbu_name,
    //             'total_product_price' => $row->total_product_price
    //         ];
    //     }
        
    //     // **Set revenue_eta dan hitung revenue_percentage**
    //     foreach ($result as &$project) {
    //         $project['revenue_eta'] = $project['total_product_price_sum']; // revenue_eta = total semua total_product_price
        
    //         foreach ($project['revenue'] as &$revenue) {
    //             $revenue['revenue_percentage'] = $project['revenue_eta'] > 0 
    //                 ? round(($revenue['total_product_price'] / $project['revenue_eta']) * 100, 2) 
    //                 : 0;
    //         }
        
    //         unset($project['total_product_price_sum']);
    //     }

    //     unset($project, $revenue); 
    //     $response = reset($result);

    //     return response()->json($response);
    // }

    public function getRevenueProject(Request $request){
        $project_id = $request->query('project_id') ?? null;
        $get = DB::Connection('mysql_crm_prod');
    
        $selectdata = $get->table('project_revenue_item as pri')
            ->select(
                'p.name as project_name',
                's.name as sbu_name',
                's.id as sbu_id',
                'sd.status as project_status',
                DB::raw('SUM(pri.price) as total_product_price')
            )
            ->join('project as p', 'p.id','=','pri.project_id')
            ->join('sales_deal as sd','sd.project_id','=','p.id')
            ->join('product as prod','prod.id','=','pri.product_id')
            ->join('sbu as s','s.id','=','prod.sbu_id')
            ->where('sd.status','on_hands')
            ->whereYear('sd.created_at',2025)
            ->groupBy('p.id', 'p.name', 's.id', 's.name','sd.status')
            ->orderBy('p.name')
            ->get();
    
        $result = [];
    
        foreach ($selectdata as $row) {
            $projectName = $row->project_name;
            $projectStatus = $row->project_status;
            if (!isset($result[$projectName])) {
                $result[$projectName] = [
                    'project_name' => $projectName,
                    'project_status' => $projectStatus, 
                    'revenue_eta' => 0, // Akan dihitung ulang di bawah
                    'revenue_act' => 0,
                    'total_product_price_sum' => 0, // Digunakan untuk perhitungan total
                    'revenue' => []
                ];
            }
        
            // Tambahkan total harga produk ke total project
            $result[$projectName]['total_product_price_sum'] += $row->total_product_price;
        
            // Simpan data per SBU
            $result[$projectName]['revenue'][] = [
                'sbu_name' => $row->sbu_name,
                'total_product_price' => $row->total_product_price
            ];
        }
        
        // **Set revenue_eta dan hitung revenue_percentage**
        foreach ($result as &$project) {
            $project['revenue_eta'] = $project['total_product_price_sum']; // revenue_eta = total semua total_product_price
        
            foreach ($project['revenue'] as &$revenue) {
                $revenue['revenue_percentage'] = $project['revenue_eta'] > 0 
                    ? round(($revenue['total_product_price'] / $project['revenue_eta']) * 100, 2) 
                    : 0;
            }
        
            unset($project['total_product_price_sum']);
        }
    
        unset($project, $revenue); 
    
        // **Kembalikan seluruh hasil, bukan hanya satu elemen pertama**
        return response()->json(array_values($result));
    }
    

    public function getInvoiceData(Request $request){
        $selectdata = DB::table('invoice_realization')->get();
        foreach ($selectdata as $row) {
            $row->percentage_project = $this->getPercentageProject($row->project_id); 
        }
    
        $data = [];
        foreach($selectdata as $row){
            $data_project = $row->percentage_project;
            // dd($row);
            $data[] = [ 
                'project_id'   => $data_project->original['project_id'],
                'sbu_id' => $data_project->original['revenue'][0]['sbu_id'],
                'project_name' => $data_project->original['project_name'],
                'sbu_name' => $data_project->original['revenue'][0]['sbu_name'],
                'revenue_percentage' => $data_project->original['revenue'][0]['revenue_percentage'],
                'invoice_number' => $row->invoice_number,
            ];
        }

        return response()->json([
            'status' => 'success',
            'data' => $data
        ]);
    }

    public function getRevenueByStatus(Request $request){
                // dd($request->query('status'));
        $status = $request->query('status') ?? null;
        $db = DB::Connection('mysql_crm_prod');
        $selectdata = $db->table('project as p')
        ->join('sales_deal as sd','sd.id','=','p.sales_deal_id')
        // ->join('project_revenue_item as pri','pri.project_id','=','p.id')
        ->select(
            'p.id as project_id',
            'p.name as project_name',
            'p.total_revenue as project_revenue',
            'sd.status as project_status',
            'p.created_at as project_date',
            // 'pri.id as id_revenue'
            )
        // ->whereYear('p.created_at', 2023)
        ->when($status, function ($query) use ($status) {
            return $query->where('sd.status','=', $status);
        })
        ->get();

        return response()->json($selectdata);
    }

    private function getPercentageProject($id){
        $project_id = $id ?? null;
        $get = DB::Connection('mysql');

        $selectdata = $get->table('project_revenue_item as pri')
        ->select(
            // 'p.name as project_name',
            // 'prod.name as product_name',
            // 'pri.price as product_price',
            // 's.name as sbu_name',
            // 'pri.created_at as revenue_date'
            'p.id as project_id',
            'p.name as project_name',
            's.name as sbu_name',
            's.id as sbu_id',
            DB::raw('SUM(pri.price) as total_product_price')
            )
        ->join('project as p', 'p.id','=','pri.project_id')
        ->join('product as prod','prod.id','=','pri.product_id')
        ->join('sbu as s','s.id','=','prod.sbu_id')
        ->where('p.id',$project_id) 
        ->groupBy('p.id', 'p.name', 's.id', 's.name')
        ->orderBy('p.name')
        ->get();

        $result = [];

        foreach ($selectdata as $row) {
            $projectName = $row->project_name;
            $projectId = $row->project_id;
            if (!isset($result[$projectName])) {
                $result[$projectName] = [
                    'project_id' => $projectId,
                    'project_name' => $projectName,
                    'revenue_eta' => 0, // Akan dihitung ulang di bawah
                    'revenue_act' => 0,
                    'total_product_price_sum' => 0, // Digunakan untuk perhitungan total
                    'revenue' => []
                ];
            }
        
            // Tambahkan total harga produk ke total project
            $result[$projectName]['total_product_price_sum'] += $row->total_product_price;
        
            // Simpan data per SBU
            $result[$projectName]['revenue'][] = [
                'sbu_id' => $row->sbu_id,
                'sbu_name' => $row->sbu_name,
                'total_product_price' => $row->total_product_price
            ];
        }
        
        // **Set revenue_eta dan hitung revenue_percentage**
        foreach ($result as &$project) {
            $project['revenue_eta'] = $project['total_product_price_sum']; // revenue_eta = total semua total_product_price
        
            foreach ($project['revenue'] as &$revenue) {
                $revenue['revenue_percentage'] = $project['revenue_eta'] > 0 
                    ? round(($revenue['total_product_price'] / $project['revenue_eta']) * 100, 2) 
                    : 0;
            }
        
            unset($project['total_product_price_sum']);
        }
        
        unset($project, $revenue); 
        $response = reset($result);

        return response()->json($response);
    }
}
