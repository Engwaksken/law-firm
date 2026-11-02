<?php

namespace App\Http\Controllers\Website;

use App\Http\Controllers\Controller;
use App\Models\Career;
use Illuminate\View\View;

class CareerController extends Controller
{
    public function index(): View
    {
        $open = Career::query()->open()->with('office')->orderBy('closes_at')->get();

        return view('website.careers.index', [
            'groups' => collect(Career::TYPES)->map(fn ($label, $type) => [
                'label' => $label,
                'careers' => $open->where('type', $type)->values(),
            ])->filter(fn ($group) => $group['careers']->isNotEmpty()),
        ]);
    }

    public function show(Career $career): View
    {
        // Closed-but-published vacancies remain viewable (links in old emails) without the form.
        abort_unless($career->status !== 'draft' && $career->published_at?->isPast(), 404);

        return view('website.careers.show', ['career' => $career->load(['office', 'department'])]);
    }
}
