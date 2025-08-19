<?php
// This file is included by index.php, which handles session and config.
?>
<div id="adBrainstormerTab" class="tab-page-content">
    <section id="ad-brainstormer-page">
        <h2>Create Your Ad Concept</h2>
        <form id="adForm">
            <div class="form-group">
                <label for="imageUpload">Upload Product Image:</label>
                <p class="field-description">Drag & drop an image here, or click to select</p>
                <div id="dropZone" class="drop-zone">
                    <span class="drop-zone-prompt">Drag & drop an image here, or click to select</span>
                    <input type="file" id="imageUpload" name="imageUpload" accept="image/*" style="display: none;">
                    <img id="imagePreview" src="#" alt="Image Preview" class="image-preview" style="display:none;">
                </div>
            </div>

            <div class="form-group">
                <label for="directionText">Direction/Indication:</label>
                <textarea id="directionText" name="directionText" rows="4" placeholder="nature, water, people, etc."></textarea>
            </div>

            <div class="form-group">
                <label for="aspectRatio">Aspect Ratio:</label>
                <select id="aspectRatio" name="aspectRatio">
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

            <div class="form-group">
                <label for="modelSelect">AI Model:</label>
                <select id="modelSelect" name="modelSelect">
                    <?php foreach ($REPLICATE_MODELS as $modelId => $modelConfig): ?>
                        <option value="<?php echo htmlspecialchars($modelId); ?>" <?php echo ($modelId === DEFAULT_REPLICATE_MODEL) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($modelConfig['display_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <button type="submit" id="createButton" class="btn-primary">Create Ads</button>
        </form>

        <div id="formLoadingIndicator" style="display: none;">
            <p>Generating ad prompts, please wait...</p>
        </div>

        <section id="resultsArea" style="display: none;">
            <h2>Generated Ad Ideas</h2>
            <div class="image-grid" id="imageGrid">
                
            </div>
            <button id="loadMoreButton" style="display: none;" class="btn-primary">Load More Ideas</button>
            <div id="gridLoadingIndicator" style="display: none;">
                <p>Loading more ad prompts, please wait...</p>
            </div>
        </section>
    </section>
</div> 