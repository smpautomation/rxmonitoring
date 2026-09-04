<?php

namespace App\Http\Controllers;

use App\Services\PersonnelScanner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * These two endpoints are called with a plain fetch/axios request, not
 * Inertia's router - a badge or work-order scan should show a result
 * ("confirmed: Juan Dela Cruz") inline in a few hundred milliseconds,
 * without reloading the whole page's props. The actual step-commit actions
 * (start/peak/stop/close/etc. in RxMonitoringController) re-validate the
 * scanned code themselves, so nothing here is trusted on its own.
 */
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

    /**
     * Looks up a scanned Work Order ID against the separate `inventory`
     * database (see config/database.php's "inventory" connection) to
     * auto-fill a layer's model / lot / quantity, mirroring the legacy
     * app's cross-database lookup.
     *
     * Some work-order tags are QR codes bundling several fields in one
     * payload, semicolon-separated: "WO12345;MODEL-100;LOT-9;...". Only
     * the first segment is the actual work order id - everything after
     * it is ignored here (the inventory lookup is the source of truth for
     * model/lot/quantity, not whatever else the QR payload claims).
     * A plain scanned/typed work order id with no semicolons passes
     * through this unchanged.
     */
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
