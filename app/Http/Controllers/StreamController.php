<?php

namespace App\Http\Controllers;

use App\Http\Controllers\RoomController;

use App\Models\User;
use App\Models\Room;
use App\Models\Message;
use App\Models\Member;


use App\Services\AI\UsageAnalyzerService;
use App\Services\AI\AIConnectionService;
use App\Services\AI\AIProviderFactory;

use App\Jobs\SendMessage;
use App\Events\RoomMessageEvent;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class StreamController extends Controller
{

    protected $usageAnalyzer;
    protected $aiConnectionService;
    private $jsonBuffer = '';

    public function __construct(
        UsageAnalyzerService $usageAnalyzer,
        AIConnectionService $aiConnectionService
    ){
        $this->usageAnalyzer = $usageAnalyzer;
        $this->aiConnectionService = $aiConnectionService;
    }


    public function handleExternalRequest(Request $request)
    {
        // Find out user model
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        try {
            // Validate request data
            $validatedData = $request->validate([
                'payload.model' => 'required|string',
                'payload.messages' => 'required|array',
                'payload.messages.*.role' => 'required|string',
                'payload.messages.*.content' => 'required|array',
                'payload.messages.*.content.text' => 'required|string',
            ]);
        } catch (ValidationException $e) {
            // Return detailed validation error response
            return response()->json([
                'success' => false,
                'message' => 'Validation Error',
                'errors' => $e->errors()
            ], 422);
        }

        $payload = $validatedData['payload'];
        $payload['stream'] = false;

        // Handle standard response
        $result = $this->aiConnectionService->processRequest(
            $payload,
            false
        );
        
        // Record usage
        if (isset($result['usage'])) {
            $this->usageAnalyzer->submitUsageRecord(
                $result['usage'], 
                'api', 
                $validatedData['payload']['model']
            );
        }
        // Return response to client
        return response()->json([
            'success' => true,
            'content' => $result['content'],
        ]);
    }
    



    /**
     * Handle AI connection requests using the new architecture
     */
    public function handleAiConnectionRequest(Request $request)
    {
        //validate payload
       
        $validatedData = $request->validate([
            'payload.model' => 'required|string',
            'payload.stream' => 'required|boolean',
            'payload.messages' => 'required|array',
            'payload.messages.*.role' => 'required|string',
            'payload.messages.*.content' => 'required|array',
            // for image responses text can be null
            'payload.messages.*.content.text' => 'nullable|string',
            'payload.messages.*.auxiliaries' => 'nullable|array',
            'payload.messages.*.auxiliaries.*.type' => 'required|string',
            'payload.messages.*.auxiliaries.*.content' => 'required|string',

            'broadcast' => 'required|boolean',
            'isUpdate' => 'nullable|boolean',
            'messageId' => 'nullable|string',
            'threadIndex' => 'nullable|int', 
            'slug' => 'nullable|string',
            'key' => 'nullable|string',
        ]);

        if ($validatedData['broadcast']) {
            $this->handleGroupChatRequest($validatedData);
        } else {
            $user = User::find(1); // HAWKI user 
            $avatar_url = $user->avatar_id !== '' ? Storage::disk('public')->url('profile_avatars/' . $user->avatar_id) : null;
            
            if ($validatedData['payload']['stream']) {
                // Handle streaming response
               
                $this->handleStreamingRequest($validatedData['payload'], $user, $avatar_url);
            } else {
                // Handle standard response
                $result = $this->aiConnectionService->processRequest(
                    $validatedData['payload'],
                    false
                );
                
                // Record usage
                if (isset($result['usage'])) {
                    $this->usageAnalyzer->submitUsageRecord(
                        $result['usage'], 
                        'private', 
                        $validatedData['payload']['model']
                    );
                }
                // Return response to client
                return response()->json([
                    'author' => [
                        'username' => $user->username,
                        'name' => $user->name,
                        'avatar_url' => $avatar_url,
                    ],
                    'model' => $validatedData['payload']['model'],
                    'isDone' => true,
                    'content' => $result['content'],
                    'auxiliaries' => $result['auxiliaries'] ?? [],
                    'imageQuota' => Auth::user()->imageQuotaData(),
                ]);
            }
        }
    }
    
    /**
     * Handle streaming request with the new architecture
     */
    private function handleStreamingRequest(array $payload, User $user, ?string $avatar_url)
    {
        // Set headers for SSE
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        header('Access-Control-Allow-Origin: *');
        
        // Get the provider for this model
        // we do this outside the $onData function so that the provider
        // instance can capture state across streaming events
        $provider = $this->aiConnectionService->getProviderForModel($payload['model']);

        // OpenAI will provide valid json
        $isOpenAIProvider = str_contains(get_class($provider), "OpenAI");
        
        // Create a callback function to process streaming chunks
        $buffer = '';
        $onData = function ($data) use ($user, $avatar_url, $payload, $provider, $isOpenAIProvider, $buffer) {
            $chunks = [];
            if ($isOpenAIProvider) {
                // This is as per https://github.com/openai/openai-python/blob/main/src/openai/_streaming.py
                // partition of data part
                $lines = preg_split('/\r\n|\r|\n/', $data);
                foreach ($lines as $line) {
                    // Check if it starts with "data:" (case-sensitive). Use strncmp for speed.
                    if (strncmp($line, 'data:', 5) === 0) {
                        
                        // Remove the "data:" prefix and trim the remainder
                        $value = substr($line, 5);
                        if ($value !== '' && $value[0] === ' ') {
                            $value = substr($value, 1);
                        }
                        $buffer .= "\n".$value;
                    }
                    if (empty($line)) {
                        
                        if (json_decode($buffer, true)) {
                            $chunks[] = $buffer;
                            $buffer = '';
                        } 
                    }
                }

                if (json_decode($buffer, true)) {
                    $chunks[] = $buffer;
                    $buffer = '';
                }
            } else {

                // Only use normaliseDataChunk if the content of $data does not begin with ‘data: ’.
                if (strpos(trim($data), 'data: ') !== 0) {
                    $data = $this->normalizeDataChunk($data);
                    //Log::info('google chunk detected');
                }

                // Skip non-JSON or empty chunks
                $chunks = explode("data: ", $data);
            }
            foreach ($chunks as $chunk) {
                if (connection_aborted()) break;
                if (!json_decode($chunk, true) || empty($chunk)) continue;
                
                // Format the chunk
                $formatted = $provider->formatStreamChunk($chunk);

                // we might want to skip an update, e.g. for intermediate responses
                // with not displayable content. The OpenAI response API for instance
                // produces tool completed updates without a displayable payload.
                if (isset($formatted['skip']) && $formatted['skip']) {
                    continue;
                }

                // Record usage if available
                if ($formatted['usage']) {
                    $this->usageAnalyzer->submitUsageRecord(
                        $formatted['usage'], 
                        'private', 
                        $payload['model']
                    );
                }
                
                // Send the formatted response to the client
                $messageData = [
                    'author' => [
                        'username' => $user->username,
                        'name' => $user->name,
                        'avatar_url' => $avatar_url,
                    ],
                    'model' => $payload['model'],
                    'isDone' => $formatted['isDone'],
                    'content' => json_encode($formatted['content']),
                    'auxiliaries' => $formatted['auxiliaries'] ?? [],
                    'isFinalText' => $formatted['isFinalText'] ?? false,
                    'imageQuota' => Auth::user()->imageQuotaData(),
                ];
                
                echo json_encode($messageData) . "\n";
            }
        };
        // Process the streaming request
        $this->aiConnectionService->processRequest(
            $payload, 
            true, 
            $onData
        );
    }
    /*
     * Helper function to translate curl return object from google to openai format
     */
    private function normalizeDataChunk(string $data): string
    {
        $this->jsonBuffer .= $data;

        if(trim($this->jsonBuffer) === "]") {
            $this->jsonBuffer = "";
            return "";
        }

        $output = "";
        while($extracted = $this->extractJsonObject($this->jsonBuffer)) {
            $jsonStr = $extracted['jsonStr'];
            $this->jsonBuffer = $extracted['rest'];
            $output .= "data: " . $jsonStr . "\n";
        }
        return $output;
    }

    // New helper function to extract only complete JSON objects from buffer
    private function extractJsonObject(string $buffer): ?array
    {
        $openBraces = 0;
        $startFound = false;
        $startPos = 0;
        $openQuote = false;

        for($i = 0; $i < strlen($buffer); $i++) {
            $char = $buffer[$i];
            if ($char === '"') $openQuote = !$openQuote;
            if ($openQuote) continue; // if we are inside a quote brace matching doesn't matter!
            if($char === '{') {
                if(!$startFound) {
                    $startFound = true;
                    $startPos = $i;
                }
                $openBraces++;
            } elseif($char === '}') {
                $openBraces--;
                if($openBraces === 0 && $startFound) {
                    $jsonStr = substr($buffer, $startPos, $i - $startPos + 1);
                    $rest = substr($buffer, $i + 1);
                    return ['jsonStr' => $jsonStr, 'rest' => $rest];
                }
            }
        }
        return null;
    }
    /**
     * Handle group chat requests with the new architecture
     */
    private function handleGroupChatRequest(array $data)
    {
        $isUpdate = (bool) ($data['isUpdate'] ?? false);
        $room = Room::where('slug', $data['slug'])->firstOrFail();
        
        // Broadcast initial generation status
        $generationStatus = [
            'type' => 'aiGenerationStatus',
            'messageData' => [
                'room_id' => $room->id,
                'isGenerating' => true,
                'model' => $data['payload']['model']
            ]
        ];
        broadcast(new RoomMessageEvent($generationStatus));
        
        // Process the request
        $result = $this->aiConnectionService->processRequest(
            $data['payload'],
            false
        );
        
        // Record usage
        if (isset($result['usage'])) {
            $this->usageAnalyzer->submitUsageRecord(
                $result['usage'], 
                'group', 
                $data['payload']['model'],
                $room->id
            );
        }
        
        // Encrypt content for storage
        $cryptoController = new EncryptionController();
        $encKey = base64_decode($data['key']);
        $encryptiedData = $cryptoController->encryptWithSymKey($encKey, json_encode($result['content']), false);
        
        // Store message
        $roomController = new RoomController();
        $member = $room->members()->where('user_id', 1)->firstOrFail();
        
        if ($isUpdate) {
            $message = $room->messages->where('message_id', $data['messageId'])->first();
            $message->update([
                'iv' => $encryptiedData['iv'],
                'tag' => $encryptiedData['tag'],
                'content' => $encryptiedData['ciphertext'],
            ]);
        } else {
            $nextMessageId = $roomController->generateMessageID($room, $data['threadIndex']);
            $message = Message::create([
                'room_id' => $room->id,
                'member_id' => $member->id,
                'message_id' => $nextMessageId,
                'message_role' => 'assistant',
                'model' => $data['payload']['model'],
                'iv' => $encryptiedData['iv'],
                'tag' => $encryptiedData['tag'],
                'content' => $encryptiedData['ciphertext'],
            ]);
        }
        
        // Queue message for broadcast
        SendMessage::dispatch($message, $isUpdate)->onQueue('message_broadcast');
        
        // Update and broadcast final generation status
        $generationStatus = [
            'type' => 'aiGenerationStatus',
            'messageData' => [
                'room_id' => $room->id,
                'isGenerating' => false,
                'model' => $data['payload']['model']
            ]
        ];
        broadcast(new RoomMessageEvent($generationStatus));
    }
    
}
