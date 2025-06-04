<?php
// This file is included by index.php, which handles session and config.
?>
<div id="multiImageTab" class="tab-page-content">
    <section id="multi-image-page">
        <h2>Combine Multiple Images</h2>
        <form id="multiImageForm">
            <div class="form-group">
                <label>Upload Images (up to 4):</label>
                <p class="field-description">Drag & drop images or click to select. The images will be combined.</p>
                <div id="multiImageDropZonesContainer" class="multi-image-dropzones">
                    <?php for ($i = 1; $i <= 4; $i++): ?>
                        <div class="multi-drop-zone-wrapper">
                            <div id="dropZoneMulti_<?php echo $i; ?>" class="drop-zone multi-drop-zone" data-slot="<?php echo $i; ?>">
                                <span class="drop-zone-prompt">Slot <?php echo $i; ?></span>
                                <input type="file" id="imageUploadMulti_<?php echo $i; ?>" name="imageUploadMulti_<?php echo $i; ?>" accept="image/*" style="display: none;">
                                <img id="imagePreviewMulti_<?php echo $i; ?>" src="#" alt="Preview <?php echo $i; ?>" class="image-preview multi-image-preview" style="display:none;">
                            </div>
                            <button type="button" id="clearImageMulti_<?php echo $i; ?>" class="btn-secondary btn-small clear-image-btn" data-slot="<?php echo $i; ?>" style="display:none;">Clear</button>
                        </div>
                    <?php endfor; ?>
                </div>
            </div>

            <div class="form-group">
                <label for="directionTextMulti">Prompt:</label>
                <textarea id="directionTextMulti" name="directionTextMulti" rows="3" placeholder="Describe what you want to create..."></textarea>
            </div>

            <div class="form-group">
                <label for="aspectRatioMulti">Aspect Ratio (for Replicate output):</label>
                <select id="aspectRatioMulti" name="aspectRatioMulti">
                    <option value="4:3" selected>4:3 (Default)</option>
                    <option value="1:1">1:1</option>
                    <option value="16:9">16:9</option>
                    <option value="9:16">9:16</option>
                    <option value="3:4">3:4</option>
                    <option value="3:2">3:2</option>
                    <option value="2:3">2:3</option>
                    <option value="4:5">4:5</option>
                    <option value="5:4">5:4</option>
                    <option value="21:9">21:9</option>
                    <option value="9:21">9:21</option>
                    <option value="2:1">2:1</option>
                    <option value="1:2">1:2</option>
                </select>
            </div>

            <button type="submit" id="createMultiImageButton" class="btn-primary">Generate with Combined Images</button>
        </form>

        <div id="formLoadingIndicatorMulti" class="loading-indicator-container" style="display: none;">
            <p>Processing images and generating result, please wait...</p>
        </div>

        <section id="resultsAreaMulti" style="display: none;">
            <h2>Combined Image Result</h2>
            <div class="image-grid" id="imageGridMulti">
                <!-- Combined image will appear here -->
            </div>
             <div id="gridLoadingIndicatorMulti" class="loading-indicator-container" style="display: none;">
                <p>Loading result...</p>
            </div>
        </section>
    </section>
</div> 

<!-- Modal for Multi Image Page -->
<div id="multiImageModal" class="modal"> <!-- Unique ID -->
    <span class="close-button multi-close-button">&times;</span> <!-- Potentially unique class if needed -->
    <img class="modal-content" id="multiFullImage"> <!-- Unique ID -->
    <div id="multiCaption"></div> <!-- Unique ID -->
</div> 