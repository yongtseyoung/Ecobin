<?php
/**
 * IoT Data Receiver API
 * Receives sensor data from ESP32 devices and updates EcoBin database
 * 
 * Endpoint: POST http://localhost/ecobin/api/iot_receiver.php
 */

// Set headers
header('Content-Type: application/json');
date_default_timezone_set('Asia/Kuala_Lumpur');

// Include database connection
require_once '../config/database.php';

// Log function for debugging
function logMessage($message) {
    $logFile = __DIR__ . '/iot_logs.txt';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'status' => 'error',
        'message' => 'Method not allowed. Use POST request.'
    ]);
    logMessage("ERROR: Invalid method - " . $_SERVER['REQUEST_METHOD']);
    exit;
}

// Get JSON data from ESP32
$json = file_get_contents('php://input');
logMessage("Received data: $json");

// Decode JSON
$data = json_decode($json, true);

// Check if JSON is valid
if ($data === null) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid JSON format'
    ]);
    logMessage("ERROR: Invalid JSON");
    exit;
}

// Validate required fields
$required = ['device_id', 'bin_code', 'fill_level'];
foreach ($required as $field) {
    if (!isset($data[$field]) || $data[$field] === '') {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => "Missing required field: $field"
        ]);
        logMessage("ERROR: Missing field - $field");
        exit;
    }
}

