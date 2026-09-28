<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\CompanyProfile;
use App\Support\CurrentCompany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class CompanyProfileController extends Controller
{
    public function edit(): Response
    {
        return Inertia::render('Settings/VisualIdentity', [
            'profile' => $this->profileForUi(),
            'success' => session('success'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        if ($request->has('tax_brackets') && is_array($request->input('tax_brackets'))) {
            $brackets = collect($request->input('tax_brackets'))
                ->map(function ($bracket) {
                    if (! is_array($bracket)) {
                        return $bracket;
                    }
                    if (($bracket['to'] ?? null) === '') {
                        $bracket['to'] = null;
                    }

                    return $bracket;
                })
                ->all();

            $request->merge(['tax_brackets' => $brackets]);
        }

        $validated = $request->validate([
            'company_name' => 'required|string|max:255',
            'tax_id' => 'nullable|string|max:255',
            'registration_number' => 'nullable|string|max:255',
            'address' => 'nullable|string',
            'header_image' => 'nullable|file|mimes:png,jpg,jpeg,webp,svg|max:4096',
            'footer_image' => 'nullable|file|mimes:png,jpg,jpeg,webp,svg|max:4096',
            'remove_header' => 'nullable|boolean',
            'remove_footer' => 'nullable|boolean',
            'template_type' => 'required|in:modern,classic,compact,custom',
            'custom_html' => 'nullable|string',
            'primary_color' => 'required|string|max:7',
            'accent_color' => 'required|string|max:7',
            'font_family' => 'required|string|max:255',
            'page_margin' => 'required|string|max:255',
            'tax_brackets' => 'nullable|array',
            'tax_brackets.*.from' => 'required_with:tax_brackets|numeric|min:0',
            'tax_brackets.*.to' => 'nullable|numeric|min:0',
            'tax_brackets.*.rate' => 'required_with:tax_brackets|numeric|min:0|max:100',
        ]);

        $profile = $this->requireActiveCompany();

        if ($request->hasFile('header_image')) {
            if ($profile->header_image_path) {
                Storage::disk('public')->delete($profile->header_image_path);
            }
            $validated['header_image_path'] = $request->file('header_image')->store('letterheads', 'public');
        } elseif ($request->boolean('remove_header')) {
            if ($profile->header_image_path) {
                Storage::disk('public')->delete($profile->header_image_path);
            }
            $validated['header_image_path'] = null;
        }

        if ($request->hasFile('footer_image')) {
            if ($profile->footer_image_path) {
                Storage::disk('public')->delete($profile->footer_image_path);
            }
            $validated['footer_image_path'] = $request->file('footer_image')->store('letterheads', 'public');
        } elseif ($request->boolean('remove_footer')) {
            if ($profile->footer_image_path) {
                Storage::disk('public')->delete($profile->footer_image_path);
            }
            $validated['footer_image_path'] = null;
        }

        unset($validated['header_image'], $validated['footer_image'], $validated['remove_header'], $validated['remove_footer']);

        if (array_key_exists('tax_brackets', $validated)) {
            $validated['tax_brackets'] = collect($validated['tax_brackets'] ?? [])
                ->map(fn ($bracket) => [
                    'from' => (float) ($bracket['from'] ?? 0),
                    'to' => ($bracket['to'] === null || $bracket['to'] === '')
                        ? null
                        : (float) $bracket['to'],
                    'rate' => (float) ($bracket['rate'] ?? 0),
                ])
                ->values()
                ->all();
        }

        $profile->update($validated);
        AuditLog::record('settings.visual_identity_updated', 'Company Branding', 'Letterhead, template or brand tokens changed for '.$profile->company_name.'.');

        return back()->with('success', 'Company branding and payslip template settings saved.');
    }

    public function activateTemplate(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'template_type' => 'required|in:modern,classic,compact',
        ]);

        $profile = $this->requireActiveCompany();
        $profile->update(['template_type' => $validated['template_type']]);

        AuditLog::record(
            'settings.template_activated',
            'Payslip Template',
            'Active template set to '.$validated['template_type'].' for '.$profile->company_name.'.'
        );

        return back()->with('success', ucfirst($validated['template_type']).' template is now active for new payslip PDFs.');
    }

    public function show(): Response
    {
        return Inertia::render('Settings/CompanyProfile', [
            'profile' => $this->profileForUi(),
        ]);
    }

    public function templates(): Response
    {
        return Inertia::render('Settings/PayslipTemplates', [
            'profile' => $this->profileForUi(),
            'success' => session('success'),
        ]);
    }

    private function requireActiveCompany(): CompanyProfile
    {
        $profile = CurrentCompany::profile();

        abort_unless($profile, 404, 'No active company selected. Create or switch to a company first.');

        return $profile;
    }

    private function profileForUi(): CompanyProfile
    {
        $profile = $this->requireActiveCompany();

        $dirty = false;

        if ($profile->header_image_path && ! Storage::disk('public')->exists($profile->header_image_path)) {
            $profile->header_image_path = null;
            $dirty = true;
        }

        if ($profile->footer_image_path && ! Storage::disk('public')->exists($profile->footer_image_path)) {
            $profile->footer_image_path = null;
            $dirty = true;
        }

        if ($dirty) {
            $profile->save();
        }

        return $profile->fresh();
    }
}
