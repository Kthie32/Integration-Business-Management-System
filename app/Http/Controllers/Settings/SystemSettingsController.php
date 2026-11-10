<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateSystemSettingsRequest;
use App\Services\System\SettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SystemSettingsController extends Controller
{
    public function __construct(private SettingsService $settings) {}

    /**
     * Opens the first settings page the user is allowed to change.
     */
    public function index(Request $request): RedirectResponse
    {
        $user = $request->user();

        return match (true) {
            $user->can('settings.organization') => redirect()->route('settings.organization.edit'),
            $user->can('settings.finance') => redirect()->route('settings.finance.edit'),
            $user->can('settings.system') => redirect()->route('settings.system.edit'),
            default => abort(403),
        };
    }

    public function edit(): View
    {
        return view('settings.system', [
            'system' => $this->settings->group('system'),
            'dateFormats' => SettingsService::DATE_FORMATS,
        ]);
    }

    public function update(UpdateSystemSettingsRequest $request): RedirectResponse
    {
        $this->settings->update('system', $request->validated());

        return redirect()->route('settings.system.edit')->with('success', 'System settings saved.');
    }
}
