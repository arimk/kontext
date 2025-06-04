<h2>Login</h2>
<?php if (!empty($error_message)): ?>
    <p class="error-message"><?php echo $error_message; ?></p>
<?php endif; ?>
<form method="POST" action="index.php?page=login">
    <div class="form-group">
        <label for="usernameOrEmail">Username or Email:</label>
        <input type="text" id="usernameOrEmail" name="usernameOrEmail" required>
    </div>
    <div class="form-group">
        <label for="password">Password:</label>
        <input type="password" id="password" name="password" required>
    </div>
    <button type="submit" class="btn-primary">Login</button>
</form>
<p>Don't have an account? <a href="index.php?page=register">Register here</a></p>
</div> 