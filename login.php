<?php

/**
 * login.php
 * Full-Page Minimalist Authentication Gate — Zero-Trust Security Implementation
 * Republic of the Philippines — Barangay Bayanihan Portal
 *
 * Stack: Pure PHP 8.x (native sessions) + Bootstrap 5.3 (utility classes only).
 *
 * Security: CSRF protection, Argon2id hashing, PDO prepared statements,
 *           session fixation prevention, rate limiting, secure error handling.
 */

// ========================================================================
// 0. SESSION COOKIE HARDENING (must execute before session_start)
// ========================================================================
$isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'domain'   => '',
    'secure'   => $isSecure,
    'httponly'  => true,
    'samesite' => 'Strict',
]);
session_start();

// ========================================================================
// 1. SESSION FIXATION PREVENTION — redirect if already authenticated
// ========================================================================
if (isset($_SESSION['user_id']) && $_SESSION['user_id'] !== '') {
    $redirectPath = ($_SESSION['role'] ?? 'citizen') === 'officer'
        ? 'officers-portal/'
        : 'citizen-portal/';
    header('Location: ' . $redirectPath);
    exit;
}

// ========================================================================
// 2. CSRF TOKEN MANAGEMENT
// ========================================================================
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

// ========================================================================
// 3. RATE LIMITING — brute-force mitigation
// ========================================================================
$MAX_ATTEMPTS    = 5;
$LOCKOUT_SECONDS = 300; // 5-minute lockout

if (!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = 0;
}
if (!isset($_SESSION['login_locked_until'])) {
    $_SESSION['login_locked_until'] = 0;
}
$isLocked = (time() < $_SESSION['login_locked_until']);

// ========================================================================
// 4. DATABASE CONNECTION — include centralized db.php
// ========================================================================
require_once __DIR__ . '/db.php';

// ========================================================================
// 5. USER HELPER FUNCTIONS (login-specific)
// ========================================================================

/**
 * Generate a random 10-digit numeric user ID.
 */
function generateUserId(): string
{
    return (string) random_int(1000000000, 9999999999);
}

/**
 * Check if a user ID already exists in the database.
 */
