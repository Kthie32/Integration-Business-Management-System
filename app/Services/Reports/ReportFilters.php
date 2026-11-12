<?php

namespace App\Services\Reports;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * The filters a report runs with. The period defaults to the current month up to today (docs/07, Phase 13).
 */
final class ReportFilters
{
    /**
     * @param  array<string, string|null>  $options  values of the report's select filters (status, method, …)
     */
    public function __construct(
        public readonly CarbonImmutable $from,
        public readonly CarbonImmutable $to,
        public readonly ?int $departmentId = null,
        public readonly array $options = [],
        public readonly ?string $sort = null,
        public readonly string $direction = 'asc',
    ) {}

    public static function defaults(): self
    {
        return new self(CarbonImmutable::today()->startOfMonth(), CarbonImmutable::today());
    }

    /**
     * Validate and read the filters from the query string. Invalid input sends the user back with errors.
     */
    public static function fromRequest(Request $request, Report $report): self
    {
        $optionRules = [];

        foreach ($report->selectFilters() as $name => $filter) {
            $optionRules[$name] = ['nullable', 'string', Rule::in(array_map('strval', array_keys($filter['options'])))];
        }

        $data = Validator::make($request->query(), [
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'department' => ['nullable', 'integer', 'exists:departments,id'],
            'sort' => ['nullable', 'string', 'max:50'],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            ...$optionRules,
        ], [
            'to.after_or_equal' => 'The end date must be on or after the start date.',
        ])->validate();

        $defaults = self::defaults();

        return new self(
            from: isset($data['from']) ? CarbonImmutable::createFromFormat('!Y-m-d', $data['from']) : $defaults->from,
            to: isset($data['to']) ? CarbonImmutable::createFromFormat('!Y-m-d', $data['to']) : $defaults->to,
            departmentId: $report->supportsDepartment() && isset($data['department']) ? (int) $data['department'] : null,
            options: array_map(fn (mixed $value): ?string => $value === null || $value === '' ? null : (string) $value,
                array_intersect_key($data, $optionRules)),
            sort: $data['sort'] ?? null,
            direction: $data['direction'] ?? 'asc',
        );
    }

    public function option(string $name): ?string
    {
        return $this->options[$name] ?? null;
    }

    /**
     * @return array{0: string, 1: string} the period as two Y-m-d dates
     */
    public function dates(): array
    {
        return [$this->from->toDateString(), $this->to->toDateString()];
    }

    /**
     * @return array{0: string, 1: string} the period as datetimes covering both whole days
     */
    public function datetimes(): array
    {
        return [$this->from->startOfDay()->toDateTimeString(), $this->to->endOfDay()->toDateTimeString()];
    }

    /**
     * The query-string values, for links that keep the current filters.
     *
     * @return array<string, string|int>
     */
    public function query(): array
    {
        return array_filter([
            'from' => $this->from->toDateString(),
            'to' => $this->to->toDateString(),
            'department' => $this->departmentId,
            ...$this->options,
            'sort' => $this->sort,
            'direction' => $this->sort !== null ? $this->direction : null,
        ], fn (mixed $value): bool => $value !== null && $value !== '');
    }
}
