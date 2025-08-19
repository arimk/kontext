<?php
ini_set('display_errors', 1); // For development, remove for production
error_reporting(E_ALL);     // For development

require_once __DIR__ . '/../config/config.php'; // Ensures session_start()

// --- Authentication Check ---
if (!isset($_SESSION['is_logged_in']) || $_SESSION['is_logged_in'] !== true) {
    header('Content-Type: application/json');
    http_response_code(401); // Unauthorized
    echo json_encode(['error' => 'Authentication required. Please log in.']);
    exit;
}
// --- End Authentication Check ---


// Ensure session is started. If not in config.php, start it here.
// This line is now redundant if config.php always starts it, but harmless.
// if (session_status() == PHP_SESSION_NONE) { // Redundant if config.php handles it
//     session_start();
// }

// require_once __DIR__ . '/../config/config.php'; // This is a duplicate require_once if already at the top
require_once __DIR__ . '/OpenAIHandler.php';
require_once __DIR__ . '/ReplicateHandler.php';

header('Content-Type: application/json');

// Get the request body for JSON
$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? $_POST['action'] ?? $_GET['action'] ?? null;


if (!$action) {
    echo json_encode(['status' => 'error', 'message' => 'No action specified.']);
    exit;
}

switch ($action) {
    case 'generate_ad_ideas':
        // ... (existing code for ad ideas) ...
        // For brevity, assuming existing ad idea generation logic is here
        // Ensure it also returns JSON
        $prompt = $input['prompt'] ?? '';
        $image_data_url = $input['image_data_url'] ?? '';
        $aspect_ratio = $input['aspect_ratio'] ?? '1:1';
        $num_outputs = $input['num_outputs'] ?? 4;
        $session_id = $input['session_id'] ?? uniqid('session_'); // Manage session for pagination

        // Simulate API call to an image generation service
        $mock_results = [];
        for ($i = 0; $i < $num_outputs; $i++) {
            $mock_results[] = [
                'id' => uniqid(),
                'prompt_text' => "Simulated ad idea for: " . substr($prompt, 0, 30) . "... #" . ($i + 1),
                'image_url' => "https://picsum.photos/seed/" . uniqid() . "/300/200?aspect_ratio=" . $aspect_ratio,
                'alt_text' => "Simulated ad image " . ($i + 1)
            ];
        }
        // Simulate delay
        sleep(1);
        echo json_encode(['status' => 'success', 'ideas' => $mock_results, 'session_id' => $session_id]);
        break;

    case 'chat_message':
        $userText = trim($input['text'] ?? '');
        $imageContext = $input['image_context'] ?? null; // Can be base64 data URI or a direct URL
        $aspectRatio = $input['aspectRatio'] ?? '1:1';
        $isRetry = $input['is_retry'] ?? false;
        $isEdit = $input['is_edit'] ?? false;

        $botResponseText = "";
        $botImageUrl = null;
        $inputImageUrlForReplicate = null;
        $tempUploadedFilePath = null;

        // --- Determine the input image for Replicate from image_context ---
        if ($imageContext) {
            // Check if it's a base64 data URI
            if (preg_match('/^data:image\/(\w+);base64,/', $imageContext, $type)) {
                $imageBase64Data = substr($imageContext, strpos($imageContext, ',') + 1);
                $imageType = strtolower($type[1]); // jpg, png, gif
                $imageDataDecoded = base64_decode($imageBase64Data);

                if ($imageDataDecoded === false) {
                    echo json_encode(['status' => 'error', 'message' => 'Failed to decode base64 image data.']);
                    exit;
                }

                $uploadDir = UPLOADS_DIR;
                if (!is_writable($uploadDir)) {
                    error_log("Chat upload directory not writable: " . $uploadDir);
                    echo json_encode(['status' => 'error', 'message' => 'Server configuration error: Upload directory not writable.']);
                    exit;
                }
                $tempFileName = 'chat_temp_user_' . uniqid() . '.' . $imageType;
                $tempUploadedFilePath = $uploadDir . $tempFileName;

                if (!file_put_contents($tempUploadedFilePath, $imageDataDecoded)) {
                    echo json_encode(['status' => 'error', 'message' => 'Failed to save temporary user chat image.']);
                    exit;
                }
                // Construct public URL for this newly uploaded temporary user image
                $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
                $host = $_SERVER['HTTP_HOST'];
                $scriptDirPath = dirname($_SERVER['SCRIPT_NAME']);
                $appRootPath = dirname($scriptDirPath);
                if ($appRootPath === '/' || $appRootPath === '\\') $appRootPath = '';
                
                $uploadUrlPath = UPLOADS_URL_PATH;
                $imagePathSegment = rtrim($uploadUrlPath, '/') . '/' . $tempFileName;
                
                $baseAppUrl = $protocol . $host . rtrim($appRootPath, '/');
                $inputImageUrlForReplicate = $baseAppUrl . '/' . ltrim($imagePathSegment, '/');
                $inputImageUrlForReplicate = preg_replace('#(?<!:)/+#', '/', $inputImageUrlForReplicate);

                $botResponseText = "Using your uploaded image. ";

            } elseif (filter_var($imageContext, FILTER_VALIDATE_URL)) {
                // It's a direct URL (from a previous bot generation or retry)
                $inputImageUrlForReplicate = $imageContext;
                $botResponseText = $isRetry ? "Retrying with the same image. " : "Using the last generated image. ";
            } else {
                // Invalid image_context format
                $botResponseText = "Received an invalid image format. ";
                // Potentially log this error or inform user more clearly
            }
        }

        // --- Core Logic: If there's text, assume image generation/modification intent ---
        // If no text, but there was an image context, bot asks what to do.
        // If no text and no image context, bot asks for a prompt.

        if (!empty($userText)) {
            // Always attempt to generate/modify if there's user text.
            // $inputImageUrlForReplicate will be null if no valid image context was established.
            $replicateHandler = new ReplicateHandler(REPLICATE_API_TOKEN);
            $originalExecutionTime = ini_get('max_execution_time');
            set_time_limit(300);

            // Get the selected model from the request, default to the configured default
            $selectedModel = $input['model'] ?? DEFAULT_REPLICATE_MODEL;
            $replicateResponse = $replicateHandler->generateImage($userText, $aspectRatio, $inputImageUrlForReplicate, $selectedModel);
            
            set_time_limit($originalExecutionTime);

            if (isset($replicateResponse['error'])) {
                $botResponseText .= "Replicate API error: " . $replicateResponse['error'];
                error_log("Replicate Error in Chat: " . $replicateResponse['error'] . " for prompt: " . $userText . " with image context: " . $inputImageUrlForReplicate);
            } elseif (isset($replicateResponse['output_url'])) {
                $botImageUrl = $replicateResponse['output_url'];
                if ($isRetry) {
                    $botResponseText .= "Here's another attempt based on your request: \"$userText\"";
                } elseif ($isEdit) {
                    $botResponseText .= "Here's the regenerated image with your edited prompt: \"$userText\"";
                } else {
                    $botResponseText .= "Here's the image based on your request: \"$userText\"";
                }
            } else {
                $botResponseText .= "Sorry, I couldn't generate an image due to an unexpected issue.";
                error_log("Replicate unexpected response in Chat for prompt '{$userText}' with image context '{$inputImageUrlForReplicate}': " . print_r($replicateResponse, true));
            }
        } elseif ($inputImageUrlForReplicate) { // No text, but there IS an image context
            $botResponseText .= "What would you like to do with this image?";
        } else { // No text AND no image context
            $botResponseText = "Hi there! Please type what you'd like to create or upload an image to edit.";
        }

        // Cleanup temporary file if one was created
        if ($tempUploadedFilePath && file_exists($tempUploadedFilePath)) {
            unlink($tempUploadedFilePath);
        }

        echo json_encode([
            'status' => 'success',
            'botText' => $botResponseText,
            'botImageUrl' => $botImageUrl
        ]);
        break;

    case 'generate_ads_prompts':
        handleGenerateAdsPrompts();
        break;

    case 'generate_single_image':
        handleGenerateSingleImage();
        break;

    case 'combine_images':
        // Placeholder for multi-image combination logic
        // This will be a complex part involving GD or Imagick
        try {
            error_log("Action: combine_images received.");
            require_once __DIR__ . '/../config/config.php'; // Ensure config is loaded

            $numImages = isset($_POST['numImages']) ? intval($_POST['numImages']) : 0;
            $directionText = $_POST['directionText'] ?? '';
            $outputAspectRatioString = $_POST['aspectRatio'] ?? '4:3';
            $uploadedImagePaths = [];
            $errors = [];

            if ($numImages === 0 || $numImages > 4) {
                echo json_encode(['error' => 'Invalid number of images. Please upload 1 to 4 images.']);
                exit;
            }

            // Ensure uploads directory exists and is writable
            if (!file_exists(UPLOADS_DIR)) {
                if (!mkdir(UPLOADS_DIR, 0777, true)) {
                    echo json_encode(['error' => 'Failed to create uploads directory.']);
                    exit;
                }
            }
            if (!is_writable(UPLOADS_DIR)) {
                echo json_encode(['error' => 'Uploads directory is not writable.']);
                exit;
            }

            for ($i = 1; $i <= $numImages; $i++) {
                if (isset($_FILES["imageUpload_{$i}"]) && $_FILES["imageUpload_{$i}"]['error'] === UPLOAD_ERR_OK) {
                    $file = $_FILES["imageUpload_{$i}"];
                    $fileName = uniqid('multi-', true) . '-' . basename($file['name']);
                    $targetPath = UPLOADS_DIR . DIRECTORY_SEPARATOR . $fileName;
                    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                        $uploadedImagePaths[] = $targetPath;
                    } else {
                        $errors[] = "Failed to move uploaded file: " . $file['name'];
                    }
                } else {
                    $errors[] = "Error with file upload for image {$i}: " . ($_FILES["imageUpload_{$i}"]['error'] ?? 'Unknown error');
                }
            }

            if (!empty($errors)) {
                // Cleanup any successfully uploaded files if others failed
                foreach ($uploadedImagePaths as $path) unlink($path);
                echo json_encode(['error' => 'Error during file uploads: ' . implode('; ', $errors)]);
                exit;
            }

            if (empty($uploadedImagePaths)) {
                echo json_encode(['error' => 'No images were successfully uploaded.']);
                exit;
            }

            require_once __DIR__ . '/ImageMerger.php';
            $merger = new ImageMerger();
            // ImageMerger::merge now determines its own canvas logic and doesn't use outputAspectRatioString for its canvas.
            // It returns an array of errors if any, or the path string on success.
            $mergeResult = $merger->merge($uploadedImagePaths, UPLOADS_DIR);

            // Clean up the individual source uploaded files now that merging is attempted (or done)
            foreach ($uploadedImagePaths as $path) {
                if (file_exists($path)) unlink($path);
            }

            if (is_array($mergeResult)) { // Errors occurred during merge
                // $mergeResult contains an array of error messages
                // We might still have a partially merged image saved by ImageMerger for debugging, 
                // but we won't proceed to Replicate.
                // If ImageMerger saved a debug image despite errors, its path might be the last element if it tried to save.
                // For simplicity, just return the errors clearly.
                echo json_encode(['error' => 'Error(s) during image merging: ' . implode("; ", $mergeResult)]);
                exit;
            }
            
            // If we reach here, $mergeResult is the path to the successfully merged image.
            $combinedImagePath = $mergeResult;

            // Construct the public URL for the newly merged local image
            $mergedImageFilename = basename($combinedImagePath);
            
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
            $host = $_SERVER['HTTP_HOST'];
            $scriptDirPath = dirname($_SERVER['SCRIPT_NAME']); // Path to /backend
            $appRootPath = dirname($scriptDirPath); // Path to app root
            if ($appRootPath === '/' || $appRootPath === '\\') $appRootPath = '';

            $uploadUrlPath = UPLOADS_URL_PATH; // Relative path like 'uploads/'
            $imagePathSegment = rtrim($uploadUrlPath, '/') . '/' . $mergedImageFilename;
            $publicCombinedImageUrl = $protocol . $host . rtrim($appRootPath, '/') . '/' . ltrim($imagePathSegment, '/');
            $publicCombinedImageUrl = preg_replace('#(?<!:)/+#', '/', $publicCombinedImageUrl);

            error_log("Public URL for combined image: " . $publicCombinedImageUrl);

            // Now, send this combined image and the prompt to Replicate to generate 4 variations
            $replicateHandler = new ReplicateHandler(REPLICATE_API_TOKEN);
            $userPrompt = $directionText; 
            $numVariationsToGenerate = 4;
            $generatedImageUrls = [];
            $generationErrors = [];

            // Get the selected model from the request, default to the configured default
            $selectedModel = $_POST['model'] ?? DEFAULT_REPLICATE_MODEL;

            $originalExecutionTime = ini_get('max_execution_time');
            // Set time limit for the whole loop of Replicate calls
            // Each Replicate call might take up to 5 mins, so 4 * 5 = 20 mins, plus buffer
            set_time_limit($numVariationsToGenerate * 300 + 60); 

            for ($i = 0; $i < $numVariationsToGenerate; $i++) {
                // To encourage variation if the model uses seed, or if minor prompt changes help:
                // $currentPrompt = $userPrompt . " (variation #" . ($i + 1) . ")"; 
                // For many models, calling without a fixed seed will produce variations naturally.
                // We'll use the same prompt for now unless model requires variation hints.
                $currentPrompt = $userPrompt; 

                $replicateResponse = $replicateHandler->generateImage($currentPrompt, $outputAspectRatioString, $publicCombinedImageUrl, $selectedModel);

                if (isset($replicateResponse['error'])) {
                    $generationErrors[] = "Variation " . ($i + 1) . ": " . $replicateResponse['error'];
                    $generatedImageUrls[] = null; // Placeholder for failed generation
                    error_log("Replicate Error (Multi-Image Variation " . ($i + 1) . "): " . $replicateResponse['error']);
                } elseif (isset($replicateResponse['output_url'])) {
                    $generatedImageUrls[] = $replicateResponse['output_url'];
                } else {
                    $generationErrors[] = "Variation " . ($i + 1) . ": Unexpected response from Replicate.";
                    $generatedImageUrls[] = null; // Placeholder for failed generation
                    error_log("Replicate unexpected response (Multi-Image Variation " . ($i + 1) . "): " . print_r($replicateResponse, true));
                }
            }
            
            set_time_limit($originalExecutionTime); // Restore execution time

            // Clean up the local combined image file now that Replicate has (tried to) use it
            if (file_exists($combinedImagePath)) {
                unlink($combinedImagePath);
            }

            if (empty(array_filter($generatedImageUrls))) { // Check if all generations failed
                 $errorString = !empty($generationErrors) ? implode("; ", $generationErrors) : "All image variations failed to generate.";
                 echo json_encode(['error' => $errorString]);
                 exit;
            }
            
            // Send back all generated URLs (some might be null if they failed individually)
            // and any errors encountered.
            echo json_encode([
                'success' => true, 
                'data' => [
                    'imageUrls' => $generatedImageUrls, // Array of URLs (or null for failures)
                    'prompt' => $userPrompt ?: 'Generated from combined images',
                    'individualErrors' => $generationErrors // Array of error messages for specific variations
                ]
            ]);

        } catch (Exception $e) {
            error_log("Error in combine_images: " . $e->getMessage());
            // Clean up any successfully uploaded files if an exception occurs before explicit cleanup
            if (!empty($uploadedImagePaths)) {
                 foreach ($uploadedImagePaths as $path) {
                    if (file_exists($path)) unlink($path);
                }
            }
            echo json_encode(['error' => 'Server error during image combination: ' . $e->getMessage()]);
        }
        break;

    default:
        echo json_encode(['status' => 'error', 'message' => 'Invalid action.']);
        break;
}

