<?php

/**
 * officers-portal/announcements.php
 * Create & Manage Announcements — Officers Portal
 *
 * Stack: Pure PHP 8.x (native sessions) + Bootstrap 5.3 (utility classes only).
 */

session_start();

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['officer', 'it officer'], true)) {
    header('Location: ../login.php?action=login');
    exit;
}

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../pusher_notify.php';

// ------------------------------------------------------------------------
// POST HANDLING — create announcement
// ------------------------------------------------------------------------
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim((string) ($_POST['title'] ?? ''));
    $body  = trim((string) ($_POST['body'] ?? ''));

    if ($title === '' || mb_strlen($title, 'UTF-8') < 5) {
        $errors['title'] = 'Title must be at least 5 characters.';
    }
    if ($body === '' || mb_strlen($body, 'UTF-8') < 10) {
        $errors['body'] = 'Body must be at least 10 characters.';
    }

    if (empty($errors)) {
        try {
            $pdo  = getDB();
            $stmt = $pdo->prepare(
                'INSERT INTO announcements (title, body, author_id, author_name, created_at)
                 VALUES (:title, :body, :author_id, :author_name, NOW())'
            );
            $stmt->execute([
                ':title'       => $title,
                ':body'        => $body,
                ':author_id'   => $_SESSION['user_id'],
                ':author_name' => $_SESSION['user_name'] ?? 'Officer',
            ]);

            // Pusher broadcast to all users
            broadcastAll('new-announcement', [
                'id'         => $pdo->lastInsertId(),
                'title'      => $title,
                'body'       => $body,
                'author'     => $_SESSION['user_name'] ?? 'Officer',
                'timestamp'  => date('Y-m-d H:i:s'),
            ]);

            $_SESSION['flash_msg']  = 'Announcement published successfully.';
            $_SESSION['flash_type'] = 'success';
            header('Location: announcements.php?success=1');
            exit;

        } catch (Throwable $e) {
            error_log('[ANNOUNCEMENT_ERROR] ' . date('Y-m-d H:i:s') . ' — ' . $e->getMessage());
            $errors['general'] = 'Failed to publish announcement. Please try again.';
        }
    }
}

// Flash
$submitMessage = '';
$submitSuccess = false;
if (isset($_SESSION['flash_msg'])) {
    $submitMessage = $_SESSION['flash_msg'];
    $submitSuccess = ($_SESSION['flash_type'] ?? 'danger') === 'success';
    unset($_SESSION['flash_msg'], $_SESSION['flash_type']);
}

// Load existing announcements
$announcements = [];
try {
    $pdo = getDB();
    $stmt = $pdo->query('SELECT id, title, body, author_name, created_at FROM announcements ORDER BY created_at DESC LIMIT 20');
    $announcements = $stmt->fetchAll();
} catch (Throwable $e) {
    error_log('[ANNOUNCEMENTS_LOAD_ERROR] ' . date('Y-m-d H:i:s') . ' — ' . $e->getMessage());
}

$userName     = htmlspecialchars($_SESSION['user_name'] ?? 'Officer', ENT_QUOTES);
$district     = htmlspecialchars($_SESSION['district'] ?? '', ENT_QUOTES);
$purokAddress = htmlspecialchars($_SESSION['purok_address'] ?? '', ENT_QUOTES);
$purok        = $district . ($purokAddress !== '' ? ', ' . $purokAddress : '');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Create and manage announcements — Officers Portal.">
    <title>Announcements — Barangay Pulpogan Officers Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
</head>

