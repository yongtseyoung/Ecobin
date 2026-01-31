<?php

session_start();
date_default_timezone_set('Asia/Kuala_Lumpur');
require_once '../config/database.php';
require_once '../config/languages.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'employee') {
    header("Location: ../login.php");
    exit;
}

$current_page = 'maintenance';

$employee_id = $_SESSION['user_id'];
$employee_name = $_SESSION['full_name'] ?? 'Employee';

$employee = getOne("SELECT e.*, a.area_name 
                    FROM employees e 
                    LEFT JOIN areas a ON e.area_id = a.area_id 
                    WHERE e.employee_id = ?", 
                    [$employee_id]);


$success = $_SESSION['success'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);
?>
<!DOCTYPE html>
<html lang="<?php echo $_SESSION['language']; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo t('report_issue'); ?> - EcoBin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #FAF1E4;
            display: flex;
            min-height: 100vh;
        }

        .main-content {
            margin-left: 250px;
            flex: 1;
            padding: 30px;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .page-header h1 {
            font-size: 32px;
            color: #435334;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
        }

        .btn-primary {
            background: #435334;
            color: white;
        }

        .btn-primary:hover {
            background: #354428;
            transform: translateY(-2px);
        }

        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            animation: slideDown 0.3s ease;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
            border-left: 4px solid #28a745;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
            border-left: 4px solid #dc3545;
        }

        .form-card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            max-width: 900px;
        }

        .form-card h2 {
            color: #435334;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .form-card .subtitle {
            color: #666;
            margin-bottom: 30px;
            font-size: 14px;
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-group label {
            display: block;
            font-size: 14px;
            font-weight: 600;
            color: #435334;
            margin-bottom: 8px;
        }

        .form-group label i {
            margin-right: 5px;
            color: #666;
        }

        .form-group label .required {
            color: #dc3545;
        }

        .form-group input[type="text"],
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 14px;
            font-family: inherit;
            transition: border-color 0.3s;
        }

        .form-group input[type="file"] {
            width: 100%;
            padding: 10px;
            border: 2px dashed #e0e0e0;
            border-radius: 10px;
            font-size: 14px;
            cursor: pointer;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #435334;
        }

        .form-group .hint {
            font-size: 12px;
            color: #999;
            margin-top: 5px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .info-box {
            background: #e3f2fd;
            border-left: 4px solid #2196f3;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 25px;
        }

        .info-box h4 {
            color: #1976d2;
            margin-bottom: 8px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .info-box ul {
            margin-left: 20px;
            color: #555;
            font-size: 13px;
        }

        .info-box li {
            margin: 5px 0;
        }

        .photo-preview {
            margin-top: 15px;
            display: none;
        }

        .photo-preview img {
            max-width: 300px;
            max-height: 200px;
            border-radius: 10px;
            border: 2px solid #e0e0e0;
        }

        .priority-badge {
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .priority-low {
            background: #e8f5e9;
            color: #2e7d32;
        }

        .priority-medium {
            background: #fff3cd;
            color: #856404;
        }

        .priority-high {
            background: #f8d7da;
            color: #721c24;
        }

@media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 80px 15px 20px;
            }

            .page-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }

            .page-header h1 {
                font-size: 24px;
            }

            .btn {
                width: 100%;
                justify-content: center;
            }

            .alert {
                padding: 12px 15px;
                font-size: 13px;
            }

            .form-card {
                padding: 20px;
                max-width: 100%;
            }

            .form-card h2 {
                font-size: 20px;
            }

            .form-card .subtitle {
                font-size: 13px;
                margin-bottom: 20px;
            }

            .form-row {
                grid-template-columns: 1fr;
                gap: 0;
            }

            .form-group {
                margin-bottom: 20px;
            }

            .form-group label {
                font-size: 13px;
            }

            .form-group input[type="text"],
            .form-group select,
            .form-group textarea {
                padding: 10px 12px;
                font-size: 14px;
            }

            .form-group textarea {
                min-height: 120px;
            }

            .form-group .hint {
                font-size: 11px;
            }

            .info-box {
                padding: 12px;
                margin-bottom: 20px;
            }

            .info-box h4 {
                font-size: 13px;
            }

            .info-box ul {
                font-size: 12px;
            }

            .photo-preview img {
                max-width: 100%;
                height: auto;
            }

            .priority-badge {
                padding: 6px 12px;
                font-size: 11px;
            }

            .btn-primary[type="submit"] {
                width: 100%;
                font-size: 15px !important;
                padding: 14px 24px !important;
            }
        }

        @media (max-width: 480px) {
            .main-content {
                padding: 70px 10px 15px;
            }

            .page-header {
                margin-bottom: 20px;
            }

            .page-header h1 {
                font-size: 20px;
            }

            .btn {
                padding: 10px 18px;
                font-size: 13px;
            }

            .alert {
                padding: 10px 12px;
                font-size: 12px;
            }

            .form-card {
                padding: 15px;
                border-radius: 12px;
            }

            .form-card h2 {
                font-size: 18px;
                margin-bottom: 8px;
            }

            .form-card .subtitle {
                font-size: 12px;
                margin-bottom: 15px;
            }

            .form-group {
                margin-bottom: 15px;
            }

            .form-group label {
                font-size: 12px;
                margin-bottom: 6px;
            }

            .form-group label i {
                font-size: 11px;
            }

            .form-group input[type="text"],
            .form-group select,
            .form-group textarea {
                padding: 10px;
                font-size: 13px;
                border-radius: 8px;
            }

            .form-group input[type="file"] {
                padding: 8px;
                font-size: 12px;
            }

            .form-group textarea {
                min-height: 100px;
            }

            .form-group .hint {
                font-size: 10px;
                margin-top: 4px;
            }

            .info-box {
                padding: 10px;
                margin-bottom: 15px;
            }

            .info-box h4 {
                font-size: 12px;
                margin-bottom: 6px;
            }

            .info-box ul {
                font-size: 11px;
                margin-left: 15px;
            }

            .info-box li {
                margin: 4px 0;
            }

            .photo-preview {
                margin-top: 10px;
            }

            .photo-preview img {
                max-width: 100%;
                border-radius: 8px;
            }

            .priority-badge {
                padding: 5px 10px;
                font-size: 10px;
            }

            .btn-primary[type="submit"] {
                width: 100%;
                font-size: 14px !important;
                padding: 12px 20px !important;
            }
        }
    </style>
