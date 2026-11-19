<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdminReportRequest;
use App\Services\AdminReportService;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    public function index(AdminReportRequest $request, AdminReportService $report): Response
    {
        [$from, $to] = $request->range();

        return Inertia::render('Admin/Report', [
            'report' => $report->build($from, $to),
            'filter' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ],
        ]);
    }
}
