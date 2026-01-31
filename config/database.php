<?php

define('DB_HOST', 'localhost');      
define('DB_NAME', 'ecobin');         
define('DB_USER', 'root');           
define('DB_PASS', '');               
define('DB_CHARSET', 'utf8mb4');     

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,    
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,           
    PDO::ATTR_EMULATE_PREPARES   => false,                      
    PDO::ATTR_PERSISTENT         => false,                      
];


try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    
    
} catch (PDOException $e) {
    die("Database Connection Failed: " . $e->getMessage());
}

/**
 * Helper function to execute queries safely
 * 
 * @param string $sql SQL query with placeholders
 * @param array $params Parameters to bind
 * @return PDOStatement
 */
function query($sql, $params = []) {
    global $pdo;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

/**
 * Helper function to get single row
 * 
 * @param string $sql SQL query
 * @param array $params Parameters to bind
 * @return array|false
 */
function getOne($sql, $params = []) {
    $stmt = query($sql, $params);
    return $stmt->fetch();
}

/**
 * Helper function to get all rows
 * 
 * @param string $sql SQL query
 * @param array $params Parameters to bind
 * @return array
 */
function getAll($sql, $params = []) {
    $stmt = query($sql, $params);
    return $stmt->fetchAll();
}

/**
 * Helper function to get last insert ID
 * 
 * @return string
 */
function lastInsertId() {
    global $pdo;
    return $pdo->lastInsertId();
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Check if user is logged in
 * 
 * @return bool
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['user_type']);
}

/**
 * Get current user type (admin or employee)
 * 
 * @return string|null
 */
function getUserType() {
    return $_SESSION['user_type'] ?? null;
}

/**
 * Get current user ID
 * 
 * @return int|null
 */
function getUserId() {
    return $_SESSION['user_id'] ?? null;
}

/**
 * Redirect if not logged in
 * 
 * @param string $redirect_to Where to redirect
 */
function requireLogin($redirect_to = '/login.php') {
    if (!isLoggedIn()) {
        header("Location: $redirect_to");
        exit;
    }
}


function requireAdmin() {
    requireLogin();
    if (getUserType() !== 'admin') {
        header("Location: /employee/dashboard.php");
        exit;
    }
}


function requireEmployee() {
    requireLogin();
    if (getUserType() !== 'employee') {
        header("Location: /admin/dashboard.php");
        exit;
    }
}

/**
 * Sanitize output for HTML display
 * 
 * @param string $string
 * @return string
 */
function escape($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}
?>
