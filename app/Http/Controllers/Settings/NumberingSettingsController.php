<?php

namespace App\Http\Controllers\Settings;

use App\Enums\DocumentType;
use App\Enums\NumberingReset;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateNumberingRequest;
use App\Services\System\NumberingService;
use App\Services\System\SettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class NumberingSettingsController extends Controller
{
    public function __construct(private SettingsService $settings, private NumberingService $numbering) {}

    public function index(): View
    {
        $documentTypes = collect(DocumentType::cases())->map(fn (DocumentType $type): array => [
            'type' => $type,
            'config' => $this->numbering->config($type),
            'next_number' => $this->numbering->nextNumber($type),
            'preview' => $this->numbering->preview($type),
        ]);

        return view('settings.numbering', [
            'documentTypes' => $documentTypes,
            'resetOptions' => NumberingReset::cases(),
        ]);
    }

    public function update(UpdateNumberingRequest $request, DocumentType $documentType): RedirectResponse
    {
        DB::transaction(function () use ($request, $documentType): void {
            $this->settings->update('numbering', [
                $documentType->value => [
                    'prefix' => $request->validated('prefix'),
                    'padding' => (int) $request->validated('padding'),
                    'reset' => $request->validated('reset'),
                ],
            ], "Updated {$documentType->label()} numbering");

            $this->numbering->setNextNumber($documentType, (int) $request->validated('next_number'));
        });

        return redirect()->route('settings.numbering.index')
            ->with('success', "{$documentType->label()} numbering saved. Next number: {$this->numbering->preview($documentType)}.");
    }
}
