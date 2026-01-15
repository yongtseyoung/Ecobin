<?php
/**
 * Inventory Actions Handler
 * Backend processing for inventory management operations
 */

session_start();
date_default_timezone_set('Asia/Kuala_Lumpur');
require_once '../config/database.php';

// Check authentication - admins only
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

$action = $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'add':
            handleAddItem();
            break;
        
        case 'edit':
            handleEditItem();
            break;
        
        case 'delete':
            handleDeleteItem();
            break;
        
        case 'restock':
            handleRestock();
            break;
        
        case 'stock_out':
            handleStockOut();
            break;
        
        default:
            throw new Exception("Invalid action");
    }
} catch (Exception $e) {
    $_SESSION['error'] = $e->getMessage();
    
    // Redirect based on action
    if (isset($_POST['inventory_id']) && in_array($action, ['edit', 'restock', 'stock_out'])) {
        header("Location: inventory_edit.php?id=" . $_POST['inventory_id']);
    } else {
        header("Location: inventory.php");
    }
    exit;
}

// ==================== ADD NEW ITEM ====================
function handleAddItem() {
    // Get form data
    $item_name = trim($_POST['item_name'] ?? '');
    $item_category = $_POST['item_category'] ?? '';
    $storage_location = trim($_POST['storage_location'] ?? '');
    $current_quantity = intval($_POST['current_quantity'] ?? 0);
    $minimum_quantity = intval($_POST['minimum_quantity'] ?? 0);
    $unit = trim($_POST['unit'] ?? '');
    
    // Validate
    if (empty($item_name) || empty($item_category) || empty($unit)) {
        throw new Exception("Please fill in all required fields");
    }
    
    if ($current_quantity < 0 || $minimum_quantity < 0) {
        throw new Exception("Quantities cannot be negative");
    }
    
    // Check for duplicate item name
    $existing = getOne("SELECT inventory_id FROM inventory WHERE item_name = ?", [$item_name]);
    if ($existing) {
        throw new Exception("An item with this name already exists");
    }
    
    // Determine status based on quantity
    $status = determineStatus($current_quantity, $minimum_quantity);
    
    // Insert item
    query("INSERT INTO inventory (
               item_name, item_category, storage_location, 
               current_quantity, minimum_quantity, unit, status, 
               created_at, updated_at
           ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())",
           [$item_name, $item_category, $storage_location ?: null, 
            $current_quantity, $minimum_quantity, $unit, $status]);
    
    $_SESSION['success'] = "Inventory item added successfully!";
    header("Location: inventory.php");
    exit;
}

// ==================== EDIT ITEM ====================
function handleEditItem() {
    $inventory_id = intval($_POST['inventory_id'] ?? 0);
    
    if (!$inventory_id) {
        throw new Exception("Invalid inventory item ID");
    }
    
    // Get existing item
    $existing = getOne("SELECT * FROM inventory WHERE inventory_id = ?", [$inventory_id]);
    if (!$existing) {
        throw new Exception("Inventory item not found");
    }
    
    // Get form data
    $item_name = trim($_POST['item_name'] ?? '');
    $item_category = $_POST['item_category'] ?? '';
    $storage_location = trim($_POST['storage_location'] ?? '');
    $current_quantity = intval($_POST['current_quantity'] ?? 0);
    $minimum_quantity = intval($_POST['minimum_quantity'] ?? 0);
    $unit = trim($_POST['unit'] ?? '');
    $status = $_POST['status'] ?? '';
    
    // Validate
    if (empty($item_name) || empty($item_category) || empty($unit) || empty($status)) {
        throw new Exception("Please fill in all required fields");
    }
    
    if ($current_quantity < 0 || $minimum_quantity < 0) {
        throw new Exception("Quantities cannot be negative");
    }
    
    // Check for duplicate item name (excluding current item)
    $duplicate = getOne("SELECT inventory_id FROM inventory WHERE item_name = ? AND inventory_id != ?", 
                        [$item_name, $inventory_id]);
    if ($duplicate) {
        throw new Exception("An item with this name already exists");
    }
    
    // Auto-update status based on quantity (override manual selection for consistency)
    $status = determineStatus($current_quantity, $minimum_quantity);
    
    // Update item
    query("UPDATE inventory SET 
           item_name = ?, 
           item_category = ?, 
           storage_location = ?, 
           current_quantity = ?, 
           minimum_quantity = ?, 
           unit = ?, 
           status = ?, 
           updated_at = NOW()
           WHERE inventory_id = ?",
           [$item_name, $item_category, $storage_location ?: null, 
            $current_quantity, $minimum_quantity, $unit, $status, $inventory_id]);
    
    $_SESSION['success'] = "Inventory item updated successfully!";
    header("Location: inventory.php");
    exit;
}

