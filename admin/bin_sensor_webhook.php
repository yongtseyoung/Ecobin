<?php
/**
 * Bin Sensor Webhook
 * Receives data from ESP32 IoT bins and auto-creates collection tasks
 * MODIFIED: Includes smart scheduled_date based on time of day
 */

date_default_timezone_set('Asia/Kuala_Lumpur');
header('Content-Type: application/json');

// Include database connection
require_once '../config/database.php';

// Log function for debugging
function log_message($message) {
    $log_file = __DIR__ . '/bin_webhook_log.txt';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[$timestamp] $message\n", FILE_APPEND);
}

/**
 * Calculate fair scheduled_date based on creation time and priority
 * This ensures employees aren't penalized for overnight tasks
 */
function calculate_scheduled_date($priority, $fill_level) {
    $current_hour = intval(date('H')); // 0-23
    $current_date = date('Y-m-d');
    
    // Define work hours: 8 AM - 5 PM
    $work_start = 8;
    $work_end = 17;
    
    // Check if within work hours
    $during_work_hours = ($current_hour >= $work_start && $current_hour < $work_end);
    
    // URGENT PRIORITY (95%+ full)
    if ($priority === 'urgent' || $fill_level >= 95) {
        if ($during_work_hours) {
            // Urgent during work hours = same day
            return $current_date;
        } else {
            // Urgent overnight = next working day (same day, employee handles in morning)
            return $current_date;
        }
    }
    
    // HIGH PRIORITY (80-94% full)
    elseif ($priority === 'high' || $fill_level >= 80) {
        if ($during_work_hours) {
            // High priority during work hours = same day
            return $current_date;
        } else {
            // High priority overnight = same day (handle in morning)
            return $current_date;
        }
    }
    
    // MEDIUM/LOW PRIORITY
    else {
        if ($during_work_hours) {
            // Medium during work hours = same day or next day
            return date('Y-m-d', strtotime('+1 day'));
        } else {
            // Medium overnight = next day
            return date('Y-m-d', strtotime('+1 day'));
        }
    }
}

