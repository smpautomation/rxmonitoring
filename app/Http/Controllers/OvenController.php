<?php

namespace App\Http\Controllers;

use App\Models\Chamber;
use App\Models\Oven;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class OvenController extends Controller
{
    public function store(Request $request)
    {
        $data = $this->validated($request);
        Oven::create($data);

        return back()->with('success', 'Oven added.');
    }

    public function update(Request $request, Oven $oven)
    {
        $data = $this->validated($request, $oven->id);
        $oven->update($data);

        return back()->with('success', 'Oven updated.');
    }

    /**
     * A hard delete is only allowed if nothing in the chambers table ever
     * pointed at this oven - otherwise historical/exported records would
     * lose their oven reference. Deactivating (is_active = false via the
     * edit form) is the normal way to retire an oven that has history.
     */
    public function destroy(Oven $oven)
    {
        if (Chamber::where('oven_id', $oven->id)->exists()) {
            throw ValidationException::withMessages([
                'oven' => 'This oven has chamber history and can\'t be deleted. Deactivate it instead so it drops out of new selections but past records stay intact.',
            ]);
        }

        $oven->delete();

        return back()->with('success', 'Oven deleted.');
    }

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'area_id' => ['required', 'exists:areas,id'],
            'oven_no' => [
                'required', 'string', 'max:60',
                Rule::unique('ovens')->where(fn ($q) => $q->where('area_id', $request->input('area_id')))->ignore($ignoreId),
            ],
            'capacity_kg' => ['nullable', 'numeric', 'min:0'],
            'peak_temp_target_c' => ['nullable', 'numeric', 'min:0', 'max:300'],
            'is_active' => ['boolean'],
        ]);
    }
}
