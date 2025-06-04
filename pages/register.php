<h2>Register</h2>
<?php if (!empty($error_message)): ?>
    <p class="error-message"><?php echo $error_message; ?></p>
<?php endif; ?>
<?php if (!empty($success_message)): ?>
    <p class="success-message"><?php echo $success_message; ?></p>
<?php endif; ?>
<form method="POST" action="index.php?page=register">
    <div class="form-group">
        <label for="username">Username:</label>
        <input type="text" id="username" name="username" required value="<?php echo htmlspecialchars($username ?? ''); ?>">
    </div>
    <div class="form-group">
        <label for="email">Email:</label>
        <input type="email" id="email" name="email" required value="<?php echo htmlspecialchars($email ?? ''); ?>">
    </div>
    <div class="form-group">
        <label for="password">Password:</label>
        <input type="password" id="password" name="password" required>
    </div>
    <div class="form-group">
        <label for="confirm_password">Confirm Password:</label>
        <input type="password" id="confirm_password" name="confirm_password" required>
    </div>
    <button type="submit" class="btn-primary">Register</button>
</form>
<p>Already have an account? <a href="index.php?page=login">Login here</a></p>
</div> 