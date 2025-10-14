<?php

namespace App\Console\Commands\Users;

use App\Models\User;
use App\Services\Xfel\AccountService;
use App\Services\Xfel\LdapService;
use Illuminate\Console\Command;

class CheckPermissions extends Command
{
    protected $signature = 'user:permissions 
        {username : username to check}'
    ;
    protected $description = 'Command checks user account permissions.';

    public function handle()
    {

        $username = $this->input->getArgument('username');
        
        //account in DB?
        $user = User::query()->where('username', $username)->first();
        if ($user) { $this->info('User exists in Ray database'); }
        else{ $this->warn('User DOES NOT exists in Ray database');}
        
        //LDAP resources
        $ldapService = new LdapService();
        $trainingResourceName = config('xfel_access.training_resource_name');
        $baseResourceName = config('xfel_access.base_resource_name');
        $ldapResourceGroups = (array)$ldapService->getReourceGroups($username);
        if (in_array($baseResourceName, $ldapResourceGroups)) { $this->info('Basic resource '.$baseResourceName.' is in LDAP'); }
        else{ $this->warn('Basic resource '.$baseResourceName.' is NOT in LDAP');}
        if (in_array($trainingResourceName, $ldapResourceGroups)) { $this->info('Training resource '.$trainingResourceName.' is in LDAP'); }
        else{ $this->warn('Training resource '.$trainingResourceName.' is NOT in LDAP');}
        
        //DACHS        
        $dachsRequirementId = config('xfel_access.dachs_requirement_id');
        $accountService = new AccountService();
        $accountInfo = $accountService->getAccountInfo($username);
        if ((bool)$accountInfo['dachs_requirements'][$dachsRequirementId]['valid']) { $this->info('DARF-DACHS requirement ' . $dachsRequirementId .' is valid'); }
        else{ $this->warn('DARF-DACHS requirement ' . $dachsRequirementId .' is INVALID'); }
    }
    
}
