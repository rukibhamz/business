<?php
/**
 * Business Management System - Email Templates Management
 * Phase 4: Hall Booking System Module
 */

// Define system constant
define('BMS_SYSTEM', true);

// Start session
session_start();

// Include required files
require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../includes/auth.php';
require_once '../../../includes/csrf.php';
require_once '../../../includes/hall-functions.php';

// Check authentication and permissions
requireLogin();
requirePermission('halls.settings');

// Get database connection
$conn = getDB();

// Process form submission
$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!validateCSRFToken($_POST['csrf_token'])) {
        $errors[] = 'Invalid request. Please try again.';
    } else {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'update_template') {
            $templateId = (int)($_POST['template_id'] ?? 0);
            $subject = trim($_POST['subject'] ?? '');
            $body = trim($_POST['body'] ?? '');
            
            if (empty($subject)) {
                $errors[] = 'Email subject is required';
            }
            
            if (empty($body)) {
                $errors[] = 'Email body is required';
            }
            
            if (empty($errors)) {
                $stmt = $conn->prepare("
                    UPDATE " . DB_PREFIX . "hall_email_templates 
                    SET subject = ?, body = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->bind_param('ssi', $subject, $body, $templateId);
                
                if ($stmt->execute()) {
                    $success = true;
                } else {
                    $errors[] = 'Failed to update template. Please try again.';
                }
            }
        }
    }
}

