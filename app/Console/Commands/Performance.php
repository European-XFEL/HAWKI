<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use App\Models\Performance as ModelsPerformance;
use Illuminate\Support\Facades\DB;


class Performance extends Command
{
    protected $signature = 'performance
        {days=30 : time scope, days}
        {offset=0 : time scope offset, days}
        {measure_on=stream_start : stream_start | over}
        {context=default : default | chat_name}
        {attachments? : \'\' | no-attachments | images}'
    ;

    protected $description = 'Show model performance over last n days';

    
    //removes regular whitespace and non-printable control characters (ASCII 0–31 and 127) 
    private function clean($input)
    {
        return preg_replace('/[\s[:cntrl:]]+/', '', $input);
    }
    public function handle()
    {
        $days = (int) $this->input->getArgument('days');
        $daysOffset = (int) $this->input->getArgument('offset');;
        //simple clean up the input to avoid injections etc
        $measureOn = $this->clean($this->input->getArgument('measure_on')); 
        $context = $this->clean($this->input->getArgument('context'));
        $attachments = $this->clean($this->input->getArgument('attachments'));
        
        $query = ModelsPerformance::query();

        if($measureOn){
            $query->where('measured_on', $measureOn);
        }
        
        if(!empty($context)){
            $query->where('context', $context);
        }
        if($attachments == 'no-attachments'){
            $query->whereNull('attachments');
        }
        elseif ($attachments == 'images'){
            $query->where('attachments', $attachments);
        }
        
        $dateEnd = Carbon::now()->subDays(intval($daysOffset))->endOfDay();
        $dateStart = clone $dateEnd;
        $dateStart->subDays(intval($days))->startOfDay();
        
        $result = $query
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
            ->toArray();
        
        $this->info('The measured value is related to ' . $measureOn . '. Context: ' . $context);
        $this->info('Time scope ' . substr($dateStart, 0, 10) .' - '. substr($dateEnd, 0, 10));
        $this->info('Attachments filter: ' . ($attachments ?: 'none'));
        if(empty($result)){
            $this->warn('No performance records found');
        }
        else{
            $this->table(array_keys($result[0]), $result);
        }
        
    }
}