function handleGenerateAdsPrompts() {
    // --- 1. Input Validation & File Handling ---
    $isLoadMore = isset($_POST['loadMore']) && $_POST['loadMore'] === 'true';
    $uploadedFile = $_FILES['imageUpload'] ?? null;
    $absoluteImageUrlForOpenAI = ''; // Will be set if a new image is uploaded

    if (!$isLoadMore) { // New image upload expected
        if (!$uploadedFile || $uploadedFile['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['error' => 'Image upload failed or no image provided. Error code: ' . ($uploadedFile['error'] ?? 'N/A')]);
            exit;
        }

        // --- Clean up previous image if it exists ---
        if (isset($_SESSION['current_uploaded_file_path']) && file_exists($_SESSION['current_uploaded_file_path'])) {
            unlink($_SESSION['current_uploaded_file_path']);
            unset($_SESSION['current_uploaded_file_path']);
            unset($_SESSION['current_uploaded_file_public_url']);
        }

        // --- Process new upload ---
        $uploadDir = UPLOADS_DIR;
        if (!is_writable($uploadDir)) {
            error_log("Upload directory not writable: " . $uploadDir);
            echo json_encode(['error' => 'Server configuration error: Upload directory not writable.']);
            exit;
        }
        
        $fileName = uniqid('img_', true) . '_' . basename($uploadedFile['name']);
        $targetFilePath = $uploadDir . $fileName;
        $fileType = strtolower(pathinfo($targetFilePath, PATHINFO_EXTENSION));
        $allowedTypes = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

        if (!in_array($fileType, $allowedTypes)) {
            echo json_encode(['error' => 'Invalid file type. Only JPG, JPEG, PNG, GIF, WEBP are allowed.']);
            exit;
        }
        if ($uploadedFile['size'] > 5 * 1024 * 1024) { // 5MB limit
            echo json_encode(['error' => 'File is too large. Maximum 5MB allowed.']);
            exit;
        }
        if (!move_uploaded_file($uploadedFile['tmp_name'], $targetFilePath)) {
            echo json_encode(['error' => 'Failed to save uploaded file.']);
            exit;
        }

        // Construct public URL for the newly uploaded image
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
        $host = $_SERVER['HTTP_HOST'];
        $scriptDirPath = dirname($_SERVER['SCRIPT_NAME']);
        $appRootPath = dirname($scriptDirPath);
        if ($appRootPath === '/' || $appRootPath === '\\') $appRootPath = '';
        else $appRootPath = '/' . ltrim(trim($appRootPath, '/'),'/');
        
        $uploadUrlPath = UPLOADS_URL_PATH; // From config.php
        $imagePathSegment = rtrim($uploadUrlPath, '/') . '/' . $fileName;
        $absoluteImageUrlForOpenAI = $protocol . $host . $appRootPath . '/' . $imagePathSegment;
        $absoluteImageUrlForOpenAI = preg_replace('#(?<!:)/+#', '/', $absoluteImageUrlForOpenAI);

        // Store new image path and its public URL in session
        $_SESSION['current_uploaded_file_path'] = $targetFilePath;
        $_SESSION['current_uploaded_file_public_url'] = $absoluteImageUrlForOpenAI;

    } else { // This is a "load more" request, use the image URL from session
        if (!isset($_SESSION['current_uploaded_file_public_url'])) {
            echo json_encode(['error' => 'No image previously uploaded for this session to load more. Please upload an image first.']);
            exit;
        }
        $absoluteImageUrlForOpenAI = $_SESSION['current_uploaded_file_public_url'];
    }

    if (empty($_POST['directionText'])) {
        echo json_encode(['error' => 'Direction text is required.']);
        exit;
    }
    // Aspect ratio is now primarily for Replicate, but OpenAI might use it for context if we adapt the prompt.
    // For now, we'll pass it to OpenAIHandler, but it might not use it directly.
    // $aspectRatio = $_POST['aspectRatio'] ?? '1:1'; 

    $directionText = trim($_POST['directionText']);
    $previousPromptsArray = [];
    if ($isLoadMore && isset($_POST['previousPrompts'])) {
        $decodedPrompts = json_decode($_POST['previousPrompts'], true);
        if (is_array($decodedPrompts)) {
            $previousPromptsArray = $decodedPrompts;
        }
    }

    // --- 2. OpenAI Call for Prompts ---
    $openAIHandler = new OpenAIHandler(OPENAI_API_KEY);
    $prompts = $openAIHandler->generateAdPrompts($directionText, $absoluteImageUrlForOpenAI, $previousPromptsArray);

    if (isset($prompts['error'])) {
        // Note: We don't delete the uploaded file here on OpenAI error if it was a new upload,
        // because the user might want to retry without re-uploading immediately.
        // The file will be cleaned up on the *next* new upload.
        echo json_encode(['error' => 'OpenAI API error: ' . $prompts['error']]);
        exit;
    }
    if (empty($prompts) || !is_array($prompts)) {
        echo json_encode(['error' => 'OpenAI did not return valid prompts.']);
        exit;
    }

    // --- 3. Return Prompts and Image URL ---
    echo json_encode([
        'data' => [
            'prompts' => $prompts,
            'uploadedImagePublicUrl' => $absoluteImageUrlForOpenAI // Client will need this for individual image calls
        ]
    ]);
}


function handleGenerateSingleImage() {
    // --- 1. Input Validation ---
    if (empty($_POST['prompt'])) {
        echo json_encode(['error' => 'Prompt is required for image generation.']);
        exit;
    }
    if (empty($_POST['originalUploadedImagePublicUrl'])) {
        echo json_encode(['error' => 'Original uploaded image public URL is required.']);
        exit;
    }
    if (empty($_POST['aspectRatio'])) {
        echo json_encode(['error' => 'Aspect ratio is required.']);
        exit;
    }

    $prompt = $_POST['prompt'];
    $originalUploadedImagePublicUrl = $_POST['originalUploadedImagePublicUrl'];
    $aspectRatio = $_POST['aspectRatio'];

    // --- 2. Replicate Call for Image ---
    $replicateHandler = new ReplicateHandler(REPLICATE_API_TOKEN);
    
    // Get the selected model from the request, default to the configured default
    $selectedModel = $_POST['model'] ?? DEFAULT_REPLICATE_MODEL;
    
    // Set higher execution time for Replicate calls
    $originalExecutionTime = ini_get('max_execution_time');
    set_time_limit(300); // 5 minutes

    $imageData = $replicateHandler->generateImage($prompt, $aspectRatio, $originalUploadedImagePublicUrl, $selectedModel);
    
    set_time_limit($originalExecutionTime); // Restore

    if (isset($imageData['error'])) {
        echo json_encode(['error' => 'Replicate API error: ' . $imageData['error'], 'prompt' => $prompt]);
        exit;
    } elseif (isset($imageData['output_url'])) {
        echo json_encode(['data' => ['imageUrl' => $imageData['output_url'], 'prompt' => $prompt]]);
    } else {
        error_log("Replicate API unexpected response for prompt '{$prompt}': " . print_r($imageData, true));
        echo json_encode(['error' => 'Unexpected response from image generation service.', 'prompt' => $prompt]);
    }
}

?> 