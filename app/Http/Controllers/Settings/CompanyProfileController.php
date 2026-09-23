<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\CompanyProfile;
use App\Models\AuditLog;
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
            'profile' => CompanyProfile::firstOrCreate([]),
            'success' => session('success'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'company_name' => 'required|string|max:255',
            'tax_id' => 'nullable|string|max:255',
            'registration_number' => 'nullable|string|max:255',
            'address' => 'nullable|string',
            'header_image' => 'nullable|file|mimes:png,jpg,jpeg,svg|max:2048',
            'footer_image' => 'nullable|file|mimes:png,jpg,jpeg,svg|max:2048',
            'remove_header' => 'nullable|boolean',
            'remove_footer' => 'nullable|boolean',
            'template_type' => 'required|in:modern,classic,compact,custom',
            'custom_html' => 'nullable|string',
            'primary_color' => 'required|string|max:7',
            'accent_color' => 'required|string|max:7',
            'font_family' => 'required|string|max:255',
            'page_margin' => 'required|string|max:255',
        ]);

        $profile = CompanyProfile::firstOrCreate([]);

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

        $profile->update($validated);
        AuditLog::record('settings.visual_identity_updated', 'Company Branding', 'Letterhead, template or brand tokens changed.');

        return back()->with('success', 'Configuration saved successfully.');
    }
        /**
     * Read-only company profile overview.
     */
        public function show(): Response
        {
            return Inertia::render('Settings/CompanyProfile', [
                'profile' => CompanyProfile::firstOrCreate([]),
            ]);
        }
    
        /**
         * Payslip templates showcase (active template highlighted).
         */
        public function templates(): Response
        {
            return Inertia::render('Settings/PayslipTemplates', [
                'profile' => CompanyProfile::firstOrCreate([]),
            ]);
        }
}