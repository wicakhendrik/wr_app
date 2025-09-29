<?php

namespace App\Http\Controllers;

use App\Services\WRService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class WRController extends Controller
{
    public function generate(Request $request, WRService $service)
    {
        $payload = $request->validate([
            'for_month' => 'required|date',
            'signature_date' => 'required|date',
            'template_id' => 'nullable|integer',
        ]);

        $month = Carbon::parse($payload['for_month'])->startOfMonth();
        $signatureDate = Carbon::parse($payload['signature_date'])->startOfDay();
        $templateId = $payload['template_id'] ?? null;

        $spread = $service->createMonthlyWRSpreadsheet($month, $signatureDate, $request->user()->id, $templateId);

        $userName = (string) ($request->user()?->name ?? 'User');
        // Nama file: WR {Nama User} - {Bulan} {Tahun}.xlsx
        $labelMonth = $month->copy()->locale('id')->translatedFormat('F Y');
        $fileName = sprintf('WR %s - %s.xlsx', $userName, $labelMonth);

        return response()->streamDownload(function () use ($spread) {
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spread);
            $writer->save('php://output');
        }, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}


