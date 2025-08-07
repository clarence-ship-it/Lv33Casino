<?php
session_start();
header('Content-Type: application/json');

// Database configuration - update these with your actual database credentials
$host = 'localhost';
$dbname = 'news_system';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Sign up functionality
    if (isset($_POST['action']) && $_POST['action'] === 'signup') {
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $password = $_POST['password'];
        
        // Validate input
        if (empty($username) || empty($email) || empty($password)) {
            echo json_encode(['success' => false, 'message' => 'All fields are required']);
            exit;
        }
        
        // Check if username already exists
        $stmt = $pdo->prepare("SELECT id FROM admin_users WHERE username = ?");
        $stmt->execute([$username]);
        
        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => false, 'message' => 'Username already exists']);
            exit;
        }
        
        // Check if email already exists
        $stmt = $pdo->prepare("SELECT id FROM admin_users WHERE email = ?");
        $stmt->execute([$email]);
        
        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => false, 'message' => 'Email already registered']);
            exit;
        }
        
        // Hash password
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        // Insert new user
        try {
            $stmt = $pdo->prepare("INSERT INTO admin_users (username, email, password, created_at) VALUES (?, ?, ?, NOW())");
            $stmt->execute([$username, $email, $hashedPassword]);
            
            echo json_encode(['success' => true, 'message' => 'Account created successfully']);
        } catch(PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Failed to create account']);
        }
    }
    
    // Login functionality
    else {
        $username = trim($_POST['username']);
        $password = $_POST['password'];
        
        // Validate input
        if (empty($username) || empty($password)) {
            echo json_encode(['success' => false, 'message' => 'Username and password are required']);
            exit;
        }
        
        // Check user credentials
        $stmt = $pdo->prepare("SELECT id, username, password, email FROM admin_users WHERE username = ?");
        $stmt->execute([$username]);
        
        if ($stmt->rowCount() === 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid username or password']);
            exit;
        }
        
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Verify password
        if (password_verify($password, $user['password'])) {
            // Update last login
            $updateStmt = $pdo->prepare("UPDATE admin_users SET last_login = NOW() WHERE id = ?");
            $updateStmt->execute([$user['id']]);
            
            // Set session
            $_SESSION['admin_id'] = $user['id'];
            $_SESSION['admin_username'] = $user['username'];
            $_SESSION['admin_email'] = $user['email'];
            $_SESSION['logged_in'] = true;
            
            echo json_encode(['success' => true, 'message' => 'Login successful']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid username or password']);
        }
    }
}

// Handle GET requests (for checking login status)
else if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (isset($_GET['action']) && $_GET['action'] === 'check_login') {
        if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
            echo json_encode(['success' => true, 'logged_in' => true]);
        } else {
            echo json_encode(['success' => false, 'logged_in' => false]);
        }
    }
    
    // Logout functionality
    else if (isset($_GET['action']) && $_GET['action'] === 'logout') {
        session_destroy();
        echo json_encode(['success' => true, 'message' => 'Logged out successfully']);
    }
}

else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>