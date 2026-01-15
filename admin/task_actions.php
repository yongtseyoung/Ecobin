<?php
/**
 * Task Actions Handler
 * Backend processing for all task operations
 * MODIFIED: Sets started_at = NOW() when completing tasks directly
 * FIXED: Auto-creates collection reports when completing collection tasks
 */

session_start();
date_default_timezone_set('Asia/Kuala_Lumpur');
require_once '../config/database.php';

// Check authentication
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$action = $_POST['action'] ?? '';
$user_type = $_SESSION['user_type'] ?? '';
$user_id = $_SESSION['user_id'];

// Create Task
if ($action === 'create' && $user_type === 'admin') {
    try {
        $task_title = trim($_POST['task_title'] ?? '');
        $task_type = $_POST['task_type'] ?? '';
        $assigned_to = $_POST['assigned_to'] ?? null;
        $area_id = $_POST['area_id'] ?? null;
        $triggered_by_bin = $_POST['triggered_by_bin'] ?? null;
        $scheduled_date = $_POST['scheduled_date'] ?? null;
        $scheduled_time = $_POST['scheduled_time'] ?? null;
        $priority = $_POST['priority'] ?? 'medium';
        $description = trim($_POST['description'] ?? '');

        // Validation
        if (empty($task_title) || empty($task_type) || empty($assigned_to) || empty($scheduled_date)) {
            throw new Exception("Please fill in all required fields");
        }

        // Insert task
        $sql = "INSERT INTO tasks (
                    task_title, task_type, priority, status,
                    assigned_to, area_id, triggered_by_bin,
                    scheduled_date, scheduled_time, description,
                    is_auto_generated, created_by, created_at
                ) VALUES (?, ?, ?, 'pending', ?, ?, ?, ?, ?, ?, 0, ?, NOW())";

        query($sql, [
            $task_title,
            $task_type,
            $priority,
            $assigned_to,
            $area_id ?: null,
            $triggered_by_bin ?: null,
            $scheduled_date,
            $scheduled_time ?: null,
            $description ?: null,
            $user_id
        ]);

        $_SESSION['success'] = "Task created successfully!";
        header("Location: tasks.php");
        exit;

    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
        header("Location: task_create.php");
        exit;
    }
}

