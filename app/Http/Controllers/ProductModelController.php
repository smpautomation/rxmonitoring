<?php

namespace App\Http\Controllers;

use App\Models\ChamberLayer;
use App\Models\ProductModel;
use App\Services\PersonnelScanner;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProductModelController extends Controller
{
    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['checked_by_id'] = $this->resolveCheckedBy($request);
        ProductModel::create($data);

        return back()->with('success', 'Model added.');
    }

    public function update(Request $request, ProductModel $productModel)
    {
        $data = $this->validated($request, $productModel->id);
        // Only overwrite checked_by_id if a fresh badge scan came in on this
        // request - editing other fields shouldn't silently blank out who
        // last verified the weight.
        $newCheckedBy = $this->resolveCheckedBy($request);
        if ($newCheckedBy !== null) {
            $data['checked_by_id'] = $newCheckedBy;
        }
        $productModel->update($data);

        return back()->with('success', 'Model updated.');
    }

    /**
     * A hard delete is only allowed if no layer ever encoded a lot against
     * this model - otherwise historical/exported records would lose their
     * model reference. Deactivating is the normal way to retire a model
     * that has history.
     */
    public function destroy(ProductModel $productModel)
    {
        if (ChamberLayer::where('product_model_id', $productModel->id)->exists()) {
            throw ValidationException::withMessages([
                'model' => 'This model has lot history and can\'t be deleted. Deactivate it instead so it drops out of new selections but past records stay intact.',
            ]);
        }

        $productModel->delete();

        return back()->with('success', 'Model deleted.');
    }

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'area_id' => ['required', 'exists:areas,id'],
            'model_name' => [
                'required', 'string', 'max:100',
                Rule::unique('product_models')->where(fn ($q) => $q->where('area_id', $request->input('area_id')))->ignore($ignoreId),
            ],
            'unit_weight_grams' => ['required', 'numeric', 'min:0.001'],
            'is_active' => ['boolean'],
        ]);
    }

    private function resolveCheckedBy(Request $request): ?int
    {
        if (! $request->filled('checked_by_scanned_code')) {
            return null;
        }

        return PersonnelScanner::resolve($request->string('checked_by_scanned_code'))->id;
    }
}
