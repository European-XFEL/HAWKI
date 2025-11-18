<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserQuota extends Model
{
    protected $guarded = [];
    
    protected $primaryKey = 'username';
    protected $keyType = 'string';
    public $timestamps = false;
    
    protected static function userRecordCheck($username){
        if (self::query()->where('username', $username)->doesntExist()){
            self::query()->insert(['username' => $username]);
        }
    }
    protected static function incCounter($username, $field){
        self::userRecordCheck($username); //create record only when we need to actually increase counters
        $user = self::query()->find($username);
        $user->$field = intval($user->$field) + 1;
        $user->save();
        return $user->$field;
    }

    protected static function getQuota($username, $field, $default_quota){
        $result = ['counter' => 0, 'quota' => $default_quota, 'reached' => false];
        $user = self::query()->find($username);
        if($user){
            $result['counter'] = intval($user[$field]);
            $result['quota'] = (intval($user[$field.'_custom_quota'])) ?: $default_quota;
            if ($result['quota']){
                $result['reached'] = ($result['counter'] >= $result['quota'] );
                $result['remaining'] = max(0, $result['quota'] - $result['counter']);
            }
        }
        return $result;
    }
    
    public static function incImageCounter($username){
        return self::incCounter($username, 'images');
    }
    
    public static function getImageQuota($username){
        return self::getQuota($username, 'images', intval(config('model_providers.default_image_quota')));
    }
    
    public static function resetCounters($fields, $username = '')
    {
        $query = self::query();
        if ($username){
            $query->where('username', $username);    
        }
        $fields = (array)$fields;
        $fields = array_intersect($fields, ['images']); //let's protect ourselves 
        
        $fields = array_fill_keys($fields, null);
        $query->update($fields);
    }


}
