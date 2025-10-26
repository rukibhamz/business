<?php
// Step 1: System Requirements
$phpRequirements = checkPHPRequirements();
$directoryPermissions = checkDirectoryPermissions();
$serverRequirements = checkServerRequirements();

$allRequirementsMet = true;
?>

<div class="requirements-section">
    <h3>PHP Requirements</h3>
    <div class="requirements-list">
        <?php foreach ($phpRequirements as $key => $requirement): ?>
        <div class="requirement-item <?php echo $requirement['status'] ? 'success' : 'error'; ?>">
            <div class="requirement-name"><?php echo $requirement['name']; ?></div>
            <div class="requirement-status">
                <span class="status-badge status-<?php echo $requirement['status'] ? 'success' : 'error'; ?>">
                    <?php echo $requirement['status'] ? '✓' : '✗'; ?>
                </span>
                <?php echo $requirement['current']; ?>
            </div>
            <div class="requirement-message"><?php echo $requirement['message']; ?></div>
        </div>
        <?php if (!$requirement['status']) $allRequirementsMet = false; ?>
        <?php endforeach; ?>
    </div>
</div>

<div class="requirements-section">
    <h3>Directory Permissions</h3>
    <div class="requirements-list">
        <?php foreach ($directoryPermissions as $key => $permission): ?>
        <div class="requirement-item <?php echo $permission['status'] ? 'success' : 'error'; ?>">
            <div class="requirement-name"><?php echo $permission['name']; ?></div>
            <div class="requirement-status">
                <span class="status-badge status-<?php echo $permission['status'] ? 'success' : 'error'; ?>">
                    <?php echo $permission['status'] ? '✓' : '✗'; ?>
                </span>
                <?php echo $permission['required']; ?>
            </div>
            <div class="requirement-message"><?php echo $permission['message']; ?></div>
        </div>
        <?php if (!$permission['status']) $allRequirementsMet = false; ?>
        <?php endforeach; ?>
    </div>
</div>

<div class="requirements-section">
    <h3>Server Requirements</h3>
    <div class="requirements-list">
        <?php foreach ($serverRequirements as $key => $requirement): ?>
        <div class="requirement-item <?php echo $requirement['status'] ? 'success' : 'warning'; ?>">
            <div class="requirement-name"><?php echo $requirement['name']; ?></div>
            <div class="requirement-status">
                <span class="status-badge status-<?php echo $requirement['status'] ? 'success' : 'warning'; ?>">
                    <?php echo $requirement['status'] ? '✓' : '⚠'; ?>
                </span>
                <?php echo $requirement['current']; ?>
            </div>
            <div class="requirement-message"><?php echo $requirement['message']; ?></div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<div class="form-actions">
    <?php if ($allRequirementsMet): ?>
    <a href="?step=2" class="btn btn-primary">Continue to Database Setup</a>
    <?php else: ?>
    <div class="error-message">
        <p>Please fix the requirements above before continuing.</p>
    </div>
    <button type="button" class="btn btn-secondary" onclick="location.reload()">Refresh Requirements</button>
    <?php endif; ?>
</div>