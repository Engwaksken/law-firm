<?php

namespace App\Http\Controllers\Website;

use App\Http\Controllers\Controller;
use App\Models\NewsletterSubscriber;
use Illuminate\View\View;

/** Both routes use signed URLs, so links can't be forged to (un)subscribe other people. */
class NewsletterController extends Controller
{
    public function confirm(NewsletterSubscriber $subscriber): View
    {
        $subscriber->forceFill(['confirmed_at' => $subscriber->confirmed_at ?? now(), 'unsubscribed_at' => null])->save();

        return view('website.newsletter-status', [
            'title' => 'Subscription confirmed',
            'message' => 'Thank you. You will now receive our legal updates and newsletters.',
        ]);
    }

    public function unsubscribe(NewsletterSubscriber $subscriber): View
    {
        $subscriber->forceFill(['unsubscribed_at' => now()])->save();

        return view('website.newsletter-status', [
            'title' => 'You have been unsubscribed',
            'message' => 'You will no longer receive our newsletters. You can subscribe again at any time.',
        ]);
    }
}