try {
    // Extract data from JSON
    $device_mac = $data['device_id']; // ESP32 MAC address
    $bin_code = $data['bin_code'];
    $fill_level = floatval($data['fill_level']);
    $weight = isset($data['weight']) ? floatval($data['weight']) : null;
    $distance = isset($data['distance']) ? floatval($data['distance']) : null;
    $battery = isset($data['battery_level']) ? floatval($data['battery_level']) : null;
    $gps_lat = isset($data['gps_lat']) ? floatval($data['gps_lat']) : null;
    $gps_lng = isset($data['gps_lng']) ? floatval($data['gps_lng']) : null;
    $lid_status = isset($data['lid_status']) ? $data['lid_status'] : 'closed';
    $signal = isset($data['signal_strength']) ? intval($data['signal_strength']) : null;
    
    logMessage("Processing data for device: $device_mac, bin: $bin_code, fill: $fill_level%");
    
    //Find or create bin
    $bin = getOne("SELECT bin_id, area_id FROM bins WHERE bin_code = ?", [$bin_code]);
    
    if (!$bin) {
        http_response_code(404);
        echo json_encode([
            'status' => 'error',
            'message' => "Bin not found: $bin_code. Please create bin in admin panel first."
        ]);
        logMessage("ERROR: Bin not found - $bin_code");
        exit;
    }
    
    $bin_id = $bin['bin_id'];
    $area_id = $bin['area_id'];
    
    logMessage("Found bin_id: $bin_id");
    
    //Register or update IoT device
    $device = getOne("SELECT device_id FROM iot_devices WHERE device_mac_address = ?", [$device_mac]);
    
    if (!$device) {
        // Register new device
        logMessage("Registering new device: $device_mac");
        
        query("
            INSERT INTO iot_devices 
            (device_code, device_mac_address, device_model, device_status, 
             battery_level, signal_strength, installation_date, last_ping)
            VALUES (?, ?, 'ESP32 DevKit V1', 'online', ?, ?, CURDATE(), NOW())
        ", [$device_mac, $device_mac, $battery, $signal]);
        
        $device_db_id = getOne("SELECT LAST_INSERT_ID() as id")['id'];
        
        // Link device to bin
        query("UPDATE bins SET device_id = ? WHERE bin_id = ?", [$device_db_id, $bin_id]);
        
        logMessage("Device registered with ID: $device_db_id");
    } else {
        $device_db_id = $device['device_id'];
        
        // Update existing device
        query("
            UPDATE iot_devices 
            SET last_ping = NOW(), 
                device_status = 'online',
                battery_level = ?,
                signal_strength = ?
            WHERE device_id = ?
        ", [$battery, $signal, $device_db_id]);
        
        logMessage("Updated device ID: $device_db_id");
    }
    
    //Determine bin status
    $status = 'normal';
    if ($fill_level >= 95) {
        $status = 'full';
    } elseif ($fill_level >= 80) {
        $status = 'full';
    } elseif ($battery !== null && $battery < 20) {
        $status = 'needs_maintenance';
    }
    
    logMessage("Bin status determined: $status");
    
    //Update bin data
    query("
        UPDATE bins 
        SET current_fill_level = ?,
            current_weight = ?,
            battery_level = ?,
            status = ?,
            gps_latitude = ?,
            gps_longitude = ?,
            lid_status = ?,
            last_updated = NOW(),
            last_weight_reading = NOW(),
            weight_sensor_status = 'online'
        WHERE bin_id = ?
    ", [
        $fill_level,
        $weight,
        $battery,
        $status,
        $gps_lat,
        $gps_lng,
        $lid_status,
        $bin_id
    ]);
    
    logMessage("Bin updated: fill=$fill_level%, weight=$weight kg, status=$status");
    
    // Update last_opened if lid was opened
    if ($lid_status === 'open') {
        query("UPDATE bins SET last_opened = NOW() WHERE bin_id = ?", [$bin_id]);
        logMessage("Lid opened timestamp updated");
    }
    
    //Log sensor reading
    query("
        INSERT INTO sensor_readings
        (device_id, bin_id, fill_level, weight, distance, battery_voltage, 
         signal_quality, gps_latitude, gps_longitude, lid_status, recorded_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ", [
        $device_db_id,
        $bin_id,
        $fill_level,
        $weight,
        $distance,
        $battery,
        $signal,
        $gps_lat,
        $gps_lng,
        $lid_status
    ]);
    
    logMessage("Sensor reading logged");
    
    //Auto-create task if bin >= 80% full
    $task_created = false;
    if ($fill_level >= 80) {
        logMessage("Fill level >= 80%, checking for existing tasks...");
        
        // Check if task already exists for this bin
        $existing_task = getOne("
            SELECT task_id FROM tasks 
            WHERE triggered_by_bin = ? 
            AND status IN ('pending', 'in_progress')
        ", [$bin_id]);
        
        if (!$existing_task) {
            logMessage("No existing task found, creating new task...");
            
            // Get bin details
            $bin_details = getOne("
                SELECT b.*, a.area_name 
                FROM bins b
                LEFT JOIN areas a ON b.area_id = a.area_id
                WHERE b.bin_id = ?
            ", [$bin_id]);
            
            // Get assigned employee for this area
            $employee = getOne("
                SELECT employee_id FROM employees 
                WHERE area_id = ? AND status = 'active' 
                ORDER BY employee_id ASC
                LIMIT 1
            ", [$area_id]);
            
            if ($employee) {
                // Determine priority
                $priority = $fill_level >= 95 ? 'urgent' : 'high';
                
                // Create task title
                $title = $fill_level >= 95 
                    ? "URGENT: Collect Bin $bin_code (OVERFLOWING {$fill_level}%)"
                    : "Collect Bin $bin_code ({$fill_level}% full)";
                
                // Create description
                $description = "Auto-generated collection task triggered by IoT sensor.\n";
                $description .= "Location: {$bin_details['location_details']}\n";
                $description .= "Fill Level: {$fill_level}%\n";
                if ($weight) $description .= "Weight: {$weight} kg\n";
                if ($battery) $description .= "Battery: {$battery}%\n";
                if ($gps_lat && $gps_lng) $description .= "GPS: {$gps_lat}, {$gps_lng}";
                
                // Get admin (creator)
                $admin = getOne("SELECT admin_id FROM admins WHERE status = 'active' LIMIT 1");
                
                if ($admin) {
                    query("
                        INSERT INTO tasks
                        (created_by, area_id, assigned_to, task_title, task_type, priority, 
                         description, status, is_auto_generated, triggered_by_bin, scheduled_date)
                        VALUES (?, ?, ?, ?, 'collection', ?, ?, 'pending', 1, ?, CURDATE())
                    ", [
                        $admin['admin_id'],
                        $area_id,
                        $employee['employee_id'],
                        $title,
                        $priority,
                        $description,
                        $bin_id
                    ]);
                    
                    $task_created = true;
                    logMessage("Task created successfully for bin $bin_code");
                } else {
                    logMessage("WARNING: No active admin found to create task");
                }
            } else {
                logMessage("WARNING: No active employee found for area_id $area_id");
            }
        } else {
            logMessage("Task already exists (task_id: {$existing_task['task_id']}), skipping creation");
        }
    }
    
    //Success response
    http_response_code(200);
    $response = [
        'status' => 'success',
        'message' => 'Data received and processed successfully',
        'data' => [
            'bin_id' => $bin_id,
            'bin_code' => $bin_code,
            'device_id' => $device_db_id,
            'fill_level' => $fill_level,
            'weight' => $weight,
            'bin_status' => $status,
            'task_created' => $task_created,
            'timestamp' => date('Y-m-d H:i:s')
        ]
    ];
    
    echo json_encode($response);
    logMessage("SUCCESS: Response sent - " . json_encode($response));
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Server error: ' . $e->getMessage()
    ]);
    logMessage("EXCEPTION: " . $e->getMessage());
}
?>