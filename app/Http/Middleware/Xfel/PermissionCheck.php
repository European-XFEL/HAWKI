<?php

namespace App\Http\Middleware\Xfel;

use App\Services\Xfel\AccountService;
use App\Services\Xfel\LdapService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;


class PermissionCheck
{
    public function handle(Request $request, Closure $next): Response
    {
        $username = $request->get('account');
        
        # Whitelist based access - for test system
        # If enabled only users from the whitelist can pass
        # User fron the white list skip all the on-top checks but still will be checked with the following default ldap auth 
        $whitelist = env('XFEL_ACCESS_WHITELIST');
        if ($whitelist){
            $whitelist = str_replace(' ','', strtolower($whitelist));
            $whitelist = explode(',', $whitelist);
            if (in_array(strtolower($username), $whitelist)){
                return $next($request);
            }
            else{
                return response()->json([
                    'success' => false,
                    'message' => 'You are not in the access list.']);
            }
        }
        
        $accountService = new AccountService();
        //skip this check if
        if (!$accountService->enabled()){
            return $next($request);
        }
        
        $ldapService = new LdapService(); 
        
        if ($ldapService->checkCredentials($username, $request->get('password'))){
            $accountInfo = $accountService->getAccountInfo($username);
            
            //If getAccountInfo fail lets just skip it. Access permissions will be checked with the following default ldap auth
            //E.g. when external systems are down
            if (empty($accountInfo) || empty($accountInfo['dachs_requirements']) || empty($accountInfo['ldap'])){
                Log::error('PermissionCheck getAccountInfo failed: ' . $username);
                return $next($request);
            }
            
            $dachsRequirementId = config('xfel_access.dachs_requirement_id');
            $trainingResourceName = config('xfel_access.training_resource_name');
            $baseResourceName = config('xfel_access.base_resource_name');
            
            // If basic resource is in configuration lets check it.
            // This will be always checked with the following default ldap auth, but here we can cut it earlier and have a proper message to user
            if( !empty($baseResourceName) && !in_array($baseResourceName, (array)$accountInfo['ldap']['resource_groups'])){
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have permissions. Staff members only.']);
            }
            
            // If conditional resources related to trainings are in the configuration lets check them.
            // This will be always checked with the following default ldap auth,
            // but here we can update resource based on training and give a nice messsages to user
            if($dachsRequirementId && $trainingResourceName){
                $trainingValid =  (bool)$accountInfo['dachs_requirements'][$dachsRequirementId]['valid'];
                $hasResource = in_array($trainingResourceName, (array)$accountInfo['ldap']['resource_groups']);

                if ($trainingValid){
                    if(!$hasResource){
                        //assign resource
                        // but user may need to wait
                        $apiResponse = $accountService->assignResource($username, $trainingResourceName);
                        return response()->json([
                            'success' => false,
                            'message' => 'We\'re setting up your permissions. Please try logging in again in about 5 minutes.']);
                    }
                    else{
                        return $next($request); // if both are ok - proceed to default ldap auth
                    }
                }
                else{
                    //If training is not valid but still have resource - revoke it
                    if($hasResource){
                        $apiResponse = $accountService->unassignResource($username, $trainingResourceName);
                    }
                    return response()->json([
                        'success' => false,
                        'message' => 'AI online training required it may take 5-10 minutes before training becomes effective).']);                    
                }
            }
            //else we do not do anything - resources will be checked with the following default ldap auth
            //proceed to log in
            return $next($request);
        }
        else{
            //invalid credentials - will be killed by the following default ldap auth
            return response()->json([
                'success' => false,
                'message' => 'Login Failed']);
        }
    }
}
