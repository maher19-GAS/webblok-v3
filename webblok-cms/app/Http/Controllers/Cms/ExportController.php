<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cms;

use App\Http\Controllers\Controller;
use App\Models\Tenant\StaticExport;
use App\Services\StaticSiteExporter;
use App\Services\TenantContext;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Static-site export: kick off a build and download the produced ZIP.
 */
final class ExportController extends Controller
{
    public function __construct(
        private readonly StaticSiteExporter $exporter,
        private readonly TenantContext $context,
    ) {}

    public function index(): View
    {
        $exports = StaticExport::query()->orderByDesc('created_at')->limit(20)->get();

        return view('cms.export', ['exports' => $exports]);
    }

    public function store(Request $request): RedirectResponse
    {
        /** @var array{locales: list<string>|null, include_api_bridge: bool|null} $v */
        $v = $request->validate([
            'locales' => ['nullable', 'array'],
            'locales.*' => ['string'],
            'include_api_bridge' => ['nullable', 'boolean'],
        ]);

        $tenant = $this->context->require();
        $this->exporter->export($tenant, $v['locales'] ?? [], $v['include_api_bridge'] ?? true);

        return redirect()->route('cms.export.index')->with('status', 'Export started.');
    }

    public function download(string $exportId): BinaryFileResponse
    {
        $export = StaticExport::query()->findOrFail($exportId);

        abort_if($export->zip_path === null, 404, 'Export archive not available.');

        return response()->download($export->zip_path);
    }
}
