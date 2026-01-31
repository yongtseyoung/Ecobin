<?php

session_start();
date_default_timezone_set('Asia/Kuala_Lumpur');
require_once '../config/database.php';
require_once '../config/languages.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$action = $_POST['action'] ?? '';
$user_type = $_SESSION['user_type'] ?? '';
$user_id = $_SESSION['user_id'];

if ($user_type === 'employee') {
    $user = getOne("SELECT language FROM employees WHERE employee_id = ?", [$user_id]);
} else {
    $user = getOne("SELECT language FROM admins WHERE admin_id = ?", [$user_id]);
}
$_SESSION['language'] = $user['language'] ?? 'en';

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

        if (empty($task_title) || empty($task_type) || empty($assigned_to) || empty($scheduled_date)) {
            throw new Exception(t('fill_all_required_fields'));
        }

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

        $_SESSION['success'] = t('task_created_success');
        header("Location: tasks.php");
        exit;

    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
        header("Location: task_create.php");
        exit;
    }
}

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

        $existing_task = getOne("SELECT * FROM tasks WHERE task_id = ?", [$task_id]);
        
        if (!$existing_task) {
            throw new Exception(t('task_not_found'));
        }

        if ($existing_task['status'] === 'completed' || $existing_task['status'] === 'cancelled') {
            throw new Exception(t('cannot_edit_completed_cancelled'));
        }

        if (empty($task_title) || empty($task_type) || empty($assigned_to) || empty($scheduled_date)) {
            throw new Exception(t('fill_all_required_fields'));
        }

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

        $started_at_update = "";
        if ($status === 'in_progress' && $existing_task['status'] === 'pending') {
            $started_at_update = ", started_at = NOW()";
        }

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

        if ($status === 'completed' && $existing_task['triggered_by_bin']) {
            query("UPDATE bins SET 
                   status = 'normal',
                   current_fill_level = 0,
                   last_collection = NOW()
                   WHERE bin_id = ?", 
                   [$existing_task['triggered_by_bin']]);
            
            if ($existing_task['task_type'] === 'collection') {
                $existing_report = getOne(
                    "SELECT report_id FROM collection_reports WHERE task_id = ?", 
                    [$task_id]
                );
                
                if (!$existing_report) {
                    query("INSERT INTO collection_reports (
                              task_id, bin_id, area_id, employee_id,
                              collection_date, collection_start, collection_end,
                              waste_condition, report_notes,
                              is_auto_generated, submitted_at
                           ) VALUES (?, ?, ?, ?, CURDATE(), NOW(), NOW(), 'normal', ?, 1, NOW())",
                           [
                               $task_id,
                               $existing_task['triggered_by_bin'],
                               $existing_task['area_id'],
                               $assigned_to,
                               t('auto_generated_collection_report_admin')
                           ]);
                }
            }
        }

        $_SESSION['success'] = t('task_updated_success');
        header("Location: task_view.php?id=$task_id");
        exit;

    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
        header("Location: task_edit.php?id=" . ($task_id ?? 0));
        exit;
    }
}

elseif ($action === 'cancel' && $user_type === 'admin') {
    try {
        $task_id = $_POST['task_id'] ?? 0;

        $task = getOne("SELECT * FROM tasks WHERE task_id = ?", [$task_id]);
        
        if (!$task) {
            throw new Exception(t('task_not_found'));
        }

        if ($task['status'] === 'completed') {
            throw new Exception(t('cannot_cancel_completed'));
        }

        query("UPDATE tasks SET status = 'cancelled' WHERE task_id = ?", [$task_id]);

        $_SESSION['success'] = t('task_cancelled_success');
        header("Location: tasks.php");
        exit;

    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
        header("Location: task_view.php?id=" . ($task_id ?? 0));
        exit;
    }
}

elseif ($action === 'start' && $user_type === 'admin') {
    try {
        $task_id = $_POST['task_id'] ?? 0;

        $task = getOne("SELECT * FROM tasks WHERE task_id = ?", [$task_id]);
        
        if (!$task) {
            throw new Exception(t('task_not_found'));
        }

        if ($task['status'] !== 'pending') {
            throw new Exception(t('only_pending_can_start'));
        }

        query("UPDATE tasks SET status = 'in_progress', started_at = NOW() WHERE task_id = ?", [$task_id]);

        $_SESSION['success'] = t('task_started_success');
        header("Location: task_view.php?id=$task_id");
        exit;

    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
        header("Location: task_view.php?id=" . ($task_id ?? 0));
        exit;
    }
}

