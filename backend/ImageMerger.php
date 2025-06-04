<?php

class ImageMerger {

    /**
     * Loads an image from a file path, attempting to detect its type.
     *
     * @param string $filePath Path to the image file.
     * @return GdImage|string A GD image resource on success, or an error message string on failure.
     */
    private function loadImage(string $filePath) {
        if (!file_exists($filePath) || !is_readable($filePath)) {
            return "ImageMerger Error: File not found or not readable: " . $filePath;
        }

        $imageInfo = getimagesize($filePath);
        if (!$imageInfo) {
            return "ImageMerger Error: Could not get image size for: " . $filePath;
        }

        $mime = $imageInfo['mime'];

        switch ($mime) {
            case 'image/jpeg':
                $img = @imagecreatefromjpeg($filePath); // Suppress E_WARNING on fail
                if (!$img) return "ImageMerger Error: Failed to load JPEG: " . $filePath;
                return $img;
            case 'image/png':
                $image = @imagecreatefrompng($filePath);
                if (!$image) return "ImageMerger Error: Failed to load PNG: " . $filePath;
                imagealphablending($image, true);
                imagesavealpha($image, true);
                return $image;
            case 'image/gif':
                $img = @imagecreatefromgif($filePath);
                if (!$img) return "ImageMerger Error: Failed to load GIF: " . $filePath;
                return $img;
            case 'image/webp':
                // Check if WebP support is available in GD
                if (!function_exists('imagecreatefromwebp')) {
                    return "ImageMerger Error: WebP support is not enabled in this PHP GD configuration for file: " . $filePath;
                }
                $img = @imagecreatefromwebp($filePath);
                if (!$img) return "ImageMerger Error: Failed to load WebP: " . $filePath;
                // For WebP, alpha blending might also be relevant if it has transparency
                imagealphablending($img, true);
                imagesavealpha($img, true);
                return $img;
            default:
                return "ImageMerger Error: Unsupported image type: " . $mime . " for file: " . $filePath;
        }
    }

