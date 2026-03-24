<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use App\Models\Performance as ModelsPerformance;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;


class Performance extends Command
{
    protected $signature = 'performance
        {days=30 : time scope, days}
        {offset=0 : time scope offset, days}
        {measure_on=response : stream_start | over | response}
        {context=default : default | chat_name}
        {attachments=no-attachments : any | no-attachments | images}'
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
        
        $params = compact('days', 'daysOffset', 'measureOn', 'context', 'attachments');
        $result = ModelsPerformance::performancePerModel($params);
        
        $dateEnd = Carbon::now()->subDays(intval($daysOffset))->endOfDay();
        $dateStart = clone $dateEnd;
        $dateStart->subDays(intval($days))->startOfDay();
        
        $this->info('The measured value is related to ' . strtoupper($measureOn) . '. Context: ' . strtoupper($context));
        $this->info('Time scope ' . substr($dateStart, 0, 10) .' - '. substr($dateEnd, 0, 10));
        $this->info('Attachments filter: ' . (strtoupper($attachments) ?: 'none'));
        if(empty($result)){
            $this->warn('No performance records found');
        }
        else{
            $this->table(array_keys(Arr::first($result)), $result);
        }
        
    }
}
