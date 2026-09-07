<?php

namespace App\Http\Controllers;

use App\Models\Area;
use App\Models\Chamber;
use App\Models\ChamberLayer;
use App\Models\Oven;
use App\Models\ProductModel;
use App\Services\PersonnelScanner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class RxMonitoringController extends Controller
{
    /**
     * The whole app is this one page. Area/chamber selection lives in the
     * query string (?area=NCP2&chamber=5) so refresh, back/forward, and
     * auto-refresh polling all behave predictably.
     */
    public function index(Request $request): Response
    {
        $areas = Area::where('is_active', true)->orderBy('code')->get(['id', 'code', 'name', 'chamber_count', 'layer_count']);

        $area = null;
        $ovens = [];
        $productModels = [];
        $openChambers = [];
        $chamber = null;

        if ($request->filled('area')) {
            $area = Area::where('code', $request->string('area'))->where('is_active', true)->first();
        }

        if ($area) {
            $ovens = Oven::where('area_id', $area->id)->where('is_active', true)->orderBy('oven_no')->get();
            $productModels = ProductModel::where('area_id', $area->id)->where('is_active', true)->orderBy('model_name')->get();

            $openChambers = Chamber::where('area_id', $area->id)
                ->where('status', 'open')
                ->with(['oven:id,oven_no', 'loadedBy:id,name'])
                ->orderBy('chamber_number')
                ->get();

            if ($request->filled('chamber')) {
                $chamber = Chamber::where('area_id', $area->id)
                    ->where('chamber_number', (int) $request->input('chamber'))
                    ->where('status', 'open')
                    ->with([
                        'oven', 'authorizedBy', 'loadedBy', 'peakCheckedBy', 'unloadedBy', 'closedBy',
                        'layers.productModel', 'layers.authorizedPic',
                    ])
                    ->first();
            }
        }

        return Inertia::render('RxMonitoring/Index', [
            'areas' => $areas,
            'area' => $area,
            'ovens' => $ovens,
            'productModels' => $productModels,
            'openChambers' => $openChambers,
            'chamber' => $chamber,
            'selectedChamberNumber' => $request->integer('chamber') ?: null,
        ]);
    }

    /**
     * Step 0: Location Parameter -> New chamber. Requires a PIC badge scan
     * (not just a typed "Authorized by" name) and reserves every layer slot
     * up front, same as the legacy app did, but as real rows joined by a
     * foreign key instead of duplicated Chamber_No text.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'area_id' => ['required', 'exists:areas,id'],
            'chamber_number' => ['required', 'integer', 'min:1'],
            'scanned_code' => ['required', 'string'],
        ]);

        $area = Area::findOrFail($data['area_id']);

        if ($data['chamber_number'] > $area->chamber_count) {
            throw ValidationException::withMessages(['chamber_number' => 'This area only has '.$area->chamber_count.' chambers.']);
        }

        $alreadyOpen = Chamber::where('area_id', $area->id)
            ->where('chamber_number', $data['chamber_number'])
            ->where('status', 'open')
            ->exists();

        if ($alreadyOpen) {
            throw ValidationException::withMessages(['chamber_number' => 'Chamber '.$data['chamber_number'].' is already open.']);
        }

        $pic = PersonnelScanner::requirePic($data['scanned_code']);

        $chamber = DB::transaction(function () use ($area, $data, $pic) {
            $chamber = Chamber::create([
                'area_id' => $area->id,
                'chamber_number' => $data['chamber_number'],
                'shift_date' => now()->toDateString(),
                'status' => 'open',
                'authorized_by_id' => $pic->id,
            ]);

            $layers = [];
            for ($i = 1; $i <= $area->layer_count; $i++) {
                $layers[] = [
                    'chamber_id' => $chamber->id,
                    'layer_no' => $i,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
            ChamberLayer::insert($layers);

            return $chamber;
        });

        return redirect()->route('rx-monitoring.index', [
            'area' => $area->code,
            'chamber' => $chamber->chamber_number,
        ])->with('success', 'Chamber '.$chamber->chamber_number.' opened by '.$pic->name.'.');
    }

    /** Step 1: RX Oven Setup. */
    public function setOven(Request $request, Chamber $chamber)
    {
        $data = $request->validate(['oven_id' => ['required', 'exists:ovens,id']]);
        $oven = Oven::where('id', $data['oven_id'])->where('area_id', $chamber->area_id)->firstOrFail();

        $chamber->update([
            'oven_id' => $oven->id,
            'oven_capacity_kg' => $oven->capacity_kg,
            'peak_temp_target_c' => $oven->peak_temp_target_c,
        ]);
        $chamber->recalculateWeight();

        return back()->with('success', 'RX Oven set to '.$oven->oven_no.'.');
    }

    /** Encode or update one lot in one layer slot. */
    public function storeLayer(Request $request, Chamber $chamber, ChamberLayer $layer)
    {
        abort_unless($layer->chamber_id === $chamber->id, 404);

        if ($chamber->start_time) {
            throw ValidationException::withMessages(['layer' => 'RX has already started for this chamber; layers can no longer be edited.']);
        }

        $data = $request->validate([
            'product_model_id' => ['required', 'exists:product_models,id'],
            'lot_no' => ['nullable', 'string', 'max:60'],
            'lot_quantity' => ['required', 'integer', 'min:1'],
            'rx_type' => ['nullable', 'string', 'max:60'],
            'remarks' => ['nullable', 'string'],
            'work_order_id' => ['nullable', 'string', 'max:60'],
            'scanned_code' => ['required', 'string'],
        ]);

        $model = ProductModel::where('id', $data['product_model_id'])->where('area_id', $chamber->area_id)->firstOrFail();
        $pic = PersonnelScanner::resolve($data['scanned_code']);

        $weightPerTray = round(((float) $model->unit_weight_grams) * $data['lot_quantity'] / 1000, 4);

        $layer->update([
            'product_model_id' => $model->id,
            'lot_no' => $data['lot_no'] ?? null,
            'lot_quantity' => $data['lot_quantity'],
            'rx_type' => $data['rx_type'] ?? null,
            'unit_weight_grams' => $model->unit_weight_grams,
            'weight_per_tray_kg' => $weightPerTray,
            'remarks' => $data['remarks'] ?? null,
            'work_order_id' => $data['work_order_id'] ?? null,
            'authorized_pic_id' => $pic->id,
        ]);

        $chamber->recalculateWeight();

        return back()->with('success', 'Layer '.$layer->layer_no.' updated.');
    }

    public function clearLayer(Chamber $chamber, ChamberLayer $layer)
    {
        abort_unless($layer->chamber_id === $chamber->id, 404);

        if ($chamber->start_time) {
            throw ValidationException::withMessages(['layer' => 'RX has already started for this chamber; layers can no longer be edited.']);
        }

        $layer->update([
            'product_model_id' => null, 'lot_no' => null, 'lot_quantity' => null,
            'rx_type' => null, 'unit_weight_grams' => null, 'weight_per_tray_kg' => null,
            'remarks' => null, 'work_order_id' => null, 'authorized_pic_id' => null,
        ]);

        $chamber->recalculateWeight();

        return back()->with('success', 'Layer '.$layer->layer_no.' cleared.');
    }

    /** Step 2: Before RX (after chamber loading). */
    public function start(Request $request, Chamber $chamber)
    {
        if (! $chamber->oven_id) {
            throw ValidationException::withMessages(['start' => 'Set the RX Oven number first.']);
        }
        if ((float) $chamber->total_weight_kg <= 0) {
            throw ValidationException::withMessages(['start' => 'No lots have been loaded into this chamber yet.']);
        }
        if ($chamber->weight_status === 'overweight') {
            throw ValidationException::withMessages(['start' => 'Oven capacity exceeded. Reduce load quantity before starting.']);
        }

        $data = $request->validate([
            'start_temperature_c' => ['required', 'numeric', 'min:0', 'max:300'],
            'scanned_code' => ['required', 'string'],
        ]);

        $operator = PersonnelScanner::resolve($data['scanned_code']);

        $chamber->update([
            'start_temperature_c' => $data['start_temperature_c'],
            'loaded_by_id' => $operator->id,
            'start_time' => now(),
        ]);

        return back()->with('success', 'Start temperature logged by '.$operator->name.'.');
    }

    /** Step 3: Peak temperature reached. */
    public function peak(Request $request, Chamber $chamber)
    {
        if (! $chamber->start_time) {
            throw ValidationException::withMessages(['peak' => 'Start temperature has not been logged yet.']);
        }

        $data = $request->validate(['scanned_code' => ['required', 'string']]);
        $person = PersonnelScanner::resolve($data['scanned_code']);

        $chamber->update([
            'peak_checked_by_id' => $person->id,
            'peak_temp_time' => now(),
        ]);

        return back()->with('success', 'Peak temperature checked by '.$person->name.'.');
    }

    /** Step 4: After RX (before chamber unloading). */
    public function stop(Request $request, Chamber $chamber)
    {
        if (! $chamber->peak_temp_time) {
            throw ValidationException::withMessages(['stop' => 'Peak temperature has not been checked yet.']);
        }

        $data = $request->validate([
            'stop_temperature_c' => ['required', 'numeric', 'min:0', 'max:300'],
            'scanned_code' => ['required', 'string'],
        ]);

        $operator = PersonnelScanner::resolve($data['scanned_code']);

        $chamber->update([
            'stop_temperature_c' => $data['stop_temperature_c'],
            'unloaded_by_id' => $operator->id,
            'stop_time' => now(),
        ]);

        return back()->with('success', 'Stop temperature logged by '.$operator->name.'.');
    }

    /** Step 5a: Chamber cooling process - start. */
    public function coolingStart(Chamber $chamber)
    {
        if (! $chamber->stop_time) {
            throw ValidationException::withMessages(['cooling' => 'Stop temperature has not been logged yet.']);
        }

        $chamber->update(['cooling_start_time' => now()]);

        return back()->with('success', 'Cooling started.');
    }

    /** Step 5b: Chamber cooling process - end. */
    public function coolingEnd(Chamber $chamber)
    {
        if (! $chamber->cooling_start_time) {
            throw ValidationException::withMessages(['cooling' => 'Cooling has not been started yet.']);
        }

        $chamber->update(['cooling_end_time' => now()]);

        return back()->with('success', 'Cooling finished.');
    }

    /**
     * Step 6: Confirmation. Replaces the old month-digit password with a PIC
     * badge scan - only a PIC badge (prefix 01) can close a chamber out.
     */
    public function close(Request $request, Chamber $chamber)
    {
        if (! $chamber->cooling_end_time) {
            throw ValidationException::withMessages(['close' => 'Cooling has not finished yet.']);
        }

        $data = $request->validate(['scanned_code' => ['required', 'string']]);
        $pic = PersonnelScanner::requirePic($data['scanned_code']);

        $chamber->update([
            'closed_by_id' => $pic->id,
            'closed_time' => now(),
            'status' => 'closed',
        ]);

        return redirect()->route('rx-monitoring.index', ['area' => $chamber->area->code])
            ->with('success', 'Chamber '.$chamber->chamber_number.' confirmed and closed by '.$pic->name.'.');
    }
}