// Update Task
elseif ($action === 'update' && $user_type === 'admin') {
    try {
        $task_id = $_POST['task_id'] ?? 0;
        $task_title = trim($_POST['task_title'] ?? '');
        $task_type = $_POST['task_type'] ?? '';
        $assigned_to = $_POST['assigned_to'] ?? null;
        $area_id = $_POST['area_id'] ?? null;
        $triggered_by_bin = $_POST['triggered_by_bin'] ?? null;
        $scheduled_date = $_POST['scheduled_date'] ?? null;
        $scheduled_time = $_POST['scheduled_time'] ?? null;
        $priority = $_POST['priority'] ?? 'medium';
        $status = $_POST['status'] ?? 'pending';
        $description = trim($_POST['description'] ?? '');

        // Get existing task
        $existing_task = getOne("SELECT * FROM tasks WHERE task_id = ?", [$task_id]);
        
        if (!$existing_task) {
            throw new Exception("Task not found");
        }

        // Check if task can be edited
        if ($existing_task['status'] === 'completed' || $existing_task['status'] === 'cancelled') {
            throw new Exception("Cannot edit completed or cancelled tasks");
        }

        // Validation
        if (empty($task_title) || empty($task_type) || empty($assigned_to) || empty($scheduled_date)) {
            throw new Exception("Please fill in all required fields");
        }

        // If status changed to completed, set completed_at
        $completed_at_update = "";
        $params = [
            $task_title,
            $task_type,
            $priority,
            $status,
            $assigned_to,
            $area_id ?: null,
            $scheduled_date,
            $scheduled_time ?: null,
            $description ?: null
        ];

        if ($status === 'completed' && $existing_task['status'] !== 'completed') {
            $completed_at_update = ", completed_at = NOW()";
        }

        // If status changed from pending to in_progress, set started_at
        $started_at_update = "";
        if ($status === 'in_progress' && $existing_task['status'] === 'pending') {
            $started_at_update = ", started_at = NOW()";
        }

        // For auto-generated tasks, keep the bin_id
        if ($existing_task['is_auto_generated']) {
            $sql = "UPDATE tasks SET
                    task_title = ?, task_type = ?, priority = ?, status = ?,
                    assigned_to = ?, area_id = ?,
                    scheduled_date = ?, scheduled_time = ?, description = ?
                    $completed_at_update $started_at_update
                    WHERE task_id = ?";
            $params[] = $task_id;
        } else {
            $sql = "UPDATE tasks SET
                    task_title = ?, task_type = ?, priority = ?, status = ?,
                    assigned_to = ?, area_id = ?, triggered_by_bin = ?,
                    scheduled_date = ?, scheduled_time = ?, description = ?
                    $completed_at_update $started_at_update
                    WHERE task_id = ?";
            array_splice($params, 6, 0, [$triggered_by_bin ?: null]);
            $params[] = $task_id;
        }

        query($sql, $params);

        // If task is completed and related to a bin, update bin status
        if ($status === 'completed' && $existing_task['triggered_by_bin']) {
            query("UPDATE bins SET 
                   status = 'normal',
                   current_fill_level = 0,
                   last_collection = NOW()
                   WHERE bin_id = ?", 
                   [$existing_task['triggered_by_bin']]);
            
// NEW: Auto-create collection report if it doesn't exist WITH WEIGHT
if ($existing_task['task_type'] === 'collection') {
    $existing_report = getOne(
        "SELECT report_id FROM collection_reports WHERE task_id = ?", 
        [$task_id]
    );
    
    if (!$existing_report) {
        // Extract weight from task description
        $weight = null;
        if ($description) {
            if (preg_match('/Weight:\s*([\d.]+)\s*kg/i', $description, $matches)) {
                $weight = floatval($matches[1]);
            }
        } elseif ($existing_task['description']) {
            if (preg_match('/Weight:\s*([\d.]+)\s*kg/i', $existing_task['description'], $matches)) {
                $weight = floatval($matches[1]);
            }
        }
        
        query("INSERT INTO collection_reports (
                  task_id, bin_id, area_id, employee_id,
                  collection_date, collection_start, collection_end,
                  total_weight,
                  waste_condition, report_notes,
                  is_auto_generated, submitted_at
               ) VALUES (?, ?, ?, ?, CURDATE(), NOW(), NOW(), ?, 'normal', ?, 1, NOW())",
               [
                   $task_id,
                   $existing_task['triggered_by_bin'],
                   $existing_task['area_id'],
                   $assigned_to,
                   $weight, // ← ADD WEIGHT HERE!
                   t('auto_generated_collection_report_admin')
               ]);
    }
}
        }

        $_SESSION['success'] = "Task updated successfully!";
        header("Location: task_view.php?id=$task_id");
        exit;

    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
        header("Location: task_edit.php?id=" . ($task_id ?? 0));
        exit;
    }
}

// Cancel Task
elseif ($action === 'cancel' && $user_type === 'admin') {
    try {
        $task_id = $_POST['task_id'] ?? 0;

        $task = getOne("SELECT * FROM tasks WHERE task_id = ?", [$task_id]);
        
        if (!$task) {
            throw new Exception("Task not found");
        }

        if ($task['status'] === 'completed') {
            throw new Exception("Cannot cancel completed tasks");
        }

        query("UPDATE tasks SET status = 'cancelled' WHERE task_id = ?", [$task_id]);

        $_SESSION['success'] = "Task cancelled successfully";
        header("Location: tasks.php");
        exit;

    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
        header("Location: task_view.php?id=" . ($task_id ?? 0));
        exit;
    }
}

// Start Task (for quick action)
elseif ($action === 'start' && $user_type === 'admin') {
    try {
        $task_id = $_POST['task_id'] ?? 0;

        $task = getOne("SELECT * FROM tasks WHERE task_id = ?", [$task_id]);
        
        if (!$task) {
            throw new Exception("Task not found");
        }

        if ($task['status'] !== 'pending') {
            throw new Exception("Only pending tasks can be started");
        }

        query("UPDATE tasks SET status = 'in_progress', started_at = NOW() WHERE task_id = ?", [$task_id]);

        $_SESSION['success'] = "Task started successfully";
        header("Location: task_view.php?id=$task_id");
        exit;

    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
        header("Location: task_view.php?id=" . ($task_id ?? 0));
        exit;
    }
}

