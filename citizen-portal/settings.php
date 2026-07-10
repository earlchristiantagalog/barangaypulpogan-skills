<?php

/**
 * settings.php
 * Dedicated Resident Profile Settings Page
 * Barangay Bayanihan Citizen Portal
 *
 * Stack: Pure PHP 8.x (native sessions) + Bootstrap 5.3 (utility classes only).
 */

// ------------------------------------------------------------------------
// 1. SESSION SECURITY GATE
// ------------------------------------------------------------------------
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php?action=login');
    exit;
}

require_once __DIR__ . '/../db.php';

// ------------------------------------------------------------------------
// 2. POST HANDLING — PRG pattern
// ------------------------------------------------------------------------
$errors = [];
$val_userId  = htmlspecialchars($_SESSION['user_id'] ?? '', ENT_QUOTES);
$val_name     = htmlspecialchars($_SESSION['user_name'] ?? 'Resident', ENT_QUOTES);
$val_district     = htmlspecialchars($_SESSION['district'] ?? 'Unassigned District', ENT_QUOTES);
$val_purokAddress = htmlspecialchars($_SESSION['purok_address'] ?? '', ENT_QUOTES);
$val_mobile   = htmlspecialchars($_SESSION['mobile'] ?? '09XXXXXXXXX', ENT_QUOTES);
$val_email    = htmlspecialchars($_SESSION['email'] ?? 'resident@example.gov.ph', ENT_QUOTES);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newName         = trim((string) ($_POST['full_name'] ?? ''));
    $newDistrict     = trim((string) ($_POST['district'] ?? ''));
    $newPurokAddress = trim((string) ($_POST['purok_address'] ?? ''));
    $newMobile       = trim((string) ($_POST['mobile'] ?? ''));
    $newEmail        = trim((string) ($_POST['email'] ?? ''));

    if ($newName === '' || mb_strlen($newName, 'UTF-8') < 2) {
        $errors['full_name'] = 'Please enter your full name (min. 2 characters).';
    }
    if ($newDistrict === '') {
        $errors['district'] = 'Please select your district.';
    }
    if ($newPurokAddress === '' || mb_strlen($newPurokAddress, 'UTF-8') < 3) {
        $errors['purok_address'] = 'Please enter your purok or street address (min. 3 characters).';
    }
    if (!preg_match('/^09[0-9]{9}$/', $newMobile) && !preg_match('/^\+63[0-9]{10}$/', $newMobile)) {
        $errors['mobile'] = 'Enter a valid Philippine mobile number (09XXXXXXXXX).';
    }
    if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Please enter a valid email address.';
    }

    if (empty($errors)) {
        try {
            $pdo  = getDB();
            $stmt = $pdo->prepare(
                'UPDATE residents
                 SET full_name = :name, district = :district, purok_address = :purok_address,
                     mobile = :mobile, email = :email
                 WHERE id = :id'
            );
            $stmt->execute([
                ':name'          => $newName,
                ':district'      => $newDistrict,
                ':purok_address' => $newPurokAddress,
                ':mobile'        => $newMobile,
                ':email'         => $newEmail,
                ':id'            => $_SESSION['user_id'],
            ]);

            $_SESSION['user_name']     = $newName;
            $_SESSION['district']      = $newDistrict;
            $_SESSION['purok_address'] = $newPurokAddress;
            $_SESSION['mobile']        = $newMobile;
            $_SESSION['email']         = $newEmail;

            $_SESSION['flash_msg']  = 'Profile updated successfully.';
            $_SESSION['flash_type'] = 'success';
            header('Location: settings.php?success=1');
            exit;

        } catch (Throwable $e) {
            error_log('[SETTINGS_ERROR] ' . date('Y-m-d H:i:s') . ' — ' . $e->getMessage());
            $errors['general'] = 'Failed to save changes. Please try again.';
        }
    }

    if (!empty($errors)) {
        $val_name         = htmlspecialchars($newName, ENT_QUOTES);
        $val_district     = htmlspecialchars($newDistrict, ENT_QUOTES);
        $val_purokAddress = htmlspecialchars($newPurokAddress, ENT_QUOTES);
        $val_mobile       = htmlspecialchars($newMobile, ENT_QUOTES);
        $val_email        = htmlspecialchars($newEmail, ENT_QUOTES);
        $_SESSION['settings_errors'] = $errors;
        $_SESSION['settings_old']    = [
            'full_name'     => $newName,
            'district'      => $newDistrict,
            'purok_address' => $newPurokAddress,
            'mobile'        => $newMobile,
            'email'         => $newEmail,
        ];
        $_SESSION['flash_msg']  = 'Please correct the errors below.';
        $_SESSION['flash_type'] = 'danger';
        header('Location: settings.php?error=1');
        exit;
    }
}

