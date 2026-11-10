<?php

namespace App\Services\System;

use App\Enums\DocumentType;
use App\Models\Setting;
use App\Support\Money;
use DateTimeInterface;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Reads and writes organization settings.
 *
 * Every setting has a default defined here, so the application works before anything is saved. Stored values
 * are cached per group and the cache is cleared whenever a group is updated. All changes are audited.
 */
class SettingsService
{
    /**
     * Date display formats users can choose from: PHP format => example.
     *
     * @var array<string, string>
     */
    public const DATE_FORMATS = [
        'd M Y' => '10 Nov 2026',
        'd/m/Y' => '10/11/2026',
        'Y-m-d' => '2026-11-10',
        'M j, Y' => 'Nov 10, 2026',
    ];

    /**
     * Values already loaded during this request, keyed by group.
     *
     * @var array<string, array<string, mixed>>
     */
    private array $loaded = [];

    public function __construct(private AuditLogger $auditLogger) {}

    /**
     * Default values for every group and key.
     *
     * @return array<string, array<string, mixed>>
     */
    public function defaults(): array
    {
        return [
            'organization' => [
                'name' => 'Magezi IBM',
                'address' => null,
                'phone' => null,
                'email' => null,
                'website' => null,
                'tin' => null,
                'bank_details' => null,
                'document_footer' => null,
                'signatory_name' => null,
                'signatory_title' => null,
                'logo_path' => null,
                'brand_color' => '#1e5aa8',
            ],
            'currency' => [
                'code' => 'UGX',
                'symbol' => 'UGX',
                'decimals' => 0,
            ],
            'finance' => [
                'quotation_validity_days' => 30,
                'invoice_payment_terms_days' => 30,
                'quotation_approval_threshold' => null,
            ],
            'system' => [
                'date_format' => 'd M Y',
                'email_notifications' => false,
                'low_stock_check' => true,
            ],
            'numbering' => collect(DocumentType::cases())
                ->mapWithKeys(fn (DocumentType $type): array => [$type->value => $type->defaultNumbering()])
                ->all(),
        ];
    }

    /**
     * Every value in a group: stored values over defaults.
     *
     * @return array<string, mixed>
     */
    public function group(string $group): array
    {
        $defaults = $this->defaults()[$group] ?? throw new InvalidArgumentException("Unknown settings group [{$group}].");

        if (isset($this->loaded[$group])) {
            return $this->loaded[$group];
        }

        /*
         * Values read inside a database transaction might still be rolled back, so they are only cached
         * when no transaction is open. Otherwise a rejected change could stay in the cache.
         */
        $canCache = DB::transactionLevel() === 0;
        $stored = Cache::get($this->cacheKey($group));

        if (! is_array($stored)) {
            $stored = Setting::query()->where('group', $group)->pluck('value', 'key')->all();

            if ($canCache) {
                Cache::forever($this->cacheKey($group), $stored);
            }
        }

        $values = array_replace($defaults, $stored);

        if ($canCache) {
            $this->loaded[$group] = $values;
        }

        return $values;
    }

    public function get(string $group, string $key, mixed $default = null): mixed
    {
        return $this->group($group)[$key] ?? $default;
    }

    /**
     * Save values for a group and record what changed. Keys without a default are rejected.
     *
     * @param  array<string, mixed>  $values
     */
    public function update(string $group, array $values, ?string $description = null): void
    {
        $known = array_keys($this->defaults()[$group] ?? throw new InvalidArgumentException("Unknown settings group [{$group}]."));
        $unknown = array_diff(array_keys($values), $known);

        if ($unknown !== []) {
            throw new InvalidArgumentException('Unknown setting(s): '.implode(', ', $unknown));
        }

        $current = $this->group($group);
        $changed = array_filter($values, fn (mixed $value, string $key): bool => $current[$key] !== $value, ARRAY_FILTER_USE_BOTH);

        if ($changed === []) {
            return;
        }

        DB::transaction(function () use ($group, $changed, $current, $description): void {
            foreach ($changed as $key => $value) {
                Setting::query()->updateOrCreate(['group' => $group, 'key' => $key], ['value' => $value]);
            }

            $this->auditLogger->log(
                'settings_updated',
                null,
                $description ?? 'Updated '.str_replace('_', ' ', $group).' settings',
                Arr::only($current, array_keys($changed)),
                $changed,
            );
        });

        $this->forget($group);
        DB::afterCommit(fn () => $this->forget($group));
    }

    public function forget(string $group): void
    {
        Cache::forget($this->cacheKey($group));
        unset($this->loaded[$group]);
    }

    public function organizationName(): string
    {
        return (string) $this->get('organization', 'name');
    }

    /**
     * Format an amount in the base currency, e.g. "UGX 1,250,000".
     */
    public function formatMoney(Money|int|string|null $amount): string
    {
        $currency = $this->group('currency');

        return Money::of($amount ?? 0)->format((int) $currency['decimals'], (string) $currency['symbol']);
    }

    /**
     * Format a date using the organization's chosen date format.
     */
    public function formatDate(?DateTimeInterface $date): string
    {
        return $date === null ? '' : $date->format((string) $this->get('system', 'date_format', 'd M Y'));
    }

    private function cacheKey(string $group): string
    {
        return "settings.{$group}";
    }
}
