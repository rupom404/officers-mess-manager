<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Member;

class ResetBalancesCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'mess:reset-balances';

    /**
     * The console command description.
     */
    protected $description = 'Zero out all member opening balances for a fresh financial cycle.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Resetting all member opening balances to zero...');
        
        // Update all members (active and inactive) to start fresh
        $updated = Member::withoutGlobalScopes()->update(['opening_balance' => 0.00]);
        
        $this->info("Successfully reset opening balances to ৳0.00 for {$updated} members.");
        $this->info('September is now ready for a fresh start!');
    }
}