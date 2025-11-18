<?php

namespace App\Services\AI\Providers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class OpenAIResponsesProvider extends BaseAIModelProvider
{

    public function mapMessages(array $messages): array
    {
        $mapped = [];

        foreach ($messages as $message) {
            $mapped[] = [
                // change: map "system" -> "developer"
                'role' => ($message['role'] === 'system') ? 'developer' : $message['role'],
                'content' => $message['content'],
                'auxiliaries' => $message['auxiliaries'] ?? [],
            ];
        }

        return $mapped;
    }
    /**
     * Format the raw payload for OpenAI Responses API
     *
     * @param array $rawPayload
     * @return array
     */
    public function formatPayload(array $rawPayload): array
    {
        $messages = $rawPayload['messages'];
        $modelId = $rawPayload['model'];
        
        // Handle special cases for specific models
        
        $messages = $this->mapMessages($messages);
        
        // add aditional configuration options
        $config = $this->config;
        $modelConfig = [];
        foreach ($config['models'] as $conf) {
            if ($conf['id'] == $modelId) {
                $modelConfig = $conf;
                break;
            }
        }

        // strip off any -SYSTEM part
        $modelId = str_replace('-SYSTEM', '', $modelId);

        // Convert messages into the Responses API "input" shape
        $input = [];
        foreach ($messages as $message) {
            
            $auxiliaries = $message['auxiliaries'] ?? [];
            
            foreach($auxiliaries as $aux) {
                // only handle auxiliaries of the correct type
                if ($aux['type'] == 'openAiResponsesSpecific') {
                    $modelSpecific = json_decode($aux['content'], true);
                    $reasoning = $modelSpecific['reasoning'] ?? [];
                    foreach ($reasoning as $reasoningItem) {
                        $input[] = $reasoningItem;
                    }
                } else if ($aux['type'] == 'imageResponse' && isset($modelConfig['add_thread_images_as_input']) && $modelConfig['add_thread_images_as_input']) {
                    $input[] = [
                        'role' => 'user',
                        'content' => [
                            [
                            'type' => 'input_image',
                            'image_url' => $aux['content'], 
                            ]
                        ]
                    ];
                } else if (strpos($aux['type'], 'attachment') === 0) {
                    // this is a stringified JSON at this point
                    $content = json_decode($aux['content'], true);
                    if (!$content) {
                        //Log::info("Attachement failure");
                        continue;
                    }
                    //Log::info("attachment ". $content['type']);

                    switch ($content['type']) {
                        case 'application/pdf':
                            $input[] = [
                                'role' => 'user',
                                'content' => [
                                    [
                                    'type' => 'input_file',
                                    'filename' => $content['name'],
                                    'file_data' => $content['content'], 
                                    ]
                                ]
                            ];
                            break;
                        case 'image/png':
                        case 'image/jpeg':
                            $input[] = [
                                'role' => 'user',
                                'content' => [
                                    [
                                    'type' => 'input_image',
                                    'image_url' => $content['content'], 
                                    ]
                                ]
                            ];
                            break;    
                    }
                    
                }
                
            }
            $contentText = $message['content']['text'] ?? '';
            $input[] = [
                'role' => $message['role'],
                'content' => $contentText
            ];
        }

        //Log::info("Input", $input);

        // Build payload for Responses endpoint
        $payload = [
            'model' => $modelId,
            'input' => $input,
             // keep stream flag; streaming handled by makeStreamingRequest
            'stream' => !empty($rawPayload['stream']) && $this->supportsStreaming($modelId),
            'store' => false, // always false for data safety, otherwise OpenAI retain may responses
        ];


        // set the reasoning effort
        if (isset($modelConfig['reasoning_effort'])) {
            $payload['reasoning'] = ['effort' => $modelConfig['reasoning_effort']];
        }

        // keep encrypted reasoning tokens if requested
        $include = [];
        if ($modelConfig['keep_reasoning_tokens']) {
            $include[] = "reasoning.encrypted_content";
        }

        $tools = [];
        if ($modelConfig['enable_image_generation'] && !Auth::user()->imageQuota()['reached']) {
            $imageTool = ['type' => 'image_generation'];
            if (isset($modelConfig['quality']) ) {
                $imageTool['quality'] = $modelConfig['quality'];
            }
            if (isset($modelConfig['size']) ) {
                $imageTool['size'] = $modelConfig['size'];
            }
            // partial images are only available when streaming
            if (isset($modelConfig['partial_images']) && $payload['stream']) {
                $imageTool['partial_images'] = $modelConfig['partial_images'];
            }
            $tools[] = $imageTool;
        }

        if (isset($modelConfig['enable_web_search']) && $modelConfig['enable_web_search']) {
            $webSearchTool = ['type' => 'web_search'];
            if (isset($modelConfig['user_location']) ) {
                $webSearchTool['user_location'] = $modelConfig['user_location'];
            }
            $tools[] = $webSearchTool;

             if (isset($modelConfig['include_search_results']) && $modelConfig['include_search_results']) {
                 $include[] = "web_search_call.action.sources";
             }
        }
       
        if (!empty($tools)) {
            $payload['tools'] = $tools;
            $payload['tool_choice'] = 'auto';
        }

        if (!empty($include)) {
             $payload['include'] = $include;
        }

        return $payload;
    }

