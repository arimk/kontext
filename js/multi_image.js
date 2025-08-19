document.addEventListener('DOMContentLoaded', () => {
    const multiImagePage = document.getElementById('multi-image-page');
    if (!multiImagePage) return; // Only run on the multi-image page

    const multiImageForm = document.getElementById('multiImageForm');
    const dropZonesContainer = document.getElementById('multiImageDropZonesContainer');
    const directionTextMulti = document.getElementById('directionTextMulti');
    const aspectRatioMulti = document.getElementById('aspectRatioMulti');
    const modelSelectMulti = document.getElementById('modelSelectMulti');
    const createMultiImageButton = document.getElementById('createMultiImageButton');
    const formLoadingIndicatorMulti = document.getElementById('formLoadingIndicatorMulti');
    const resultsAreaMulti = document.getElementById('resultsAreaMulti');
    const imageGridMulti = document.getElementById('imageGridMulti');

    const MAX_IMAGES = 4;
    let uploadedFiles = new Array(MAX_IMAGES).fill(null);

    // Initialize drop zones and file inputs
    for (let i = 1; i <= MAX_IMAGES; i++) {
        const dropZone = document.getElementById(`dropZoneMulti_${i}`);
        const imageUpload = document.getElementById(`imageUploadMulti_${i}`);
        const imagePreview = document.getElementById(`imagePreviewMulti_${i}`);
        const clearButton = document.getElementById(`clearImageMulti_${i}`);
        const promptSpan = dropZone.querySelector('.drop-zone-prompt');

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
                handleFile(files[0], i, imagePreview, promptSpan, clearButton);
            }
        });

        dropZone.addEventListener('click', () => {
            imageUpload.click();
        });

        imageUpload.addEventListener('change', (e) => {
            if (e.target.files.length > 0) {
                handleFile(e.target.files[0], i, imagePreview, promptSpan, clearButton);
            }
        });

        clearButton.addEventListener('click', () => {
            uploadedFiles[i - 1] = null;
            imagePreview.src = '#';
            imagePreview.style.display = 'none';
            promptSpan.style.display = 'block';
            clearButton.style.display = 'none';
            imageUpload.value = ''; // Reset file input
        });
    }

    function handleFile(file, slotIndex, previewElement, promptSpan, clearButton) {
        if (!file.type.startsWith('image/')) {
            alert('Please upload an image file.');
            return;
        }
        uploadedFiles[slotIndex - 1] = file;
        previewElement.src = URL.createObjectURL(file);
        previewElement.style.display = 'block';
        promptSpan.style.display = 'none';
        clearButton.style.display = 'inline-block';
    }

    multiImageForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const activeFiles = uploadedFiles.filter(f => f !== null);
        if (activeFiles.length === 0) {
            alert('Please upload at least one image.');
            return;
        }

        createMultiImageButton.disabled = true;
        formLoadingIndicatorMulti.style.display = 'block';
        resultsAreaMulti.style.display = 'none';
        imageGridMulti.innerHTML = '';

        const formData = new FormData();
        formData.append('action', 'combine_images'); // New backend action
        activeFiles.forEach((file, index) => {
            formData.append(`imageUpload_${index + 1}`, file);
        });
        formData.append('numImages', activeFiles.length.toString());
        formData.append('directionText', directionTextMulti.value);
        formData.append('aspectRatio', aspectRatioMulti.value);
        formData.append('model', modelSelectMulti.value);

        try {
            const response = await fetch('backend/api.php', {
                method: 'POST',
                body: formData
            });

            // Check if the response is OK (status in the range 200-299)
            if (!response.ok) {
                let errorMsg = `HTTP error! status: ${response.status}`;
                try {
                    // Try to parse error message from JSON response
                    const errorData = await response.json();
                    errorMsg = errorData.error || errorMsg;
                } catch (e) {
                    // If response is not JSON, use the raw text
                    const textError = await response.text();
                    errorMsg = textError || errorMsg;
                }
                throw new Error(errorMsg);
            }

            const result = await response.json();

            if (result.error) {
                throw new Error(result.error);
            }
            
            // Expecting an array of URLs now
            if (result.success && result.data && Array.isArray(result.data.imageUrls)) {
                displayGeneratedVariations(result.data.imageUrls, result.data.prompt, result.data.individualErrors || []);
            } else {
                throw new Error('Invalid response from server: Expected an array of image URLs.');
            }

        } catch (error) {
            console.error('Error in multi-image generation process:', error);
            alert(`Error: ${error.message}`);
            imageGridMulti.innerHTML = `<p>Failed to generate images. ${error.message}</p>`;
            resultsAreaMulti.style.display = 'block';
        } finally {
            createMultiImageButton.disabled = false;
            formLoadingIndicatorMulti.style.display = 'none';
        }
    });

    function displayGeneratedVariations(imageUrls, basePrompt, individualErrors) {
        imageGridMulti.innerHTML = ''; // Clear previous or error
        resultsAreaMulti.style.display = 'block';

        if (imageUrls.length === 0) {
            imageGridMulti.innerHTML = '<p>No images were generated.</p>';
            return;
        }

        imageUrls.forEach((imageUrl, index) => {
            const itemDiv = document.createElement('div');
            itemDiv.classList.add('image-item'); // Reuse ad brainstormer style

            // Display the base prompt or a variation-specific prompt if we had one
            const promptP = document.createElement('p');
            promptP.classList.add('prompt-text');
            promptP.textContent = `${basePrompt || 'Variation'} #${index + 1}`;
            if (individualErrors[index]) {
                promptP.textContent += ` (Error: ${individualErrors[index]})`;
                itemDiv.classList.add('image-item-failed'); // Add a class for visual indication
            }
            itemDiv.appendChild(promptP);

            const imageContainer = document.createElement('div');
            imageContainer.classList.add('image-container');

            if (imageUrl) {
                const img = document.createElement('img');
                img.src = imageUrl;
                img.alt = `${basePrompt || 'Variation'} #${index + 1}`;
                img.addEventListener('click', () => openModal(imageUrl, `${basePrompt || 'Variation'} #${index + 1}`)); 
                imageContainer.appendChild(img);
            } else {
                // Display placeholder or error text if imageUrl is null (generation failed for this item)
                imageContainer.innerHTML = `<p class="error-text">Generation failed for variation #${index + 1}.</p>`;
                 if(individualErrors[index]) {
                    imageContainer.innerHTML += `<p class="error-text-detail">${individualErrors[index]}</p>`;
                }
            }
            itemDiv.appendChild(imageContainer);
            imageGridMulti.appendChild(itemDiv);
        });
    }

    // Ensure you have modal HTML elements in pages/multi_image.php if you enable this.
    
    const modalMulti = document.getElementById('multiImageModal'); 
    const modalImgMulti = document.getElementById('multiFullImage');
    const captionTextMulti = document.getElementById('multiCaption');
    // Use a more specific selector if you added a unique class, or ensure only one .close-button is in this modal scope
    const closeButtonMulti = modalMulti ? modalMulti.querySelector('.close-button') : null; 

    function openModal(src, caption) {
        if(modalMulti && modalImgMulti && captionTextMulti) {
            modalMulti.style.display = "block"; // Or "flex" if using flex for centering
            modalImgMulti.src = src;
            captionTextMulti.textContent = caption;
        }
    }

    if(closeButtonMulti) {
        closeButtonMulti.onclick = function() {
            if(modalMulti) modalMulti.style.display = "none";
        }
    }

    window.addEventListener('click', function(event) {
        if (event.target == modalMulti) {
            if(modalMulti) modalMulti.style.display = "none";
        }
    });
    
}); 