document.addEventListener('DOMContentLoaded', () => {
    const adForm = document.getElementById('adForm');
    const imageUpload = document.getElementById('imageUpload');
    const dropZone = document.getElementById('dropZone');
    const imagePreview = document.getElementById('imagePreview');
    const directionText = document.getElementById('directionText');
    const aspectRatio = document.getElementById('aspectRatio');
    const createButton = document.getElementById('createButton');
    const formLoadingIndicator = document.getElementById('formLoadingIndicator');
    const gridLoadingIndicator = document.getElementById('gridLoadingIndicator');
    const imageGrid = document.getElementById('imageGrid');
    const loadMoreButton = document.getElementById('loadMoreButton');
    const resultsArea = document.getElementById('resultsArea');

    // Modal elements
    const modal = document.getElementById('imageModal');
    const modalImg = document.getElementById('fullImage');
    const captionText = document.getElementById('caption');
    const closeButton = document.querySelector('.close-button');

    let uploadedFile = null;
    let previousPrompts = [];
    let currentUploadedImagePublicUrl = null;

    // Drag and Drop
    dropZone.addEventListener('dragover', (e) => {
        e.preventDefault();
        dropZone.classList.add('dragover');
    });

    dropZone.addEventListener('dragleave', () => {
        dropZone.classList.remove('dragover');
    });

    dropZone.addEventListener('drop', (e) => {
        e.preventDefault();
        dropZone.classList.remove('dragover');
        const files = e.dataTransfer.files;
        if (files.length > 0) {
            handleFile(files[0]);
        }
    });

    dropZone.addEventListener('click', () => {
        imageUpload.click();
    });

    imageUpload.addEventListener('change', (e) => {
        if (e.target.files.length > 0) {
            handleFile(e.target.files[0]);
        }
    });

    function handleFile(file) {
        if (!file.type.startsWith('image/')) {
            alert('Please upload an image file.');
            return;
        }
        uploadedFile = file;
        imagePreview.src = URL.createObjectURL(file);
        imagePreview.style.display = 'block';
        dropZone.querySelector('.drop-zone-prompt').style.display = 'none';

        // Reset state for a new image
        imageGrid.innerHTML = '';
        previousPrompts = [];
        currentUploadedImagePublicUrl = null;
        resultsArea.style.display = 'none';
        loadMoreButton.style.display = 'none';
    }

    adForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (!uploadedFile) {
            alert('Please upload an image.');
            return;
        }
        if (!directionText.value.trim()) {
            alert('Please provide a direction/indication.');
            return;
        }
        // For a new "Create" submission
        imageGrid.innerHTML = ''; // Clear previous visual results
        previousPrompts = [];     // Reset prompt history for OpenAI
        currentUploadedImagePublicUrl = null; // Will be set by the backend
        
        await handlePromptGeneration(false);
    });

    loadMoreButton.addEventListener('click', async () => {
        // uploadedFile should still be set from the initial upload
        // currentUploadedImagePublicUrl should also be set
        if (!uploadedFile || !currentUploadedImagePublicUrl) {
            alert('Initial image and successful prompt generation are required to load more.');
            return;
        }
        await handlePromptGeneration(true);
    });

    async function handlePromptGeneration(isLoadMore) {
        createButton.disabled = true;
        loadMoreButton.style.display = 'none';

        if (isLoadMore) {
            gridLoadingIndicator.style.display = 'block';
            formLoadingIndicator.style.display = 'none';
        } else {
            formLoadingIndicator.style.display = 'block';
            gridLoadingIndicator.style.display = 'none';
        }

        const formData = new FormData();
        // Only send the image file if it's NOT a "load more" request for prompts
        // For "load more", the backend uses the image URL from the session.
        if (!isLoadMore) {
            formData.append('imageUpload', uploadedFile);
        }
        formData.append('directionText', directionText.value);
        // Aspect ratio is sent with each image generation request now,
        // but we can keep sending it here if OpenAIHandler might use it for context.
        // formData.append('aspectRatio', aspectRatio.value); 
        formData.append('action', 'generate_ads_prompts');
        
        if (isLoadMore) {
            formData.append('loadMore', 'true');
            formData.append('previousPrompts', JSON.stringify(previousPrompts));
        }

        try {
            const response = await fetch('backend/api.php', {
                method: 'POST',
                body: formData
            });

            if (!response.ok) {
                const errorData = await response.json().catch(() => ({ error: 'Server error with no JSON response.' }));
                throw new Error(errorData.error || `HTTP error! status: ${response.status}`);
            }

            const results = await response.json();

            if (results.error) {
                alert(`Error fetching prompts: ${results.error}`);
            } else if (results.data && results.data.prompts && results.data.prompts.length > 0) {
                currentUploadedImagePublicUrl = results.data.uploadedImagePublicUrl; // Store this crucial URL
                
                const newPrompts = results.data.prompts;
                newPrompts.forEach(p => { // Add to global history
                    if (!previousPrompts.includes(p)) previousPrompts.push(p);
                });

                resultsArea.style.display = 'block';
                displayPromptPlaceholdersAndFetchImages(newPrompts);

                if (newPrompts.length === 4) { // OpenAI returned a full batch
                    loadMoreButton.style.display = 'block';
                } else {
                    loadMoreButton.style.display = 'none'; // No more prompts expected
                }
            } else {
                if (!isLoadMore) imageGrid.innerHTML = '<p>No prompts generated.</p>';
                loadMoreButton.style.display = 'none';
            }

        } catch (error) {
            console.error('Error fetching prompts:', error);
            alert(`An error occurred while fetching prompts: ${error.message}`);
            if (!isLoadMore) imageGrid.innerHTML = `<p>Failed to fetch prompts. ${error.message}</p>`;
        } finally {
            formLoadingIndicator.style.display = 'none';
            gridLoadingIndicator.style.display = 'none';
            createButton.disabled = false;
            // Load more button visibility is handled based on prompt results
        }
    }

    function displayPromptPlaceholdersAndFetchImages(promptsArray) {
        if (!currentUploadedImagePublicUrl) {
            console.error("Cannot fetch images without currentUploadedImagePublicUrl");
            alert("A critical error occurred: The uploaded image URL is missing.");
            return;
        }
        promptsArray.forEach((promptText, index) => {
            const uniqueItemId = `prompt-item-${Date.now()}-${index}`; // Unique ID for each item
            
            const itemDiv = document.createElement('div');
            itemDiv.classList.add('image-item');
            itemDiv.id = uniqueItemId;

            const promptP = document.createElement('p');
            promptP.classList.add('prompt-text');
            promptP.title = promptText;
            promptP.textContent = promptText;

            const imageContainer = document.createElement('div');
            imageContainer.classList.add('image-container'); // For styling the image or loader
            imageContainer.innerHTML = `<div class="individual-loader"></div>`; // Simple loader

            itemDiv.appendChild(promptP);
            itemDiv.appendChild(imageContainer);
            imageGrid.appendChild(itemDiv);

            // Fetch the image for this specific prompt
            fetchSingleImage(promptText, uniqueItemId, aspectRatio.value);
        });
    }

    async function fetchSingleImage(promptText, itemDivId, currentAspectRatio) {
        const itemDiv = document.getElementById(itemDivId);
        const imageContainer = itemDiv.querySelector('.image-container');
        if (!imageContainer) {
            console.error(`Image container not found for ${itemDivId}`);
            return;
        }
        imageContainer.innerHTML = `<div class="individual-loader"></div>`; // Show loader

        const formData = new FormData();
        formData.append('action', 'generate_single_image');
        formData.append('prompt', promptText);
        formData.append('originalUploadedImagePublicUrl', currentUploadedImagePublicUrl);
        formData.append('aspectRatio', currentAspectRatio);

        try {
            const response = await fetch('backend/api.php', {
                method: 'POST',
                body: formData
            });

            if (!response.ok) {
                const errorData = await response.json().catch(() => ({ error: 'Server error with no JSON response.' }));
                throw new Error(errorData.error || `HTTP error! status: ${response.status}`);
            }
            const result = await response.json();

            if (result.error) {
                throw new Error(result.error); // Let catch block handle it
            } else if (result.data && result.data.imageUrl) {
                imageContainer.innerHTML = ''; // Clear loader
                const img = document.createElement('img');
                img.src = result.data.imageUrl;
                img.alt = promptText;
                img.addEventListener('click', () => openModal(result.data.imageUrl, promptText));
                imageContainer.appendChild(img);
                itemDiv.classList.remove('image-item-failed');
            } else {
                throw new Error('Unexpected response from image generation service.');
            }
        } catch (error) {
            console.error(`Error generating image for prompt "${promptText}":`, error);
            imageContainer.innerHTML = ''; // Clear loader
            const errorP = document.createElement('p');
            errorP.classList.add('error-text');
            errorP.textContent = `Failed: ${error.message}`;
            
            const retryButton = document.createElement('button');
            retryButton.textContent = 'Retry';
            retryButton.classList.add('retry-button');
            retryButton.onclick = () => fetchSingleImage(promptText, itemDivId, currentAspectRatio); // Pass current aspect ratio

            imageContainer.appendChild(errorP);
            imageContainer.appendChild(retryButton);
            itemDiv.classList.add('image-item-failed');
        }
    }

    // Modal functions
    function openModal(src, caption) {
        modal.style.display = "block";
        modalImg.src = src;
        captionText.textContent = caption;
    }

    closeButton.onclick = function() {
        modal.style.display = "none";
    }

    window.onclick = function(event) {
        if (event.target == modal) {
            modal.style.display = "none";
        }
    }
}); 