// Get email templates
$templates = $conn->query("
    SELECT * FROM " . DB_PREFIX . "hall_email_templates 
    ORDER BY template_type, template_name
")->fetch_all(MYSQLI_ASSOC);

// Group templates by type
$templateGroups = [];
foreach ($templates as $template) {
    $templateGroups[$template['template_type']][] = $template;
}

// Set page title
$pageTitle = 'Email Templates';

include '../../includes/header.php';
?>

<div class="page-header">
    <div class="page-title">
        <h1>Email Templates</h1>
        <p>Manage automated email templates for hall bookings</p>
    </div>
    <div class="page-actions">
        <a href="../settings.php" class="btn btn-secondary">
            <i class="icon-arrow-left"></i> Back to Settings
        </a>
    </div>
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger">
    <h4><i class="icon-exclamation-triangle"></i> Please correct the following errors:</h4>
    <ul class="mb-0">
        <?php foreach ($errors as $error): ?>
        <li><?php echo htmlspecialchars($error); ?></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<?php if ($success): ?>
<div class="alert alert-success">
    <i class="icon-check-circle"></i> Email template updated successfully!
</div>
<?php endif; ?>

<div class="row">
    <?php foreach ($templateGroups as $type => $templates): ?>
    <div class="col-lg-6 mb-4">
        <div class="card">
            <div class="card-header">
                <h3><i class="icon-envelope"></i> <?php echo htmlspecialchars($type); ?> Templates</h3>
            </div>
            <div class="card-body">
                <?php foreach ($templates as $template): ?>
                <div class="template-item">
                    <div class="template-header">
                        <h5><?php echo htmlspecialchars($template['template_name']); ?></h5>
                        <div class="template-actions">
                            <button class="btn btn-sm btn-outline-primary" 
                                    onclick="editTemplate(<?php echo $template['id']; ?>, '<?php echo htmlspecialchars($template['template_name']); ?>', '<?php echo htmlspecialchars($template['subject']); ?>', `<?php echo htmlspecialchars($template['body']); ?>`)">
                                <i class="icon-edit"></i> Edit
                            </button>
                        </div>
                    </div>
                    <div class="template-preview">
                        <div class="preview-subject">
                            <strong>Subject:</strong> <?php echo htmlspecialchars($template['subject']); ?>
                        </div>
                        <div class="preview-body">
                            <strong>Body Preview:</strong>
                            <div class="preview-content">
                                <?php echo htmlspecialchars(substr(strip_tags($template['body']), 0, 150)); ?>
                                <?php if (strlen(strip_tags($template['body'])) > 150): ?>
                                    <span class="text-muted">...</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Edit Template Modal -->
<div class="modal fade" id="editTemplateModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Email Template</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form method="POST" id="editTemplateForm">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="update_template">
                    <input type="hidden" name="template_id" id="template_id">
                    
                    <div class="form-group">
                        <label for="template_name">Template Name</label>
                        <input type="text" id="template_name" class="form-control" readonly>
                    </div>
                    
                    <div class="form-group">
                        <label for="email_subject">Email Subject *</label>
                        <input type="text" name="subject" id="email_subject" class="form-control" required>
                        <small class="form-text text-muted">Use variables like {{customer_name}}, {{hall_name}}, {{booking_number}}</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="email_body">Email Body *</label>
                        <textarea name="body" id="email_body" rows="15" class="form-control" required></textarea>
                        <small class="form-text text-muted">
                            Available variables: {{customer_name}}, {{hall_name}}, {{booking_number}}, {{event_name}}, 
                            {{booking_date}}, {{start_time}}, {{end_time}}, {{total_amount}}, {{payment_amount}}
                        </small>
                    </div>
                    
                    <div class="template-variables">
                        <h6>Available Variables:</h6>
                        <div class="variable-list">
                            <span class="badge badge-info" onclick="insertVariable('{{customer_name}}')">{{customer_name}}</span>
                            <span class="badge badge-info" onclick="insertVariable('{{hall_name}}')">{{hall_name}}</span>
                            <span class="badge badge-info" onclick="insertVariable('{{booking_number}}')">{{booking_number}}</span>
                            <span class="badge badge-info" onclick="insertVariable('{{event_name}}')">{{event_name}}</span>
                            <span class="badge badge-info" onclick="insertVariable('{{booking_date}}')">{{booking_date}}</span>
                            <span class="badge badge-info" onclick="insertVariable('{{start_time}}')">{{start_time}}</span>
                            <span class="badge badge-info" onclick="insertVariable('{{end_time}}')">{{end_time}}</span>
                            <span class="badge badge-info" onclick="insertVariable('{{total_amount}}')">{{total_amount}}</span>
                            <span class="badge badge-info" onclick="insertVariable('{{payment_amount}}')">{{payment_amount}}</span>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Template</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editTemplate(id, name, subject, body) {
    document.getElementById('template_id').value = id;
    document.getElementById('template_name').value = name;
    document.getElementById('email_subject').value = subject;
    document.getElementById('email_body').value = body;
    $('#editTemplateModal').modal('show');
}

function insertVariable(variable) {
    const textarea = document.getElementById('email_body');
    const start = textarea.selectionStart;
    const end = textarea.selectionEnd;
    const text = textarea.value;
    const before = text.substring(0, start);
    const after = text.substring(end, text.length);
    
    textarea.value = before + variable + after;
    textarea.selectionStart = textarea.selectionEnd = start + variable.length;
    textarea.focus();
}
</script>

<style>
.template-item {
    border: 1px solid #eee;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 20px;
    background-color: #f8f9fa;
}

.template-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
}

.template-header h5 {
    margin: 0;
    color: #333;
}

.template-preview {
    font-size: 14px;
}

.preview-subject {
    margin-bottom: 10px;
    color: #666;
}

.preview-body {
    color: #666;
}

.preview-content {
    background-color: white;
    padding: 10px;
    border-radius: 4px;
    margin-top: 5px;
    border-left: 3px solid #007bff;
}

.template-variables {
    margin-top: 20px;
    padding-top: 20px;
    border-top: 1px solid #eee;
}

.variable-list {
    display: flex;
    flex-wrap: wrap;
    gap: 5px;
    margin-top: 10px;
}

.variable-list .badge {
    cursor: pointer;
    font-size: 12px;
}

.variable-list .badge:hover {
    background-color: #0056b3;
}
</style>

<?php include '../../includes/footer.php'; ?>