// ==================== DELETE ITEM ====================
function handleDeleteItem() {
    $inventory_id = intval($_POST['inventory_id'] ?? 0);
    
    if (!$inventory_id) {
        throw new Exception("Invalid inventory item ID");
    }
    
    // Check if item exists
    $item = getOne("SELECT * FROM inventory WHERE inventory_id = ?", [$inventory_id]);
    if (!$item) {
        throw new Exception("Inventory item not found");
    }
    
    // Check if item is referenced in supply requests
    $has_requests = getOne("SELECT COUNT(*) as count FROM supply_requests WHERE inventory_id = ?", [$inventory_id]);
    if ($has_requests['count'] > 0) {
        throw new Exception("Cannot delete this item. It has related supply requests. Consider marking it as out of stock instead.");
    }
    
    // Delete item
    query("DELETE FROM inventory WHERE inventory_id = ?", [$inventory_id]);
    
    $_SESSION['success'] = "Inventory item deleted successfully";
    header("Location: inventory.php");
    exit;
}

// ==================== RESTOCK (ADD STOCK) ====================
function handleRestock() {
    $inventory_id = intval($_POST['inventory_id'] ?? 0);
    $quantity = intval($_POST['quantity'] ?? 0);
    
    if (!$inventory_id) {
        throw new Exception("Invalid inventory item ID");
    }
    
    if ($quantity <= 0) {
        throw new Exception("Quantity to add must be greater than 0");
    }
    
    // Get current item
    $item = getOne("SELECT * FROM inventory WHERE inventory_id = ?", [$inventory_id]);
    if (!$item) {
        throw new Exception("Inventory item not found");
    }
    
    // Calculate new quantity
    $new_quantity = $item['current_quantity'] + $quantity;
    
    // Determine new status
    $new_status = determineStatus($new_quantity, $item['minimum_quantity']);
    
    // Update inventory
    query("UPDATE inventory SET 
           current_quantity = ?, 
           status = ?, 
           updated_at = NOW()
           WHERE inventory_id = ?",
           [$new_quantity, $new_status, $inventory_id]);
    
    $_SESSION['success'] = "Stock added successfully! Added {$quantity} {$item['unit']}. New quantity: {$new_quantity}";
    header("Location: inventory_edit.php?id=" . $inventory_id);
    exit;
}

// ==================== STOCK OUT (REMOVE STOCK) ====================
function handleStockOut() {
    $inventory_id = intval($_POST['inventory_id'] ?? 0);
    $quantity = intval($_POST['quantity'] ?? 0);
    
    if (!$inventory_id) {
        throw new Exception("Invalid inventory item ID");
    }
    
    if ($quantity <= 0) {
        throw new Exception("Quantity to remove must be greater than 0");
    }
    
    // Get current item
    $item = getOne("SELECT * FROM inventory WHERE inventory_id = ?", [$inventory_id]);
    if (!$item) {
        throw new Exception("Inventory item not found");
    }
    
    // Check if enough stock
    if ($quantity > $item['current_quantity']) {
        throw new Exception("Insufficient stock! Available: {$item['current_quantity']} {$item['unit']}");
    }
    
    // Calculate new quantity
    $new_quantity = $item['current_quantity'] - $quantity;
    
    // Determine new status
    $new_status = determineStatus($new_quantity, $item['minimum_quantity']);
    
    // Update inventory
    query("UPDATE inventory SET 
           current_quantity = ?, 
           status = ?, 
           updated_at = NOW()
           WHERE inventory_id = ?",
           [$new_quantity, $new_status, $inventory_id]);
    
    $_SESSION['success'] = "Stock removed successfully! Removed {$quantity} {$item['unit']}. Remaining quantity: {$new_quantity}";
    header("Location: inventory_edit.php?id=" . $inventory_id);
    exit;
}

// ==================== HELPER FUNCTIONS ====================

/**
 * Determine inventory status based on quantity levels
 */
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