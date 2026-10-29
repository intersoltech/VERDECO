<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PlantScope
{
    public function handle(Request $request, Closure $next)
    {
        if (! Auth::check()) {
            return $next($request);
        }

        $plantId = $request->query('plant_id');

        if ($plantId === null) {
            return $next($request);
        }

        $plant = \App\Domain\Organization\Models\Plant::find((int) $plantId);

        if ($plant === null || ! Auth::user()->canAccessPlant($plant)) {
            abort(403, 'You do not have access to the requested plant.');
        }

        $request->attributes->set('plant_id', $plant->getKey());

        return $next($request);
    }
}
