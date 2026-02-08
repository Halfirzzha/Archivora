<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\UserLoginActivity;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CheckSuspiciousActivity extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'users:check-suspicious-activity 
                            {--hours=24 : Number of hours to check}
                            {--min-ips=3 : Minimum different IPs to flag as suspicious}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check for suspicious login activities (multiple IPs in short time)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $hours = (int) $this->option('hours');
        $minIps = (int) $this->option('min-ips');

        $this->info("Checking for suspicious activities in the last {$hours} hours...");

        // Find users with multiple different IPs in the specified timeframe
        $suspiciousActivities = UserLoginActivity::select('user_id', DB::raw('COUNT(DISTINCT ip_address) as unique_ips'))
            ->where('created_at', '>=', now()->subHours($hours))
            ->whereNotNull('user_id')
            ->groupBy('user_id')
            ->having('unique_ips', '>=', $minIps)
            ->with('user:id,name,email')
            ->get();

        if ($suspiciousActivities->isEmpty()) {
            $this->info('No suspicious activities detected.');
            return self::SUCCESS;
        }

        $this->warn("Found {$suspiciousActivities->count()} user(s) with suspicious activity:");

        $headers = ['User ID', 'Email', 'Name', 'Unique IPs'];
        $rows = [];

        foreach ($suspiciousActivities as $activity) {
            if ($activity->user) {
                $rows[] = [
                    $activity->user_id,
                    $activity->user->email,
                    $activity->user->name,
                    $activity->unique_ips,
                ];
            }
        }

        $this->table($headers, $rows);

        // Get detailed IP addresses for each suspicious user
        foreach ($suspiciousActivities as $activity) {
            if (!$activity->user) {
                continue;
            }

            $this->newLine();
            $this->line("Details for: {$activity->user->email}");

            $ips = UserLoginActivity::where('user_id', $activity->user_id)
                ->where('created_at', '>=', now()->subHours($hours))
                ->select('ip_address', DB::raw('COUNT(*) as count'), DB::raw('MAX(created_at) as last_seen'))
                ->groupBy('ip_address')
                ->orderBy('count', 'desc')
                ->get();

            foreach ($ips as $ip) {
                $this->line("  - IP: {$ip->ip_address} | Count: {$ip->count} | Last: {$ip->last_seen}");
            }
        }

        return self::SUCCESS;
    }
}
