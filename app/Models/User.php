<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    private $_imageQuota = null;
    
    protected $fillable = [
        'name',
        'email',
        'username',
        'employeetype',
        'publicKey',
        'avatar_id',
        'bio',
        'isRemoved'
    ];


    public function members()
    {
        return $this->hasMany(Member::class)->where('isRemoved', false);
    }

    public function rooms()
    {
        return $this->belongsToMany(Room::class, 'members', 'user_id', 'room_id')
                    ->wherePivot('isRemoved', false);
    }

    // Define the relationship with AiConv
    public function conversations()
    {
        return $this->hasMany(AiConv::class);
    }

    public function invitations()
    {
        return $this->hasMany(Invitation::class, 'username', 'username');
    }

    public function revokProfile(){
        $this->update(['isRemoved'=> 1]);
    }
    
    public function imageQuota(){
        if ($this->_imageQuota === null) {
            $this->_imageQuota = UserQuota::getImageQuota($this->username);
        }
        return $this->_imageQuota;
    }
    
    public function incImageCounter(){
        UserQuota::incImageCounter($this->username);
        $this->_imageQuota = null; //reset lazy counters on change
    }
    
}