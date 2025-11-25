<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Performance extends Model
{
    protected $table='performance';
    protected $guarded = [];
    
    public $timestamps = true;
    
    private $_start;
    private $_end;
    
    const UPDATED_AT = null;
    
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->group_id = self::uniqId();
    }
    
    public static function timeDelta($start, $end = null){
        if(!$end) $end = microtime(true);
        return (int)round(($end - $start) * 1000); //time delta, ms
    }
    
    private function _timeDelta()
    {
        return self::timeDelta($this->_start, $this->_end);
    }
        
        
    public static function uniqId()
    {
        return md5(str(microtime(true)) .'_'. bin2hex(random_bytes(4)));
    }
    
    public function start()
    {
        $this->_start = microtime(true);
    }
    public function end($save=true){
        $this->_end = microtime(true);
        if($save && $this->_start){
            $this->response_ms = $this->_timeDelta();
            $this->save();
        }
    }
    

}
