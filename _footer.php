    <?php /* Closing tag for <main> is here, opened in _header.php */ ?>
    </main>

    <footer>
        <p>&copy; <?php echo date("Y"); ?> Kontext - All rights reserved.</p>
    </footer>

    <!-- Modal Structure (shared or page-specific as needed) -->
    <!-- Example for Ad Brainstormer - make sure this or similar exists if used by its JS -->
    <?php if ($currentPage === 'ad_brainstormer'): ?>
    <div id="imageModal" class="modal">
        <span class="close-button">&times;</span>
        <img class="modal-content" id="fullImage">
        <div id="caption"></div>
    </div>
    <?php endif; ?>
    <script src="js/app.js"></script>
<?php 
// Consolidated script loading
if ($currentPage === 'ad_brainstormer') {
    echo '<script src="js/ad_brainstormer.js"></script>';
} elseif ($currentPage === 'conversational_editing') {
    echo '<script src="js/conversational_editing.js"></script>';
} elseif ($currentPage === 'multi_image') {
    echo '<script src="js/multi_image.js"></script>';
}
?>
</body>
</html> 