<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

abstract class Controller
{
    /**
     * Resolve a safe, clamped per-page value for list endpoints.
     * Prevents memory-exhaustion DoS via an unbounded ?per_page=.
     */
    protected function perPage(Request $request, int $default = 20, int $max = 100): int
    {
        return min($max, max(1, (int) $request->query('per_page', $default)));
    }
}
