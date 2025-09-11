<?php

namespace App\Services\Xfel;

use Illuminate\Support\Facades\Log;

class LdapService
{
    protected $_connection;
    protected function _getConnection(){
        if (!$this->_connection){
            $ldap_host = config('ldap.custom_connection.ldap_host');
            $ldap_port = config('ldap.custom_connection.ldap_port');
            
            $this->_connection = ldap_connect($ldap_host, $ldap_port);
            if (!$this->_connection) {
                Log::error("PermissionCheck::Could not connect to LDAP server");
                return false;
            }
            ldap_set_option($this->_connection, LDAP_OPT_PROTOCOL_VERSION, 3);
            ldap_set_option($this->_connection, LDAP_OPT_REFERRALS, 0);            
        }
        return $this->_connection;
    }
    
    public function checkCredentials($username, $password){
        $ldap_base = config('ldap.custom_connection.ldap_search_dn');
        $ldap_attribute_username = config('ldap.custom_connection.attribute_map.username');
        $userDn = $ldap_attribute_username ."=".$username.",ou=people,".$ldap_base;
        
        $bind = @ldap_bind($this->_getConnection(), $userDn, $password);
        return (bool)$bind;
    }
    
    public function getReourceGroups($username)
    {
        $ldap_base = env('LDAP_SEARCH_DN');
        $ldap_group_base = env('LDAP_RESOURCE_SEARCH_DN');
        if(!$ldap_group_base){
            return [];
        }
        $ldap_attribute_username = config('ldap.custom_connection.attribute_map.username');
        $groupDn  = "ou=group," . $ldap_group_base;
        $peopleDn = "ou=people,".$ldap_base;
        $userDn = $ldap_attribute_username ."=".$username.",".$peopleDn;
        
        $search_filter = "uniqueMember=".$userDn;
        $attributes = ["cn"];
        $result = ldap_search($this->_getConnection(), $groupDn, $search_filter, $attributes);
        
        if (!$result) {
            return [];
        }
        $entries = ldap_get_entries($this->_getConnection(), $result);
        if ($entries["count"] == 0) {
            return [];
        }
        $result = [];
        for ($i = 0; $i < $entries["count"]; $i++) {
            $result[] = $entries[$i]["cn"][0];
        }
        return $result;
    }
    
}
