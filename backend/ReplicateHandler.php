<?php

class ReplicateHandler {
    private $apiToken;
    private $baseUrl;

    public function __construct($apiToken) {
        $this->apiToken = $apiToken;
        $this->baseUrl = REPLICATE_BASE_URL; // From config.php
    }

    public function generateImage($prompt, $aspectRatio, $inputImageUrl, $model = null) {
        // Use default model if none specified
        if ($model === null) {
            $model = DEFAULT_REPLICATE_MODEL;
        }
        
        // Construct the full API URL for the specific model
        $apiUrl = $this->baseUrl . $model . '/predictions';
        
        // Initialize the input array for the payload
        $payloadInput = [
            'prompt' => $prompt,
            // Assuming the Replicate model pointed to by REPLICATE_API_URL
            // directly accepts 'aspect_ratio' as a string (e.g., "16:9").
            // If it requires width and height, that logic would need to be here.
            'aspect_ratio' => $aspectRatio,
        ];

        // Conditionally add input_image if a valid URL is provided
        if (!empty($inputImageUrl) && filter_var($inputImageUrl, FILTER_VALIDATE_URL)) {
            // The key 'input_image' should match what your specific Replicate model expects.
            // It could be 'image', 'init_image', etc.
            $payloadInput['input_image'] = $inputImageUrl;
        }

        // Construct the final payload
        $payload = ['input' => $payloadInput];

        $ch = curl_init($apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->apiToken,
            'Content-Type: application/json',
            'Prefer: wait' // Wait for the prediction to complete
        ]);
        // Timeout needs to be long enough for Replicate to process
        // This is handled by set_time_limit in api.php before calling this loop.
        // curl_setopt($ch, CURLOPT_TIMEOUT, 240); // e.g., 4 minutes, adjust based on model processing time

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            return ['error' => 'cURL Error: ' . $curlError];
        }

        $responseData = json_decode($response, true);

        if ($httpCode !== 200 && $httpCode !== 201) { // Replicate might return 201 for prediction creation
            $errorMessage = $responseData['detail'] ?? ($responseData['error'] ?? 'Unknown API error');
            return ['error' => "API request failed with code {$httpCode}. Response: " . $errorMessage];
        }
        
        if (isset($responseData['status']) && ($responseData['status'] === 'succeeded' || $responseData['status'] === 'completed')) {
            if (isset($responseData['output']) && is_array($responseData['output']) && !empty($responseData['output'][0])) {
                // Assuming the model returns an array of image URLs, take the first one.
                return ['output_url' => $responseData['output'][0]];
            } elseif (isset($responseData['output']) && is_string($responseData['output'])) {
                 // Some models might return a direct string URL
                return ['output_url' => $responseData['output']];
            } else {
                return ['error' => 'Image generation succeeded but output URL not found or in unexpected format. Response: ' . $response];
            }
        } elseif (isset($responseData['status']) && ($responseData['status'] === 'failed' || $responseData['status'] === 'canceled')) {
            $errorLog = isset($responseData['logs']) ? $responseData['logs'] : (isset($responseData['error']) ? $responseData['error'] : 'Prediction failed or was canceled.');
            return ['error' => 'Image generation ' . $responseData['status'] . '. Log: ' . $errorLog];
        } elseif (isset($responseData['error'])) { // Direct error from Replicate API before prediction
             return ['error' => 'Replicate API Error: ' . $responseData['error']];
        }


        // Fallback if status is not explicitly success/failure but we got a 200/201
        // This might happen if 'Prefer: wait' isn't fully synchronous or if the response structure varies.
        // Check for output directly.
        if (isset($responseData['output']) && is_array($responseData['output']) && !empty($responseData['output'][0])) {
            return ['output_url' => $responseData['output'][0]];
        }
        if (isset($responseData['output']) && is_string($responseData['output'])) {
            return ['output_url' => $responseData['output']];
        }

        return ['error' => 'Unexpected response structure or prediction did not complete successfully. Response: ' . $response];
    }
} 