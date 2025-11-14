<?php

namespace App\Console\Commands\Users;

use App\Models\UserQuota;
use Illuminate\Console\Command;

class ResetQuotas extends Command
{
    protected $signature = 'user:reset_quotas
        {fields=images : comma separated fields.}
        {username? : username, empty - for all users.}
    ';
    protected $description = 'This reset quota counters';

    public function handle()
    {
        $fields = explode(',', str_replace(' ', '', $this->input->getArgument('fields')));
        $username = str_replace(' ', '', $this->input->getArgument('username'));
        UserQuota::resetCounters($fields, $username);
    }
    
}
