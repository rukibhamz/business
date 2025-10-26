<?php
// Step 5: Installation Complete
?>

<div class="completion-section">
    <div class="completion-icon">
        <div class="success-icon">✓</div>
    </div>
    
    <h3>Installation Complete!</h3>
    <p>Congratulations! Your Business Management System has been successfully installed and configured.</p>
    
    <div class="completion-details">
        <h4>What's Next?</h4>
        <ul>
            <li>✅ Database schema installed successfully</li>
            <li>✅ Configuration files created</li>
            <li>✅ Admin account created</li>
            <li>✅ System ready for use</li>
        </ul>
        
        <div class="important-notes">
            <h4>Important Notes:</h4>
            <ul>
                <li>Keep your admin credentials safe and secure</li>
                <li>Delete the <code>/install</code> directory for security</li>
                <li>Configure your web server settings as needed</li>
                <li>Set up regular database backups</li>
            </ul>
        </div>
    </div>
    
    <div class="completion-actions">
        <form method="POST" class="install-form">
            <input type="hidden" name="action" value="complete_installation">
            <button type="submit" class="btn btn-success btn-large">Access Admin Panel</button>
        </form>
        
        <div class="help-links">
            <p>Need help getting started?</p>
            <ul>
                <li><a href="../README.md" target="_blank">Read the Documentation</a></li>
                <li><a href="#" onclick="alert('Support information will be available after installation.'); return false;">Contact Support</a></li>
            </ul>
        </div>
    </div>
</div>

<style>
.completion-section {
    text-align: center;
    padding: 40px 20px;
}

.completion-icon {
    margin-bottom: 30px;
}

.success-icon {
    width: 80px;
    height: 80px;
    background: var(--success-color);
    color: white;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 2.5rem;
    font-weight: bold;
    margin: 0 auto;
}

.completion-section h3 {
    font-size: 2rem;
    color: var(--primary-color);
    margin: 0 0 15px 0;
}

.completion-section p {
    font-size: 1.1rem;
    color: var(--secondary-color);
    margin: 0 0 30px 0;
}

.completion-details {
    background: var(--light-color);
    padding: 25px;
    border-radius: var(--border-radius);
    border: 1px solid var(--border-color);
    margin-bottom: 30px;
    text-align: left;
}

.completion-details h4 {
    color: var(--primary-color);
    margin: 0 0 15px 0;
    font-size: 1.2rem;
}

.completion-details ul {
    margin: 0 0 20px 0;
    padding-left: 20px;
}

.completion-details li {
    margin-bottom: 8px;
    color: var(--secondary-color);
}

.important-notes {
    background: white;
    padding: 20px;
    border-radius: var(--border-radius);
    border: 1px solid var(--border-color);
}

.important-notes h4 {
    color: var(--warning-color);
    margin: 0 0 15px 0;
}

.important-notes code {
    background: var(--light-color);
    padding: 2px 6px;
    border-radius: 3px;
    font-family: 'Courier New', monospace;
    font-size: 0.9rem;
}

.completion-actions {
    margin-top: 30px;
}

.btn-large {
    padding: 15px 40px;
    font-size: 1.1rem;
    font-weight: 600;
}

.help-links {
    margin-top: 25px;
    padding-top: 20px;
    border-top: 1px solid var(--border-color);
}

.help-links p {
    margin: 0 0 10px 0;
    color: var(--secondary-color);
}

.help-links ul {
    list-style: none;
    padding: 0;
    margin: 0;
}

.help-links li {
    margin-bottom: 8px;
}

.help-links a {
    color: var(--primary-color);
    text-decoration: none;
    font-weight: 500;
}

.help-links a:hover {
    text-decoration: underline;
}
</style>