<?php

namespace App\Services\Xfel;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;

trait HttpClient
{
    protected $_client;
    protected $_clientConfig = [];

    protected function client(){
        if (empty($this->_client)){
            $this->_client = new Client(
                array_merge(
                    ['http_errors' => false, 'connect_timeout' => 15,],
                    $this->_clientConfig
                )
            );
        }
        return $this->_client;
    }

    public function responseFromJson(Response $response, $key = null) {
        $result = [];
        if($response instanceof Response) {
            $result = json_decode((string) $response->getBody(), true);
            foreach ($result as &$field){
                if(is_string($field)){
                    $field = trim($field);
                }
            }
        }
        if ($key){
            return $result[$key];
        }
        else{
            return $result;        
        }
    }

    public function responseToString(Response $response) {
        return (string) $response->getBody();
    }

    public function isValidResponse($response){
        return ($response instanceof Response);
    }
}
