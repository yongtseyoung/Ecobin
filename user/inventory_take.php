<?php


session_start();
date_default_timezone_set('Asia/Kuala_Lumpur');
require_once '../config/database.php';
require_once '../config/languages.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'employee') {
    header("Location: ../login.php");
    exit;
}

$employee_id = $_SESSION['user_id'];

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
    
    if (!$inventory_id || $quantity <= 0) {
        throw new Exception(t('invalid_item_or_quantity'));
    }
    
    $item = getOne("SELECT * FROM inventory WHERE inventory_id = ?", [$inventory_id]);
    if (!$item) {
        throw new Exception(t('item_not_found'));
    }
    
    if ($quantity > $item['current_quantity']) {
        throw new Exception(t('insufficient_stock') . "! " . t('only') . " {$item['current_quantity']} {$item['unit']} " . t('available'));
    }
    
    $previous_quantity = $item['current_quantity'];
    $new_quantity = $previous_quantity - $quantity;
    
    $new_status = determineStatus($new_quantity, $item['minimum_quantity']);
    
    query("START TRANSACTION");
    
    try {
        query("UPDATE inventory SET 
               current_quantity = ?,
               status = ?,
               updated_at = NOW()
               WHERE inventory_id = ?",
               [$new_quantity, $new_status, $inventory_id]);
        
        query("INSERT INTO inventory_transactions (
                   inventory_id, employee_id, transaction_type,
                   quantity, previous_quantity, new_quantity, 
                   reason, created_at
               ) VALUES (?, ?, 'take', ?, ?, ?, ?, NOW())",
               [$inventory_id, $employee_id, $quantity, 
                $previous_quantity, $new_quantity, t('self_service_take')]);
        
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