elseif ($action === 'employee_start' && $user_type === 'employee') {
    try {
        $task_id = $_POST['task_id'] ?? 0;

        $task = getOne("SELECT * FROM tasks WHERE task_id = ? AND assigned_to = ?", [$task_id, $user_id]);
        
        if (!$task) {
            throw new Exception(t('task_not_found_not_assigned'));
        }

        if ($task['status'] !== 'pending') {
            throw new Exception(t('task_already_started'));
        }

        query("UPDATE tasks SET status = 'in_progress', started_at = NOW() WHERE task_id = ?", [$task_id]);

        $_SESSION['success'] = t('task_started_good_luck');
        header("Location: my_tasks.php");
        exit;

    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
        header("Location: my_tasks.php");
        exit;
    }
}


elseif ($action === 'employee_complete' && $user_type === 'employee') {
    try {
        $task_id = $_POST['task_id'] ?? 0;
        $completion_notes = trim($_POST['completion_notes'] ?? '');

        $task = getOne("SELECT * FROM tasks WHERE task_id = ? AND assigned_to = ?", [$task_id, $user_id]);
        
        if (!$task) {
            throw new Exception(t('task_not_found_not_assigned'));
        }

        if ($task['status'] === 'completed') {
            throw new Exception(t('task_already_completed'));
        }

        if ($task['status'] === 'cancelled') {
            throw new Exception(t('cannot_complete_cancelled'));
        }

        $weight = null;
        if ($task['description']) {
            if (preg_match('/Weight:\s*([\d.]+)\s*kg/i', $task['description'], $matches)) {
                $weight = floatval($matches[1]);
            }
        }

        query("UPDATE tasks SET 
               status = 'completed', 
               started_at = NOW(),
               completed_at = NOW(),
               completion_notes = ?
               WHERE task_id = ?", 
               [$completion_notes ?: null, $task_id]);

        if ($task['task_type'] === 'collection' && $task['triggered_by_bin']) {
            $existing_report = getOne(
                "SELECT report_id FROM collection_reports WHERE task_id = ?", 
                [$task_id]
            );
            
            if (!$existing_report) {
                query("INSERT INTO collection_reports (
                          task_id, 
                          bin_id, 
                          area_id, 
                          employee_id,
                          collection_date, 
                          collection_start, 
                          collection_end,
                          total_weight,
                          waste_condition,
                          report_notes,
                          is_auto_generated,
                          submitted_at
                       ) VALUES (?, ?, ?, ?, CURDATE(), NOW(), NOW(), ?, 'normal', ?, 1, NOW())",
                       [
                           $task_id,
                           $task['triggered_by_bin'],
                           $task['area_id'],
                           $user_id,
                           $weight,
                           $completion_notes ?: t('auto_generated_collection_report')
                       ]);
            }
        }

        if ($task['triggered_by_bin']) {
            query("UPDATE bins SET 
                   status = 'normal',
                   current_fill_level = 0,
                   last_collection = NOW()
                   WHERE bin_id = ?", 
                   [$task['triggered_by_bin']]);
        }

        $_SESSION['success'] = t('task_completed_great_job');
        header("Location: my_tasks.php");
        exit;

    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
        header("Location: my_tasks.php");
        exit;
    }
}

elseif ($action === 'delete' && $user_type === 'admin') {
    try {
        $task_id = $_POST['task_id'] ?? 0;

        $task = getOne("SELECT * FROM tasks WHERE task_id = ?", [$task_id]);
        
        if (!$task) {
            throw new Exception(t('task_not_found'));
        }

        query("DELETE FROM collection_reports WHERE task_id = ?", [$task_id]);

        query("DELETE FROM tasks WHERE task_id = ?", [$task_id]);

        $_SESSION['success'] = t('task_deleted_success');
        header("Location: tasks.php");
        exit;

    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
        header("Location: tasks.php");
        exit;
    }
}

else {
    $_SESSION['error'] = t('invalid_action_insufficient_permissions');
    header("Location: tasks.php");
    exit;
}
?>