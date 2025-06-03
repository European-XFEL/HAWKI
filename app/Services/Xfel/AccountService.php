<?php

namespace App\Services\Xfel;


use Illuminate\Support\Facades\Log;

class AccountService
{
    use HttpClient;
    
    protected $_key;
    
    public function __construct() {
        $this->_clientConfig = [
            'verify' => !(bool)env('XFEL_ACCESS_IGNORE_CERT'),
        ];
    }
    protected function key($refresh = false){
        if (!$this->enabled()) {
            return;
        }
        
        if ($refresh || empty($this->_key)){
            $response = $this->client()
                ->post(config('xfel_access.token_url'), [
                    'json' => [
                        'grant_type' => 'client_credentials',
                        'client_id' => config('xfel_access.client'),
                        'client_secret' => config('xfel_access.secret'),
                    ],
                ]);
            $this->_key = $this->responseFromJson($response, 'access_token');
        }
        
        return $this->_key;
    }
    
    protected function execute($method, $url, array $options = []){
        if (!$this->enabled()) {
            return;
        }
        if (!array_key_exists('headers', $options)){
            $options['headers'] = $this->defaultHeaders();
        }
        return $this->client()->$method($this->apiUrl($url), $options);
    }
    
    protected function executeAndGetData($method, $url, array $options = []){
        $response = $this->execute($method, $url, $options);
        return $this->responseFromJson($response);
    }
    
    protected function apiUrl($url = ''){
        return config('xfel_access.api_url') . '/' . trim($url, '/');
    }
    
    public function enabled(){
        return !empty(config('xfel_access.base_url'));
    }
   
    protected function defaultHeaders(){
        return [
            'Accept' => 'application/json; version=1',
            'Authorization' => 'Bearer ' . $this->key(),
        ];
    }
    
    //==========================================
    public function getAccountInfo($login){
        $request = [
            'form_params' => [
                'ldap_name' => $login,
                'ldap' => 1,
                'registry' => 1,
                'dachs' => 1,
            ],
        ];
        return $this->executeAndGetData('post','user/get_external_data', $request);
    }
    
    public function assignGroup($login, $group){
        Log::info('AccountService::assignGroup. user:' . $login . ' group:'. $group);
    }

    public function revokeGroup($login, $group){
        Log::info('AccountService::revokeGroup. user:' . $login . ' group:'. $group);
    }
    
}

