<?php

/**
 * post.php
 * Dedicated Create New Post Page
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

// ------------------------------------------------------------------------
// 2. POST HANDLING — PRG pattern (Post / Redirect / Get)
// ------------------------------------------------------------------------
$errors = [];
$val_title = '';
$val_postType = '';
$val_category = '';
$val_description = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $val_title       = trim((string) ($_POST['title'] ?? ''));
    $val_postType    = trim((string) ($_POST['post_type'] ?? ''));
    $val_category    = trim((string) ($_POST['category'] ?? ''));
    $val_description = trim((string) ($_POST['description'] ?? ''));

    if ($val_title === '') {
        $errors['title'] = 'Please enter a listing title.';
    }
    if (!in_array($val_postType, ['offer', 'request'], true)) {
        $errors['post_type'] = 'Please select a post type.';
    }
    if ($val_category === '') {
        $errors['category'] = 'Please select a category.';
    }
    if ($val_description === '') {
        $errors['description'] = 'Please enter a service description.';
    }

    if (empty($errors)) {
        if (!isset($_SESSION['my_posts']) || !is_array($_SESSION['my_posts'])) {
            $_SESSION['my_posts'] = [];
        }
        $_SESSION['my_posts'][] = [
            'id'          => 'P-' . (count($_SESSION['my_posts']) + 1) . '-' . time(),
            'title'       => $val_title,
            'post_type'   => $val_postType,
            'category'    => $val_category,
            'description' => $val_description,
            'status'      => 'Pending Verification',
            'timestamp'   => date('Y-m-d H:i:s'),
        ];
        $_SESSION['flash_msg']  = 'Your post has been submitted successfully and is now under verification review.';
        $_SESSION['flash_type'] = 'success';
        header('Location: post.php?success=1');
        exit;
    } else {
        $_SESSION['post_errors']   = $errors;
        $_SESSION['post_old']      = ['title' => $val_title, 'post_type' => $val_postType, 'category' => $val_category, 'description' => $val_description];
        $_SESSION['flash_msg']     = 'Please correct the errors below and try again.';
        $_SESSION['flash_type']    = 'danger';
        header('Location: post.php?error=1');
        exit;
    }
}

// ------------------------------------------------------------------------
// 3. GET — load flash messages and old values from session
// ------------------------------------------------------------------------
$submitMessage  = '';
$submitSuccess  = false;

if (isset($_SESSION['flash_msg'])) {
    $submitMessage = $_SESSION['flash_msg'];
    $submitSuccess = ($_SESSION['flash_type'] ?? 'danger') === 'success';
    unset($_SESSION['flash_msg'], $_SESSION['flash_type']);
}

if (isset($_SESSION['post_errors']) && is_array($_SESSION['post_errors'])) {
    $errors = $_SESSION['post_errors'];
    unset($_SESSION['post_errors']);
}
if (isset($_SESSION['post_old']) && is_array($_SESSION['post_old'])) {
    $val_title       = $_SESSION['post_old']['title'] ?? '';
    $val_postType    = $_SESSION['post_old']['post_type'] ?? '';
    $val_category    = $_SESSION['post_old']['category'] ?? '';
    $val_description = $_SESSION['post_old']['description'] ?? '';
    unset($_SESSION['post_old']);
}

// Mock category options
$categoryOptions = [
    'Skilled Trade',
    'Education',
    'Health',
    'Food',
    'Errands',
    'Household',
    'Family',
    'Environment',
];

$userName = htmlspecialchars($_SESSION['user_name'] ?? 'Resident', ENT_QUOTES);
$district     = htmlspecialchars($_SESSION['district'] ?? 'Unassigned District', ENT_QUOTES);
$purokAddress = htmlspecialchars($_SESSION['purok_address'] ?? '', ENT_QUOTES);
$purok        = $district . ($purokAddress !== '' ? ', ' . $purokAddress : '');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Create a new listing on the Barangay Bayanihan Mutual Aid System.">
    <title>Create New Post — Barangay Bayanihan Portal</title>
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
                        Barangay Pulpogan Citizen Portal
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
                                    <span class="fw-bold"><?php echo $userName; ?></span><br>
                                    <span class="text-secondary"><?php echo $purok; ?></span>
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
                        <a href="post.php" class="nav-link p-0 text-dark text-decoration-none d-flex align-items-center border-start border-3 border-dark ps-2">
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
                        <a href="settings.php" class="nav-link p-0 text-dark text-decoration-none d-flex align-items-center border-start border-3 border-transparent ps-2">
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
                        <li class="breadcrumb-item active" aria-current="page">Create New Post</li>
                    </ol>
                </nav>

                <h2 class="h5 fw-bold text-uppercase mb-1">Create New Post</h2>
                <p class="text-secondary mb-4">
                    Publish a verified listing to the community bulletin board. All submissions are reviewed by
                    the barangay before public display.
                </p>

                <?php if ($submitMessage !== ''): ?>
                    <div class="alert <?php echo $submitSuccess ? 'alert-success' : 'alert-danger'; ?>
                                 rounded-1 py-3 mb-4" role="alert">
                        <?php if ($submitSuccess): ?>
                            <i class="bi bi-check-circle-fill me-2"></i>
                        <?php else: ?>
                            <i class="bi bi-exclamation-triangle-fill me-2"></i>
                        <?php endif; ?>
                        <?php echo htmlspecialchars($submitMessage, ENT_QUOTES); ?>
                        <?php if ($submitSuccess): ?>
                            <a href="index.php" class="alert-link fw-semibold ms-2">Go to Dashboard</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <!-- Post submission form -->
                <form method="POST" action="post.php" novalidate>

                    <!-- Section: Post Type -->
                    <div class="border border-secondary-subtle rounded-1 bg-white mb-4">
                        <div class="bg-light border-bottom border-secondary-subtle px-3 py-2">
                            <h3 class="h6 fw-bold mb-0">
                                <i class="bi bi-tag me-2"></i>Post Type
                                <span class="text-danger">*</span>
                            </h3>
                        </div>
                        <div class="p-3">
                            <div class="row g-3">
                                <div class="col-12 col-sm-6">
                                    <div class="position-relative">
                                        <input type="radio" name="post_type" id="typeOffer" value="offer"
                                            class="d-none"
                                            <?php echo ($val_postType === 'offer') ? 'checked' : ''; ?> required>
                                        <label for="typeOffer" class="d-block border rounded-1 p-3 text-center mb-0 <?php echo ($val_postType === 'offer') ? 'border-success border-2 bg-success-subtle' : (isset($errors['post_type']) ? 'border-danger' : 'border-secondary-subtle'); ?>">
                                            <i class="bi bi-hand-thumbs-up fs-3 d-block mb-1 <?php echo ($val_postType === 'offer') ? 'text-success' : 'text-secondary'; ?>"></i>
                                            <span class="fw-bold d-block">Offer Help</span>
                                            <span class="small text-secondary">Share a skill or resource</span>
                                        </label>
                                    </div>
                                </div>
                                <div class="col-12 col-sm-6">
                                    <div class="position-relative">
                                        <input type="radio" name="post_type" id="typeRequest" value="request"
                                            class="d-none"
                                            <?php echo ($val_postType === 'request') ? 'checked' : ''; ?> required>
                                        <label for="typeRequest" class="d-block border rounded-1 p-3 text-center mb-0 <?php echo ($val_postType === 'request') ? 'border-success border-2 bg-success-subtle' : (isset($errors['post_type']) ? 'border-danger' : 'border-secondary-subtle'); ?>">
                                            <i class="bi bi-megaphone fs-3 d-block mb-1 <?php echo ($val_postType === 'request') ? 'text-success' : 'text-secondary'; ?>"></i>
                                            <span class="fw-bold d-block">Request Assistance</span>
                                            <span class="small text-secondary">Ask for help from neighbors</span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                            <?php if (isset($errors['post_type'])): ?>
                                <p class="text-danger small mt-2 mb-0"><i class="bi bi-exclamation-circle me-1"></i><?php echo $errors['post_type']; ?></p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Section: Listing Details -->
                    <div class="border border-secondary-subtle rounded-1 bg-white mb-4">
                        <div class="bg-light border-bottom border-secondary-subtle px-3 py-2">
                            <h3 class="h6 fw-bold mb-0">
                                <i class="bi bi-pencil-square me-2"></i>Listing Details
                            </h3>
                        </div>
                        <div class="p-3">
                            <div class="row g-3 mb-3">
                                <div class="col-12 col-md-7">
                                    <label for="title" class="form-label fw-semibold">Listing Title <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control py-2 rounded-1 <?php echo isset($errors['title']) ? 'is-invalid' : ''; ?>"
                                        id="title" name="title"
                                        value="<?php echo htmlspecialchars($val_title, ENT_QUOTES); ?>"
                                        placeholder="e.g. Free Carpentry & Furniture Repair" required>
                                    <?php if (isset($errors['title'])): ?>
                                        <div class="invalid-feedback"><?php echo $errors['title']; ?></div>
                                    <?php else: ?>
                                        <p class="form-text small text-secondary mb-0">Keep it short and descriptive.</p>
                                    <?php endif; ?>
                                </div>
                                <div class="col-12 col-md-5">
                                    <label for="category" class="form-label fw-semibold">Category <span class="text-danger">*</span></label>
                                    <select class="form-select py-2 rounded-1 <?php echo isset($errors['category']) ? 'is-invalid' : ''; ?>"
                                        id="category" name="category" required>
                                        <option value="" selected disabled>Select a category</option>
                                        <?php foreach ($categoryOptions as $cat): ?>
                                            <option value="<?php echo htmlspecialchars($cat, ENT_QUOTES); ?>"
                                                <?php echo ($val_category === $cat) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($cat, ENT_QUOTES); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?php if (isset($errors['category'])): ?>
                                        <div class="invalid-feedback"><?php echo $errors['category']; ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="description" class="form-label fw-semibold">Service Description <span class="text-danger">*</span></label>
                                <textarea class="form-control py-2 rounded-1 <?php echo isset($errors['description']) ? 'is-invalid' : ''; ?>"
                                    id="description" name="description" rows="6"
                                    placeholder="Describe the skill offered or assistance requested. Include schedule, location, and any requirements." required><?php echo htmlspecialchars($val_description, ENT_QUOTES); ?></textarea>
                                <?php if (isset($errors['description'])): ?>
                                    <div class="invalid-feedback"><?php echo $errors['description']; ?></div>
                                <?php else: ?>
                                    <p class="form-text small text-secondary mb-0">Be specific so residents can respond efficiently.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Submit -->
                    <div class="d-flex flex-column flex-sm-row align-items-start gap-3 mb-4">
                        <button type="submit" class="btn btn-success rounded-1 py-2 px-4 fw-semibold">
                            <i class="bi bi-send me-2"></i>Publish Post for Verification Review
                        </button>
                    </div>

                </form>

                <!-- Guidelines -->
                <div class="border border-secondary-subtle rounded-1 bg-light p-3 mb-4">
                    <h3 class="h6 fw-bold mb-2">
                        <i class="bi bi-info-circle me-2"></i>Posting Guidelines
                    </h3>
                    <ul class="small text-secondary mb-0 ps-3">
                        <li class="mb-1">All listings are reviewed within 24–48 hours before public display.</li>
                        <li class="mb-1">Provide accurate location (Purok/Sitio) for faster neighbor response.</li>
                        <li class="mb-1">Health and transport-related assistance may require additional barangay clearance.</li>
                        <li class="mb-0">Listings must comply with community standards and data privacy regulations.</li>
                    </ul>
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
    <script>
        (function() {
            var offerRadio = document.getElementById('typeOffer');
            var requestRadio = document.getElementById('typeRequest');
            var offerLabel = document.querySelector('label[for="typeOffer"]');
            var requestLabel = document.querySelector('label[for="typeRequest"]');
            var offerIcon = offerLabel.querySelector('i');
            var requestIcon = requestLabel.querySelector('i');

            function resetStyles() {
                offerLabel.className = 'd-block border border-secondary-subtle rounded-1 p-3 text-center mb-0';
                requestLabel.className = 'd-block border border-secondary-subtle rounded-1 p-3 text-center mb-0';
                offerIcon.className = 'bi bi-hand-thumbs-up fs-3 d-block mb-1 text-secondary';
                requestIcon.className = 'bi bi-megaphone fs-3 d-block mb-1 text-secondary';
            }

            function applyActive(value) {
                resetStyles();
                if (value === 'offer') {
                    offerLabel.classList.add('border-success', 'border-2', 'bg-success-subtle');
                    offerIcon.classList.replace('text-secondary', 'text-success');
                } else if (value === 'request') {
                    requestLabel.classList.add('border-success', 'border-2', 'bg-success-subtle');
                    requestIcon.classList.replace('text-secondary', 'text-success');
                }
            }

            offerLabel.addEventListener('click', function(e) {
                e.preventDefault();
                offerRadio.checked = true;
                applyActive('offer');
            });

            requestLabel.addEventListener('click', function(e) {
                e.preventDefault();
                requestRadio.checked = true;
                applyActive('request');
            });

            if (offerRadio.checked) applyActive('offer');
            if (requestRadio.checked) applyActive('request');
        })();
    </script>
</body>

</html>