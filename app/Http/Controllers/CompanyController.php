<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\CompanyProfile;
use App\Models\SalaryComponent;
use App\Support\CurrentCompany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CompanyController extends Controller
{
    public function index(): Response
    {
        $user = auth()->user();

        $companiesQuery = CompanyProfile::query()
            ->withCount(['branches', 'employees']);

        if ($user && ! $user->isPlatformOperator()) {
            $companyId = $user->effectiveCompanyId();
            $companiesQuery->where('id', $companyId);
        }

        $companies = $companiesQuery
            ->orderBy('company_name')
            ->get()
            ->map(function (CompanyProfile $company) {
                return [
                    'id' => $company->id,
                    'company_name' => $company->company_name,
                    'tax_id' => $company->tax_id,
                    'registration_number' => $company->registration_number,
                    'address' => $company->address,
                    'template_type' => $company->template_type,
                    'is_active' => (bool) $company->is_active,
                    'branches_count' => $company->branches_count,
                    'employees_count' => $company->employees_count,
                    'is_current' => (int) CurrentCompany::id() === (int) $company->id,
                ];
            });

        return Inertia::render('Companies/Index', [
            'companies' => $companies,
            'currentCompanyId' => CurrentCompany::id(),
            'success' => session('success'),
            'error' => session('error'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'company_name' => ['required', 'string', 'max:255', 'unique:company_profiles,company_name'],
            'tax_id' => ['nullable', 'string', 'max:255'],
            'registration_number' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string'],
            'copy_salary_components' => ['nullable', 'boolean'],
            'create_default_branch' => ['nullable', 'boolean'],
        ]);

        $company = DB::transaction(function () use ($validated, $request) {
            $company = CompanyProfile::create([
                'company_name' => $validated['company_name'],
                'tax_id' => $validated['tax_id'] ?? null,
                'registration_number' => $validated['registration_number'] ?? null,
                'address' => $validated['address'] ?? null,
                'template_type' => 'modern',
                'primary_color' => '#1D4ED8',
                'accent_color' => '#0EA5E9',
                'font_family' => 'Inter',
                'page_margin' => '18mm',
                'is_active' => true,
            ]);

            if ($request->boolean('create_default_branch', true)) {
                Branch::create([
                    'company_id' => $company->id,
                    'code' => 'HQ-'.$company->id,
                    'name' => 'Head Office',
                    'location' => $validated['address'] ?? null,
                    'currency_code' => 'USD',
                    'currency_symbol' => '$',
                    'is_active' => true,
                ]);
            }

            if ($request->boolean('copy_salary_components', true)) {
                $sourceId = CurrentCompany::id();
                $sourceComponents = SalaryComponent::query()
                    ->when($sourceId, fn ($q) => $q->where('company_id', $sourceId))
                    ->orderBy('id')
                    ->get();

                foreach ($sourceComponents as $component) {
                    SalaryComponent::create([
                        'company_id' => $company->id,
                        'name' => $component->name,
                        'slug' => $component->slug,
                        'type' => $component->type,
                        'calculation_type' => $component->calculation_type,
                        'default_value' => $component->default_value,
                        'is_taxable' => $component->is_taxable,
                        'is_active' => $component->is_active,
                        'description' => $component->description,
                    ]);
                }
            }

            return $company;
        });

        CurrentCompany::set($company->id);

        return redirect()
            ->route('companies.index')
            ->with('success', "Company \"{$company->company_name}\" created and set as active.");
    }

    public function update(Request $request, CompanyProfile $company): RedirectResponse
    {
        $validated = $request->validate([
            'company_name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('company_profiles', 'company_name')->ignore($company->id),
            ],
            'tax_id' => ['nullable', 'string', 'max:255'],
            'registration_number' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string'],
            'is_active' => ['required', 'boolean'],
        ]);

        $company->update($validated);

        return back()->with('success', 'Company updated successfully.');
    }

    public function switch(CompanyProfile $company): RedirectResponse
    {
        abort_unless($company->is_active, 403, 'Cannot switch to an inactive company.');
        abort_unless(auth()->user()?->canAccessCompany($company->id), 403, 'You cannot access that company.');

        CurrentCompany::set($company->id);

        return back()->with('success', "Switched to {$company->company_name}.");
    }

    public function destroy(CompanyProfile $company): RedirectResponse
    {
        $total = CompanyProfile::count();
        if ($total <= 1) {
            return back()->with('error', 'You must keep at least one company.');
        }

        if ((int) CurrentCompany::id() === (int) $company->id) {
            $next = CompanyProfile::query()
                ->where('id', '!=', $company->id)
                ->where('is_active', true)
                ->orderBy('id')
                ->first();

            if ($next) {
                CurrentCompany::set($next->id);
            } else {
                CurrentCompany::clear();
            }
        }

        $name = $company->company_name;
        $company->delete();

        return redirect()
            ->route('companies.index')
            ->with('success', "Company \"{$name}\" deleted.");
    }
}
