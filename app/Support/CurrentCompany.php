<?php

namespace App\Support;

use App\Models\CompanyProfile;
use Illuminate\Support\Facades\Session;

class CurrentCompany
{
    public const SESSION_KEY = 'current_company_id';

    public static function bind(int $companyId): void
    {
        app()->instance('current_company_id', $companyId);
    }

    public static function clearBinding(): void
    {
        if (app()->bound('current_company_id')) {
            app()->forgetInstance('current_company_id');
        }
    }

    public static function id(): ?int
    {
        if (app()->bound('current_company_id')) {
            return (int) app('current_company_id');
        }

        $id = Session::get(self::SESSION_KEY);

        if ($id) {
            $exists = CompanyProfile::query()
                ->where('id', $id)
                ->where('is_active', true)
                ->exists();

            if ($exists) {
                return (int) $id;
            }
        }

        $fallback = CompanyProfile::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->value('id');

        if ($fallback) {
            Session::put(self::SESSION_KEY, $fallback);

            return (int) $fallback;
        }

        return null;
    }

    public static function profile(): ?CompanyProfile
    {
        $id = self::id();

        return $id ? CompanyProfile::query()->find($id) : null;
    }

    public static function set(int $companyId): void
    {
        Session::put(self::SESSION_KEY, $companyId);
        self::bind($companyId);
    }

    public static function clear(): void
    {
        Session::forget(self::SESSION_KEY);
        self::clearBinding();
    }
}
