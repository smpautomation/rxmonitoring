<?php

namespace App\Http\Controllers;

use App\Http\Middleware\EnsureManageAccess;
use App\Models\Area;
use App\Models\Oven;
use App\Models\ProductModel;
use App\Services\PersonnelScanner;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class ManageController extends Controller
{
    public function index(Request $request): InertiaResponse
    {
        $until = $request->session()->get(EnsureManageAccess::SESSION_KEY);
        $unlocked = $until && now()->lt($until);

        if (! $unlocked) {
            return Inertia::render('Manage/Index', [
                'locked' => true,
                'unlockedBy' => null,
                'areas' => [],
                'ovens' => [],
                'productModels' => [],
            ]);
        }

        return Inertia::render('Manage/Index', [
            'locked' => false,
            'unlockedBy' => $request->session()->get(EnsureManageAccess::UNLOCKED_BY_KEY),
            'areas' => Area::orderBy('code')->get(),
            'ovens' => Oven::with('area:id,code')
                ->orderBy('area_id')->orderBy('oven_no')->get(),
            'productModels' => ProductModel::with(['area:id,code', 'checkedBy:id,name'])
                ->orderBy('area_id')->orderBy('model_name')->get(),
        ]);
    }

    public function unlock(Request $request)
    {
        $data = $request->validate([
            'scanned_code' => ['nullable', 'string'],
            'password' => ['nullable', 'string'],
        ]);

        if (blank($data['scanned_code'] ?? null) && blank($data['password'] ?? null)) {
            throw ValidationException::withMessages([
                'unlock' => 'Scan a PIC badge or enter the password.',
            ]);
        }

        if (filled($data['scanned_code'] ?? null)) {
            $pic = PersonnelScanner::requirePic($data['scanned_code']);
            $unlockedBy = $pic->name;
        } else {
            $configured = config('rx-monitoring.manage_password');
            $valid = is_string($configured) && $configured !== ''
                && hash_equals($configured, (string) $data['password']);

            if (! $valid) {
                throw ValidationException::withMessages(['password' => 'Incorrect password.']);
            }

            $unlockedBy = 'password';
        }

        $request->session()->put(EnsureManageAccess::SESSION_KEY, now()->addMinutes(EnsureManageAccess::MINUTES));
        $request->session()->put(EnsureManageAccess::UNLOCKED_BY_KEY, $unlockedBy);

        return back()->with('success', "Manage unlocked ({$unlockedBy}).");
    }

    public function lock(Request $request)
    {
        $request->session()->forget([EnsureManageAccess::SESSION_KEY, EnsureManageAccess::UNLOCKED_BY_KEY]);

        return redirect()->route('rx-monitoring.manage.index');
    }
}
