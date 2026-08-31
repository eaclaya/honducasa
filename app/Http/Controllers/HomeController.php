<?php

namespace App\Http\Controllers;

use App\Models\SavedSearch;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function __invoke(Request $request): Response
    {
        return Inertia::render('Welcome', [
            'savedSearches' => fn () => $request->user()?->savedSearches()
                ->latest()
                ->limit(6)
                ->get(['id', 'name', 'filters'])
                ->map(fn (SavedSearch $savedSearch) => [
                    'id' => $savedSearch->id,
                    'name' => $savedSearch->name,
                    'filters' => $savedSearch->filters,
                ]) ?? [],
        ]);
    }
}
