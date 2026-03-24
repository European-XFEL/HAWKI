<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

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
    
    public static function performancePerModel($params)
    {
        $query = self::query();
        
        //stream_start | over
        if($params['measureOn']){
            $query->where('measured_on', $params['measureOn']);
        }
        
        //default | chat_name
        if(!empty($params['context'])){
            $query->where('context', $params['context']);
        }
        
        // '' | no-attachments | images
        if($params['attachments'] == 'no-attachments'){
            $query->whereNull('attachments');
        }
        elseif ($params['attachments'] == 'images'){
            $query->where('attachments', $params['attachments']);
        }
        
        //time scope offset, days
        $dateEnd = Carbon::now()->subDays(intval($params['daysOffset']))->endOfDay();
        $dateStart = clone $dateEnd;
        //time scope, days
        $dateStart->subDays(intval($params['days']))->startOfDay();
        
        if($params['minimumRequests']){
            $query->having('requests', '>=', intval($params['minimumRequests']));
        }
        
        return $query
            ->whereBetween('created_at', [$dateStart, $dateEnd])
            ->groupBy('model')
            ->orderBy('model')
            ->get([
                'model',
                DB::raw('round(avg(response_ms)/1000, 2) as AVERAGE_TIME_SEC'),
                DB::raw('count(*) as requests'),
                DB::raw('round(min(response_ms)/1000,2) as min_time'),
                DB::raw('round(max(response_ms)/1000,2) as max_time')
            ])
            ->keyBy('model')
            ->toArray();         
    }
    
    public static function performanceForUsers($cache = false){
        $params = [
            'days' => 7, 
            'daysOffset' => 0, 
            'measureOn' => 'stream_start', 
            'context' => 'default', 
            'attachments' => 'no-attachments',
            'minimumRequests' => 10,
        ];
        
        if($cache){
            $cache_key = 'performanceForUsers_'.md5(json_encode($params));
            return Cache::remember($cache_key, now()->addHour(), function () use ($params) {
                return self::performancePerModel($params);
            });
        }
        else{
            return self::performancePerModel($params);
        }
    }

}
