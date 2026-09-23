<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\SalaryComponent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use App\Models\Branch;

class SalaryComponentController extends Controller
{
    public function index(): Response{
        return Inertia::render('Settings/SalaryComponents',
        [
            'components' => SalaryComponent::orderBy('type')->orderBy('name')->get(),
            'branches' => Branch::orderBy('name')->get(),
            'success' => session('success')
        ]);
    }

    public function store(Request $request): RedirectResponse{
        $validated = $this->validateComponent($request);
        $validated['slug'] = $this->uniqueSlug($validated['name']);

        SalaryComponent::create($validated);

        return back()->with('success', 'Salary component created successfully');
    }

    public function update(Request $request, SalaryComponent $component): RedirectResponse
    {
        $validated = $this->validateComponent($request);
        $validated['slug'] = $this->uniqueSlug($validated['name'], $component->id);

        $component->update($validated);

        return back()->with('success', 'Salary component updated successfully');
    }

    public function destroy(SalaryComponent $component): RedirectResponse
    {
        $component->delete();

        return back()->with('success', 'Salary component deleted successfully');
    }

    public function validateComponent(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(['earning', 'deduction'])],
            'calculation_type' => ['required', Rule::in(['fixed', 'percentage', 'statutory'])],
            'default_value' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
            'is_taxable' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);
    }

    public function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name, '_');
        $slug = $base;
        $i = 2;

        while(
            SalaryComponent::where('slug', $slug)->when($ignoreId, fn($q) => $q->where('id', '!=', $ignoreId))
            ->exists()
        ){
            $slug = $base . '_' . $i++;
        }
        return $slug;
    }
}
