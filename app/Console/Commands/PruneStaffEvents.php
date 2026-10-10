<?php

namespace App\Console\Commands;

use App\Models\StaffEvent;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('staff-events:prune {--hours=24}')]
#[Description('Hapus event SSE kasir/dapur yang sudah lama')]
class PruneStaffEvents extends Command
{
    public function handle(): int
    {
        $deleted = StaffEvent::where('created_at', '<', now()->subHours((int) $this->option('hours')))->delete();

        $this->info("{$deleted} event dihapus.");

        return self::SUCCESS;
    }
}