    /**
     * Merges multiple images into a single image.
     * The output aspect ratio string is now IGNORED for the internal canvas creation.
     * It will be used by Replicate externally.
     *
     * @param array $sourceImagePaths Array of paths to the source images.
     * @param string $outputDir The directory to save the merged image.
     * @param int $maxOutputDimension Maximum width or height for the output image (approximate for the canvas).
     * @return string|array Path to the merged image on success, or an array of error messages on failure.
     */
    public function merge(array $sourceImagePaths, string $outputDir, int $maxOutputDimension = 1024) {
        $numImages = count($sourceImagePaths);
        if ($numImages === 0 || $numImages > 4) {
            return ["ImageMerger Error: Invalid number of images (must be 1-4)."];
        }
        $errors = [];

        // Load all images first to get their dimensions and handle loading errors
        $loadedImages = [];
        foreach ($sourceImagePaths as $index => $imagePath) {
            $sourceImageOrError = $this->loadImage($imagePath);
            if (is_string($sourceImageOrError)) {
                $errors[] = $sourceImageOrError . " (Slot " . ($index + 1) . ")";
                $loadedImages[] = null; // Add null placeholder
            } else {
                $loadedImages[] = $sourceImageOrError;
            }
        }

        // If critical errors occurred (e.g., no images loaded), decide if we can even create a canvas
        $validLoadedImages = array_filter($loadedImages);
        if (empty($validLoadedImages)) {
            // No images loaded at all, return collected errors
            return !empty($errors) ? $errors : ["ImageMerger Error: No valid images could be loaded."];
        }

        // --- Dynamically determine canvas size based on content, especially for 2 images ---
        $canvasWidth = $maxOutputDimension; // Default for 1, 3, 4 images (can be refined)
        $canvasHeight = $maxOutputDimension; // Default for 1, 3, 4 images (can be refined)

        if ($numImages === 2 && $loadedImages[0] && $loadedImages[1]) {
            $img1 = $loadedImages[0];
            $img2 = $loadedImages[1];

            $h1_orig = imagesy($img1);
            $w1_orig = imagesx($img1);
            $h2_orig = imagesy($img2);
            $w2_orig = imagesx($img2);

            // Determine common target height: strictly the height of the shorter image.
            $commonTargetHeight = min($h1_orig, $h2_orig);
            // Ensure a minimum height to prevent division by zero if an image has 0 height for some reason.
            $commonTargetHeight = max(1, $commonTargetHeight);
            
            $w1_scaled = ($h1_orig > 0) ? (int)round($w1_orig * ($commonTargetHeight / $h1_orig)) : 0;
            // $h1_scaled will be $commonTargetHeight

            $w2_scaled = ($h2_orig > 0) ? (int)round($w2_orig * ($commonTargetHeight / $h2_orig)) : 0;
            // $h2_scaled will be $commonTargetHeight
            
            // Canvas takes combined width and common height
            $canvasWidth = $w1_scaled + $w2_scaled;
            $canvasHeight = $commonTargetHeight;

        } else if ($numImages === 1 && $loadedImages[0]) {
            $img1 = $loadedImages[0];
            $h1_orig = imagesy($img1);
            $w1_orig = imagesx($img1);
            // Scale to fit within $maxOutputDimension box, maintaining aspect ratio
            if ($w1_orig > $h1_orig) { // Landscape or square
                $canvasWidth = $maxOutputDimension;
                $canvasHeight = ($w1_orig > 0) ? (int)round($h1_orig * ($maxOutputDimension / $w1_orig)) : $maxOutputDimension;
            } else { // Portrait
                $canvasHeight = $maxOutputDimension;
                $canvasWidth = ($h1_orig > 0) ? (int)round($w1_orig * ($maxOutputDimension / $h1_orig)) : $maxOutputDimension;
            }
        } 
        // For 3 & 4 images, the $maxOutputDimension x $maxOutputDimension canvas with 2x2 cell logic is a reasonable default to simplify.
        // More adaptive logic for 3 & 4 can be very complex.

        $canvasWidth = max(1, $canvasWidth);
        $canvasHeight = max(1, $canvasHeight);

        $mergedImage = imagecreatetruecolor($canvasWidth, $canvasHeight);
        if (!$mergedImage) {
            $errors[] = "ImageMerger Error: Failed to create true color image canvas.";
            foreach ($loadedImages as $img) { if ($img) imagedestroy($img); }
            return $errors;
        }

        $backgroundColor = imagecolorallocate($mergedImage, 230, 230, 230); // Light grey for any uncovered areas
        imagefill($mergedImage, 0, 0, $backgroundColor);
        
        // --- Direct drawing for 1 or 2 successfully loaded images for tightest fit ---
        if ($numImages === 1 && $loadedImages[0]) {
            $img1 = $loadedImages[0];
            // Canvas is already sized to this single image
            imagecopyresampled($mergedImage, $img1, 0, 0, 0, 0, $canvasWidth, $canvasHeight, imagesx($img1), imagesy($img1));
        } elseif ($numImages === 2 && $loadedImages[0] && $loadedImages[1]) {
            $img1 = $loadedImages[0];
            $img2 = $loadedImages[1];
            // Canvas is already sized: $canvasWidth = $w1_scaled + $w2_scaled, $canvasHeight = $commonTargetHeight (from canvas sizing block)
            // $w1_scaled and $w2_scaled were calculated during canvas setup based on $commonTargetHeight
            // We need to re-access/re-calculate them here if they weren't stored as member vars.
            // For clarity, let's re-calculate based on the known $canvasHeight (which is commonTargetHeight)
            
            $h1_orig_temp = imagesy($img1);
            $w1_orig_temp = imagesx($img1);
            $h2_orig_temp = imagesy($img2);
            $w2_orig_temp = imagesx($img2);

            $final_w1 = ($h1_orig_temp > 0) ? (int)round($w1_orig_temp * ($canvasHeight / $h1_orig_temp)) : 0;
            $final_h1 = $canvasHeight;

            $final_w2 = ($h2_orig_temp > 0) ? (int)round($w2_orig_temp * ($canvasHeight / $h2_orig_temp)) : 0;
            $final_h2 = $canvasHeight;

            imagecopyresampled($mergedImage, $img1, 0, 0, 0, 0, $final_w1, $final_h1, $w1_orig_temp, $h1_orig_temp);
            imagecopyresampled($mergedImage, $img2, $final_w1, 0, 0, 0, $final_w2, $final_h2, $w2_orig_temp, $h2_orig_temp);
        
        } else { 
            // --- Fallback to generic cell-based logic for 3, 4 images, or if 1,2 image case had load errors ---
            $positions = []; // This array will now store GdImage objects directly if successfully loaded
            $cellWidth = (int)floor($canvasWidth / 2);
            $cellHeight = (int)floor($canvasHeight / 2);
            $layoutMap = [];

            // Define base cells for a 2x2 grid (some might not be used or might be overridden)
            if ($numImages >= 1 || $errors) $layoutMap[0] = ['x' => 0, 'y' => 0, 'w' => $cellWidth, 'h' => $cellHeight];
            if ($numImages >= 2 || $errors) $layoutMap[1] = ['x' => $cellWidth, 'y' => 0, 'w' => $canvasWidth - $cellWidth, 'h' => $cellHeight];
            if ($numImages >= 3 || $errors) $layoutMap[2] = ['x' => 0, 'y' => $cellHeight, 'w' => $cellWidth, 'h' => $canvasHeight - $cellHeight];
            if ($numImages == 4 || $errors) $layoutMap[3] = ['x' => $cellWidth, 'y' => $cellHeight, 'w' => $canvasWidth - $cellWidth, 'h' => $canvasHeight - $cellHeight];
            
            // Special handling for 3 images to make the third one span bottom or be larger
            // This only applies if we actually have 3 successfully loaded images and are in this fallback path.
            // However, the dynamic sizing for 1 and 2 images should be preferred.
            // This fallback primarily serves 3, 4, or error cases for 1,2.
            if (count($validLoadedImages) === 3 && $numImages === 3) { // Ensure we are in 3 image mode
                 $layoutMap[2] = ['x' => 0, 'y' => $cellHeight, 'w' => $canvasWidth, 'h' => $canvasHeight - $cellHeight];
            }

            foreach ($loadedImages as $index => $imgObject) {
                if (isset($layoutMap[$index])) {
                    if ($imgObject) {
                        $positions[] = array_merge($layoutMap[$index], ['img' => $imgObject]);
                    } else {
                        // Draw error placeholder for this slot using layoutMap cell
                        $currentCell = $layoutMap[$index];
                        $errorBgColor = imagecolorallocate($mergedImage, 180, 180, 180);
                        imagefilledrectangle($mergedImage, $currentCell['x'], $currentCell['y'], $currentCell['x'] + $currentCell['w'] - 1, $currentCell['y'] + $currentCell['h'] - 1, $errorBgColor);
                        $errorTextColor = imagecolorallocate($mergedImage, 0, 0, 0);
                        imagestring($mergedImage, 3, $currentCell['x'] + 5, $currentCell['y'] + 5, "Err: Slot ".($index+1), $errorTextColor);
                    }
                }
            }
            
            // This loop is now only for the fallback cases (3, 4 images, or 1,2 with errors)
            foreach ($positions as $pos) {
                $sourceImage = $pos['img']; // This is GdImage object
                $currentCell = $pos;
                $srcWidth = imagesx($sourceImage);
                $srcHeight = imagesy($sourceImage);

                $targetCellWidth = $currentCell['w'];
                $targetCellHeight = $currentCell['h'];

                $scale = min($targetCellWidth / $srcWidth, $targetCellHeight / $srcHeight);
                $renderWidth = (int)round($srcWidth * $scale);
                $renderHeight = (int)round($srcHeight * $scale);

                $dstX_in_cell = (int)round(($targetCellWidth - $renderWidth) / 2);
                $dstY_in_cell = (int)round(($targetCellHeight - $renderHeight) / 2);
                
                $finalDstX = $currentCell['x'] + $dstX_in_cell;
                $finalDstY = $currentCell['y'] + $dstY_in_cell;
                
                imagecopyresampled($mergedImage, $sourceImage, $finalDstX, $finalDstY, 0, 0, $renderWidth, $renderHeight, $srcWidth, $srcHeight);
            }
        }
        
        // Destroy all loaded GdImage objects
        foreach ($loadedImages as $img) { if ($img) imagedestroy($img); }

        if (!empty($errors)) {
            // Even if there were loading errors, we might have a debug image with placeholders
            // We save it and return the errors.
        }

        $outputFilename = uniqid('merged-debug-', true) . '.jpg';
        $outputPath = rtrim($outputDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $outputFilename;

        if (!imagejpeg($mergedImage, $outputPath, 90)) {
            imagedestroy($mergedImage);
            $errors[] = "ImageMerger Error: Failed to save merged image to " . $outputPath;
            return $errors; // Return all errors including this save error
        }
        imagedestroy($mergedImage);

        return !empty($errors) ? $errors : $outputPath; // Return path if no errors, else array of errors
    }
} 