    /**
     * Format the complete response from Responses API
     *
     * @param mixed $response
     * @return array
     */
    public function formatResponse($response): array
    {
        
        $responseContent = $response->getContent();
        $jsonContent = json_decode($responseContent, true);

        $texts = [];
        $reasoning = [];
        $imageData = '';
        $annotations = [];
        
        if (!empty($jsonContent['output']) && is_array($jsonContent['output'])) {
            foreach ($jsonContent['output'] as $outputItem) {
                // this is the actual model output
                if ($outputItem['type'] == "message") {
                    if (!empty($outputItem['content']) && is_array($outputItem['content'])) {
                            foreach ($outputItem['content'] as $c) {
                                if ($c['type'] == 'output_text') {
                                    $texts[] = $c['text'];
                                }

                                foreach($c['annotations'] ?? [] as $a) {
                                    if ($a['type'] == 'url_citation') {
                                        $annotations[] = [
                                            "url" => $a['url'],
                                            "title" => $a['title'],
                                        ];
                                    }
                                }
                            }
                        }
                } else if ($outputItem['type'] == "reasoning") {
                    // here we get encrypted reasoning tokens
                    // for input we'll need the entire data structure
                    $reasoning[] = $outputItem;
                } else if ($outputItem['type'] == "image_generation_call") {
                    $imageData = $outputItem['result'];
                }
            }
        }

        $contentText = implode('', $texts);
        $auxiliaries =  [
            [
                'type' => 'openAiResponsesSpecific',
                'content' => json_encode(["reasoning" => $reasoning]),
            ]
        ];

        if ($imageData) {
            $auxiliaries[] = [
                'type' => 'imageResponse',
                // the prefix is missing
                'content' => "data:image/png;base64,".$imageData,
            ];
        }

        if ($annotations) {
            $auxiliaries[] = [
                'type' => 'webSearchAnnotations',
                // the prefix is missing
                'content' => json_encode($annotations),
            ];
        }
        
        
        return [
            'content' => [
                'text' => $contentText,
            ],
            'usage' => $this->extractUsage($jsonContent),
            // we don't need this in the CLI, hence we pass an encoded vesion
            'auxiliaries' => $auxiliaries,
        ];
    }

