<?php

namespace App\Console\Commands\Users;

use App\Models\UserQuota;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

class SetQuotas extends Command
{
    protected $signature = 'user:quotas
        {username : username}
        {quota : quota name: images|...}
        {operation=get : get|set|unset}
        {value? : values for set}
    ';
    protected $description = 'Manage user CUSTOM quotas';

    public function handle()
    {
        $_fields = ['images'];
        $_operations = ['get', 'set', 'unset'];
        
        $field = trim($this->input->getArgument('quota'));
        $username = trim($this->input->getArgument('username'));
        $operation = trim($this->input->getArgument('operation'));
        $value = intval($this->input->getArgument('value'));
        
        
        $validator = Validator::make(compact('field', 'operation', 'username', 'operation', 'value'), $_fields, [
            'username' => 'required|string|max:32|alpha_dash',
            'quota' => 'required|in:images',
            'operation' => 'required|in:get,set,unset',
            'value' =>'nullable|numeric',
        ]);
        if ($validator->fails()) {
            $this->error('Bad parameters: ' . implode(',', array_keys($validator->getMessageBag()->getMessages())));
            return;
        }
        
        
        if ($operation == 'get') {
            $result = UserQuota::find($username);
            if(!$result){
                $this->warn('User '.$username.' not found');
            }
            else{
                $this->info('CUSTOM quota for ' . $username . ': ' . $field .' - ' . ($result[$field.'_custom_quota'] ?? 'not set') . '; Current usage: ' . intval($result[$field]));    
            }
            
        }
        else{
            $value = ($operation == 'set' && $value >0) ? $value :null;
            UserQuota::query()
                ->where('username', $username)
                ->update([$field.'_custom_quota' => $value]);
            $this->info('Set quotas for user ' . $username . ': ' . $field .' - ' . ($value ?? 'unset'));
        }
    }
    
}
