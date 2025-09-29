<?php

namespace App\Jobs;

use App\Models\Task;
use App\Models\Ticket;
use App\Models\Upload;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class ParseUploadJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $uploadId) {}

    public function handle(): void
    {
        $upload = Upload::findOrFail($this->uploadId);

        $full = Storage::path($upload->stored_path);

        try {
            $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($full);
            $spread = $reader->load($full);
        } catch (\Throwable $e) {
            return; // silently fail; could log
        }

        $sheet = $spread->getSheet(0);
        $rows = $sheet->toArray(null, true, true, true);

        $headerRow = null;
        foreach ($rows as $i => $r) {
            $line = implode('|', array_map(fn($v)=>strtolower(trim((string)$v)), $r));
            if ($upload->kind === 'resolved' && str_contains($line,'created') && str_contains($line,'resolved') && str_contains($line,'request type')) {
                $headerRow = $i; break;
            }
            if ($upload->kind === 'ticket_eval' && str_contains($line,'created') && str_contains($line,'request id')) {
                $headerRow = $i; break;
            }
            if ($upload->kind === 'actual_end' && str_contains($line,'task id') && str_contains($line,'actual end')) {
                $headerRow = $i; break;
            }
        }
        if (!$headerRow) return;

        $headers = $rows[$headerRow];
        $map = [];
        foreach ($headers as $col => $val) {
            $k = strtolower(trim((string)$val));
            if ($k !== '') $map[$k] = $col;
        }

        for ($i=$headerRow+1; $i<=count($rows); $i++) {
            $r = $rows[$i] ?? null;
            if (!$r) continue;

            if ($upload->kind === 'resolved' || $upload->kind === 'ticket_eval') {
                $created = $r[$map['created time'] ?? ''] ?? null;
                $resolved= $r[$map['resolved time'] ?? ''] ?? null;
                $rtype   = $r[$map['request type'] ?? ''] ?? null;
                $rid     = $r[$map['request id'] ?? ''] ?? null;
                $subj    = $r[$map['subject'] ?? ''] ?? null;

                if ($upload->kind === 'resolved') {
                    if (!$resolved && !$rtype && !$rid && !$subj) continue;
                } else {
                    // ticket_eval minimal created + request id or subject
                    if (!$created && !$rid && !$subj) continue;
                }

                Ticket::create([
                    'upload_id' => $upload->id,
                    'created_at_src'  => $this->toJakartaTs($created),
                    'resolved_at_src' => $this->toJakartaTs($resolved),
                    'request_type'    => $this->nz($rtype),
                    'request_id'      => $this->nz($rid),
                    'subject'         => $this->nz($subj),
                ]);
            } else {
                $tid  = $r[$map['task id'] ?? ''] ?? null;
                $rid  = $r[$map['request id'] ?? ''] ?? null;
                $pid  = $r[$map['problem id'] ?? ''] ?? null;
                $cid  = $r[$map['change id'] ?? ''] ?? null;
                $title= $r[$map['title'] ?? ''] ?? null;
                $end  = $r[$map['actual end time'] ?? ''] ?? null;

                if (!$end && !$tid && !$title) continue;

                Task::create([
                    'upload_id' => $upload->id,
                    'task_id'   => $this->nz($tid),
                    'request_id'=> $this->nz($rid),
                    'problem_id'=> $this->nz($pid),
                    'change_id' => $this->nz($cid),
                    'title'     => $this->nz($title),
                    'actual_end_at_src' => $this->toJakartaTs($end),
                ]);
            }
        }
    }

    private function toJakartaTs($val)
    {
        if (!$val) return null;
        try {
            // Parse as Asia/Jakarta from source, then normalize to UTC for storage
            return Carbon::parse($val, 'Asia/Jakarta')->setTimezone('UTC');
        } catch (\Throwable $e) { return null; }
    }

    private function nz($v)
    {
        $s = trim((string)$v);
        return ($s==='' || strtolower($s)==='null') ? null : $s;
    }
}

