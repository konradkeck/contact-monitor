<?php

namespace App\Http\Middleware;

use App\Models\SynchronizerServer;
use Closure;
use Illuminate\Http\Request;

class RequireSetup
{
    public function handle(Request $request, Closure $next)
    {
        if (!SynchronizerServer::exists()) {
            return redirect()->route('synchronizer.servers.index');
        }

        return $next($request);
    }
}
