<?php
/**
 * Supply Request Actions (Employee)
 * Handles employee supply request submissions
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
$from_inventory = $_POST['from_inventory'] ?? 0; // Check if request came from inventory page

try {
    if ($action === 'request') {
        handleSupplyRequest();
    } else {
        throw new Exception(t('invalid_action'));
    }
} catch (Exception $e) {
    $_SESSION['error'] = $e->getMessage();
    
    // Redirect based on where request came from
    if ($from_inventory) {
        header("Location: inventory.php");
    } else {
        header("Location: supply_requests.php");
    }
    exit;
}

function handleSupplyRequest() {
    global $employee_id, $from_inventory;
    
    // Get form data
    $inventory_id = intval($_POST['inventory_id'] ?? 0);
    $quantity_requested = intval($_POST['quantity_requested'] ?? 0);
    $urgency = $_POST['urgency'] ?? 'medium';
    $employee_reason = trim($_POST['employee_reason'] ?? '');
    
    // Validate
    if (!$inventory_id || $quantity_requested <= 0) {
        throw new Exception(t('select_item_valid_quantity'));
    }
    
    if (!in_array($urgency, ['low', 'medium', 'high'])) {
        throw new Exception(t('invalid_urgency_level'));
    }
    
    // Check if item exists and get details
    $item = getOne("SELECT * FROM inventory WHERE inventory_id = ?", [$inventory_id]);
    if (!$item) {
        throw new Exception(t('item_not_found'));
    }
    
    // Insert supply request
    query("INSERT INTO supply_requests (
               employee_id, inventory_id, quantity_requested, 
               employee_reason, urgency, status, requested_at
           ) VALUES (?, ?, ?, ?, ?, 'pending', NOW())",
           [$employee_id, $inventory_id, $quantity_requested, 
            $employee_reason ?: null, $urgency]);
    
    $_SESSION['success'] = t('restock_request_submitted') . "! " . t('your_request_for') . " {$quantity_requested} {$item['unit']} " . t('of') . " {$item['item_name']} " . t('awaiting_admin_approval') . ".";
    $_SESSION['success_type'] = 'request';
    $_SESSION['highlight_item'] = $inventory_id;
    
    // Redirect based on where request came from
    if ($from_inventory) {
        header("Location: inventory.php");
    } else {
        header("Location: supply_requests.php?tab=my-requests");
    }
    exit;
}
?>