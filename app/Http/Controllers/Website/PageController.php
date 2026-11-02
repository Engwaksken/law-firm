<?php

namespace App\Http\Controllers\Website;

use App\Http\Controllers\Controller;
use App\Models\Advocate;
use App\Models\Article;
use App\Models\Office;
use App\Models\PracticeArea;
use App\Models\Testimonial;
use Illuminate\View\View;

class PageController extends Controller
{
    public function home(): View
    {
        $areas = PracticeArea::query()->visible()->get();

        return view('website.home', [
            // Featured areas first; fall back to the first six so the section is never empty.
            'practiceAreas' => $areas->where('is_featured', true)->whenEmpty(fn () => $areas)->take(6),
            'advocates' => Advocate::query()->published()->with('practiceAreas')
                ->orderByDesc('is_featured')->take(4)->get(),
            'insights' => Article::query()->published()->where('type', '!=', 'announcement')
                ->orderByDesc('is_featured')->latest('published_at')->take(3)->get(),
            'news' => Article::query()->published()->where('type', 'announcement')->latest('published_at')->take(3)->get(),
            'testimonials' => Testimonial::query()->publishable()->take(3)->get(),
        ]);
    }

    public function about(): View
    {
        return view('website.about', [
            'leaders' => Advocate::query()->published()->with('practiceAreas')
                ->where('is_featured', true)->take(8)->get(),
            'offices' => Office::query()->where('is_active', true)->orderByDesc('is_head_office')->orderBy('name')->get(),
        ]);
    }

    public function privacy(): View
    {
        return view('website.privacy');
    }

    public function contact(): View
    {
        return view('website.contact', [
            'offices' => Office::query()->where('is_active', true)->orderByDesc('is_head_office')->orderBy('name')->get(),
        ]);
    }

    public function consultation(): View
    {
        return view('website.consultation');
    }
}
