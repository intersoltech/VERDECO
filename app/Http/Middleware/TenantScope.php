<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TenantScope
{
    public function handle(Request $request, Closure $next)
    {
        if (! Auth::check()) {
            return $next($request);
        }

        $user = Auth::user();
        $tenantId = $request->query('tenant_id');

        if ($tenantId === null) {
            return $next($request);
        }

        $tenant = \App\Domain\Organization\Models\Organization::find((int) $tenantId);

        if ($tenant === null || ! $user->can('view-all-tenants')) {
            abort(403, 'You do not have access to the requested tenant.');
        }

        $request->attributes->set('tenant_id', $tenant->getKey());

        return $next($request);
    }
}
