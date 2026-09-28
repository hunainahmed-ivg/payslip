<?php

namespace App\Http\Controllers;

use App\Models\PayrollRun;
use App\Models\StampedCopyRequest;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        $user = auth()->user();

        if (! $user?->isAdmin()) {
            return Inertia::render('Dashboard', [
                'stats' => null,
                'isAdmin' => false,
            ]);
        }

        return Inertia::render('Dashboard', [
            'isAdmin' => true,
            'stats' => [
                'open_drafts' => PayrollRun::where('status', 'draft')->count(),
                'pending_stamps' => StampedCopyRequest::where('status', 'pending')->count(),
                'approved_ready' => PayrollRun::where('status', 'approved')->count(),
                'latest_period' => PayrollRun::orderByDesc('period')->value('period'),
            ],
        ]);
    }
}