// Load flash
$submitMessage = '';
$submitSuccess = false;
if (isset($_SESSION['flash_msg'])) {
    $submitMessage = $_SESSION['flash_msg'];
    $submitSuccess = ($_SESSION['flash_type'] ?? 'danger') === 'success';
    unset($_SESSION['flash_msg'], $_SESSION['flash_type']);
}
if (isset($_SESSION['settings_errors']) && is_array($_SESSION['settings_errors'])) {
    $errors = $_SESSION['settings_errors'];
    unset($_SESSION['settings_errors']);
}
if (isset($_SESSION['settings_old']) && is_array($_SESSION['settings_old'])) {
    $val_name         = htmlspecialchars($_SESSION['settings_old']['full_name'] ?? '', ENT_QUOTES);
    $val_district     = htmlspecialchars($_SESSION['settings_old']['district'] ?? '', ENT_QUOTES);
    $val_purokAddress = htmlspecialchars($_SESSION['settings_old']['purok_address'] ?? '', ENT_QUOTES);
    $val_mobile       = htmlspecialchars($_SESSION['settings_old']['mobile'] ?? '', ENT_QUOTES);
    $val_email        = htmlspecialchars($_SESSION['settings_old']['email'] ?? '', ENT_QUOTES);
    unset($_SESSION['settings_old']);
}

$districtOptions = [
    'District 1',
    'District 2',
    'District 3',
    'District 4',
    'District 5',
    'District 6',
    'District 7',
];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Manage your resident profile on the Barangay Bayanihan Mutual Aid System.">
    <title>Settings — Barangay Bayanihan Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
</head>

