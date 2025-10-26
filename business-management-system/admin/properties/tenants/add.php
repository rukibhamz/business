<?php
/**
 * Business Management System - Add Tenant
 * Phase 5: Property Management & Rent Expiry System
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
require_once '../../../includes/property-functions.php';

// Check authentication and permissions
requireLogin();
requirePermission('tenants.create');

// Get database connection
$conn = getDB();

$errors = [];
$success = false;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'])) {
        $errors[] = 'Invalid security token. Please try again.';
    } else {
        // Validate required fields
        $requiredFields = ['first_name', 'last_name', 'email', 'phone'];
        foreach ($requiredFields as $field) {
            if (empty($_POST[$field])) {
                $errors[] = ucfirst(str_replace('_', ' ', $field)) . ' is required.';
            }
        }
        
        // Validate email format
        if (!empty($_POST['email']) && !filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address.';
        }
        
        // Check if email already exists
        if (!empty($_POST['email'])) {
            $stmt = $conn->prepare("SELECT id FROM " . DB_PREFIX . "tenants WHERE email = ?");
            $stmt->bind_param('s', $_POST['email']);
            $stmt->execute();
            if ($stmt->get_result()->fetch_assoc()) {
                $errors[] = 'A tenant with this email address already exists.';
            }
        }
        
        if (empty($errors)) {
            $conn->begin_transaction();
            
            try {
                // Generate tenant code
                $tenantCode = generateTenantCode();
                
                // Handle file uploads
                $passportPhoto = null;
                $idDocument = null;
                $employmentLetter = null;
                $bankStatement = null;
                
                // Upload passport photo
                if (isset($_FILES['passport_photo']) && $_FILES['passport_photo']['error'] === UPLOAD_ERR_OK) {
                    $uploadDir = '../../../uploads/tenants/photos/';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0755, true);
                    }
                    
                    $fileName = time() . '_photo_' . $_FILES['passport_photo']['name'];
                    $filePath = $uploadDir . $fileName;
                    
                    if (move_uploaded_file($_FILES['passport_photo']['tmp_name'], $filePath)) {
                        $passportPhoto = 'uploads/tenants/photos/' . $fileName;
                    }
                }
                
                // Upload ID document
                if (isset($_FILES['id_document']) && $_FILES['id_document']['error'] === UPLOAD_ERR_OK) {
                    $uploadDir = '../../../uploads/tenants/documents/';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0755, true);
                    }
                    
                    $fileName = time() . '_id_' . $_FILES['id_document']['name'];
                    $filePath = $uploadDir . $fileName;
                    
                    if (move_uploaded_file($_FILES['id_document']['tmp_name'], $filePath)) {
                        $idDocument = 'uploads/tenants/documents/' . $fileName;
                    }
                }
                
                // Upload employment letter
                if (isset($_FILES['employment_letter']) && $_FILES['employment_letter']['error'] === UPLOAD_ERR_OK) {
                    $uploadDir = '../../../uploads/tenants/documents/';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0755, true);
                    }
                    
                    $fileName = time() . '_employment_' . $_FILES['employment_letter']['name'];
                    $filePath = $uploadDir . $fileName;
                    
                    if (move_uploaded_file($_FILES['employment_letter']['tmp_name'], $filePath)) {
                        $employmentLetter = 'uploads/tenants/documents/' . $fileName;
                    }
                }
                
                // Upload bank statement
                if (isset($_FILES['bank_statement']) && $_FILES['bank_statement']['error'] === UPLOAD_ERR_OK) {
                    $uploadDir = '../../../uploads/tenants/documents/';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0755, true);
                    }
                    
                    $fileName = time() . '_bank_' . $_FILES['bank_statement']['name'];
                    $filePath = $uploadDir . $fileName;
                    
                    if (move_uploaded_file($_FILES['bank_statement']['tmp_name'], $filePath)) {
                        $bankStatement = 'uploads/tenants/documents/' . $fileName;
                    }
                }
                
                // Insert tenant
                $stmt = $conn->prepare("
                    INSERT INTO " . DB_PREFIX . "tenants (
                        tenant_code, title, first_name, last_name, middle_name, date_of_birth,
                        gender, marital_status, occupation, employer_name, employer_address,
                        email, phone, alt_phone, current_address, permanent_address, city,
                        state, country, id_type, id_number, id_document, passport_photo,
                        emergency_contact_name, emergency_contact_phone, emergency_contact_relationship,
                        employment_letter, bank_statement, reference_name, reference_phone,
                        reference_address, notes, status, created_by
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                $stmt->bind_param('ssssssssssssssssssssssssssssssssssi',
                    $tenantCode,
                    $_POST['title'],
                    $_POST['first_name'],
                    $_POST['last_name'],
                    $_POST['middle_name'],
                    $_POST['date_of_birth'],
                    $_POST['gender'],
                    $_POST['marital_status'],
                    $_POST['occupation'],
                    $_POST['employer_name'],
                    $_POST['employer_address'],
                    $_POST['email'],
                    $_POST['phone'],
                    $_POST['alt_phone'],
                    $_POST['current_address'],
                    $_POST['permanent_address'],
                    $_POST['city'],
                    $_POST['state'],
                    $_POST['country'],
                    $_POST['id_type'],
                    $_POST['id_number'],
                    $idDocument,
                    $passportPhoto,
                    $_POST['emergency_contact_name'],
                    $_POST['emergency_contact_phone'],
                    $_POST['emergency_contact_relationship'],
                    $employmentLetter,
                    $bankStatement,
                    $_POST['reference_name'],
                    $_POST['reference_phone'],
                    $_POST['reference_address'],
                    $_POST['notes'],
                    $_POST['status'],
                    $_SESSION['user_id']
                );
                
                $stmt->execute();
                /** @var mysqli $conn */
                $tenantId = $conn->insert_id;
                
                // Create customer account if requested
                if (!empty($_POST['create_customer_account'])) {
                    $stmt = $conn->prepare("
                        INSERT INTO " . DB_PREFIX . "customers (
                            customer_code, first_name, last_name, email, phone, address,
                            city, state, country, customer_type, status, created_by
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Individual', 'Active', ?)
                    ");
                    
                    $customerCode = 'CUST-' . str_pad($tenantId, 4, '0', STR_PAD_LEFT);
                    $stmt->bind_param('sssssssssi',
                        $customerCode,
                        $_POST['first_name'],
                        $_POST['last_name'],
                        $_POST['email'],
                        $_POST['phone'],
                        $_POST['current_address'],
                        $_POST['city'],
                        $_POST['state'],
                        $_POST['country'],
                        $_SESSION['user_id']
                    );
                    $stmt->execute();
                    /** @var mysqli $conn */
                    $customerId = $conn->insert_id;
                    
                    // Update tenant with customer ID
                    $stmt = $conn->prepare("UPDATE " . DB_PREFIX . "tenants SET customer_id = ? WHERE id = ?");
                    $stmt->bind_param('ii', $customerId, $tenantId);
                    $stmt->execute();
                }
                
                $conn->commit();
                $success = true;
                
                // Redirect to tenant view
                header('Location: view.php?id=' . $tenantId . '&success=created');
                exit;
                
            } catch (Exception $e) {
                $conn->rollback();
                $errors[] = 'Error creating tenant: ' . $e->getMessage();
            }
        }
    }
}

