<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Guards the Manage CRUD actions (areas/ovens/product-models store, update,
 * destroy) behind a PIC badge scan, without standing up a full auth system.
 *
 * ManageController::index handles its own "locked" state and renders a
 * scan gate instead of the real data when nothing has been unlocked yet -
 * that route is deliberately NOT behind this middleware, since redirecting
 * it to itself on failure would loop. This middleware only needs to sit on
 * the routes that actually change data, so someone can't bypass the gate
 * by POSTing straight to an endpoint they found in devtools.
 *
 * The unlock is a session flag with a sliding expiry (see MINUTES below) -
 * scanning once keeps Manage open for that long, refreshed on each action,
 * then it locks again automatically. This is access control for a shared
 * shop-floor/office terminal, not a substitute for real user accounts; if
 * this app grows real auth, swap this middleware for that instead.
 */
class EnsureManageAccess
{
    public const SESSION_KEY = 'rx_manage_unlocked_until';

    public const UNLOCKED_BY_KEY = 'rx_manage_unlocked_by';

    public const MINUTES = 20;

    public function handle(Request $request, Closure $next)
    {
        $until = $request->session()->get(self::SESSION_KEY);

        if ($until && now()->lt($until)) {
            $request->session()->put(self::SESSION_KEY, now()->addMinutes(self::MINUTES));

            return $next($request);
        }

        return redirect()->route('rx-monitoring.manage.index')
            ->with('error', 'Manage access has expired. Scan a PIC badge to continue.');
    }
}
