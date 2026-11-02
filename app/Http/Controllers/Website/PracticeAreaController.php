<?php

namespace App\Http\Controllers\Website;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\PracticeArea;
use Illuminate\View\View;

class PracticeAreaController extends Controller
{
    public function index(): View
    {
        return view('website.practice-areas.index', [
            'practiceAreas' => PracticeArea::query()->visible()->get(),
        ]);
    }

    public function show(PracticeArea $practiceArea): View
    {
        abort_unless($practiceArea->is_active, 404);

        return view('website.practice-areas.show', [
            'area' => $practiceArea,
            'advocates' => $practiceArea->advocates()->published()->get(),
            'related' => PracticeArea::query()->visible()->whereKeyNot($practiceArea->id)->take(3)->get(),
            // Insights tagged with this practice area's name.
            'insights' => Article::query()->published()->whereJsonContains('tags', $practiceArea->name)
                ->latest('published_at')->take(3)->get(),
        ]);
    }
}