function userIdExists(string $id): bool
{
    $pdo  = getDB();
    $stmt = $pdo->prepare('SELECT 1 FROM residents WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
    return $stmt->fetch() !== false;
}

/**
 * Generate a unique random 10-digit user ID.
 */
function generateUniqueUserId(): string
{
    $maxAttempts = 10;
    for ($i = 0; $i < $maxAttempts; $i++) {
        $id = generateUserId();
        if (!userIdExists($id)) {
            return $id;
        }
    }
    throw new RuntimeException('Unable to generate a unique user ID after ' . $maxAttempts . ' attempts.');
}

/**
 * Find a user by email.
 */
function findUserByEmail(string $email): ?array
{
    $pdo  = getDB();
    $stmt = $pdo->prepare('SELECT * FROM residents WHERE email = :email LIMIT 1');
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch();
    return $user ?: null;
}

/**
 * Create a new user record in the database.
 */
function createUser(string $name, string $district, string $purokAddress, string $mobile, string $email, string $passwordHash): string
{
    $pdo  = getDB();
    $id   = generateUniqueUserId();

    $stmt = $pdo->prepare(
        'INSERT INTO residents (id, full_name, district, purok_address, mobile, email, password_hash, created_at)
         VALUES (:id, :name, :district, :purok_address, :mobile, :email, :hash, NOW())'
    );
    $stmt->execute([
        ':id'             => $id,
        ':name'           => $name,
        ':district'       => $district,
        ':purok_address'  => $purokAddress,
        ':mobile'         => $mobile,
        ':email'          => $email,
        ':hash'           => $passwordHash,
    ]);

    return $id;
}

/**
 * Update the password hash for a user (rehash on login if needed).
 */
function updateUserPasswordHash(string $userId, string $newHash): void
{
    $pdo  = getDB();
    $stmt = $pdo->prepare('UPDATE residents SET password_hash = :hash WHERE id = :id');
    $stmt->execute([':hash' => $newHash, ':id' => $userId]);
}

// ========================================================================
// 5. INPUT SANITIZATION HELPERS
// ========================================================================

/** Strip non-printable characters, enforce max length */
function sanitizeText(string $input, int $maxLen = 100): string
{
    $clean = preg_replace('/[^\p{L}\p{N}\s\-\.\,\@\+\/]/u', '', trim($input));
    return mb_substr($clean, 0, $maxLen, 'UTF-8');
}

/** Validate email strictly */
function validateEmail(string $email): bool
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/** Enforce password strength: min 8 chars, at least one letter and one number */
function validatePasswordStrength(string $password): bool
{
    if (strlen($password) < 8 || strlen($password) > 128) {
        return false;
    }
    return preg_match('/[A-Za-z]/', $password) && preg_match('/[0-9]/', $password);
}

// ========================================================================
// 6. SECURE ERROR LOGGING
// ========================================================================
function secureLog(string $context, string $message): void
{
    $entry = sprintf(
        "[%s] [%s] %s — IP: %s — UA: %s\n",
        date('Y-m-d H:i:s'),
        $context,
        $message,
        $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
    );
    error_log($entry, 3, __DIR__ . '/security.log');
}

// ========================================================================
// 7. VIEW TOGGLE
// ========================================================================
$action = (isset($_GET['action']) && in_array($_GET['action'], ['login', 'register'], true))
    ? $_GET['action']
    : 'login';

// ========================================================================
// 8. POST HANDLING
// ========================================================================
$formErrors   = [];
$formValues   = [];
$alertMessage = '';
$alertType    = 'danger';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ------------------------------------------------------------------
    // 8a. CSRF VALIDATION (time-attack resistant)
    // ------------------------------------------------------------------
    $submittedToken = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $submittedToken)) {
        secureLog('CSRF', 'Token mismatch — request discarded.');
        $alertMessage = 'Session expired. Please refresh the page and try again.';
        $alertType    = 'danger';
        // Regenerate CSRF token
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $csrfToken = $_SESSION['csrf_token'];
    } else {

        // ------------------------------------------------------------------
        // 8b. RATE LIMIT CHECK
        // ------------------------------------------------------------------
        if ($isLocked) {
            $remaining = $_SESSION['login_locked_until'] - time();
            $alertMessage = 'Too many failed attempts. Please wait ' . $remaining . ' seconds.';
            $alertType    = 'danger';
        } else {

            // Determine which form was submitted
            $isRegister = isset($_POST['full_name']);

            if ($isRegister) {
                // ==============================================================
                // REGISTRATION HANDLER
                // ==============================================================
                $rawName         = trim((string) ($_POST['full_name'] ?? ''));
                $rawDistrict     = trim((string) ($_POST['district'] ?? ''));
                $rawPurokAddress = trim((string) ($_POST['purok_address'] ?? ''));
                $rawMobile       = trim((string) ($_POST['mobile'] ?? ''));
                $rawEmail        = trim((string) ($_POST['email'] ?? ''));
                $rawPassword     = (string) ($_POST['password'] ?? '');
                $consent         = isset($_POST['consent']);

                // Store for re-rendering
                $formValues = [
                    'full_name'     => $rawName,
                    'district'      => $rawDistrict,
                    'purok_address' => $rawPurokAddress,
                    'mobile'        => $rawMobile,
                    'email'         => $rawEmail,
                ];

                // -- Validate Full Name --
                $name = sanitizeText($rawName, 100);
                if ($name === '' || mb_strlen($name, 'UTF-8') < 2) {
                    $formErrors['full_name'] = 'Full name must be at least 2 characters.';
                } elseif (preg_match('/\d/', $name)) {
                    $formErrors['full_name'] = 'Full name must not contain numbers.';
                }

                // -- Validate District --
                $districtOptions = [
                    'District 1',
                    'District 2',
                    'District 3',
                    'District 4',
                    'District 5',
                    'District 6',
                    'District 7',
                ];
                $district = sanitizeText($rawDistrict, 20);
                if (!in_array($district, $districtOptions, true)) {
                    $formErrors['district'] = 'Please select a valid district.';
                }

                // -- Validate Purok / Address --
                $purokAddress = sanitizeText($rawPurokAddress, 150);
                if ($purokAddress === '' || mb_strlen($purokAddress, 'UTF-8') < 3) {
                    $formErrors['purok_address'] = 'Please enter your purok or street address (min. 3 characters).';
                }

                // -- Validate Mobile --
                $mobile = preg_replace('/[^0-9\+]/', '', $rawMobile);
                if (!preg_match('/^09[0-9]{9}$/', $mobile) && !preg_match('/^\+63[0-9]{10}$/', $mobile)) {
                    $formErrors['mobile'] = 'Enter a valid Philippine mobile number (09XXXXXXXXX).';
                } elseif (mb_strlen($mobile) > 15) {
                    $formErrors['mobile'] = 'Mobile number exceeds maximum length.';
                }

                // -- Validate Email --
                if (!validateEmail($rawEmail)) {
                    $formErrors['email'] = 'Please enter a valid email address.';
                } elseif (mb_strlen($rawEmail) > 254) {
                    $formErrors['email'] = 'Email address exceeds maximum length.';
                }

                // -- Validate Password --
                if (!validatePasswordStrength($rawPassword)) {
                    $formErrors['password'] = 'Password must be 8–128 characters with at least one letter and one number.';
                }

                // -- Validate Consent --
                if (!$consent) {
                    $formErrors['consent'] = 'You must agree to the data privacy terms.';
                }

                // -- If no errors, attempt registration --
                if (empty($formErrors)) {
                    try {
                        // Check if email already exists
                        $existingUser = findUserByEmail($rawEmail);
                        if ($existingUser) {
                            $formErrors['email'] = 'An account with this email already exists.';
                        } else {
                            // Hash password with Argon2id (fallback to PASSWORD_DEFAULT if unavailable)
                            $algo = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT;
                            $passwordHash = password_hash($rawPassword, $algo);

                            // Create user in database
                            $newUserId = createUser($name, $district, $purokAddress, $mobile, $rawEmail, $passwordHash);
                            secureLog('REGISTER', 'New account created — ID: ' . $newUserId);

                            // Auto-login after registration
                            session_regenerate_id(true);
                            $_SESSION['user_id']        = $newUserId;
                            $_SESSION['user_name']      = $name;
                            $_SESSION['district']       = $district;
                            $_SESSION['purok_address']  = $purokAddress;
                            $_SESSION['mobile']         = $mobile;
                            $_SESSION['email']          = $rawEmail;
                            $_SESSION['role']           = 'citizen';

                            header('Location: citizen-portal/');
                            exit;
                        }
                    } catch (Throwable $e) {
                        secureLog('REGISTER_EXCEPTION', $e->getMessage());
                        $alertMessage = 'An unexpected error occurred. Please try again later.';
                        $alertType    = 'danger';
                    }
                } else {
                    $action = 'register';
                }
            } else {
                // ==============================================================
                // LOGIN HANDLER
                // ==============================================================
                $rawLoginId = trim((string) ($_POST['login_id'] ?? ''));
                $rawPassword = (string) ($_POST['password'] ?? '');

                $formValues = ['login_id' => $rawLoginId];

                // Basic input checks
                $loginId = sanitizeText($rawLoginId, 254);
                if ($loginId === '') {
                    $formErrors['login_id'] = 'Please enter your email or resident ID.';
                }
                if ($rawPassword === '') {
                    $formErrors['password'] = 'Please enter your password.';
                }

                if (empty($formErrors)) {
                    try {
                        // Look up user by email
                        $user = findUserByEmail($loginId);

                        if ($user && password_verify($rawPassword, $user['password_hash'])) {
                            // Password valid — check if hash needs rehashing
                            $algo = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT;
                            if (password_needs_rehash($user['password_hash'], $algo)) {
                                $newHash = password_hash($rawPassword, $algo);
                                updateUserPasswordHash($user['id'], $newHash);
                            }

                            // Reset rate limit on success
                            $_SESSION['login_attempts']    = 0;
                            $_SESSION['login_locked_until'] = 0;

                            // Regenerate session to prevent fixation
                            session_regenerate_id(true);

                            $_SESSION['user_id']        = $user['id'];
                            $_SESSION['user_name']      = $user['full_name'];
                            $_SESSION['district']       = $user['district'];
                            $_SESSION['purok_address']  = $user['purok_address'];
                            $_SESSION['mobile']         = $user['mobile'];
                            $_SESSION['email']          = $user['email'];
                            $_SESSION['role']           = $user['role'] ?? 'citizen';

                            $redirectPath = ($_SESSION['role'] === 'officer')
                                ? 'officers-portal/'
                                : 'citizen-portal/';

                            secureLog('LOGIN_SUCCESS', 'User ID: ' . $user['id'] . ' — Role: ' . $_SESSION['role']);
                            header('Location: ' . $redirectPath);
                            exit;
                        } else {
                            // Invalid credentials — increment attempt counter
                            $_SESSION['login_attempts']++;
                            secureLog('LOGIN_FAIL', 'Failed login for: ' . $loginId . ' — Attempt: ' . $_SESSION['login_attempts']);

                            if ($_SESSION['login_attempts'] >= $MAX_ATTEMPTS) {
                                $_SESSION['login_locked_until'] = time() + $LOCKOUT_SECONDS;
                                $alertMessage = 'Account locked due to too many failed attempts. Please try again in 5 minutes.';
                                secureLog('LOCKOUT', 'Rate limit triggered for IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
                            } else {
                                $remaining = $MAX_ATTEMPTS - $_SESSION['login_attempts'];
                                $alertMessage = 'Invalid email or password. You have ' . $remaining . ' attempt(s) remaining.';
                            }
                            $alertType = 'danger';
                        }
                    } catch (Throwable $e) {
                        secureLog('LOGIN_EXCEPTION', $e->getMessage());
                        $alertMessage = 'An unexpected error occurred. Please try again later.';
                        $alertType    = 'danger';
                    }
                }
            }
        }
    }

    // Regenerate CSRF token after every POST
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    $csrfToken = $_SESSION['csrf_token'];
}

