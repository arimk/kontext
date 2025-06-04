<?php

class OpenAIHandler {
    private $apiKey;
    private $apiUrl;
    private $model;

    public function __construct($apiKey) {
        $this->apiKey = $apiKey;
        $this->apiUrl = OPENAI_API_URL; // From config.php
        $this->model = OPENAI_MODEL;   // From config.php
    }

    public function generateAdPrompts($userDirection, $imageUrl, $allPreviousPrompts = []) {
        $systemPrompt = "You are an artistic director for ads of multiple types of products. The user will give you an image of a product and optionally a direction to follow. Your task will be to create 4 prompts of ads that can be done for this product and the direction. Each prompt should be a complete sentence and suitable for an image generation model. Prompt is in a format \"Make an ad for this [product] [production_description], [background] [position] etc\". You always answer with this exact JSON array of 4 distinct strings like that: [\"make an ad for...\", \"make an ad for...\", \"make an ad for...\", \"make an ad for...\"] nothing more and no changing the JSON format. Ensure the new prompts are distinct from any previous suggestions if provided in the conversation history.";
        
        $messages = [];
        $messages[] = ['role' => 'system', 'content' => $systemPrompt];

        // --- Build Conversation History ---

        // 1. The very first user message in the conversation includes the image and initial direction.
        $firstUserMessageContent = [
            ["type" => "text", "text" => "User directions:\n" . $userDirection]
        ];
        if (!empty($imageUrl)) {
            $firstUserMessageContent[] = [
                "type" => "image_url",
                "image_url" => [
                    "url" => $imageUrl,
                    "detail" => "auto"
                ]
            ];
        }
        $messages[] = ['role' => 'user', 'content' => $firstUserMessageContent];

        // 2. Add historical assistant responses and subsequent user requests for "more".
        if (!empty($allPreviousPrompts)) {
            $chunkSize = 4; // Assuming we always generate 4 prompts at a time.
            for ($i = 0; $i < count($allPreviousPrompts); $i += $chunkSize) {
                $promptChunk = array_slice($allPreviousPrompts, $i, $chunkSize);
                if (empty($promptChunk)) {
                    continue;
                }

                // Assistant's past response (a set of 4 prompts)
                $messages[] = ['role' => 'assistant', 'content' => json_encode($promptChunk)];

                // User's subsequent request for more (that led to the next chunk of history, or leads to the current new request)
                // This message will be the one asking for the *current* batch if this is the last iteration of historical prompts.
                $currentUserRequestText = "Considering the previous suggestions, please provide 4 *new and distinct* ad concepts based on the original image and directions.";
                $messages[] = ['role' => 'user', 'content' => [["type" => "text", "text" => $currentUserRequestText]]];
                // Note: The image is in the *first* user message. Vision models can refer back to images in earlier parts of the conversation.
            }
        }
        // If $allPreviousPrompts was empty, the $messages array currently contains:
        // 1. System
        // 2. User (initial request with image + direction)
        // This is correct for a first-time request. The API will generate based on this.

        // If $allPreviousPrompts was NOT empty, the $messages array currently contains:
        // 1. System
        // 2. User (initial request with image + direction)
        // 3. Assistant (chunk 1 of previous prompts)
        // 4. User (asking for more, which will lead to the current generation)
        // 5. Assistant (chunk 2 of previous prompts)
        // 6. User (asking for more, which will lead to the current generation)
        // ...
        // N. User (the final message, asking for the *current* new batch of 4 prompts)
        // This structure correctly represents the ongoing conversation.

        $payload = [
            'model' => $this->model,
            'messages' => $messages,
            'response_format' => ['type' => 'json_object'],
            'temperature' => 0.75 // Slightly higher temp might encourage more variation
        ];

        $ch = curl_init($this->apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->apiKey
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60); // 60 seconds timeout

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            return ['error' => 'cURL Error: ' . $curlError];
        }

        if ($httpCode !== 200) {
            $errorDetails = json_decode($response, true);
            return ['error' => "OpenAI API request failed with code {$httpCode}. Response: " . ($errorDetails['error']['message'] ?? $response)];
        }

        $responseData = json_decode($response, true);