// Set page title
$pageTitle = 'Add Tenant';

include '../../includes/header.php';
?>

<div class="page-header">
    <div class="page-title">
        <h1>Add Tenant</h1>
        <p>Create a new tenant record</p>
    </div>
    <div class="page-actions">
        <a href="index.php" class="btn btn-secondary">
            <i class="icon-arrow-left"></i> Back to Tenants
        </a>
    </div>
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger">
    <ul class="mb-0">
        <?php foreach ($errors as $error): ?>
        <li><?php echo htmlspecialchars($error); ?></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<?php if ($success): ?>
<div class="alert alert-success">
    Tenant created successfully!
</div>
<?php endif; ?>

<form method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
    
    <!-- Tab Navigation -->
    <ul class="nav nav-tabs mb-4" id="tenantTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="personal-tab" data-bs-toggle="tab" data-bs-target="#personal" type="button" role="tab">
                Personal Information
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="contact-tab" data-bs-toggle="tab" data-bs-target="#contact" type="button" role="tab">
                Contact Information
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="employment-tab" data-bs-toggle="tab" data-bs-target="#employment" type="button" role="tab">
                Employment Information
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="identification-tab" data-bs-toggle="tab" data-bs-target="#identification" type="button" role="tab">
                Identification
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="emergency-tab" data-bs-toggle="tab" data-bs-target="#emergency" type="button" role="tab">
                Emergency Contact
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="reference-tab" data-bs-toggle="tab" data-bs-target="#reference" type="button" role="tab">
                Reference
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="documents-tab" data-bs-toggle="tab" data-bs-target="#documents" type="button" role="tab">
                Documents
            </button>
        </li>
    </ul>
    
    <!-- Tab Content -->
    <div class="tab-content" id="tenantTabsContent">
        <!-- Personal Information Tab -->
        <div class="tab-pane fade show active" id="personal" role="tabpanel">
            <div class="card">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-2">
                            <div class="mb-3">
                                <label for="title" class="form-label">Title</label>
                                <select class="form-select" id="title" name="title">
                                    <option value="Mr" <?php echo (!isset($_POST['title']) || $_POST['title'] == 'Mr') ? 'selected' : ''; ?>>Mr</option>
                                    <option value="Mrs" <?php echo (isset($_POST['title']) && $_POST['title'] == 'Mrs') ? 'selected' : ''; ?>>Mrs</option>
                                    <option value="Miss" <?php echo (isset($_POST['title']) && $_POST['title'] == 'Miss') ? 'selected' : ''; ?>>Miss</option>
                                    <option value="Dr" <?php echo (isset($_POST['title']) && $_POST['title'] == 'Dr') ? 'selected' : ''; ?>>Dr</option>
                                    <option value="Prof" <?php echo (isset($_POST['title']) && $_POST['title'] == 'Prof') ? 'selected' : ''; ?>>Prof</option>
                                    <option value="Chief" <?php echo (isset($_POST['title']) && $_POST['title'] == 'Chief') ? 'selected' : ''; ?>>Chief</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="first_name" class="form-label">First Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="first_name" name="first_name" required 
                                       value="<?php echo isset($_POST['first_name']) ? htmlspecialchars($_POST['first_name']) : ''; ?>">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label for="middle_name" class="form-label">Middle Name</label>
                                <input type="text" class="form-control" id="middle_name" name="middle_name" 
                                       value="<?php echo isset($_POST['middle_name']) ? htmlspecialchars($_POST['middle_name']) : ''; ?>">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label for="last_name" class="form-label">Last Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="last_name" name="last_name" required 
                                       value="<?php echo isset($_POST['last_name']) ? htmlspecialchars($_POST['last_name']) : ''; ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="date_of_birth" class="form-label">Date of Birth</label>
                                <input type="date" class="form-control" id="date_of_birth" name="date_of_birth" 
                                       value="<?php echo isset($_POST['date_of_birth']) ? htmlspecialchars($_POST['date_of_birth']) : ''; ?>">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="gender" class="form-label">Gender</label>
                                <select class="form-select" id="gender" name="gender">
                                    <option value="">Select Gender</option>
                                    <option value="Male" <?php echo (isset($_POST['gender']) && $_POST['gender'] == 'Male') ? 'selected' : ''; ?>>Male</option>
                                    <option value="Female" <?php echo (isset($_POST['gender']) && $_POST['gender'] == 'Female') ? 'selected' : ''; ?>>Female</option>
                                    <option value="Other" <?php echo (isset($_POST['gender']) && $_POST['gender'] == 'Other') ? 'selected' : ''; ?>>Other</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="marital_status" class="form-label">Marital Status</label>
                                <select class="form-select" id="marital_status" name="marital_status">
                                    <option value="">Select Status</option>
                                    <option value="Single" <?php echo (isset($_POST['marital_status']) && $_POST['marital_status'] == 'Single') ? 'selected' : ''; ?>>Single</option>
                                    <option value="Married" <?php echo (isset($_POST['marital_status']) && $_POST['marital_status'] == 'Married') ? 'selected' : ''; ?>>Married</option>
                                    <option value="Divorced" <?php echo (isset($_POST['marital_status']) && $_POST['marital_status'] == 'Divorced') ? 'selected' : ''; ?>>Divorced</option>
                                    <option value="Widowed" <?php echo (isset($_POST['marital_status']) && $_POST['marital_status'] == 'Widowed') ? 'selected' : ''; ?>>Widowed</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Contact Information Tab -->
        <div class="tab-pane fade" id="contact" role="tabpanel">
            <div class="card">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" id="email" name="email" required 
                                       value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="phone" class="form-label">Phone <span class="text-danger">*</span></label>
                                <input type="tel" class="form-control" id="phone" name="phone" required 
                                       value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="alt_phone" class="form-label">Alternative Phone</label>
                                <input type="tel" class="form-control" id="alt_phone" name="alt_phone" 
                                       value="<?php echo isset($_POST['alt_phone']) ? htmlspecialchars($_POST['alt_phone']) : ''; ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="city" class="form-label">City</label>
                                <input type="text" class="form-control" id="city" name="city" 
                                       value="<?php echo isset($_POST['city']) ? htmlspecialchars($_POST['city']) : ''; ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="state" class="form-label">State</label>
                                <input type="text" class="form-control" id="state" name="state" 
                                       value="<?php echo isset($_POST['state']) ? htmlspecialchars($_POST['state']) : ''; ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="country" class="form-label">Country</label>
                                <input type="text" class="form-control" id="country" name="country" 
                                       value="<?php echo isset($_POST['country']) ? htmlspecialchars($_POST['country']) : 'Nigeria'; ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="current_address" class="form-label">Current Address</label>
                        <textarea class="form-control" id="current_address" name="current_address" rows="3" 
                                  placeholder="Enter current address..."><?php echo isset($_POST['current_address']) ? htmlspecialchars($_POST['current_address']) : ''; ?></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="permanent_address" class="form-label">Permanent Address</label>
                        <textarea class="form-control" id="permanent_address" name="permanent_address" rows="3" 
                                  placeholder="Enter permanent address..."><?php echo isset($_POST['permanent_address']) ? htmlspecialchars($_POST['permanent_address']) : ''; ?></textarea>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Employment Information Tab -->
        <div class="tab-pane fade" id="employment" role="tabpanel">
            <div class="card">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="occupation" class="form-label">Occupation</label>
                                <input type="text" class="form-control" id="occupation" name="occupation" 
                                       value="<?php echo isset($_POST['occupation']) ? htmlspecialchars($_POST['occupation']) : ''; ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="employer_name" class="form-label">Employer Name</label>
                                <input type="text" class="form-control" id="employer_name" name="employer_name" 
                                       value="<?php echo isset($_POST['employer_name']) ? htmlspecialchars($_POST['employer_name']) : ''; ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="employer_address" class="form-label">Employer Address</label>
                        <textarea class="form-control" id="employer_address" name="employer_address" rows="3" 
                                  placeholder="Enter employer address..."><?php echo isset($_POST['employer_address']) ? htmlspecialchars($_POST['employer_address']) : ''; ?></textarea>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Identification Tab -->
        <div class="tab-pane fade" id="identification" role="tabpanel">
            <div class="card">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="id_type" class="form-label">ID Type</label>
                                <select class="form-select" id="id_type" name="id_type">
                                    <option value="">Select ID Type</option>
                                    <option value="National ID" <?php echo (isset($_POST['id_type']) && $_POST['id_type'] == 'National ID') ? 'selected' : ''; ?>>National ID</option>
                                    <option value="Drivers License" <?php echo (isset($_POST['id_type']) && $_POST['id_type'] == 'Drivers License') ? 'selected' : ''; ?>>Driver's License</option>
                                    <option value="Passport" <?php echo (isset($_POST['id_type']) && $_POST['id_type'] == 'Passport') ? 'selected' : ''; ?>>Passport</option>
                                    <option value="Voters Card" <?php echo (isset($_POST['id_type']) && $_POST['id_type'] == 'Voters Card') ? 'selected' : ''; ?>>Voter's Card</option>
                                    <option value="Other" <?php echo (isset($_POST['id_type']) && $_POST['id_type'] == 'Other') ? 'selected' : ''; ?>>Other</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="id_number" class="form-label">ID Number</label>
                                <input type="text" class="form-control" id="id_number" name="id_number" 
                                       value="<?php echo isset($_POST['id_number']) ? htmlspecialchars($_POST['id_number']) : ''; ?>">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Emergency Contact Tab -->
        <div class="tab-pane fade" id="emergency" role="tabpanel">
            <div class="card">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="emergency_contact_name" class="form-label">Emergency Contact Name</label>
                                <input type="text" class="form-control" id="emergency_contact_name" name="emergency_contact_name" 
                                       value="<?php echo isset($_POST['emergency_contact_name']) ? htmlspecialchars($_POST['emergency_contact_name']) : ''; ?>">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="emergency_contact_phone" class="form-label">Emergency Contact Phone</label>
                                <input type="tel" class="form-control" id="emergency_contact_phone" name="emergency_contact_phone" 
                                       value="<?php echo isset($_POST['emergency_contact_phone']) ? htmlspecialchars($_POST['emergency_contact_phone']) : ''; ?>">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="emergency_contact_relationship" class="form-label">Relationship</label>
                                <input type="text" class="form-control" id="emergency_contact_relationship" name="emergency_contact_relationship" 
                                       value="<?php echo isset($_POST['emergency_contact_relationship']) ? htmlspecialchars($_POST['emergency_contact_relationship']) : ''; ?>">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Reference Tab -->
        <div class="tab-pane fade" id="reference" role="tabpanel">
            <div class="card">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="reference_name" class="form-label">Reference Name</label>
                                <input type="text" class="form-control" id="reference_name" name="reference_name" 
                                       value="<?php echo isset($_POST['reference_name']) ? htmlspecialchars($_POST['reference_name']) : ''; ?>">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="reference_phone" class="form-label">Reference Phone</label>
                                <input type="tel" class="form-control" id="reference_phone" name="reference_phone" 
                                       value="<?php echo isset($_POST['reference_phone']) ? htmlspecialchars($_POST['reference_phone']) : ''; ?>">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="reference_address" class="form-label">Reference Address</label>
                                <textarea class="form-control" id="reference_address" name="reference_address" rows="2" 
                                          placeholder="Enter reference address..."><?php echo isset($_POST['reference_address']) ? htmlspecialchars($_POST['reference_address']) : ''; ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Documents Tab -->
        <div class="tab-pane fade" id="documents" role="tabpanel">
            <div class="card">
                <div class="card-body">
                    <div class="mb-3">
                        <label for="passport_photo" class="form-label">Passport Photo</label>
                        <input type="file" class="form-control" id="passport_photo" name="passport_photo" accept="image/*">
                        <div class="form-text">Upload a clear passport photo (max 5MB, JPG/PNG)</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="id_document" class="form-label">ID Document</label>
                        <input type="file" class="form-control" id="id_document" name="id_document" accept="image/*,.pdf">
                        <div class="form-text">Upload scanned copy of ID document (max 10MB, JPG/PNG/PDF)</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="employment_letter" class="form-label">Employment Letter</label>
                        <input type="file" class="form-control" id="employment_letter" name="employment_letter" accept="image/*,.pdf">
                        <div class="form-text">Upload employment letter (max 10MB, JPG/PNG/PDF)</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="bank_statement" class="form-label">Bank Statement</label>
                        <input type="file" class="form-control" id="bank_statement" name="bank_statement" accept="image/*,.pdf">
                        <div class="form-text">Upload bank statement (last 3 months, max 10MB, JPG/PNG/PDF)</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Additional Options -->
    <div class="card mt-4">
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="Active" <?php echo (!isset($_POST['status']) || $_POST['status'] == 'Active') ? 'selected' : ''; ?>>Active</option>
                            <option value="Inactive" <?php echo (isset($_POST['status']) && $_POST['status'] == 'Inactive') ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-check mt-4">
                        <input class="form-check-input" type="checkbox" id="create_customer_account" name="create_customer_account" value="1" 
                               <?php echo (isset($_POST['create_customer_account']) && $_POST['create_customer_account']) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="create_customer_account">
                            Create Customer Account (for tenant portal access)
                        </label>
                    </div>
                </div>
            </div>
            
            <div class="mb-3">
                <label for="notes" class="form-label">Internal Notes</label>
                <textarea class="form-control" id="notes" name="notes" rows="3" 
                          placeholder="Any additional notes about the tenant..."><?php echo isset($_POST['notes']) ? htmlspecialchars($_POST['notes']) : ''; ?></textarea>
            </div>
        </div>
    </div>
    
    <!-- Form Actions -->
    <div class="card mt-4">
        <div class="card-body">
            <div class="d-flex justify-content-between">
                <a href="index.php" class="btn btn-secondary">
                    <i class="icon-arrow-left"></i> Cancel
                </a>
                <button type="submit" class="btn btn-primary">
                    <i class="icon-save"></i> Create Tenant
                </button>
            </div>
        </div>
    </div>
</form>

<script>
// Form validation
(function() {
    'use strict';
    window.addEventListener('load', function() {
        var forms = document.getElementsByClassName('needs-validation');
        var validation = Array.prototype.filter.call(forms, function(form) {
            form.addEventListener('submit', function(event) {
                if (form.checkValidity() === false) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                form.classList.add('was-validated');
            }, false);
        });
    }, false);
})();

// Tab validation
document.getElementById('tenantTabs').addEventListener('click', function(e) {
    if (e.target.classList.contains('nav-link')) {
        var activeTab = e.target.getAttribute('data-bs-target');
        var form = document.querySelector('form');
        
        // Check if current tab has required fields
        var currentTabPane = document.querySelector(activeTab);
        var requiredFields = currentTabPane.querySelectorAll('[required]');
        
        var isValid = true;
        requiredFields.forEach(function(field) {
            if (!field.value.trim()) {
                isValid = false;
            }
        });
        
        if (!isValid) {
            e.preventDefault();
            alert('Please fill in all required fields in the current tab before proceeding.');
        }
    }
});
</script>

<?php include '../../includes/footer.php'; ?>