// ========================================================================
// 9. DISTRICT OPTIONS (shared by registration)
// ========================================================================
$districtOptions = [
    'District 1',
    'District 2',
    'District 3',
    'District 4',
    'District 5',
    'District 6',
    'District 7',
];

// Helper to safely output form values
function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Secure citizen access portal for the Barangay Bayanihan Mutual Aid System.">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Citizen Secure Access — Barangay Bayanihan Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
</head>

<body class="bg-light">

    <!-- ============================================================
         VIEWPORT-PARADIGM CENTERED GATEWAY
         ============================================================ -->
    <div class="min-vh-100 d-flex align-items-md-center justify-content-center bg-light p-3 py-4">

        <?php
        $wrapClass = ($action === 'register')
            ? 'col-12 col-sm-11 col-md-10 col-lg-8 col-xl-7'
            : 'col-12 col-sm-10 col-md-6 col-lg-4';
        ?>
        <div class="<?php echo $wrapClass; ?>">

            <div class="card rounded-1 border border-secondary-subtle bg-white">
                <div class="card-body p-4">

                    <!-- Card Header: seal + formal masthead -->
                    <div class="text-center border-bottom border-secondary-subtle pb-3 mb-4">
                        <div class="border border-secondary-subtle rounded-1 d-inline-flex align-items-center justify-content-center bg-light p-2 mb-2"
                            aria-hidden="true">
                            <span class="fw-bold text-secondary">BM</span>
                        </div>
                        <p class="small text-secondary mb-1 text-uppercase">Republic of the Philippines</p>
                        <h1 class="h6 fw-bold text-uppercase mb-0">Barangay Bayanihan Portal</h1>
                    </div>

                    <!-- ============================================================
                         GLOBAL ALERTS
                         ============================================================ -->
                    <?php if ($alertMessage !== ''): ?>
                        <div class="alert alert-<?php echo $alertType; ?> border border-<?php echo $alertType; ?> rounded-1 py-3 mb-4 small" role="alert">
                            <?php echo e($alertMessage); ?>
                        </div>
                    <?php endif; ?>

                    <!-- Field-level validation errors summary -->
                    <?php if (!empty($formErrors)): ?>
                        <div class="alert alert-danger border border-danger rounded-1 py-3 mb-4 small" role="alert">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i>Please correct the following:
                            <ul class="mb-0 mt-2 ps-3">
                                <?php foreach ($formErrors as $err): ?>
                                    <li><?php echo e($err); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <?php if ($action === 'register'): ?>
                        <!-- ================================================
                             VIEW B: REGISTRATION
                             ================================================ -->
                        <h2 class="h5 fw-bold mb-3">Resident Profile Registration</h2>
                        <form method="POST" action="login.php?action=register" novalidate>
                            <!-- CSRF TOKEN -->
                            <input type="hidden" name="csrf_token" value="<?php echo e($csrfToken); ?>">

                            <div class="row g-3">
                                <!-- Full Name -->
                                <div class="col-12 col-md-6">
                                    <label for="fullName" class="form-label fw-semibold">Full Name <span class="text-danger">*</span></label>
                                    <input type="text"
                                        class="form-control py-2 rounded-1 <?php echo isset($formErrors['full_name']) ? 'is-invalid' : ''; ?>"
                                        id="fullName" name="full_name"
                                        value="<?php echo e($formValues['full_name'] ?? ''); ?>"
                                        placeholder="Juan D. Dela Cruz"
                                        maxlength="100" required>
                                    <?php if (isset($formErrors['full_name'])): ?>
                                        <div class="invalid-feedback"><?php echo e($formErrors['full_name']); ?></div>
                                    <?php endif; ?>
                                </div>

                                <!-- District -->
                                <div class="col-12 col-md-6">
                                    <label for="district" class="form-label fw-semibold">District <span class="text-danger">*</span></label>
                                    <select class="form-select py-2 rounded-1 <?php echo isset($formErrors['district']) ? 'is-invalid' : ''; ?>"
                                        id="district" name="district" required>
                                        <option value="" selected disabled>Select your district</option>
                                        <?php foreach ($districtOptions as $opt): ?>
                                            <option value="<?php echo e($opt); ?>"
                                                <?php echo (($formValues['district'] ?? '') === $opt) ? 'selected' : ''; ?>>
                                                <?php echo e($opt); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?php if (isset($formErrors['district'])): ?>
                                        <div class="invalid-feedback"><?php echo e($formErrors['district']); ?></div>
                                    <?php endif; ?>
                                </div>

                                <!-- Purok / Address -->
                                <div class="col-12 col-md-6">
                                    <label for="purokAddress" class="form-label fw-semibold">Purok / Address <span class="text-danger">*</span></label>
                                    <input type="text"
                                        class="form-control py-2 rounded-1 <?php echo isset($formErrors['purok_address']) ? 'is-invalid' : ''; ?>"
                                        id="purokAddress" name="purok_address"
                                        value="<?php echo e($formValues['purok_address'] ?? ''); ?>"
                                        placeholder="e.g. Purok 3, Sitio Masagana"
                                        maxlength="150" required>
                                    <?php if (isset($formErrors['purok_address'])): ?>
                                        <div class="invalid-feedback"><?php echo e($formErrors['purok_address']); ?></div>
                                    <?php endif; ?>
                                </div>

                                <!-- Mobile -->
                                <div class="col-12 col-md-6">
                                    <label for="mobile" class="form-label fw-semibold">Mobile Number <span class="text-danger">*</span></label>
                                    <input type="tel"
                                        class="form-control py-2 rounded-1 <?php echo isset($formErrors['mobile']) ? 'is-invalid' : ''; ?>"
                                        id="mobile" name="mobile"
                                        value="<?php echo e($formValues['mobile'] ?? ''); ?>"
                                        placeholder="09XXXXXXXXX"
                                        maxlength="15" required>
                                    <?php if (isset($formErrors['mobile'])): ?>
                                        <div class="invalid-feedback"><?php echo e($formErrors['mobile']); ?></div>
                                    <?php endif; ?>
                                </div>

                                <!-- Email -->
                                <div class="col-12 col-md-6">
                                    <label for="email" class="form-label fw-semibold">Email Address <span class="text-danger">*</span></label>
                                    <input type="email"
                                        class="form-control py-2 rounded-1 <?php echo isset($formErrors['email']) ? 'is-invalid' : ''; ?>"
                                        id="email" name="email"
                                        value="<?php echo e($formValues['email'] ?? ''); ?>"
                                        placeholder="resident@example.gov.ph"
                                        maxlength="254" required>
                                    <?php if (isset($formErrors['email'])): ?>
                                        <div class="invalid-feedback"><?php echo e($formErrors['email']); ?></div>
                                    <?php endif; ?>
                                </div>

                                <!-- Password -->
                                <div class="col-12 col-md-6">
                                    <label for="password" class="form-label fw-semibold">Password <span class="text-danger">*</span></label>
                                    <input type="password"
                                        class="form-control py-2 rounded-1 <?php echo isset($formErrors['password']) ? 'is-invalid' : ''; ?>"
                                        id="password" name="password"
                                        placeholder="Min 8 characters, 1 letter + 1 number"
                                        maxlength="128" required>
                                    <?php if (isset($formErrors['password'])): ?>
                                        <div class="invalid-feedback"><?php echo e($formErrors['password']); ?></div>
                                    <?php endif; ?>
                                </div>

                                <!-- Consent -->
                                <div class="col-12">
                                    <div class="form-check <?php echo isset($formErrors['consent']) ? 'is-invalid' : ''; ?>">
                                        <input type="checkbox"
                                            class="form-check-input <?php echo isset($formErrors['consent']) ? 'is-invalid' : ''; ?>"
                                            id="consent" name="consent" required>
                                        <label class="form-check-label small" for="consent">
                                            I consent to the collection and processing of my personal data under the
                                            Data Privacy Act of 2012 (RA 10173) for verified resident mutual-aid services.
                                        </label>
                                    </div>
                                    <?php if (isset($formErrors['consent'])): ?>
                                        <div class="invalid-feedback d-block"><?php echo e($formErrors['consent']); ?></div>
                                    <?php endif; ?>
                                </div>

                                <div class="col-12">
                                    <button type="submit" class="btn btn-dark w-100 rounded-1 py-2 fw-semibold">
                                        Register Household Profile
                                    </button>
                                </div>
                            </div>
                        </form>

                        <p class="text-center small mt-3 mb-0">
                            <a href="login.php?action=login" class="text-decoration-none fw-semibold">Return to secure sign in</a>
                        </p>

                    <?php else: ?>
                        <!-- ================================================
                             VIEW A: SIGN IN (DEFAULT)
                             ================================================ -->
                        <h2 class="h5 fw-bold mb-3">Citizen Secure Access</h2>
                        <form method="POST" action="login.php?action=login" novalidate>
                            <!-- CSRF TOKEN -->
                            <input type="hidden" name="csrf_token" value="<?php echo e($csrfToken); ?>">

                            <div class="mb-3">
                                <label for="loginId" class="form-label fw-semibold">Email / Resident ID <span class="text-danger">*</span></label>
                                <input type="text"
                                    class="form-control py-2 rounded-1 <?php echo isset($formErrors['login_id']) ? 'is-invalid' : ''; ?>"
                                    id="loginId" name="login_id"
                                    value="<?php echo e($formValues['login_id'] ?? ''); ?>"
                                    placeholder="resident@example.gov.ph or BRGY-0000"
                                    maxlength="254" required>
                                <?php if (isset($formErrors['login_id'])): ?>
                                    <div class="invalid-feedback"><?php echo e($formErrors['login_id']); ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="mb-3">
                                <label for="loginPassword" class="form-label fw-semibold">Password <span class="text-danger">*</span></label>
                                <input type="password"
                                    class="form-control py-2 rounded-1 <?php echo isset($formErrors['password']) ? 'is-invalid' : ''; ?>"
                                    id="loginPassword" name="password"
                                    placeholder="Enter your password"
                                    maxlength="128" required>
                                <?php if (isset($formErrors['password'])): ?>
                                    <div class="invalid-feedback"><?php echo e($formErrors['password']); ?></div>
                                <?php endif; ?>
                            </div>

                            <button type="submit" class="btn btn-primary w-100 rounded-1 py-2 fw-semibold">
                                Login to Dashboard
                            </button>
                        </form>

                        <p class="text-center small mt-3 mb-0">
                            <a href="login.php?action=register" class="text-decoration-none fw-semibold">
                                Create a verified resident account
                            </a>
                        </p>
                    <?php endif; ?>

                </div>
            </div>

            <!-- Compliance subtext -->
            <p class="text-center small text-secondary mt-3 mb-0 px-2">
                Access is governed by the Philippines Data Privacy Act of 2012 (Republic Act 10173).
                All resident information is processed strictly for public mutual-aid coordination.
            </p>

        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>