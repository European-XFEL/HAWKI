<?php

namespace App\Http\Middleware\Xfel;

use App\Services\Xfel\LdapService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;


class PermissionCheck
{
    protected $ldap;

    protected function hasTraining($training){
        return true;
    }

    public function handle(Request $request, Closure $next): Response
    {
        $requiredLdapGroup = env('XFEL_LDAP_GROUP');
        $requiredTrainingID = env('XFEL_TRAINING_ID');
        
        //skip this check if no requirements
        if (!$requiredLdapGroup || !$requiredTrainingID){
            return $next($request);
        }
        
        $this->ldap = new LdapService(); 
        
        if ($this->ldap->checkCredentials($request->get('account'), $request->get('password'))){
            if ($this->hasTraining($requiredTrainingID)){
                if ($this->ldap->isInGroup($request->get('account'), $requiredLdapGroup)){
                    //All good
                    return $next($request);
                }
                else{
                    //@TODO add group
                    return response()->json([
                        'success' => false,
                        'message' => 'Permissions set up is in progress... Please try to login after 5 minutes']);
                }
            }
            else{
                //@TODO revoke group
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have permissions. Be sure you did the required training']);
            }
        }
        else{
            //invalid credentials - will be killed on the next step
            return $next($request);
        }
    }
}
