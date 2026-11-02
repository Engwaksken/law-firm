<?php

namespace App\Http\Controllers\Website;

use App\Http\Controllers\Controller;
use App\Support\Settings;
use Illuminate\Http\Response;

class RobotsController extends Controller
{
    public function __invoke(): Response
    {
        $lines = ['User-agent: *'];

        if (Settings::get('seo_discourage_indexing')) {
            // Staging / pre-launch: ask crawlers to stay out entirely.
            $lines[] = 'Disallow: /';
        } else {
            array_push($lines, 'Disallow: /admin', 'Disallow: /preview', 'Disallow: /livewire', 'Disallow: /newsletter', '', 'Sitemap: '.route('sitemap'));
        }

        return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
