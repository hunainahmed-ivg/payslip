<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        User::query()
            ->whereHas('employee')
            ->where('email', '!=', 'admin@example.com')
            ->where(function ($query) {
                $query->whereNull('role')
                    ->orWhere('role', 'admin');
            })
            ->update(['role' => 'employee']);
    }

    public function down(): void
    {
        // Non-destructive: roles are not reverted automatically.
    }
};
