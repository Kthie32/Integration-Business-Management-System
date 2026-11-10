<?php

namespace App\Services\Settings;

use App\Services\System\SettingsService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Saves the organization profile, including replacing and removing the logo on the public disk.
 */
class OrganizationSettingsService
{
    public function __construct(private SettingsService $settings) {}

    /**
     * @param  array<string, mixed>  $profile
     */
    public function update(array $profile, ?UploadedFile $logo = null): void
    {
        $previousLogo = $this->settings->get('organization', 'logo_path');

        if ($logo !== null) {
            $profile['logo_path'] = $logo->store('branding', 'public');
        }

        $this->settings->update('organization', $profile, 'Updated organization profile');

        if ($logo !== null && $previousLogo !== null) {
            Storage::disk('public')->delete($previousLogo);
        }
    }

    public function removeLogo(): void
    {
        $logo = $this->settings->get('organization', 'logo_path');

        if ($logo === null) {
            return;
        }

        $this->settings->update('organization', ['logo_path' => null], 'Removed organization logo');
        Storage::disk('public')->delete($logo);
    }

    /**
     * Public URL of the logo, or null when none is uploaded.
     */
    public function logoUrl(): ?string
    {
        $logo = $this->settings->get('organization', 'logo_path');

        return $logo !== null ? Storage::disk('public')->url($logo) : null;
    }
}
