<?php
session_start();
// Rename this file to config.php and fill in your API keys and credentials

// OpenAI API Key
define('OPENAI_API_KEY', 'YOUR_OPENAI_API_KEY');

// Replicate API Token
define('REPLICATE_API_TOKEN', 'YOUR_REPLICATE_API_TOKEN');

// OpenAI API Endpoint
define('OPENAI_API_URL', 'https://api.openai.com/v1/chat/completions');

// OpenAI Model (Vision-capable model recommended)
define('OPENAI_MODEL', 'gpt-4o'); // Or 'gpt-4-vision-preview', or other suitable vision model

// Replicate API Base URL (without specific model)
define('REPLICATE_BASE_URL', 'https://api.replicate.com/v1/models/');

// Available Replicate Models - Add your preferred models here
// Format: 'model_identifier' => 'Display Name'
$REPLICATE_MODELS = [
    'black-forest-labs/flux-kontext-pro' => 'Flux Kontext Pro',
    'black-forest-labs/flux-kontext-max' => 'Flux Kontext Max',
    'qwen/qwen-image-edit' => 'Qwen Image Edit',
    // Add more models as needed
];

// Default model to use if none is selected
define('DEFAULT_REPLICATE_MODEL', 'black-forest-labs/flux-kontext-pro');

// Define a username and password for basic authentication
define('LOGIN_USER', 'admin'); // Change this
define('LOGIN_PASSWORD', 'password123'); // Change this to a strong password!

// Base URL of your application (e.g., http://localhost/ad-brainstormer-app) - This constant is being removed.
// Used to construct public URLs for uploaded files.
// Make sure it ends with a slash.
// define('APP_BASE_URL', 'http://localhost/ad-brainstormer-app/'); // Adjust as per your setup - REMOVING THIS
define('UPLOADS_DIR', __DIR__ . '/../uploads/'); // Path relative to this config file (config/../uploads -> uploads/)
define('UPLOADS_URL_PATH', 'uploads/'); // This will be used as a relative path from the app root

// Ensure the uploads directory exists and is writable
if (!is_dir(UPLOADS_DIR)) {
    if (!mkdir(UPLOADS_DIR, 0775, true) && !is_dir(UPLOADS_DIR)) {
        // Handle error: directory could not be created.
        error_log("Uploads directory could not be created: " . UPLOADS_DIR);
        // Depending on your setup, you might want to die() here or handle it differently.
    }
}
if (!is_writable(UPLOADS_DIR)) {
    // Handle error: directory not writable. You might want to log this or die.
    error_log("Uploads directory is not writable: " . UPLOADS_DIR);
} 