<body class="bg-white">

    <!-- GOVPH SLIM BAR -->
    <div class="bg-light border-bottom border-secondary-subtle">
        <div class="container-fluid px-3 px-md-4">
            <div class="row py-1 small">
                <div class="col-12 col-md-6">
                    <span class="fw-bold">GOVPH</span> <span class="text-secondary">|</span> Republic of the Philippines
                </div>
                <div class="col-12 col-md-6 text-md-end text-secondary">
                    Barangay Pulpogan Officers Portal
                </div>
            </div>
        </div>
    </div>

    <!-- MAIN HEADER -->
    <header class="bg-dark text-light border-bottom border-dark">
        <div class="container-fluid px-3 px-md-4">
            <div class="row align-items-center py-3">
                <div class="col-8 col-md-10 text-center text-md-center">
                    <h1 class="h4 fw-bold text-uppercase mb-0">Barangay Pulpogan Officers Portal</h1>
                </div>
                <div class="col-4 col-md-2 d-flex justify-content-end">
                    <div class="dropdown">
                        <button class="btn p-0 border-0 bg-transparent dropdown-toggle" type="button"
                            id="avatarMenu" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-person-circle text-light fs-4"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end rounded-1 border border-secondary-subtle" aria-labelledby="avatarMenu">
                            <li>
                                <span class="dropdown-item-text small">
                                    <span class="fw-bold"><?php echo $userName; ?></span><br>
                                    <span class="text-secondary"><?php echo $purok; ?></span>
                                </span>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="index.php"><i class="bi bi-grid-3x3-gap me-2"></i>Dashboard</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="index.php?action=logout"><i class="bi bi-box-arrow-right me-2"></i>Log Out</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- MAIN GRID -->
    <div class="container-fluid px-0">
        <div class="row g-0">

            <!-- SIDEBAR -->
            <nav class="col-12 col-md-3 col-lg-2 bg-light border-end border-secondary-subtle pt-4 pb-5" aria-label="Officers navigation">
                <div class="px-3 px-md-4 mb-4">
                    <span class="text-dark fw-bold small text-uppercase">
                        <i class="bi bi-shield-fill-check me-1"></i> Barangay Officer
                    </span>
                </div>
                <ul class="nav flex-column px-3 px-md-4">
                    <li class="nav-item mb-3">
                        <a href="index.php" class="nav-link p-0 text-dark text-decoration-none d-flex align-items-center border-start border-3 border-transparent ps-2">
                            <i class="bi bi-grid-3x3-gap-fill fs-3 me-3 text-dark"></i>
                            <span class="fw-bold text-uppercase small">Dashboard</span>
                        </a>
                    </li>
                    <li class="nav-item mb-3">
                        <a href="verify.php" class="nav-link p-0 text-dark text-decoration-none d-flex align-items-center border-start border-3 border-transparent ps-2">
                            <i class="bi bi-check2-square fs-3 me-3 text-dark"></i>
                            <span class="fw-bold text-uppercase small">Verify Posts</span>
                        </a>
                    </li>
                    <li class="nav-item mb-3">
                        <a href="residents.php" class="nav-link p-0 text-dark text-decoration-none d-flex align-items-center border-start border-3 border-transparent ps-2">
                            <i class="bi bi-people fs-3 me-3 text-dark"></i>
                            <span class="fw-bold text-uppercase small">Residents</span>
                        </a>
                    </li>
                    <li class="nav-item mb-3">
                        <a href="announcements.php" class="nav-link p-0 text-dark text-decoration-none d-flex align-items-center border-start border-3 border-dark ps-2">
                            <i class="bi bi-megaphone fs-3 me-3 text-dark"></i>
                            <span class="fw-bold text-uppercase small">Announcements</span>
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

            <!-- WORKSPACE -->
            <main class="col-12 col-md-9 col-lg-10 bg-white p-3 p-md-4">

                <nav aria-label="Breadcrumb" class="mb-3">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none">Dashboard</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Announcements</li>
                    </ol>
                </nav>

                <h2 class="h5 fw-bold text-uppercase mb-1">Announcements & Updates</h2>
                <p class="text-secondary mb-4">
                    Create community announcements. Published announcements appear in real-time on all resident dashboards.
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

                <!-- Create Announcement Form -->
                <div class="border border-secondary-subtle rounded-1 bg-white mb-4">
                    <div class="bg-light border-bottom border-secondary-subtle px-3 py-2">
                        <h3 class="h6 fw-bold mb-0">
                            <i class="bi bi-megaphone me-2"></i>New Announcement
                        </h3>
                    </div>
                    <div class="p-3">
                        <form method="POST" action="announcements.php" novalidate>
                            <div class="mb-3">
                                <label for="annTitle" class="form-label fw-semibold">Title <span class="text-danger">*</span></label>
                                <input type="text"
                                    class="form-control py-2 rounded-1 <?php echo isset($errors['title']) ? 'is-invalid' : ''; ?>"
                                    id="annTitle" name="title"
                                    value="<?php echo htmlspecialchars($_POST['title'] ?? '', ENT_QUOTES); ?>"
                                    placeholder="e.g. Community Clean-Up Drive Schedule"
                                    maxlength="150" required>
                                <?php if (isset($errors['title'])): ?>
                                    <div class="invalid-feedback"><?php echo $errors['title']; ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="mb-3">
                                <label for="annBody" class="form-label fw-semibold">Body <span class="text-danger">*</span></label>
                                <textarea
                                    class="form-control py-2 rounded-1 <?php echo isset($errors['body']) ? 'is-invalid' : ''; ?>"
                                    id="annBody" name="body" rows="5"
                                    placeholder="Write the full announcement details here..." required><?php echo htmlspecialchars($_POST['body'] ?? '', ENT_QUOTES); ?></textarea>
                                <?php if (isset($errors['body'])): ?>
                                    <div class="invalid-feedback"><?php echo $errors['body']; ?></div>
                                <?php endif; ?>
                            </div>
                            <button type="submit" class="btn btn-success rounded-1 py-2 px-4 fw-semibold">
                                <i class="bi bi-send me-2"></i>Publish Announcement
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Existing Announcements -->
                <div class="border border-secondary-subtle rounded-1 bg-white mb-4">
                    <div class="bg-light border-bottom border-secondary-subtle px-3 py-2">
                        <h3 class="h6 fw-bold mb-0">
                            <i class="bi bi-clock-history me-2"></i>Recent Announcements
                        </h3>
                    </div>
                    <div class="p-3">
                        <?php if (empty($announcements)): ?>
                            <p class="text-secondary small mb-0">No announcements yet.</p>
                        <?php else: ?>
                            <?php foreach ($announcements as $ann): ?>
                                <div class="border border-secondary-subtle rounded-1 p-3 mb-3">
                                    <div class="d-flex justify-content-between align-items-start mb-1">
                                        <h4 class="h6 fw-bold mb-0"><?php echo htmlspecialchars($ann['title'], ENT_QUOTES); ?></h4>
                                        <span class="small text-secondary"><?php echo htmlspecialchars($ann['created_at'], ENT_QUOTES); ?></span>
                                    </div>
                                    <p class="small text-secondary mb-2"><?php echo nl2br(htmlspecialchars($ann['body'], ENT_QUOTES)); ?></p>
                                    <p class="small text-secondary mb-0"><i class="bi bi-person me-1"></i><?php echo htmlspecialchars($ann['author_name'], ENT_QUOTES); ?></p>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="border-top border-secondary-subtle pt-3">
                    <p class="small text-secondary mb-0">
                        &copy; <?php echo date('Y'); ?> Barangay Pulpogan Portal. Officers access only.
                    </p>
                </div>

            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
