<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Manage password fallback
    |--------------------------------------------------------------------------
    | Lets someone unlock the Manage screen (see EnsureManageAccess) with a
    | shared password instead of a PIC badge scan - for supervisors or
    | developers who need access but don't have a badge on them.
    |
    | Leave RX_MANAGE_PASSWORD unset/empty in .env to disable this fallback
    | entirely - only PIC badges will work in that case. If set, treat it
    | like any other shared secret: put it in .env, never commit it, and
    | rotate it if it leaks. The unlock endpoint is rate-limited (see
    | routes/rx-monitoring.php) since this is a single password reachable
    | over the network, not a per-person credential.
    */
    'manage_password' => env('RX_MANAGE_PASSWORD'),
];
