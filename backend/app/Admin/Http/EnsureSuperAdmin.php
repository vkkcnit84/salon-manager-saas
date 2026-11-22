<?php

namespace App\Admin\Http;

use Closure;
use Illuminate\Http\Request;

class EnsureSuperAdmin
{
    public function handle(Request $request, Closure $next)
    {
        abort_unless($request->user()?->is_super_admin, 403, 'Platform administrators only.');

        return $next($request);
    }
}
