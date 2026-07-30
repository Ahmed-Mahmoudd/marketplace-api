<?php

namespace App\Console\Commands;

use App\Models\IdempotencyKey;
use Illuminate\Console\Command;

class CleanupIdempotencyKeys extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'idempotency:cleanup';

    /**
     * The console command description.
     */
    protected $description = 'Delete expired idempotency keys';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $deleted = IdempotencyKey::query()
            ->where('expires_at', '<', now())
            ->delete();

        $this->info("Deleted {$deleted} expired idempotency key(s).");

        return self::SUCCESS;
    }
}
