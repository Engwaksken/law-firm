<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class CalendarFeedController extends Controller
{
    public function __invoke(Request $request, string $token): \Illuminate\Http\Response
    {
        abort_unless(preg_match('/^[a-f0-9]{64}$/', $token) === 1, 404);

        $user = User::query()->where('is_active', true)
            ->where('calendar_feed_token_hash', hash('sha256', $token))
            ->first();
        abort_unless($user && ! $user->trashed(), 404);

        $request->setUserResolver(fn () => $user);

        return app(CalendarExportController::class)($request);
    }
}