    /**
     * Format a single chunk from a streaming Responses API stream
     *
     * @param string $chunk
     * @return array
     */
    public function formatStreamChunk(string $chunk): array
    {
        
        $jsonChunk = json_decode($chunk, true );
        $content = '';
        $isDone = false;
        $usage = null;

        
       
        if (empty($jsonChunk) || !is_array($jsonChunk)) {
            return [
                'content' => ['text' => ''],
                'isDone' => false,
                'usage' => null,
            ];
        }

        // we need to push out the very first update, even if it's empty
        // otherwise the UI will not know that streaming is in progress.
        $isFirstUpdate = $this->isFirstUpdate ?? true;
        $this->isFirstUpdate = false;

        // The Responses streaming events often include a "type" field.
        // Completed event:
        $reasoning = [];
        $imageData = '';
        $annotations = [];
        $isFinalText = false;

        // check for errors - if we find one we are done at this point
        if (isset($jsonChunk['type']) && $jsonChunk['type'] == 'error') {
            $isDone = true;
            $content = $jsonChunk['message'] ?? "Unknown error!";
        }

        if (isset($jsonChunk['type']) && $jsonChunk['type'] == 'response.failed') {
            $isDone = true;
            $content = isset($jsonChunk['response']['error']['message']) 
                      ? $jsonChunk['response']['error']['message'] 
                      : 'Unknown error!';

        }

        if (isset($jsonChunk['type']) && $jsonChunk['type'] == 'response.incomplete') {
            $isDone = true;
            $reason = isset($jsonChunk['response']['incomplete_details']['reason']) 
                      ? $jsonChunk['response']['incomplete_details']['reason'] 
                      : 'Unknown reason!';
            $content = "Streaming didn't complete due to error: " . $reason;
        }
        
        //when the whole request is completed
        if (isset($jsonChunk['type']) && in_array($jsonChunk['type'], ['response.completed', 'response.refreshed'], true)) {
            $isDone = true;
            // check for encrypted reasoning tokens
            $output = $jsonChunk['response']['output'];
            foreach($output as $item) {
                if ($item['type'] == 'reasoning' && isset($item['encrypted_content'])) {
                    $reasoning[] = $item;
                } else if ($item['type'] == "message") {
                    if (!empty($item['content']) && is_array($item['content'])) {
                        foreach ($item['content'] as $c) {
                            foreach($c['annotations'] ?? [] as $a) {
                                if ($a['type'] == 'url_citation') {
                                    $annotations[] = [
                                        "url" => $a['url'],
                                        "title" => $a['title'],
                                    ];
                                }
                            }
                            if ($c["type"] == "output_text") {
                                $content = $content.$c["text"];
                                $isFinalText = true;
                            }
                        }
                    }
                }
                else if ($item['type'] == 'image_generation_call') { 
                    Auth::user()->incImageCounter(); //count images generated via the request
                }
            }
            // get the final image if we have one
            $idx = $this->image_output_index ?? -1;
            if ($idx > 0) {
                $imageData = $jsonChunk['response']['output'][$idx]['result'];
            } 
        }

        // Delta-style updates may include output/content deltas
        if (isset($jsonChunk['type']) && $jsonChunk['type'] == 'response.output_text.delta') {
           $content = $jsonChunk['delta'];
        }

        // final response
        if (isset($jsonChunk['type']) && $jsonChunk['type'] == 'response.output_text.done') {
           $content = $jsonChunk['text'];
           $isFinalText = true;
        }

        // image data comes in form of partial images - or only the final - it's the same data
        
        if (isset($jsonChunk['type']) && $jsonChunk['type'] == 'response.image_generation_call.partial_image') {
           $imageData = $jsonChunk['partial_image_b64'];
        }

        if (isset($jsonChunk['type']) && $jsonChunk['type'] == 'response.image_generation_call.completed') {
            $this->image_output_index = $jsonChunk['output_index'];
            // return an empty chunk as not to overwrite any partial image
            return [
                'content' => ['text' => ''],
                'isDone' => false,
                'usage' => null,
                'skip' => !$isFirstUpdate,
            ];
        }

        // websearch updates, we forward the urls to show as "searching"
        $thinking_updates = [];
        if (isset($jsonChunk['type']) && $jsonChunk['type'] == 'response.output_item.done') {
            if (isset($jsonChunk['item'])) {
                $item = $jsonChunk['item'];
                if (($item['type'] ?? '') == 'web_search_call' && ($item['status'] ?? '') == 'completed') {
                    if (isset($item['action'])) {
                        $action = $item['action'];
                        if (($action['type'] ?? '') == 'search') {
                            foreach (($action['sources'] ?? []) as $source) {
                                if (($source['type'] ?? '') == 'url') {
                                    $thinking_updates[] = $source['url'];
                                }
                            }
                        }
                    }
                }
            }
        }

        if (isset($jsonChunk['type']) && in_array($jsonChunk['type'], ['response.web_search_call.searching', ' response.web_search_call.in_progress'], true)) {
            $thinking_updates[] = 'Searching web...';
        }
    
        
        // Extract usage data if available
        if (!empty($jsonChunk['usage'])) {
            $usage = $this->extractUsage($jsonChunk);
        } elseif (!empty($jsonChunk['metadata']['usage'])) {
            $usage = $this->extractUsage($jsonChunk['metadata']['usage']);
        }

        $responseId = '';
        if (!empty($jsonChunk['id'])) {
            $responseId = $jsonChunk['id'];
        }

        $response = [
            'content' => [
                'text' => $content,
            ],
            'isDone' => $isDone,
            'usage' => $usage,
            'auxiliaries' => [],
            'skip' => empty($content) && $content != "0" && empty($imageData) && !$isDone && !$isFirstUpdate && empty($thinking_updates) && !$isFinalText,
            'isFinalText' => $isFinalText,
        ];
        
        
        if ($reasoning) {
            $response['auxiliaries'][] = [
                    'type' => 'openAiResponsesSpecific',
                    'content' => json_encode(["reasoning" => $reasoning]),
            ];
            
        }

        if ($imageData) {
            $response['auxiliaries'][] = [
                    'type' => 'imageResponse',
                    // the prefix is missing
                    'content' => "data:image/png;base64,".$imageData,
                    'img_id' => $jsonChunk['item_id'],
            ];
            
        }

        if ($annotations) {
            $response['auxiliaries'][] = [
                'type' => 'webSearchAnnotations',
                'content' => json_encode($annotations),
            ];
        }

        if ($thinking_updates) {
            $response['auxiliaries'][] = [
                'type' => 'thinkingUpdates',
                'content' => json_encode($thinking_updates),
            ];
        }

        
        
        return $response;
    }

