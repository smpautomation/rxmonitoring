<?php

namespace App\Http\Controllers;

use App\Models\Area;
use App\Models\Oven;
use App\Models\ProductModel;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * Back-office CRUD for the three lookup tables (areas, ovens,
 * product_models) that feed the main monitoring page's dropdowns. The
 * legacy app had no admin screen for any of them - areas were a hardcoded
 * password list in the VB source, and oven_list / model_list were
 * maintained by hand directly in the database. This is meant to sit
 * behind whatever admin/supervisor auth your app uses, not be reachable
 * by shop-floor operators - it's not linked from the main page's header
 * for that reason.
 *
 * All three lists are small enough (dozens of rows, not thousands) to
 * load in full and filter client-side by area rather than round-tripping
 * to the server for every filter change.
 */
class ManageController extends Controller
{
    public function index(): InertiaResponse
    {
        return Inertia::render('Manage/Index', [
            'areas' => Area::orderBy('code')->get(),
            'ovens' => Oven::with('area:id,code')
                ->orderBy('area_id')->orderBy('oven_no')->get(),
            'productModels' => ProductModel::with(['area:id,code', 'checkedBy:id,name'])
                ->orderBy('area_id')->orderBy('model_name')->get(),
        ]);
    }
}
