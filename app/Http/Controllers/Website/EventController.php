<?php

namespace App\Http\Controllers\Website;

use App\Http\Controllers\Controller;
use App\Models\Event;
use Illuminate\View\View;

class EventController extends Controller
{
    public function index(): View
    {
        $upcoming = Event::query()->upcoming()->with('image')->get();

        return view('website.events.index', [
            'upcoming' => $upcoming,
            'past' => Event::query()->visible()->with('image')
                ->whereNotIn('id', $upcoming->pluck('id'))
                ->where('starts_at', '<', now())
                ->orderByDesc('starts_at')
                ->paginate(9, pageName: 'past')
                ->withQueryString(),
        ]);
    }

    public function show(Event $event): View
    {
        abort_unless(Event::query()->visible()->whereKey($event->id)->exists(), 404);

        return view('website.events.show', ['event' => $event->load('image')]);
    }
}
