<?php
/**
 * Step 1: System Requirements Check
 */

// Get requirements
$phpRequirements = checkPHPRequirements();
$directoryPermissions = checkDirectoryPermissions();
$serverRequirements = checkServerRequirements();

// Check if all requirements are met
$allRequirementsMet = true;
$phpPassed = true;
$directoriesPassed = true;
$serverPassed = true;

// Check PHP requirements
foreach ($phpRequirements as $req) {
    if (!$req['status']) {
        $phpPassed = false;
        $allRequirementsMet = false;
    }
}

// Check directory permissions
foreach ($directoryPermissions as $dir) {
    if (!$dir['status']) {
        $directoriesPassed = false;
        $allRequirementsMet = false;
    }
}

// Check server requirements
foreach ($serverRequirements as $req) {
    if (!$req['status']) {
        $serverPassed = false;
        $allRequirementsMet = false;
    }
}
?>

<div class="requirements-check">
    <div class="requirements-section">
        <h3><i class="icon-php"></i> PHP Requirements</h3>
        <div class="requirements-list">
            <?php foreach ($phpRequirements as $key => $req): ?>
            <div class="requirement-item <?php echo $req['status'] ? 'passed' : 'failed'; ?>">
                <div class="requirement-name">
                    <span class="status-icon"><?php echo $req['status'] ? '✓' : '✗'; ?></span>
                    <?php echo $req['name']; ?>
                </div>
                <div class="requirement-details">
                    <span class="required">Required: <?php echo $req['required']; ?></span>
                    <span class="current">Current: <?php echo $req['current']; ?></span>
                </div>
                <?php if (!$req['status']): ?>
                <div class="requirement-message"><?php echo $req['message']; ?></div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="section-status <?php echo $phpPassed ? 'passed' : 'failed'; ?>">
            <?php echo $phpPassed ? 'All PHP requirements met ✓' : 'Some PHP requirements failed ✗'; ?>
        </div>
    </div>

    <div class="requirements-section">
        <h3><i class="icon-folder"></i> Directory Permissions</h3>
        <div class="requirements-list">
            <?php foreach ($directoryPermissions as $key => $dir): ?>
            <div class="requirement-item <?php echo $dir['status'] ? 'passed' : 'failed'; ?>">
                <div class="requirement-name">
                    <span class="status-icon"><?php echo $dir['status'] ? '✓' : '✗'; ?></span>
                    <?php echo $dir['name']; ?>
                </div>
                <div class="requirement-details">
                    <span class="path"><?php echo $dir['path']; ?></span>
                    <span class="required"><?php echo $dir['required']; ?></span>
                </div>
                <?php if (!$dir['status']): ?>
                <div class="requirement-message"><?php echo $dir['message']; ?></div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="section-status <?php echo $directoriesPassed ? 'passed' : 'failed'; ?>">
            <?php echo $directoriesPassed ? 'All directory permissions correct ✓' : 'Some directory permissions failed ✗'; ?>
        </div>
    </div>

    <div class="requirements-section">
        <h3><i class="icon-server"></i> Server Requirements</h3>
        <div class="requirements-list">
            <?php foreach ($serverRequirements as $key => $req): ?>
            <div class="requirement-item <?php echo $req['status'] ? 'passed' : 'failed'; ?>">
                <div class="requirement-name">
                    <span class="status-icon"><?php echo $req['status'] ? '✓' : '✗'; ?></span>
                    <?php echo $req['name']; ?>
                </div>
                <div class="requirement-details">
                    <span class="required">Required: <?php echo $req['required']; ?></span>
                    <span class="current">Current: <?php echo $req['current']; ?></span>
                </div>
                <?php if (!$req['status']): ?>
                <div class="requirement-message"><?php echo $req['message']; ?></div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="section-status <?php echo $serverPassed ? 'passed' : 'failed'; ?>">
            <?php echo $serverPassed ? 'All server requirements met ✓' : 'Some server requirements failed ✗'; ?>
        </div>
    </div>
</div>

<div class="requirements-summary">
    <div class="summary-header">
        <h3>Installation Requirements Summary</h3>
    </div>
    <div class="summary-content">
        <div class="summary-item">
            <span class="label">PHP Version:</span>
            <span class="value <?php echo $phpPassed ? 'success' : 'error'; ?>">
                <?php echo $phpPassed ? 'Compatible' : 'Incompatible'; ?>
            </span>
        </div>
        <div class="summary-item">
            <span class="label">Directory Permissions:</span>
            <span class="value <?php echo $directoriesPassed ? 'success' : 'error'; ?>">
                <?php echo $directoriesPassed ? 'Correct' : 'Incorrect'; ?>
            </span>
        </div>
        <div class="summary-item">
            <span class="label">Server Configuration:</span>
            <span class="value <?php echo $serverPassed ? 'success' : 'error'; ?>">
                <?php echo $serverPassed ? 'Compatible' : 'Incompatible'; ?>
            </span>
        </div>
    </div>
    <div class="summary-status <?php echo $allRequirementsMet ? 'success' : 'error'; ?>">
        <?php if ($allRequirementsMet): ?>
            <i class="icon-check"></i>
            <strong>All requirements met! You can proceed with the installation.</strong>
        <?php else: ?>
            <i class="icon-warning"></i>
            <strong>Some requirements are not met. Please fix the issues above before continuing.</strong>
        <?php endif; ?>
    </div>
</div>

<div class="step-actions">
    <button type="button" class="btn btn-secondary" onclick="location.reload()">
        <i class="icon-refresh"></i> Refresh Check
    </button>
    
    <?php if ($allRequirementsMet): ?>
    <a href="?step=2" class="btn btn-primary">
        <i class="icon-arrow-right"></i> Continue to Database Setup
    </a>
    <?php else: ?>
    <button type="button" class="btn btn-primary" disabled>
        <i class="icon-lock"></i> Fix Requirements First
    </button>
    <?php endif; ?>
</div>

<div class="help-section">
    <h4>Need Help?</h4>
    <div class="help-content">
        <div class="help-item">
            <strong>PHP Extensions:</strong>
            <p>Contact your hosting provider to enable the required PHP extensions. Most shared hosting providers have these enabled by default.</p>
        </div>
        <div class="help-item">
            <strong>Directory Permissions:</strong>
            <p>Set the following directories to 755 permissions using your file manager or FTP client:</p>
            <ul>
                <li><code>/config</code> - For configuration files</li>
                <li><code>/uploads</code> - For file uploads</li>
                <li><code>/cache</code> - For system cache</li>
                <li><code>/logs</code> - For system logs</li>
            </ul>
        </div>
        <div class="help-item">
            <strong>Apache mod_rewrite:</strong>
            <p>Enable mod_rewrite in your Apache configuration or contact your hosting provider to enable it.</p>
        </div>
    </div>
</div>
