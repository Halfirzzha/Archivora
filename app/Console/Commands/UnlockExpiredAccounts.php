<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class UnlockExpiredAccounts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'users:unlock-expired';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Unlock accounts that have been locked for more than 30 minutes';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Checking for expired account locks...');

        // Find locked accounts that should be unlocked (locked > 30 minutes ago)
        $lockedUsers = User::whereNotNull('locked_at')
            ->where('locked_at', '<=', now()->subMinutes(30))
            ->get();

        if ($lockedUsers->isEmpty()) {
            $this->info('No accounts found that need unlocking.');
            return self::SUCCESS;
        }

        $count = 0;
        foreach ($lockedUsers as $user) {
            $user->unlock();
            $count++;
            $this->line("✓ Unlocked account: {$user->email}");
        }

        $this->info("Successfully unlocked {$count} account(s).");

        // Find temporarily blocked accounts that should be unblocked
        $blockedUsers = User::whereNotNull('blocked_until')
            ->where('blocked_until', '<=', now())
            ->get();

        if ($blockedUsers->isNotEmpty()) {
            $blockedCount = 0;
            foreach ($blockedUsers as $user) {
                $user->unblock();
                $blockedCount++;
                $this->line("✓ Unblocked account: {$user->email}");
            }

            $this->info("Successfully unblocked {$blockedCount} account(s).");
        }

        return self::SUCCESS;
    }
}
