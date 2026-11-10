<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateOrganizationSettingsRequest;
use App\Services\Settings\OrganizationSettingsService;
use App\Services\System\SettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class OrganizationSettingsController extends Controller
{
    public function __construct(
        private SettingsService $settings,
        private OrganizationSettingsService $organizationSettings,
    ) {}

    public function edit(): View
    {
        return view('settings.organization', [
            'organization' => $this->settings->group('organization'),
            'logoUrl' => $this->organizationSettings->logoUrl(),
        ]);
    }

    public function update(UpdateOrganizationSettingsRequest $request): RedirectResponse
    {
        $this->organizationSettings->update(
            $request->safe()->except('logo'),
            $request->file('logo'),
        );

        return redirect()->route('settings.organization.edit')->with('success', 'Organization profile saved.');
    }

    public function destroyLogo(): RedirectResponse
    {
        $this->organizationSettings->removeLogo();

        return redirect()->route('settings.organization.edit')->with('success', 'Logo removed.');
    }
}
