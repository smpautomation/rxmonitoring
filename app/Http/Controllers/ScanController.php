<?php

namespace App\Http\Controllers;

use App\Services\PersonnelScanner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ScanController extends Controller
{
    public function personnel(Request $request)
    {
        $request->validate([
            'code' => ['required', 'string'],
            'require_pic' => ['sometimes', 'boolean'],
        ]);

        try {
            $person = $request->boolean('require_pic')
                ? PersonnelScanner::requirePic($request->string('code'))
                : PersonnelScanner::resolve($request->string('code'));
        } catch (InvalidArgumentException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'ok' => true,
            'employee_id' => $person->employee_id,
            'name' => $person->name,
            'role' => $person->role,
        ]);
    }

    public function workOrder(Request $request)
    {
        $request->validate(['work_order_id' => ['required', 'string']]);

        $workOrderId = strtoupper(trim(explode(';', trim($request->string('work_order_id')))[0]));

        if ($workOrderId === '') {
            return response()->json(['ok' => false, 'message' => 'That code didn\'t contain a work order id.'], 422);
        }

        $row = DB::connection('inventory')
            ->table('datalist')
            ->where('ID', $workOrderId)
            ->first();

        if (! $row) {
            return response()->json(['ok' => false, 'message' => 'No work order found for "'.$workOrderId.'".'], 404);
        }

        return response()->json([
            'ok' => true,
            'work_order_id' => $workOrderId,
            'model_name' => $row->Model_Name ?? null,
            'lot_no' => $row->Lot_No ?? null,
            'quantity' => $row->Running_Quantity_2 ?? null,
        ]);
    }
}
