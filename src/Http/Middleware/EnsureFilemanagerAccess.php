<?php

namespace OpenSID\LaravelFilemanager\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class EnsureFilemanagerAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(Gate::allows('filemanager.access'), 403);

        return $next($request);
    }
}
