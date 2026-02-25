<?php

namespace App\Models;

use Illuminate\Support\Arr;

class ModelConfiguration
{
    private $_config = []; 
    public static function gptModel($id, $label, $config = [])
    {
        $result = self::abstractModel($id, $label, $config);
        $result->_config = array_merge($result->gptDefaultConfig(), $result->_config); //config in "constructor" can overrides defaults
        
        //model specific settings
        if(substr(trim(strtolower($id)),0,5) == 'gpt-4'){
            $result->set('keep_reasoning_tokens', false);
            $result->forget('reasoning_effort');
        } 
        
        return $result;
    }
    
    public static function abstractModel($id, $label, $config = [])
    {
        $result = new self();
        $result->_config['id'] = $id;
        $result->_config['label'] = $label;
        $result->_config['visible'] = true;
        Arr::forget($config, ['id', 'label']); //do not allow to override id and label
        $result->_config = array_merge($result->_config, $config);
        return $result;
    }
    
    private function gptDefaultConfig(){
        return
            [
                'streamable' => true,
                'reasoning_effort' => 'medium',
                'keep_reasoning_tokens' => true, 
                
                //Images
                'enable_image_generation' => true,
                'quality' => 'auto', // low, medium, high, auto
                'size' => '1024x1024', //1024x1024, 1024x1536, 1536x1024, or auto
                'partial_images' => 3, // maximum number of partial image updates to generate when streaming, can be up to 3,
                'add_thread_images_as_input' => true, // previously generated images in the thread are added to the input of the next request.
                
                //Upload files
                'enable_document_input' => true,
                'max_attachment_size_kb' => 2*1024, // maximum document size in KB (for the b64 encoded document), // maximum document size in KB (for the b64 encoded document)
                
                //Web search
                'enable_web_search' => true,
                'user_location' =>   [  // the location assumed for the websearch, to not add a location, comment this
                    'country' => 'DE',
                    'city' => 'Hamburg',
                    "region" => 'Hamburg',
                    "type" => "approximate",
                ],
                'include_search_results' => true,
            ];
    }
    
    public function set($key, $value){ Arr::set($this->_config, $key, $value); return $this;}
    public function forget($keys){ Arr::forget($this->_config, $keys); return $this;}
    public function get(){return $this->_config;}

    public function images($flag){ return $this->set('enable_image_generation', (bool) $flag);}
    public function documents($flag){ return $this->set('enable_document_input', (bool) $flag);}
    public function web($flag){ return $this->set('enable_web_search', (bool) $flag);}
    public function visible($flag){ return $this->set('visible', (bool) $flag);}
    public function description($desc){ return $this->set('description', $desc);}

}