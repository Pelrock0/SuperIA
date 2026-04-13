<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class UnsubscribeWeeklySummaryController extends Controller
{
    public function handle(Request $request, User $user): Response
    {
        $user->forceFill([
            'weekly_summary_email_opted_in' => false,
        ])->save();

        return response()->view('emails.weekly-summary-unsubscribed', [
            'userName' => $user->name,
        ]);
    }
}
