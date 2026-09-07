<?php

namespace App\Http\Controllers;

use App\Models\Area;
use App\Models\Chamber;
use App\Models\Oven;
use App\Models\ProductModel;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AreaController extends Controller
{
    public function store(Request $request)
    {
        Area::create($this->validated($request));

        return back()->with('success', 'Area added.');
    }

    public function update(Request $request, Area $area)
    {
        $area->update($this->validated($request, $area->id));

        return back()->with('success', 'Area updated.');
    }

    /**
     * area_id is set to cascadeOnDelete() on ovens, product_models, and
     * chambers (and, through chambers, chamber_layers too) - so deleting a
     * live area here would silently wipe out every oven, model, and unit of
     * production history that ever belonged to it. That's only safe if
     * none of the three exist yet; otherwise this blocks and tells the
     * user to deactivate the area instead.
     */
    public function destroy(Area $area)
    {
        $inUse = Oven::where('area_id', $area->id)->exists()
            || ProductModel::where('area_id', $area->id)->exists()
            || Chamber::where('area_id', $area->id)->exists();

        if ($inUse) {
            throw ValidationException::withMessages([
                'area' => 'This area has ovens, models, or chamber history and can\'t be deleted - that would cascade-delete all of it. Deactivate it instead so it drops out of new selections but past records stay intact.',
            ]);
        }

        $area->delete();

        return back()->with('success', 'Area deleted.');
    }

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:30', Rule::unique('areas')->ignore($ignoreId)],
            'name' => ['required', 'string', 'max:100'],
            'chamber_count' => ['required', 'integer', 'min:1', 'max:50'],
            'layer_count' => ['required', 'integer', 'min:1', 'max:50'],
            'is_active' => ['boolean'],
        ]);

        $data['code'] = strtoupper(trim($data['code']));

        return $data;
    }
}
