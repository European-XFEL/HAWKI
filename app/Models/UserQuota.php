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
       self::userRecordCheck($username);
        $user = self::query()->find($username);
        $user->$field = intval($user->$field) + 1;
        $user->save();
        return $user->$field;
    }

    protected static function getCounter($username, $field){
        $user = self::query()->find($username);
        if(!$user){
            return 0;
        }
        return intval($user[$field]);
    }
    
    public static function incImageCounter($username){
        return self::incCounter($username, 'images');
    }
    
    public static function getImageCounter($username){
        return self::getCounter($username, 'images');
    }
    
    public static function resetQuotas()
    {
        self::truncate();
    }


}
