<?php
// login_module.php=
    include 'header.html'; 

// Start session if not already started.
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in.
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    // Process login form if submitted.
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['username']) && isset($_POST['password'])) {
        $valid_username = 'admin';
        $valid_password = 'password123';

        if ($_POST['username'] === $valid_username && $_POST['password'] === $valid_password) {
            $_SESSION['loggedin'] = true;
            header("Location: " . $_SERVER['REQUEST_URI']);
            exit;
        } else {
            $error = "Invalid credentials.";
        }
    }

    // Include your site's header and footer from the includes folder.
    include 'includes/header.html'; 
    ?>
    
<!-- Centered Card Wrapper -->
    <div class="login-page-wrapper">
        <div class="login-card">
            <h2>Access Restricted</h2>
            <?php if (isset($error)) { echo '<p class="error-text">' . htmlspecialchars($error) . '</p>'; } ?>
            
            <form method="post" action="">
                <input type="text" name="username" placeholder="Username" required>
                <input type="password" name="password" placeholder="Password" required>
                <button type="submit">Sign In</button>
            </form>
        </div>
    </div>

    <?php 
    include 'footer.html'; 
    exit; 
}
?>