<body class="bg-white">

    <!-- ============================================================
         GOVPH SLIM BAR
         ============================================================ -->
    <div class="bg-light border-bottom border-secondary-subtle">
        <div class="container-fluid px-3 px-md-4">
            <div class="row py-1 small">
                <div class="col-12 col-md-6">
                    <span class="fw-bold">GOVPH</span> <span class="text-secondary">|</span> Republic of the Philippines
                </div>
                <div class="col-12 col-md-6 text-md-end text-secondary">
                    Barangay Bayanihan Citizen Portal
                </div>
            </div>
        </div>
    </div>

    <!-- ============================================================
         MAIN HEADER
         ============================================================ -->
    <header class="bg-secondary text-light border-bottom border-secondary">
        <div class="container-fluid px-3 px-md-4">
            <div class="row align-items-center py-3">
                <div class="col-8 col-md-10 text-center text-md-center">
                    <h1 class="h4 fw-bold text-uppercase mb-0">
                        Barangay Bayanihan Citizen Portal
                    </h1>
                </div>
                <div class="col-4 col-md-2 d-flex justify-content-end">
                    <div class="dropdown">
                        <button class="btn p-0 border-0 bg-transparent dropdown-toggle" type="button"
                            id="avatarMenu" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-person-circle text-dark fs-4"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end rounded-1 border border-secondary-subtle" aria-labelledby="avatarMenu">
                            <li>
                                <span class="dropdown-item-text small">
                                    <span class="text-secondary small">ID: <?php echo $val_userId; ?></span><br>
                                    <span class="fw-bold"><?php echo $val_name; ?></span><br>
                                    <span class="text-secondary"><?php echo $val_district . ($val_purokAddress !== '' ? ', ' . $val_purokAddress : ''); ?></span>
                                </span>
                            </li>
                            <li>
                                <hr class="dropdown-divider">
                            </li>
                            <li><a class="dropdown-item" href="index.php"><i class="bi bi-grid-3x3-gap me-2"></i>Dashboard</a></li>
                            <li><a class="dropdown-item" href="login.php?action=login"><i class="bi bi-person-gear me-2"></i>Account</a></li>
                            <li>
                                <hr class="dropdown-divider">
                            </li>
                            <li><a class="dropdown-item text-danger" href="index.php?action=logout"><i class="bi bi-box-arrow-right me-2"></i>Log Out</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- ============================================================
         MAIN GRID SPLIT: SIDEBAR + WORKSPACE
         ============================================================ -->
    <div class="container-fluid px-0">
        <div class="row g-0">

            <!-- ---------------- SIDEBAR ---------------- -->
            <nav class="col-12 col-md-3 col-lg-2 bg-light border-end border-secondary-subtle pt-4 pb-5" aria-label="Dashboard navigation">

                <!-- Verified badge -->
                <div class="px-3 px-md-4 mb-4">
                    <span class="text-success fw-bold small text-uppercase">
                        <i class="bi bi-shield-fill-check me-1"></i> Verified User
                    </span>
                </div>

                <!-- Navigation menu with icons -->
                <ul class="nav flex-column px-3 px-md-4">
                    <li class="nav-item mb-3">
                        <a href="index.php" class="nav-link p-0 text-dark text-decoration-none d-flex align-items-center border-start border-3 border-transparent ps-2">
                            <i class="bi bi-grid-3x3-gap-fill fs-3 me-3 text-dark"></i>
                            <span class="fw-bold text-uppercase small">Dashboard</span>
                        </a>
                    </li>
                    <li class="nav-item mb-3">
                        <a href="post.php" class="nav-link p-0 text-dark text-decoration-none d-flex align-items-center border-start border-3 border-transparent ps-2">
                            <i class="bi bi-pencil-square fs-3 me-3 text-dark"></i>
                            <span class="fw-bold text-uppercase small">Post</span>
                        </a>
                    </li>
                    <li class="nav-item mb-3">
                        <a href="submissions.php" class="nav-link p-0 text-dark text-decoration-none d-flex align-items-center border-start border-3 border-transparent ps-2">
                            <i class="bi bi-list-check fs-3 me-3 text-dark"></i>
                            <span class="fw-bold text-uppercase small">Submissions</span>
                        </a>
                    </li>
                    <li class="nav-item mb-3">
                        <a href="settings.php" class="nav-link p-0 text-dark text-decoration-none d-flex align-items-center border-start border-3 border-dark ps-2">
                            <i class="bi bi-gear-fill fs-3 me-3 text-dark"></i>
                            <span class="fw-bold text-uppercase small">Settings</span>
                        </a>
                    </li>
                </ul>
            </nav>

            <!-- ---------------- WORKSPACE CANVAS ---------------- -->
            <main class="col-12 col-md-9 col-lg-10 bg-white p-3 p-md-4">

                <!-- Breadcrumb -->
                <nav aria-label="Breadcrumb" class="mb-3">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none">Portal</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Settings</li>
                    </ol>
                </nav>

                <h2 class="h5 fw-bold text-uppercase mb-1">Resident Profile Settings</h2>
                <p class="text-secondary mb-4">
                    Manage your personal information and account preferences.
                </p>

                <?php if ($submitMessage !== ''): ?>
                    <div class="alert <?php echo $submitSuccess ? 'alert-success' : 'alert-danger'; ?>
                                border border-<?php echo $submitSuccess ? 'success' : 'danger'; ?> rounded-1 py-3 mb-4" role="alert">
                        <?php if ($submitSuccess): ?>
                            <i class="bi bi-check-circle-fill me-2"></i>
                        <?php else: ?>
                            <i class="bi bi-exclamation-triangle-fill me-2"></i>
                        <?php endif; ?>
                        <?php echo htmlspecialchars($submitMessage, ENT_QUOTES); ?>
                    </div>
                <?php endif; ?>

                <?php if (isset($errors['general'])): ?>
                    <div class="alert alert-danger border border-danger rounded-1 py-3 mb-4" role="alert">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                        <?php echo htmlspecialchars($errors['general'], ENT_QUOTES); ?>
                    </div>
                <?php endif; ?>

                <!-- Profile form -->
                <form method="POST" action="settings.php" novalidate>

                    <!-- Section: Personal Information -->
                    <div class="border border-secondary-subtle rounded-1 bg-white mb-4">
                        <div class="bg-light border-bottom border-secondary-subtle px-3 py-2">
                            <h3 class="h6 fw-bold mb-0">
                                <i class="bi bi-person me-2"></i>Personal Information
                            </h3>
                        </div>
                        <div class="p-3">
                            <div class="row g-3">
                                <div class="col-12 col-md-6">
                                    <label class="form-label fw-semibold">Resident ID</label>
                                    <input type="text" class="form-control py-2 rounded-1 bg-light" readonly
                                        value="<?php echo $val_userId; ?>">
                                </div>
                                <div class="col-12 col-md-6">
                                    <label for="fullName" class="form-label fw-semibold">Full Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control py-2 rounded-1 <?php echo isset($errors['full_name']) ? 'is-invalid' : ''; ?>"
                                        id="fullName" name="full_name"
                                        value="<?php echo $val_name; ?>"
                                        placeholder="Juan D. Dela Cruz" required>
                                    <?php if (isset($errors['full_name'])): ?>
                                        <div class="invalid-feedback"><?php echo $errors['full_name']; ?></div>
                                    <?php endif; ?>
                                </div>
                                <div class="col-12 col-md-6">
                                    <label for="district" class="form-label fw-semibold">District <span class="text-danger">*</span></label>
                                    <select class="form-select py-2 rounded-1 <?php echo isset($errors['district']) ? 'is-invalid' : ''; ?>"
                                        id="district" name="district" required>
                                        <option value="" selected disabled>Select your district</option>
                                        <?php foreach ($districtOptions as $opt): ?>
                                            <option value="<?php echo htmlspecialchars($opt, ENT_QUOTES); ?>"
                                                <?php echo ($val_district === $opt) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($opt, ENT_QUOTES); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?php if (isset($errors['district'])): ?>
                                        <div class="invalid-feedback"><?php echo $errors['district']; ?></div>
                                    <?php endif; ?>
                                </div>
                                <div class="col-12 col-md-6">
                                    <label for="purok_address" class="form-label fw-semibold">Purok / Address <span class="text-danger">*</span></label>
                                    <input type="text"
                                        class="form-control py-2 rounded-1 <?php echo isset($errors['purok_address']) ? 'is-invalid' : ''; ?>"
                                        id="purok_address" name="purok_address"
                                        value="<?php echo htmlspecialchars($val_purokAddress, ENT_QUOTES); ?>"
                                        placeholder="e.g. Purok 3, Sitio Masagana"
                                        maxlength="150" required>
                                    <?php if (isset($errors['purok_address'])): ?>
                                        <div class="invalid-feedback"><?php echo $errors['purok_address']; ?></div>
                                    <?php endif; ?>
                                </div>
                                <div class="col-12 col-md-6">
                                    <label for="mobile" class="form-label fw-semibold">Mobile Number <span class="text-danger">*</span></label>
                                    <input type="tel" class="form-control py-2 rounded-1 <?php echo isset($errors['mobile']) ? 'is-invalid' : ''; ?>"
                                        id="mobile" name="mobile"
                                        value="<?php echo $val_mobile; ?>"
                                        placeholder="09XXXXXXXXX" required>
                                    <?php if (isset($errors['mobile'])): ?>
                                        <div class="invalid-feedback"><?php echo $errors['mobile']; ?></div>
                                    <?php endif; ?>
                                </div>
                                <div class="col-12 col-md-6">
                                    <label for="email" class="form-label fw-semibold">Email Address <span class="text-danger">*</span></label>
                                    <input type="email" class="form-control py-2 rounded-1 <?php echo isset($errors['email']) ? 'is-invalid' : ''; ?>"
                                        id="email" name="email"
                                        value="<?php echo $val_email; ?>"
                                        placeholder="resident@example.gov.ph" required>
                                    <?php if (isset($errors['email'])): ?>
                                        <div class="invalid-feedback"><?php echo $errors['email']; ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Section: Account Status -->
                    <div class="border border-secondary-subtle rounded-1 bg-white mb-4">
                        <div class="bg-light border-bottom border-secondary-subtle px-3 py-2">
                            <h3 class="h6 fw-bold mb-0">
                                <i class="bi bi-shield-check me-2"></i>Account Status
                            </h3>
                        </div>
                        <div class="p-3">
                            <div class="row g-3 align-items-center">
                                <div class="col-12 col-md-6">
                                    <p class="mb-1 small text-secondary">Verification Status</p>
                                    <span class="badge bg-success rounded-1 px-2 py-1">Verified Citizen</span>
                                </div>
                                <div class="col-12 col-md-6">
                                    <p class="mb-1 small text-secondary">Account Type</p>
                                    <span class="badge bg-secondary-subtle text-dark rounded-1 px-2 py-1">Resident</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Submit -->
                    <div class="d-flex flex-column flex-sm-row align-items-start gap-3 mb-4">
                        <button type="submit" class="btn btn-success rounded-1 py-2 px-4 fw-semibold">
                            <i class="bi bi-check-circle me-2"></i>Save Changes
                        </button>
                    </div>

                </form>

                <!-- Data privacy -->
                <div class="border border-secondary-subtle rounded-1 bg-light p-3 mb-4">
                    <h3 class="h6 fw-bold mb-2">
                        <i class="bi bi-info-circle me-2"></i>Data Privacy Notice
                    </h3>
                    <p class="small text-secondary mb-0">
                        Your personal information is processed under the Data Privacy Act of 2012 (Republic Act 10173)
                        and is used solely for community mutual-aid coordination. Contact the Barangay Data Protection
                        Officer for any data-related concerns.
                    </p>
                </div>

                <!-- Dashboard footer -->
                <div class="border-top border-secondary-subtle pt-3">
                    <p class="small text-secondary mb-0">
                        &copy; <?php echo date('Y'); ?> Barangay Bayanihan Portal. Data handled under the
                        Data Privacy Act of 2012 (RA 10173). Authorized resident use only.
                    </p>
                </div>

            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>