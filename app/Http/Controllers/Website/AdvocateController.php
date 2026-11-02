<?php

namespace App\Http\Controllers\Website;

use App\Http\Controllers\Controller;
use App\Models\Advocate;
use App\Models\PracticeArea;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdvocateController extends Controller
{
    public function index(Request $request): View
    {
        $area = $request->filled('practice_area')
            ? PracticeArea::query()->visible()->where('slug', $request->string('practice_area'))->first()
            : null;
        $search = trim((string) $request->string('q'));

        $advocates = Advocate::query()->published()->with('practiceAreas')
            ->when($area, fn ($q) => $q->whereHas('practiceAreas', fn ($p) => $p->whereKey($area->id)))
            ->when($search !== '', fn ($q) => $q->where('name', 'like', '%'.str_replace(['%', '_'], ['\%', '\_'], $search).'%'))
            ->get();

        return view('website.advocates.index', [
            'advocates' => $advocates,
            'practiceAreas' => PracticeArea::query()->visible()->get(['id', 'name', 'slug']),
            'selectedArea' => $area,
            'search' => $search,
        ]);
    }

    public function show(Advocate $advocate): View
    {
        // Unpublished or unverified profiles don't exist as far as the public is concerned.
        abort_unless(Advocate::query()->published()->whereKey($advocate->id)->exists(), 404);

        return view('website.advocates.show', [
            'advocate' => $advocate->load(['practiceAreas' => fn ($q) => $q->visible(), 'office']),
        ]);
    }
}
