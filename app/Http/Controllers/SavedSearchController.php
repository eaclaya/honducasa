<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSavedSearchRequest;
use App\Http\Requests\UpdateSavedSearchRequest;
use App\Models\SavedSearch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SavedSearchController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('saved-searches/Index', [
            'savedSearches' => $request->user()->savedSearches()->latest()->get()->map(fn (SavedSearch $search) => [
                'id' => $search->id, 'name' => $search->name, 'filters' => $search->filters,
                'alertsEnabled' => $search->alerts_enabled, 'createdAt' => $search->created_at?->diffForHumans(),
            ]),
        ]);
    }

    public function store(StoreSavedSearchRequest $request): RedirectResponse
    {
        $request->user()->savedSearches()->create($request->validated());

        return back();
    }

    public function update(UpdateSavedSearchRequest $request, SavedSearch $savedSearch): RedirectResponse
    {
        $savedSearch->update($request->validated());

        return back();
    }

    public function destroy(Request $request, SavedSearch $savedSearch): RedirectResponse
    {
        abort_unless($savedSearch->user_id === $request->user()->id, 403);
        $savedSearch->delete();

        return back();
    }
}
