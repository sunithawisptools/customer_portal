<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Config;

class TicketMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        if (Config::get("customer_portal.ticketing_enabled") !== true)
        {
            return redirect()->back()->withErrors(trans("errors.sectionDisabled"));
        }
        return $next($request);
    }
}
