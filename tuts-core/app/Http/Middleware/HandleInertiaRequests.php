<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Tighten\Ziggy\Ziggy;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        return array_merge(parent::share($request), [
            'auth' => [
                'user' => $request->user(),
            ],
            'ziggy' => fn() => (new Ziggy)->toArray(),
        ]);
    }
}
