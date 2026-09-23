<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Security/Audit', [
            'logs' => AuditLog::with('user')->latest()->limit(200)->get(),
            'environment' => [
                'app_env' => config('app.env'),
                'app_debug' => (bool) config('app.debug'),
                'session_driver' => config('session.driver'),
                'queue_connection' => config('queue.default'),
                'mail_mailer' => config('mail.default'),
                'db_connection' => config('database.default'),
            ],
        ]);
    }
}