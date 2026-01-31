<?php

session_start();
date_default_timezone_set('Asia/Kuala_Lumpur');
require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$user_type = $_SESSION['user_type'];
$action = $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'approve':
            handleApproveRequest();
            break;
        
        case 'reject':
            handleRejectRequest();
            break;
        
        case 'fulfill':
            handleFulfillRequest();
            break;
        
        default:
            throw new Exception("Invalid action");
    }
} catch (Exception $e) {
    $_SESSION['error'] = $e->getMessage();
    
    if ($user_type === 'admin') {
        header("Location: supply_requests.php");
    } else {
        header("Location: ../user/my_supply_requests.php");
    }
    exit;
}

function handleApproveRequest() {
    global $user_id, $user_type;
    
    if ($user_type !== 'admin') {
        throw new Exception("Only admins can approve supply requests");
    }
    
    $request_id = intval($_POST['request_id'] ?? 0);
    $admin_response = trim($_POST['admin_response'] ?? '');
    
    if (!$request_id) {
        throw new Exception("Invalid request ID");
    }
    
    $request = getOne("SELECT sr.*, i.item_name, i.current_quantity, i.unit
                       FROM supply_requests sr
                       JOIN inventory i ON sr.inventory_id = i.inventory_id
                       WHERE sr.request_id = ?", [$request_id]);
    
    if (!$request) {
        throw new Exception("Supply request not found");
    }
    
    if ($request['status'] !== 'pending') {
        throw new Exception("This request has already been processed");
    }
    
    if ($request['current_quantity'] < $request['quantity_requested']) {
        throw new Exception("Insufficient stock! Available: {$request['current_quantity']} {$request['unit']}, Requested: {$request['quantity_requested']} {$request['unit']}");
    }
    
    query("UPDATE supply_requests SET 
           status = 'approved',
           reviewed_by = ?,
           reviewed_at = NOW(),
           admin_response = ?
           WHERE request_id = ?",
           [$user_id, $admin_response ?: null, $request_id]);
    
    $_SESSION['success'] = "Supply request approved successfully! Don't forget to fulfill it.";
    header("Location: supply_requests.php");
    exit;
}

function handleRejectRequest() {
    global $user_id, $user_type;
    
    if ($user_type !== 'admin') {
        throw new Exception("Only admins can reject supply requests");
    }
    
    $request_id = intval($_POST['request_id'] ?? 0);
    $admin_response = trim($_POST['admin_response'] ?? '');
    
    if (!$request_id) {
        throw new Exception("Invalid request ID");
    }
    
    if (empty($admin_response)) {
        throw new Exception("Please provide a reason for rejection");
    }
    
    $request = getOne("SELECT * FROM supply_requests WHERE request_id = ?", [$request_id]);
    
    if (!$request) {
        throw new Exception("Supply request not found");
    }
    
    if ($request['status'] !== 'pending') {
        throw new Exception("This request has already been processed");
    }
    
    query("UPDATE supply_requests SET 
           status = 'rejected',
           reviewed_by = ?,
           reviewed_at = NOW(),
           admin_response = ?
           WHERE request_id = ?",
           [$user_id, $admin_response, $request_id]);
    
    $_SESSION['success'] = "Supply request rejected";
    header("Location: supply_requests.php");
    exit;
}

function handleFulfillRequest() {
    global $user_id, $user_type;
    
    if ($user_type !== 'admin') {
        throw new Exception("Only admins can fulfill restock requests");
    }
    
    $request_id = intval($_POST['request_id'] ?? 0);
    $quantity_restocked = intval($_POST['quantity_restocked'] ?? 0);
    
    if (!$request_id) {
        throw new Exception("Invalid request ID");
    }
    
    if ($quantity_restocked <= 0) {
        throw new Exception("Please enter quantity restocked");
    }
    
    $request = getOne("SELECT sr.*, i.item_name, i.current_quantity, i.minimum_quantity, i.unit
                       FROM supply_requests sr
                       JOIN inventory i ON sr.inventory_id = i.inventory_id
                       WHERE sr.request_id = ?", [$request_id]);
    
    if (!$request) {
        throw new Exception("Restock request not found");
    }
    
    if ($request['status'] !== 'pending' && $request['status'] !== 'approved') {
        throw new Exception("This request has already been processed");
    }
    
    $previous_quantity = $request['current_quantity'];
    $new_quantity = $previous_quantity + $quantity_restocked;
    
    $new_status = determineStatus($new_quantity, $request['minimum_quantity']);
    
    query("START TRANSACTION");
    
    try {
        query("UPDATE inventory SET 
               current_quantity = ?,
               status = ?,
               updated_at = NOW()
               WHERE inventory_id = ?",
               [$new_quantity, $new_status, $request['inventory_id']]);
        
        query("UPDATE supply_requests SET 
               status = 'fulfilled',
               quantity_restocked = ?,
               reviewed_by = ?,
               reviewed_at = NOW()
               WHERE request_id = ?",
               [$quantity_restocked, $user_id, $request_id]);
        
        query("INSERT INTO inventory_transactions (
                   inventory_id, employee_id, transaction_type,
                   quantity, previous_quantity, new_quantity,
                   reason, created_at
               ) VALUES (?, NULL, 'restock', ?, ?, ?, ?, NOW())",
               [$request['inventory_id'], $quantity_restocked,
                $previous_quantity, $new_quantity, 
                "Restock fulfillment for request #" . $request_id . " by admin"]);
        
        query("COMMIT");
        
        $_SESSION['success'] = "Restock request fulfilled! Added {$quantity_restocked} {$request['unit']} of {$request['item_name']}. New quantity: {$new_quantity}";
        header("Location: supply_requests.php");
        exit;
        
    } catch (Exception $e) {
        query("ROLLBACK");
        throw new Exception("Failed to fulfill request: " . $e->getMessage());
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