<?php

namespace App\Console\Commands\Users;

use App\Models\UserQuota;
use Illuminate\Console\Command;

class ResetQoutas extends Command
{
    protected $signature = 'user:reset_quotas';
    protected $description = 'This reset all quotas for all users';

    public function handle()
    {
        UserQuota::resetQuotas();
    }
    
}
