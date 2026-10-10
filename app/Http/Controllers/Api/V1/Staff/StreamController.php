<?php

namespace App\Http\Controllers\Api\V1\Staff;

use App\Http\Controllers\Controller;
use App\Models\StaffEvent;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Server-Sent Events untuk kasir & dapur. Event hanya memberi tahu "ada perubahan";
 * data lengkapnya diambil lewat endpoint REST biasa.
 */
class StreamController extends Controller
{
    public function orders(Request $request): StreamedResponse
    {
        $branchId = $request->user()->branch_id;

        // Sambung ulang: lanjut dari event terakhir yang diterima. Koneksi baru: mulai dari sekarang.
        $lastEventId = $request->header('Last-Event-ID');
        $lastId = is_numeric($lastEventId)
            ? (int) $lastEventId
            : (int) StaffEvent::where('branch_id', $branchId)->max('id');

        $duration = (float) config('staff_stream.duration');
        $intervalMicro = (int) config('staff_stream.interval_ms') * 1000;
        $heartbeat = (int) config('staff_stream.heartbeat');

        return response()->stream(function () use ($branchId, $lastId, $duration, $intervalMicro, $heartbeat) {
            // Koneksi sengaja panjang; jangan dipotong batas waktu eksekusi PHP (default 30 detik).
            set_time_limit(0);

            $send = function (string $chunk) {
                echo $chunk;
                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();
            };

            $send("retry: 3000\n\n");

            $startedAt = $lastBeatAt = microtime(true);

            while (microtime(true) - $startedAt < $duration && ! connection_aborted()) {
                $events = StaffEvent::where('branch_id', $branchId)
                    ->where('id', '>', $lastId)
                    ->orderBy('id')
                    ->limit(100)
                    ->get();

                foreach ($events as $event) {
                    $send("id: {$event->id}\nevent: {$event->type}\ndata: ".json_encode($event->data)."\n\n");
                    $lastId = $event->id;
                    $lastBeatAt = microtime(true);
                }

                if ($events->isEmpty() && microtime(true) - $lastBeatAt >= $heartbeat) {
                    $send(": ping\n\n");
                    $lastBeatAt = microtime(true);
                }

                usleep($intervalMicro);
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache, no-transform',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no', // matikan buffering di nginx / proxy
        ]);
    }
}
