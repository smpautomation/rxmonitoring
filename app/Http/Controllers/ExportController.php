<?php

namespace App\Http\Controllers;

use App\Models\Area;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Successor to the legacy frmExport.vb, which let an operator pick any
 * rx_sheet column from a dropdown, type a keyword, and get a LIKE '%...%'
 * search - then exported by literally copying the results grid to the
 * clipboard and pasting it into a new Excel workbook via COM automation.
 *
 * That "pick a column" search was a workaround for not having real filters,
 * not a deliberate design choice, so it's replaced here with structured
 * filters (area, status, date range, keyword-across-the-fields-that-
 * actually-matter) and a real streamed CSV download instead of a
 * clipboard/COM trick that only ever worked on the desktop app itself.
 */
class ExportController extends Controller
{
    private const MAX_ON_SCREEN = 500;

    public function index(Request $request): InertiaResponse
    {
        $rows = (clone $this->baseQuery($request))
            ->select($this->selectColumns())
            ->limit(self::MAX_ON_SCREEN)
            ->get();

        return Inertia::render('Export/Index', [
            'areas' => Area::where('is_active', true)->orderBy('code')->get(['code', 'name']),
            'filters' => $request->only(['area', 'status', 'date_from', 'date_to', 'keyword']),
            'rows' => $rows,
            'truncated' => $rows->count() >= self::MAX_ON_SCREEN,
        ]);
    }

    public function download(Request $request): StreamedResponse
    {
        $rows = $this->baseQuery($request)->select($this->selectColumns())->get();
        $filename = 'rx-monitoring-export-'.now()->format('Y-m-d_His').'.csv';

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');

            fputcsv($out, [
                'Shift date', 'Area', 'Chamber', 'Oven', 'Layer', 'Model', 'Lot no.', 'Quantity',
                'Baking category', 'Weight per tray (kg)', 'Encoded by',
                'Authorized by', 'Start temp (C)', 'Loaded by', 'Start time',
                'Peak checked by', 'Peak time', 'Stop temp (C)', 'Unloaded by', 'Stop time',
                'Cooling start', 'Cooling end', 'Closed by', 'Closed time', 'Status',
            ]);

            foreach ($rows as $r) {
                fputcsv($out, [
                    $r->shift_date, $r->area, 'Chamber '.str_pad((string) $r->chamber_number, 2, '0', STR_PAD_LEFT),
                    $r->oven_no, $r->layer_no, $r->model_name, $r->lot_no, $r->lot_quantity,
                    $r->rx_type, $r->weight_per_tray_kg, $r->authorized_pic,
                    $r->authorized_by, $r->start_temperature_c, $r->loaded_by, $r->start_time,
                    $r->peak_checked_by, $r->peak_temp_time, $r->stop_temperature_c, $r->unloaded_by, $r->stop_time,
                    $r->cooling_start_time, $r->cooling_end_time, $r->closed_by, $r->closed_time, $r->status,
                ]);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /**
     * One row per lot-in-a-layer, flattened back out to roughly the shape
     * the legacy rx_sheet export had - familiar for anyone used to that
     * spreadsheet, but built from the normalized chambers/chamber_layers
     * tables instead of one bloated table.
     */
    private function baseQuery(Request $request)
    {
        $query = DB::table('chamber_layers as l')
            ->join('chambers as c', 'c.id', '=', 'l.chamber_id')
            ->join('areas as a', 'a.id', '=', 'c.area_id')
            ->leftJoin('ovens as o', 'o.id', '=', 'c.oven_id')
            ->leftJoin('product_models as m', 'm.id', '=', 'l.product_model_id')
            ->leftJoin('personnel as p_auth', 'p_auth.id', '=', 'c.authorized_by_id')
            ->leftJoin('personnel as p_load', 'p_load.id', '=', 'c.loaded_by_id')
            ->leftJoin('personnel as p_peak', 'p_peak.id', '=', 'c.peak_checked_by_id')
            ->leftJoin('personnel as p_unload', 'p_unload.id', '=', 'c.unloaded_by_id')
            ->leftJoin('personnel as p_close', 'p_close.id', '=', 'c.closed_by_id')
            ->leftJoin('personnel as p_lot', 'p_lot.id', '=', 'l.authorized_pic_id')
            ->whereNotNull('l.product_model_id'); // skip never-encoded empty layer slots

        if ($request->filled('area')) {
            $query->where('a.code', $request->string('area'));
        }
        if ($request->filled('status') && $request->input('status') !== 'all') {
            $query->where('c.status', $request->input('status'));
        }
        if ($request->filled('date_from')) {
            $query->whereDate('c.shift_date', '>=', $request->date('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->whereDate('c.shift_date', '<=', $request->date('date_to'));
        }
        if ($request->filled('keyword')) {
            $kw = '%'.$request->string('keyword').'%';
            $query->where(function ($q) use ($kw) {
                $q->where('l.lot_no', 'like', $kw)
                    ->orWhere('m.model_name', 'like', $kw)
                    ->orWhere('l.remarks', 'like', $kw)
                    ->orWhere('l.work_order_id', 'like', $kw);
            });
        }

        return $query->orderByDesc('c.shift_date')->orderBy('c.chamber_number')->orderBy('l.layer_no');
    }

    private function selectColumns(): array
    {
        return [
            'c.shift_date', 'a.code as area', 'c.chamber_number',
            'o.oven_no', 'l.layer_no', 'm.model_name', 'l.lot_no', 'l.lot_quantity',
            'l.rx_type', 'l.weight_per_tray_kg', 'p_lot.name as authorized_pic',
            'p_auth.name as authorized_by', 'c.start_temperature_c', 'p_load.name as loaded_by',
            'c.start_time', 'p_peak.name as peak_checked_by', 'c.peak_temp_time',
            'c.stop_temperature_c', 'p_unload.name as unloaded_by', 'c.stop_time',
            'c.cooling_start_time', 'c.cooling_end_time',
            'p_close.name as closed_by', 'c.closed_time', 'c.status',
        ];
    }
}
