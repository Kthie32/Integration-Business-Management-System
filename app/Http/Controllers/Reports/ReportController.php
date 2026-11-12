<?php

namespace App\Http\Controllers\Reports;

use App\Exports\ReportExport;
use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Services\Reports\Report;
use App\Services\Reports\ReportFilters;
use App\Services\Reports\ReportFormatter;
use App\Services\Reports\ReportRegistry;
use App\Services\Reports\ReportResult;
use App\Services\System\AuditLogger;
use App\Services\System\DocumentBranding;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\Response;

/**
 * The Reports hub and every report's screen, PDF, Excel and print view (FR-RPT-01…06).
 * Running a report needs its own permission; PDF and Excel downloads also need reports.export and are audited.
 */
class ReportController extends Controller
{
    /**
     * Rows shown on screen; exports always contain every row.
     */
    public const SCREEN_ROW_LIMIT = 1000;

    public function __construct(
        private ReportRegistry $registry,
        private ReportFormatter $formatter,
        private DocumentBranding $branding,
        private AuditLogger $auditLogger,
    ) {}

    public function index(Request $request): View
    {
        $groups = $this->registry->hubFor($request->user());

        abort_if($groups === [], 403);

        return view('reports.index', ['groups' => $groups]);
    }

    public function show(Request $request, string $report): View
    {
        $definition = $this->resolve($request, $report);
        $filters = ReportFilters::fromRequest($request, $definition);
        $result = $this->run($definition, $filters, $request);

        return view('reports.show', [
            ...$this->viewData($definition, $filters, $result),
            'departments' => $definition->supportsDepartment() ? Department::query()->orderBy('name')->pluck('name', 'id')->all() : [],
            'rowLimit' => self::SCREEN_ROW_LIMIT,
            'canExport' => $request->user()->can('reports.export'),
        ]);
    }

    public function export(Request $request, string $report, string $format): Response|View
    {
        $definition = $this->resolve($request, $report);

        if ($format !== 'print') {
            abort_unless($request->user()->can('reports.export'), 403);
        }

        $filters = ReportFilters::fromRequest($request, $definition);
        $result = $this->run($definition, $filters, $request);
        $data = [...$this->viewData($definition, $filters, $result), 'organization' => $this->branding->details()];

        if ($format === 'print') {
            return view('pdf.report', [...$data, 'forPdf' => false]);
        }

        $this->auditLogger->log('report_exported', null, "Exported {$definition->title()} ({$format}) – {$data['subtitle']}", newValues: [
            'report' => $definition->key(),
            'format' => $format,
            'filters' => $filters->query(),
            'rows' => count($result->rows),
        ]);

        $fileName = Str::slug($definition->title()).'-'.now()->format('Ymd-His');

        if ($format === 'xlsx') {
            return Excel::download(new ReportExport($definition->title(), $result), $fileName.'.xlsx');
        }

        return Pdf::loadView('pdf.report', [...$data, 'forPdf' => true])
            ->setPaper('a4', count($result->columns) > 6 ? 'landscape' : 'portrait')
            ->download($fileName.'.pdf');
    }

    private function resolve(Request $request, string $key): Report
    {
        $definition = $this->registry->find($key);

        abort_if($definition === null, 404);
        abort_unless($definition->authorize($request->user()), 403);

        return $definition;
    }

    private function run(Report $definition, ReportFilters $filters, Request $request): ReportResult
    {
        $result = $definition->build($filters, $request->user());

        if ($filters->sort !== null) {
            $result->sortBy($filters->sort, $filters->direction);
        }

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    private function viewData(Report $definition, ReportFilters $filters, ReportResult $result): array
    {
        $departmentName = $filters->departmentId !== null ? Department::query()->whereKey($filters->departmentId)->value('name') : null;

        return [
            'report' => $definition,
            'filters' => $filters,
            'result' => $result,
            'formatter' => $this->formatter,
            'subtitle' => $definition->describeFilters($filters, $this->formatter->period($filters), $departmentName),
        ];
    }
}
