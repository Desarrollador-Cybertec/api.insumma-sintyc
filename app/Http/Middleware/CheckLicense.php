<?php

namespace App\Http\Middleware;

use App\Services\LicenseService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckLicense
{
    public function __construct(
        protected LicenseService $licenseService,
    ) {}

    public function handle(Request $request, Closure $next, ?string $action = null): Response
    {
        if ($action) {
            $this->licenseService->authorize($action);
        }

        return $next($request);
    }
}