</head>
<body>
    <?php include '../includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="page-header">
            <h1>
                <i class="fa-solid fa-clipboard-check"></i>
                <?php echo t('report_issue'); ?>
            </h1>
            <a href="my_reports.php" class="btn btn-primary">
                <i class="fa-solid fa-clipboard-list"></i>
                <?php echo t('view_my_reports'); ?>
            </a>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fa-solid fa-circle-check"></i>
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fa-solid fa-triangle-exclamation"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <div class="form-card">
            <h2>
                <?php echo t('submit_maintenance_report'); ?>
            </h2>

            <form action="report_actions.php" method="POST" enctype="multipart/form-data" onsubmit="return validateForm()">
                <input type="hidden" name="action" value="submit_report">

                <div class="form-group">
                    <label>
                        <?php echo t('issue_title'); ?> <span class="required">*</span>
                    </label>
                    <input type="text" name="issue_title" required maxlength="255" placeholder="<?php echo t('issue_title_placeholder'); ?>">
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>
                            <?php echo t('category'); ?> <span class="required">*</span>
                        </label>
                        <select name="issue_category" required>
                            <option value=""><?php echo t('select_category'); ?>...</option>
                            <option value="bin_issue">🗑️ <?php echo t('bin_issue'); ?></option>
                            <option value="equipment_issue">🧹 <?php echo t('equipment_issue'); ?></option>
                            <option value="facility_issue">🏢 <?php echo t('facility_issue'); ?></option>
                            <option value="safety_hazard">⚠️ <?php echo t('safety_hazard'); ?></option>
                            <option value="other">📦 <?php echo t('other'); ?></option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>
                            <?php echo t('priority'); ?> <span class="required">*</span>
                        </label>
                        <select name="priority" id="prioritySelect" required onchange="updatePriorityInfo()">
                            <option value="low"><?php echo t('low'); ?></option>
                            <option value="medium" selected><?php echo t('medium'); ?></option>
                            <option value="high"><?php echo t('high'); ?></option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>
                        <?php echo t('location'); ?> <span class="required">*</span>
                    </label>
                    <input type="text" name="location" required maxlength="255" placeholder="<?php echo t('location_placeholder'); ?>">
                </div>

                <div class="form-group">
                    <label>
                        <?php echo t('issue_description'); ?> <span class="required">*</span>
                    </label>
                    <textarea name="issue_description" rows="5" required placeholder="<?php echo t('issue_description_placeholder'); ?>"></textarea>
                </div>

                <div class="form-group">
                    <label>
                        <?php echo t('photo_evidence_optional'); ?>
                    </label>
                    <input type="file" name="photo" id="photoInput" accept="image/*" onchange="previewPhoto(event)">
                    <div class="hint">
                        (<?php echo t('max_5mb_jpg_png'); ?>)
                    </div>
                    <div class="photo-preview" id="photoPreview">
                        <img id="previewImage" src="" alt="<?php echo t('photo_preview'); ?>">
                    </div>
                </div>

                <button type="submit" class="btn btn-primary" style="font-size: 16px; padding: 14px 32px;">
                    <i class="fa-solid fa-paper-plane"></i>
                    <?php echo t('submit_report'); ?>
                </button>
            </form>
        </div>
    </main>

    <script>
        const translations = {
            fileSizeError: "<?php echo t('file_size_error'); ?>",
            fileTypeError: "<?php echo t('file_type_error'); ?>",
            fillAllFields: "<?php echo t('fill_all_required_fields'); ?>",
            titleTooShort: "<?php echo t('title_too_short'); ?>",
            descriptionTooShort: "<?php echo t('description_too_short'); ?>",
            confirmSubmit: "<?php echo t('confirm_submit_report'); ?>",
            priorityLowDesc: "<?php echo t('priority_low_desc'); ?>",
            priorityMediumDesc: "<?php echo t('priority_medium_desc'); ?>",
            priorityHighDesc: "<?php echo t('priority_high_desc'); ?>"
        };

        function previewPhoto(event) {
            const file = event.target.files[0];
            const preview = document.getElementById('photoPreview');
            const previewImage = document.getElementById('previewImage');

            if (file) {
                if (file.size > 5 * 1024 * 1024) {
                    alert(translations.fileSizeError);
                    event.target.value = '';
                    preview.style.display = 'none';
                    return;
                }

                if (!file.type.match('image/(jpeg|jpg|png)')) {
                    alert(translations.fileTypeError);
                    event.target.value = '';
                    preview.style.display = 'none';
                    return;
                }

                const reader = new FileReader();
                reader.onload = function(e) {
                    previewImage.src = e.target.result;
                    preview.style.display = 'block';
                };
                reader.readAsDataURL(file);
            } else {
                preview.style.display = 'none';
            }
        }

        function updatePriorityInfo() {
            const select = document.getElementById('prioritySelect');
            const info = document.getElementById('priorityInfo');
            const priority = select.value;

            let badge = '';
            if (priority === 'low') {
                badge = '<span class="priority-badge priority-low"><i class="fa-solid fa-circle-dot"></i> ' + translations.priorityLowDesc + '</span>';
            } else if (priority === 'medium') {
                badge = '<span class="priority-badge priority-medium"><i class="fa-solid fa-circle"></i> ' + translations.priorityMediumDesc + '</span>';
            } else if (priority === 'high') {
                badge = '<span class="priority-badge priority-high"><i class="fa-solid fa-circle-exclamation"></i> ' + translations.priorityHighDesc + '</span>';
            }

            info.innerHTML = badge;
        }

        function validateForm() {
            const title = document.querySelector('input[name="issue_title"]').value.trim();
            const category = document.querySelector('select[name="issue_category"]').value;
            const location = document.querySelector('input[name="location"]').value.trim();
            const description = document.querySelector('textarea[name="issue_description"]').value.trim();

            if (!title || !category || !location || !description) {
                alert(translations.fillAllFields);
                return false;
            }

            if (title.length < 5) {
                alert(translations.titleTooShort);
                return false;
            }

            if (description.length < 10) {
                alert(translations.descriptionTooShort);
                return false;
            }

            return confirm(translations.confirmSubmit);
        }

        <?php if ($success): ?>
        setTimeout(() => {
            const alert = document.querySelector('.alert-success');
            if (alert) {
                alert.style.transition = 'opacity 0.5s';
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 500);
            }
        }, 5000);
        <?php endif; ?>
    </script>
</body>
</html>