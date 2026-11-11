<?php

namespace App\Services\System;

use Illuminate\Support\Facades\Storage;

/**
 * Organization details for printed and PDF documents (quotations, invoices, receipts, payslips).
 */
class DocumentBranding
{
    private const MAX_LOGO_BYTES = 2 * 1024 * 1024;

    public function __construct(private SettingsService $settings) {}

    /**
     * @return array{name: string, address: string|null, phone: string|null, email: string|null, website: string|null, tin: string|null, bank_details: string|null, document_footer: string|null, signatory_name: string|null, signatory_title: string|null, brand_color: string, logo: string|null}
     */
    public function details(): array
    {
        $organization = $this->settings->group('organization');

        return [
            'name' => $this->settings->organizationName(),
            'address' => $organization['address'] ?? null,
            'phone' => $organization['phone'] ?? null,
            'email' => $organization['email'] ?? null,
            'website' => $organization['website'] ?? null,
            'tin' => $organization['tin'] ?? null,
            'bank_details' => $organization['bank_details'] ?? null,
            'document_footer' => $organization['document_footer'] ?? null,
            'signatory_name' => $organization['signatory_name'] ?? null,
            'signatory_title' => $organization['signatory_title'] ?? null,
            'brand_color' => $this->safeColor($organization['brand_color'] ?? null),
            'logo' => $this->logoDataUri($organization['logo_path'] ?? null),
        ];
    }

    /**
     * The logo embedded as a data URI, so PDFs render it without fetching files over HTTP.
     */
    private function logoDataUri(?string $path): ?string
    {
        if (blank($path)) {
            return null;
        }

        $disk = Storage::disk('public');

        if (! $disk->exists($path) || $disk->size($path) > self::MAX_LOGO_BYTES) {
            return null;
        }

        $mime = $disk->mimeType($path);

        if (! in_array($mime, ['image/png', 'image/jpeg', 'image/webp'], true)) {
            return null;
        }

        return 'data:'.$mime.';base64,'.base64_encode((string) $disk->get($path));
    }

    private function safeColor(?string $color): string
    {
        return is_string($color) && preg_match('/^#[0-9a-fA-F]{6}$/', $color) === 1 ? $color : '#1e5aa8';
    }
}