try {
    // Get JSON data from ESP32
    $json = file_get_contents('php://input');
    
    if (empty($json)) {
        throw new Exception('No data received');
    }

    log_message("Received data: $json");

    // Parse JSON
    $data = json_decode($json, true);

    if (!$data) {
        throw new Exception('Invalid JSON format');
    }

    // Validate required fields
    if (!isset($data['bin_id'])) {
        throw new Exception('bin_id is required');
    }

    // Extract data with defaults
    $bin_id = intval($data['bin_id']);
    $fill_level = floatval($data['fill_level'] ?? 0);
    $distance = floatval($data['distance'] ?? null);
    $battery_percentage = floatval($data['battery_percentage'] ?? null);
    $battery_voltage = floatval($data['battery_voltage'] ?? null);
    $gps_lat = floatval($data['gps_latitude'] ?? null);
    $gps_lng = floatval($data['gps_longitude'] ?? null);
    $lid_status = $data['lid_status'] ?? 'closed';
    $last_opened = $data['last_opened'] ?? null;
    $temperature = floatval($data['temperature'] ?? null);

    // Verify bin exists
    $bin = getOne("SELECT * FROM bins WHERE bin_id = ?", [$bin_id]);
    
    if (!$bin) {
        throw new Exception("Bin ID $bin_id not found in database");
    }

    // Determine bin status based on fill level
    $bin_status = 'normal';
    if ($fill_level >= 95) {
        $bin_status = 'full';
    } elseif ($fill_level >= 80) {
        $bin_status = 'full'; // Will trigger task
    } elseif ($fill_level >= 60) {
        $bin_status = 'normal';
    }

    // Update bins table - only update columns that exist
    $sql = "UPDATE bins SET 
            current_fill_level = ?,
            battery_level = ?,
            gps_latitude = ?,
            gps_longitude = ?,
            lid_status = ?,
            last_opened = ?,
            status = ?,
            last_updated = NOW()
            WHERE bin_id = ?";
    
    query($sql, [
        $fill_level,
        $battery_percentage,
        $gps_lat,
        $gps_lng,
        $lid_status,
        $last_opened,
        $bin_status,
        $bin_id
    ]);

    log_message("Updated bin $bin_id: fill_level=$fill_level%, battery=$battery_percentage%, status=$bin_status");

    // Update IoT device last_ping if device exists
    if (isset($bin['device_id']) && $bin['device_id']) {
        try {
            query("UPDATE iot_devices SET 
                   last_ping = NOW(),
                   device_status = 'online',
                   battery_level = ?
                   WHERE device_id = ?", 
                   [$battery_percentage, $bin['device_id']]);
            log_message("Updated device status for device {$bin['device_id']}");
        } catch (Exception $e) {
            log_message("Warning: Could not update device status - " . $e->getMessage());
        }
    }

    // AUTO-CREATE TASK if fill level >= 80% and no active task exists
    $task_created = false;
    $task_id = null;

    if ($fill_level >= 80) {
        // Check if there's already an active task for this bin
        $existing_task = getOne("SELECT * FROM tasks 
                                 WHERE triggered_by_bin = ? 
                                 AND status IN ('pending', 'in_progress')
                                 ORDER BY created_at DESC
                                 LIMIT 1", 
                                 [$bin_id]);

        if (!$existing_task) {
            // Find employee assigned to this bin's area
            $employee = getOne("SELECT employee_id, full_name 
                               FROM employees 
                               WHERE area_id = ? 
                               AND status = 'active' 
                               ORDER BY RAND()
                               LIMIT 1", 
                               [$bin['area_id']]);

            if ($employee) {
                // Get first admin (system user) for created_by
                $system_admin = getOne("SELECT admin_id FROM admins ORDER BY admin_id LIMIT 1");
                $created_by_admin = $system_admin ? $system_admin['admin_id'] : 1; // Fallback to 1 if no admin found

                // Determine priority based on fill level
                if ($fill_level >= 95) {
                    $priority = 'urgent';
                    $task_title = "URGENT: Collect Bin {$bin['bin_code']} (OVERFLOWING {$fill_level}%)";
                } elseif ($fill_level >= 90) {
                    $priority = 'high';
                    $task_title = "Collect Bin {$bin['bin_code']} (Nearly Full {$fill_level}%)";
                } else {
                    $priority = 'high';
                    $task_title = "Collect Bin {$bin['bin_code']} ({$fill_level}% full)";
                }

                // SMART SCHEDULING: Calculate fair scheduled_date based on time and priority
                $scheduled_date = calculate_scheduled_date($priority, $fill_level);
                $current_hour = intval(date('H'));
                
                // Log scheduling decision for transparency
                log_message("Task scheduling: created at " . date('H:i') . ", priority=$priority, fill=$fill_level%, scheduled_date=$scheduled_date");

                // Create auto-generated task with smart scheduled_date
                $sql = "INSERT INTO tasks (
                            task_title, task_type, priority, status,
                            assigned_to, area_id, triggered_by_bin,
                            scheduled_date, description,
                            is_auto_generated, created_by, created_at
                        ) VALUES (?, 'collection', ?, 'pending', ?, ?, ?, ?, ?, 1, ?, NOW())";

                $description = "Auto-generated collection task triggered by IoT sensor.\n" .
                              "Location: {$bin['location_details']}\n" .
                              "Fill Level: {$fill_level}%\n" .
                              "Battery: {$battery_percentage}%";

                if ($gps_lat && $gps_lng) {
                    $description .= "\nGPS: {$gps_lat}, {$gps_lng}";
                }

                try {
                    query($sql, [
                        $task_title,
                        $priority,
                        $employee['employee_id'],
                        $bin['area_id'],
                        $bin_id,
                        $scheduled_date,  // Smart scheduled date
                        $description,
                        $created_by_admin
                    ]);

                    $task_id = lastInsertId();
                    $task_created = true;

                    log_message("AUTO-CREATED TASK #$task_id for bin {$bin['bin_code']} - Assigned to {$employee['full_name']} (Priority: $priority, Scheduled: $scheduled_date)");
                } catch (Exception $e) {
                    log_message("ERROR creating task: " . $e->getMessage());
                    throw $e;
                }
            } else {
                log_message("WARNING: No active employee found for area {$bin['area_id']}");
            }
        } else {
            log_message("Task already exists for bin $bin_id (Task #{$existing_task['task_id']})");
            $task_id = $existing_task['task_id'];
        }
    }

    // Check for low battery warning
    $battery_warning = false;
    if ($battery_percentage < 20) {
        log_message("WARNING: Low battery for bin $bin_id - {$battery_percentage}%");
        $battery_warning = true;
    }

    // Success response
    $response = [
        'success' => true,
        'message' => 'Data received and processed successfully',
        'bin_id' => $bin_id,
        'bin_code' => $bin['bin_code'],
        'fill_level' => $fill_level,
        'battery_level' => $battery_percentage,
        'status' => $bin_status,
        'task_created' => $task_created,
        'task_id' => $task_id,
        'battery_warning' => $battery_warning,
        'timestamp' => date('Y-m-d H:i:s')
    ];

    log_message("Response: " . json_encode($response));

    http_response_code(200);
    echo json_encode($response);

} catch (Exception $e) {
    log_message("ERROR: " . $e->getMessage());
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}
?>