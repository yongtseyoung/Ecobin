<?php
/**
 * Inventory Take Handler (Employee)
 * Records when employees take supplies (logs to inventory_transactions)
 */

session_start();
date_default_timezone_set('Asia/Kuala_Lumpur');
require_once '../config/database.php';
require_once '../config/languages.php';

// Check authentication - employees only
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'employee') {
    header("Location: ../login.php");
    exit;
}

$employee_id = $_SESSION['user_id'];

// Get employee for language preference
$employee = getOne("SELECT language FROM employees WHERE employee_id = ?", [$employee_id]);
$_SESSION['language'] = $employee['language'] ?? 'en';

$action = $_POST['action'] ?? '';

try {
    if ($action === 'take') {
        handleTakeSupply();
    } else {
        throw new Exception(t('invalid_action'));
    }
} catch (Exception $e) {
    $_SESSION['error'] = $e->getMessage();
    header("Location: inventory.php");
    exit;
}

function handleTakeSupply() {
    global $employee_id;
    
    $inventory_id = intval($_POST['inventory_id'] ?? 0);
    $quantity = intval($_POST['quantity'] ?? 0);
    
    // Validate
    if (!$inventory_id || $quantity <= 0) {
        throw new Exception(t('invalid_item_or_quantity'));
    }
    
    // Get item details
    $item = getOne("SELECT * FROM inventory WHERE inventory_id = ?", [$inventory_id]);
    if (!$item) {
        throw new Exception(t('item_not_found'));
    }
    
    // Check if enough stock
    if ($quantity > $item['current_quantity']) {
        throw new Exception(t('insufficient_stock') . "! " . t('only') . " {$item['current_quantity']} {$item['unit']} " . t('available'));
    }
    
    // Calculate new quantity
    $previous_quantity = $item['current_quantity'];
    $new_quantity = $previous_quantity - $quantity;
    
    // Determine new status
    $new_status = determineStatus($new_quantity, $item['minimum_quantity']);
    
    // Start transaction
    query("START TRANSACTION");
    
    try {
        // Update inventory
        query("UPDATE inventory SET 
               current_quantity = ?,
               status = ?,
               updated_at = NOW()
               WHERE inventory_id = ?",
               [$new_quantity, $new_status, $inventory_id]);
        
        // Log transaction to inventory_transactions table
        query("INSERT INTO inventory_transactions (
                   inventory_id, employee_id, transaction_type,
                   quantity, previous_quantity, new_quantity, 
                   reason, created_at
               ) VALUES (?, ?, 'take', ?, ?, ?, ?, NOW())",
               [$inventory_id, $employee_id, $quantity, 
                $previous_quantity, $new_quantity, t('self_service_take')]);
        
        // Commit transaction
        query("COMMIT");
        
        $_SESSION['success'] = t('successfully_taken') . " {$quantity} {$item['unit']} " . t('of') . " {$item['item_name']}! " . t('remaining') . ": {$new_quantity} {$item['unit']}";
        header("Location: inventory.php");
        exit;
        
    } catch (Exception $e) {
        query("ROLLBACK");
        throw new Exception(t('failed_to_record_transaction') . ": " . $e->getMessage());
    }
}

function determineStatus($current_quantity, $minimum_quantity) {
    if ($current_quantity == 0) {
        return 'out_of_stock';
    } elseif ($current_quantity <= $minimum_quantity) {
        return 'low_stock';
    } else {
        return 'in_stock';
    }
}
?>