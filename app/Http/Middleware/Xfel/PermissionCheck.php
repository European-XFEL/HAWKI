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
        $accountService = new AccountService();
            
        //skip this check if
        if (!$accountService->enabled()){
            return $next($request);
        }
        
        $ldapService = new LdapService(); 
        
        $username = $request->get('account');
        if ($ldapService->checkCredentials($username, $request->get('password'))){
            $accountInfo = $accountService->getAccountInfo($username);
            
            //if getAccountInfo fail lets just skip it. Access right will be checked based on current LDAP
            //the case of external system is down
            if (empty($accountInfo) || empty($accountInfo['dachs']) || empty($accountInfo['ldap'])){
                Log::error('PermissionCheck getAccountInfo failed: ' . $username);
                return $next($request);
            }
            
            $accessRightId = config('xfel_access.access_right_id');
            $accessGroupName = config('xfel_access.access_group_name');
            
            //we consider values are not valid if no params set
            $trainingValid =  (bool)$accessRightId && (bool)$accountInfo['dachs'][$accessRightId]['valid'];
            $hasGroup = (bool)$accessGroupName && in_array($accessGroupName, (array)$accountInfo['ldap']['groups']);
            
            //if both aspects are checked then lets try to set/unset group based on training
            if($accessRightId && $accessGroupName){
                if ($trainingValid && !$hasGroup){
                    //assign group
                    // but user may need to wait
                    $accountService->assignGroup($username, $accessGroupName);
                    return response()->json([
                        'success' => false,
                        'message' => 'Permissions set up is in progress... Please try to login after 5 minutes']);
                }
                if (!$trainingValid && $hasGroup){
                    //revoke group
                    // revoke may take time
                    $accountService->revokeGroup($username, $accessGroupName); 
                    return response()->json([
                        'success' => false,
                        'message' => 'You do not have permissions. Be sure you did the required training']);                    
                }
            }
            
            //In this point we have !$accessRightId || !$accessGroupName
            //the only interesting case here is $accessRightId && !$trainingValid - we should drop it
            
            //if access right required but failed
            if ($accessRightId && !$trainingValid){
                // in this step there is no $accessGroupName knownw so we can not update it - just exit
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have permissions. Be sure you did the required training']);
            }
            
            //All other cases - nothing to do
            //Group will be considered on the next step with ldap filter config
            //proceed to log in
            return $next($request);
        }
        else{
            //invalid credentials - will be killed on the next step
            return response()->json([
                'success' => false,
                'message' => 'Login Failed']);
        }
    }
}