        if (isset($responseData['choices'][0]['message']['content'])) {
            $jsonContent = $responseData['choices'][0]['message']['content'];
            $promptsContainer = json_decode($jsonContent, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($promptsContainer)) {
                // The model is asked to return a JSON object which contains the array of prompts,
                // or it might directly be the array, or an object with numbered keys.
                
                // Scenario 1: The JSON content is an object with 0-indexed numeric keys
                // e.g. {"0": "prompt1", "1": "prompt2", "2": "prompt3", "3": "prompt4"}
                if (count($promptsContainer) === 4 && isset($promptsContainer[0]) && is_string($promptsContainer[0]) && array_keys($promptsContainer) === range(0,3) ) {
                    $allStrings = true;
                    foreach ($promptsContainer as $p) {
                        if (!is_string($p)) {
                            $allStrings = false;
                            break;
                        }
                    }
                    if ($allStrings) return $promptsContainer;
                }

                // Scenario 2: The JSON content is an object with a key containing the array of 4 prompts, e.g. {"ad_prompts": ["p1", ...]}
                // This often happens if the system prompt asks for an object with a specific key.
                foreach ($promptsContainer as $key => $value) {
                    if (is_array($value) && count($value) === 4 && isset($value[0]) && is_string($value[0]) && array_keys($value) === range(0,3)) {
                        $allStrings = true;
                        foreach ($value as $p_val) {
                            if (!is_string($p_val)) {
                                $allStrings = false;
                                break;
                            }
                        }
                        if ($allStrings) return $value;
                    }
                }

                // Scenario 3: The JSON content is an object with keys "1", "2", "3", "4" and string values.
                // e.g. {"1": "prompt1", "2": "prompt2", "3": "prompt3", "4": "prompt4"}
                if (count($promptsContainer) === 4 &&
                    isset($promptsContainer['1']) && is_string($promptsContainer['1']) &&
                    isset($promptsContainer['2']) && is_string($promptsContainer['2']) &&
                    isset($promptsContainer['3']) && is_string($promptsContainer['3']) &&
                    isset($promptsContainer['4']) && is_string($promptsContainer['4'])) {
                    
                    return [
                        trim($promptsContainer['1'], " \t\n\r\0\x0B,"),
                        trim($promptsContainer['2'], " \t\n\r\0\x0B,"),
                        trim($promptsContainer['3'], " \t\n\r\0\x0B,"),
                        trim($promptsContainer['4'], " \t\n\r\0\x0B,")
                    ];
                }

                // Scenario 4: The JSON content is an object with 2 key-value pairs,
                // where keys are prompts and values are prompts.
                // e.g. {"promptA_key,": "promptB_value,", "promptC_key,": "promptD_value,"}
                if (is_array($promptsContainer) && count($promptsContainer) === 2) {
                    $keys = array_keys($promptsContainer);
                    $values = array_values($promptsContainer);

                    if (isset($keys[0]) && is_string($keys[0]) &&
                        isset($values[0]) && is_string($values[0]) &&
                        isset($keys[1]) && is_string($keys[1]) &&
                        isset($values[1]) && is_string($values[1])) {

                        $prompt1 = trim($keys[0], " \t\n\r\0\x0B,");
                        $prompt2 = trim($values[0], " \t\n\r\0\x0B,");
                        $prompt3 = trim($keys[1], " \t\n\r\0\x0B,");
                        $prompt4 = trim($values[1], " \t\n\r\0\x0B,");

                        if (!empty($prompt1) && !empty($prompt2) && !empty($prompt3) && !empty($prompt4)) {
                            return [$prompt1, $prompt2, $prompt3, $prompt4];
                        }
                    }
                }

                return ['error' => 'OpenAI returned JSON, but not in any of the expected array formats of 4 prompts. Content: ' . $jsonContent];
            } else {
                 return ['error' => 'Failed to parse JSON from OpenAI response or not an array. JSON Error: ' . json_last_error_msg() . '. Content: ' . $jsonContent];
            }
        } elseif (isset($responseData['error'])) {
            return ['error' => 'OpenAI API Error: ' . $responseData['error']['message']];
        }

        return ['error' => 'Unexpected response structure from OpenAI.'];
    }
} 