<?php

namespace App\Http\Controllers\api\v1\Export;

use App\Exports\OrderHistoryExport;
use App\Exports\ProductExport;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
class ExportController extends Controller
{
    public function exportOrderHistory()
    {
        return Excel::download(new OrderHistoryExport(), 'order_history.xlsx');
    }
    public function exportProducts()
    {
        return Excel::download(new ProductExport(), 'products.xlsx');
    }
}
