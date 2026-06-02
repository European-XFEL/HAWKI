<?php

namespace App\Services\AI;

use App\Models\Performance;
use App\Services\AI\AIProviderFactory;
use Illuminate\Support\Facades\Log;

class AIConnectionService
{
    /**
     * The provider factory
     * 
     * @var AIProviderFactory
     */
    private $providerFactory;
    
    /**
     * Create a new connection service
     * 
     * @param AIProviderFactory $providerFactory
     */
    public function __construct(AIProviderFactory $providerFactory)
    {
        $this->providerFactory = $providerFactory;
    }
    
    /**
     * Process a request to an AI model
     * 
     * @param array $rawPayload The unformatted payload
     * @param bool $streaming Whether to stream the response
     * @param callable|null $streamCallback Callback for streaming responses
     * @return mixed The response from the AI model
     */
    public function processRequest(array $rawPayload, bool $streaming = false, ?callable $streamCallback = null)
    {
        $modelId = $rawPayload['model'];
        $modelDetails = $this->getModelDetails($modelId);
        $provider = $this->providerFactory->getProviderForModel($modelId);
        
        // Format the payload according to provider requirements
        $formattedPayload = $provider->formatPayload($rawPayload);
        
        //for local models we may have OpenAI format API but ollama messages dormat 
        if($modelDetails['message_format'] == 'ollama'){
            $formattedPayload['messages'] = $formattedPayload['input'];
            unset($formattedPayload['input']);
        }
        
        if ($streaming && $streamCallback) {
            // Handle streaming response
            return $provider->connect($formattedPayload, $streamCallback);
        } else {
            // Handle standard response
            $performance = new Performance([
                'measured_on' => 'over',
                'model' => $rawPayload['model'],
                'streaming' => (($rawPayload['stream'])? 1 : null),
                'context' => $rawPayload['context'] ?: 'default',
            ]);
            $performance->start();
            $response = $provider->connect($formattedPayload);
            $performance->end();
            return $provider->formatResponse($response);
        }
    }
    
    /**
     * Get a list of all available models
     * 
     * @return array
     */
    public function getAvailableModels(): array
    {
        $models = [];
        $providers = config('model_providers')['providers'];
        
        foreach ($providers as $provider) {
            if ($provider['active']) {

                $providerInterface = $this->providerFactory->getProviderInterface($provider['id']);

                if (method_exists($providerInterface, 'getModelsStatus') && 
                    $provider['status_check'] &&
                    !empty($provider['ping_url'])) {

                        $stats = $providerInterface->getModelsStatus();
                        foreach($stats as $stat){
                            $models[] = $stat;
                        }
                } else {
                    foreach ($provider['models'] as $model) {
                        $models[] = $model;
                    }
                }  
            }
        }

        return [
            'models' => $models,
            'defaultModel' => config('model_providers')['defaultModel'],
            'systemModels' => config('model_providers')['system_models']
        ];
    }
    
    /**
     * Get details for a specific model
     * 
     * @param string $modelId
     * @return array
     */
    public function getModelDetails(string $modelId): array
    {
        $provider = $this->providerFactory->getProviderForModel($modelId);
        return $provider->getModelDetails($modelId);
    }
    
    /**
     * Get the provider instance for a specific model
     * 
     * @param string $modelId
     * @return \App\Services\AI\Interfaces\AIModelProviderInterface
     */
    public function getProviderForModel(string $modelId)
    {
        return $this->providerFactory->getProviderForModel($modelId);
    }


    public function checkModelsStatus(){

    }
}
