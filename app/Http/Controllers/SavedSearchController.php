<?php

namespace App\Http\Controllers;

use App\Actions\SavedSearches\CreateSavedSearch;
use App\Http\Requests\StoreSavedSearchRequest;
use App\Http\Requests\UpdateSavedSearchRequest;
use App\Models\SavedSearch;
use App\Support\SavedSearchFilters;
use Illuminate\Database\UniqueConstraintViolationException;
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

    public function store(StoreSavedSearchRequest $request, CreateSavedSearch $createSavedSearch): RedirectResponse
    {
        $savedSearch = $createSavedSearch->handle($request->user(), $request->validated());

        Inertia::flash('toast', $savedSearch->wasRecentlyCreated
            ? ['type' => 'success', 'message' => __('Search saved.')]
            : ['type' => 'info', 'message' => __('This search is already saved.')]);

        return back();
    }

    public function update(UpdateSavedSearchRequest $request, SavedSearch $savedSearch): RedirectResponse
    {
        $attributes = $request->validated();

        if (isset($attributes['filters'])) {
            $attributes['filters'] = SavedSearchFilters::normalize($attributes['filters']);
            $attributes['fingerprint'] = SavedSearchFilters::fingerprint($attributes['filters']);

            $duplicateExists = $request->user()->savedSearches()
                ->whereKeyNot($savedSearch->id)
                ->where('fingerprint', $attributes['fingerprint'])
                ->exists();

            if ($duplicateExists) {
                Inertia::flash('toast', [
                    'type' => 'info',
                    'message' => __('This search is already saved.'),
                ]);

                return back();
            }
        }

        try {
            $savedSearch->update($attributes);
        } catch (UniqueConstraintViolationException) {
            Inertia::flash('toast', [
                'type' => 'info',
                'message' => __('This search is already saved.'),
            ]);

            return back();
        }

        if (isset($attributes['filters'])) {
            Inertia::flash('toast', [
                'type' => 'success',
                'message' => __('Search updated.'),
            ]);
        }

        return back();
    }

    public function destroy(Request $request, SavedSearch $savedSearch): RedirectResponse
    {
        abort_unless($savedSearch->user_id === $request->user()->id, 403);
        $savedSearch->delete();

        return back();
    }
}