    /**
     * Extract usage information from Responses API output
     *
     * @param array $data
     * @return array|null
     */
    protected function extractUsage(array $data): ?array
    {
        // Responses API usage may appear under top-level 'usage' or nested structures.
        if (empty($data)) {
            return null;
        }

        if (!empty($data['usage']) && is_array($data['usage'])) {
            $usage = $data['usage'];
            return [
                'prompt_tokens' => $usage['prompt_tokens'] ?? ($usage['input_tokens'] ?? null),
                'completion_tokens' => $usage['completion_tokens'] ?? ($usage['output_tokens'] ?? null),
            ];
        }

        // Fallback: some events may include usage-like fields under metadata
        if (!empty($data['metadata']) && !empty($data['metadata']['usage'])) {
            $usage = $data['metadata']['usage'];
            return [
                'prompt_tokens' => $usage['prompt_tokens'] ?? null,
                'completion_tokens' => $usage['completion_tokens'] ?? null,
            ];
        }

        return null;
    }

    /**
     * Make a non-streaming request to the OpenAI API
     *
     * @param array $payload The formatted payload
     * @return mixed The response
     */
    public function makeNonStreamingRequest(array $payload)
    {
        // Ensure stream is set to false
        $payload['stream'] = false;
        
        $time_limit = $this->config['time_limit'] ?? 240;
        set_time_limit($time_limit);

        // Initialize cURL
        $ch = curl_init();
        $modelConfig = [];
        foreach ($this->config['models'] as $conf) {
            if ($conf['id'] == $payload['model']) {
                $modelConfig = $conf;
                break;
            }
        }
        $api_url = $modelConfig['api_url'] ?? $this->config['api_url'];
        curl_setopt($ch, CURLOPT_URL, $api_url);

        if ($modelConfig['unsafe_ssl'] ?? false) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        }
        
        //curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        

        // Set common cURL options
        $this->setCommonCurlOptions($ch, $payload, $this->getHttpHeaders());

        // Execute the request
        $response = curl_exec($ch);

        // Handle errors
        if (curl_errno($ch)) {
            $error = 'Error: ' . curl_error($ch);
            curl_close($ch);
            return response()->json(['error' => $error], 500);
        }
        curl_close($ch);
        return response($response)->header('Content-Type', 'application/json');
    }

    /**
     * Make a streaming request to the OpenAI API
     *
     * @param array $payload The formatted payload
     * @param callable $streamCallback Callback for streaming responses
     * @return void
     */
    public function makeStreamingRequest(array $payload, callable $streamCallback)
    {
        // Ensure stream is set to true
        $payload['stream'] = true;
        
        $time_limit = $this->config['streaming_time_limit'] ?? 240;
        set_time_limit($time_limit);

        // Set headers for SSE
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        header('Access-Control-Allow-Origin: *');

        // Initialize cURL
        $ch = curl_init();
        $modelConfig = [];
        foreach ($this->config['models'] as $conf) {
            if ($conf['id'] == $payload['model']) {
                $modelConfig = $conf;
                break;
            }
        }
        $api_url = $modelConfig['api_url'] ?? $this->config['api_url'];
        curl_setopt($ch, CURLOPT_URL, $api_url);

        if ($modelConfig['unsafe_ssl'] ?? false) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        }

        // Set common cURL options
        $this->setCommonCurlOptions($ch, $payload, $this->getHttpHeaders(true));

        // Set streaming-specific options
        $this->setStreamingCurlOptions($ch, $streamCallback);

         // Set low speed options to prevent timeout
        curl_setopt($ch, CURLOPT_LOW_SPEED_LIMIT, 1); // 1 byte per second
        curl_setopt($ch, CURLOPT_LOW_SPEED_TIME, 60); // 20 seconds

        // Execute the cURL session
        curl_exec($ch);

        // Handle errors
        if (curl_errno($ch)) {
            Log::info('Error: ' . curl_error($ch));
            $streamCallback('Error: ' . curl_error($ch));
            if (ob_get_length()) {
                ob_flush();
            }
            flush();
        }

        curl_close($ch);

        // Flush any remaining data
        if (ob_get_length()) {
            ob_flush();
        }
        flush();
    }

}
