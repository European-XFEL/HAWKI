<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    private $_imagesToday = null;
    
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
    
    public function imagesToday(){
        if ($this->_imagesToday === null) {
            $this->_imagesToday = AiConvMsg::query()
                ->from('ai_convs as c')
                    ->leftJoin('ai_conv_msgs as m', 'm.conv_id', '=', 'c.id')
                    ->leftJoin('ai_conv_msg_auxes as ma','ma.msg_id', '=', 'm.id')
                    ->where('c.user_id', $this->id)
                    ->where('ma.type', 'imageResponse')
                    ->whereDate('ma.created_at', today())
                    ->count();
        }
        return $this->_imagesToday;
    }
    
    public function imageQuotaReached(){
        $_quota = intval(config('model_providers.image_quota'));
        if ($_quota == 0){
            return false;
        }
        return ($this->imagesToday() >= $_quota );
    }
    
    public function imageQuotaData(){
        return [
            'quota' => config('model_providers.image_quota'),
            'today' => $this->imagesToday(),
            'reached' => $this->imageQuotaReached(),
        ];
    }
    

}