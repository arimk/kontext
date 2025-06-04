<?php
// This file is included by index.php, which handles session and config.
?>
<div id="conversationalEditingTab" class="tab-page-content"> 
    <section id="conversational-editing-page">
        <div class="page-header-flex">
            <h2>Conversational Editing</h2>
            <button id="chatStartOverButton" class="btn-secondary">Start Over</button>
        </div>
        <p>Chat interface for iterative image editing.</p>
        <div class="chat-container">
            <div class="chat-messages" id="chatMessages">
                <?php /* Removed the placeholder comment from here */ ?>
            </div>
            <div class="chat-input-area">
                <input type="file" id="chatImageUpload" accept="image/*" style="display: none;" title="Upload an image to start or edit">
                <button id="chatAttachImageButton" title="Attach Image">
                    <svg width="20px" height="20px" stroke-width="1.5" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" color="currentColor">
                        <path d="M13 21H3.6a.6.6 0 01-.6-.6V3.6a.6.6 0 01.6-.6h16.8a.6.6 0 01.6.6V13" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                        <path d="M3 16l7-3 5.5 2.5M16 10a2 2 0 110-4 2 2 0 010 4zM16 19h6M19 16v6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                    </svg>
                </button>
                <textarea id="chatTextInput" placeholder="Type your message or describe your edit..." rows="1"></textarea>
                <select id="chatAspectRatio" name="chatAspectRatio" title="Aspect Ratio for new images">
                    <option value="match_input_image" selected>Match input (or 1:1)</option>
                    <option value="4:3">4:3</option>
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
                <button id="chatSendButton" class="btn-primary" title="Send">Send</button>
            </div>
        </div>
    </section>

    <!-- Chat Image Modal -->
    <div id="chatImageModal" class="image-modal">
        <span class="image-modal-close-button" id="chatModalCloseButton">&times;</span>
        <img class="image-modal-content" id="chatModalImage">
        <div id="chatModalCaption"></div>
    </div>
</div> 