// Employee Actions (for my_tasks.php)
elseif ($action === 'employee_start' && $user_type === 'employee') {
    try {
        $task_id = $_POST['task_id'] ?? 0;

        $task = getOne("SELECT * FROM tasks WHERE task_id = ? AND assigned_to = ?", [$task_id, $user_id]);
        
        if (!$task) {
            throw new Exception("Task not found or not assigned to you");
        }

        if ($task['status'] !== 'pending') {
            throw new Exception("This task has already been started");
        }

        query("UPDATE tasks SET status = 'in_progress', started_at = NOW() WHERE task_id = ?", [$task_id]);

        $_SESSION['success'] = "Task started! Good luck! 💪";
        header("Location: my_tasks.php");
        exit;

    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
        header("Location: my_tasks.php");
        exit;
    }
}

// Employee Complete Task - MODIFIED to set started_at = NOW() when completing
// FIXED: Now auto-creates collection reports for collection tasks
elseif ($action === 'employee_complete' && $user_type === 'employee') {
    try {
        $task_id = $_POST['task_id'] ?? 0;
        $completion_notes = trim($_POST['completion_notes'] ?? '');

        $task = getOne("SELECT * FROM tasks WHERE task_id = ? AND assigned_to = ?", [$task_id, $user_id]);
        
        if (!$task) {
            throw new Exception("Task not found or not assigned to you");
        }

        if ($task['status'] === 'completed') {
            throw new Exception("This task is already completed");
        }

        if ($task['status'] === 'cancelled') {
            throw new Exception("Cannot complete a cancelled task");
        }

        // IMPORTANT: Set both started_at and completed_at to NOW()
        // This allows fair performance tracking using:
        // - On-Time Rate: completed_at vs scheduled_date (primary metric)
        // - Response Time: completed_at - created_at (secondary metric for same-day tasks)
        query("UPDATE tasks SET 
               status = 'completed', 
               started_at = NOW(),
               completed_at = NOW(),
               completion_notes = ?
               WHERE task_id = ?", 
               [$completion_notes ?: null, $task_id]);

        // **NEW: Auto-create collection report for collection tasks**
        if ($task['task_type'] === 'collection' && $task['triggered_by_bin']) {
            // Check if collection report already exists for this task
            $existing_report = getOne(
                "SELECT report_id FROM collection_reports WHERE task_id = ?", 
                [$task_id]
            );
            
            if (!$existing_report) {
                // Create collection report automatically
                query("INSERT INTO collection_reports (
                          task_id, 
                          bin_id, 
                          area_id, 
                          employee_id,
                          collection_date, 
                          collection_start, 
                          collection_end,
                          waste_condition,
                          report_notes,
                          is_auto_generated,
                          submitted_at
                       ) VALUES (?, ?, ?, ?, CURDATE(), NOW(), NOW(), 'normal', ?, 1, NOW())",
                       [
                           $task_id,
                           $task['triggered_by_bin'],
                           $task['area_id'],
                           $user_id,
                           $completion_notes ?: 'Auto-generated collection report'
                       ]);
            }
        }

        // If task has a bin, update bin status and set last_collection
        if ($task['triggered_by_bin']) {
            query("UPDATE bins SET 
                   status = 'normal',
                   current_fill_level = 0,
                   last_collection = NOW()
                   WHERE bin_id = ?", 
                   [$task['triggered_by_bin']]);
        }

        $_SESSION['success'] = "Task completed successfully! Great job! 🎉";
        header("Location: my_tasks.php");
        exit;

    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
        header("Location: my_tasks.php");
        exit;
    }
}

// Delete Task (Admin only - permanent deletion)
elseif ($action === 'delete' && $user_type === 'admin') {
    try {
        $task_id = $_POST['task_id'] ?? 0;

        $task = getOne("SELECT * FROM tasks WHERE task_id = ?", [$task_id]);
        
        if (!$task) {
            throw new Exception("Task not found");
        }

        // Delete related collection reports first (foreign key)
        query("DELETE FROM collection_reports WHERE task_id = ?", [$task_id]);

        // Delete the task
        query("DELETE FROM tasks WHERE task_id = ?", [$task_id]);

        $_SESSION['success'] = "Task deleted successfully";
        header("Location: tasks.php");
        exit;

    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
        header("Location: tasks.php");
        exit;
    }
}

// Invalid action
else {
    $_SESSION['error'] = "Invalid action or insufficient permissions";
    header("Location: tasks.php");
    exit;
}
?>