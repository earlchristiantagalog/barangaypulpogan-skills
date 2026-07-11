<?php

/**
 * officers-portal/verify.php
 * Verify / Reject Community Posts — Officers Portal
 *
 * Stack: Pure PHP 8.x (native sessions) + Bootstrap 5.3 (utility classes only).
 */

// ------------------------------------------------------------------------
// 1. SESSION SECURITY GATE
// ------------------------------------------------------------------------
session_start();

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['officer', 'it officer'], true)) {
    header('Location: ../login.php?action=login');
    exit;
}

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../pusher_notify.php';

// ------------------------------------------------------------------------
// 2. POST HANDLING — approve / reject
// ------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postId = trim((string) ($_POST['post_id'] ?? ''));
    $action = trim((string) ($_POST['action'] ?? ''));

    if ($postId !== '' && in_array($action, ['verify', 'reject'], true)) {
        try {
            $pdo  = getDB();
            $newStatus = ($action === 'verify') ? 'Verified' : 'Rejected';

            // Get post owner before updating
            $getStmt = $pdo->prepare('SELECT resident_id, title FROM posts WHERE id = :id');
            $getStmt->execute([':id' => $postId]);
            $postRow = $getStmt->fetch();

            $stmt = $pdo->prepare('UPDATE posts SET status = :status WHERE id = :id');
            $stmt->execute([':status' => $newStatus, ':id' => $postId]);

            // Pusher notification to the post owner
            if ($postRow) {
                notifyUser($postRow['resident_id'], 'post-status-updated', [
                    'post_id'  => $postId,
                    'title'    => $postRow['title'],
                    'status'   => $newStatus,
                    'message'  => 'Your post "' . $postRow['title'] . '" has been ' . strtolower($newStatus) . '.',
                    'timestamp'=> date('Y-m-d H:i:s'),
                ]);
            }

            $_SESSION['flash_msg']  = 'Post ' . $newStatus . ' successfully.';
            $_SESSION['flash_type'] = 'success';
        } catch (Throwable $e) {
            error_log('[VERIFY_ERROR] ' . date('Y-m-d H:i:s') . ' — ' . $e->getMessage());
            $_SESSION['flash_msg']  = 'Failed to update post. Please try again.';
            $_SESSION['flash_type'] = 'danger';
        }
    }
    header('Location: verify.php');
    exit;
}

// ------------------------------------------------------------------------
// 3. LOAD POSTS
// ------------------------------------------------------------------------
$filter = (isset($_GET['filter']) && in_array($_GET['filter'], ['pending', 'verified', 'rejected', 'all'], true))
    ? $_GET['filter']
    : 'all';

$posts = [];
try {
    $pdo = getDB();

    $where = '';
    if ($filter === 'pending') {
        $where = "WHERE p.status = 'Pending Verification'";
    } elseif ($filter === 'verified') {
        $where = "WHERE p.status = 'Verified'";
    } elseif ($filter === 'rejected') {
        $where = "WHERE p.status = 'Rejected'";
    }

    $stmt = $pdo->query(
        "SELECT p.id, p.post_type, p.title, p.description, p.status, p.created_at, r.full_name, r.district
         FROM posts p
         JOIN residents r ON r.id = p.resident_id
         {$where}
         ORDER BY p.created_at DESC"
    );
    $posts = $stmt->fetchAll();
} catch (Throwable $e) {
    error_log('[VERIFY_LOAD_ERROR] ' . date('Y-m-d H:i:s') . ' — ' . $e->getMessage());
}

// Flash messages
$submitMessage = '';
$submitSuccess = false;
if (isset($_SESSION['flash_msg'])) {
    $submitMessage = $_SESSION['flash_msg'];
    $submitSuccess = ($_SESSION['flash_type'] ?? 'danger') === 'success';
    unset($_SESSION['flash_msg'], $_SESSION['flash_type']);
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
    <meta name="description" content="Verify or reject community posts — Officers Portal.">
    <title>Verify Posts — Barangay Pulpogan Officers Portal</title>
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
                        <a href="verify.php" class="nav-link p-0 text-dark text-decoration-none d-flex align-items-center border-start border-3 border-dark ps-2">
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
                        <a href="settings.php" class="nav-link p-0 text-dark text-decoration-none d-flex align-items-center border-start border-3 border-transparent ps-2">
                            <i class="bi bi-gear-fill fs-3 me-3 text-dark"></i>
                            <span class="fw-bold text-uppercase small">Settings</span>
                        </a>
                    </li>
                </ul>
            </nav>

            <!-- WORKSPACE -->
            <main class="col-12 col-md-9 col-lg-10 bg-white p-3 p-md-4">

                <!-- Breadcrumb -->
                <nav aria-label="Breadcrumb" class="mb-3">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none">Dashboard</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Verify Posts</li>
                    </ol>
                </nav>

                <h2 class="h5 fw-bold text-uppercase mb-1">Verify Community Posts</h2>
                <p class="text-secondary mb-4">
                    Review, approve, or reject resident-submitted listings before they appear on the public board.
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

                <!-- Filter Buttons -->
                <div class="d-flex flex-wrap gap-2 mb-4">
                    <a href="verify.php?filter=all" class="btn btn-sm rounded-1 <?php echo $filter === 'all' ? 'btn-dark' : 'btn-outline-dark'; ?> py-2 px-3 fw-semibold">
                        All (<?php echo count($posts); ?>)
                    </a>
                    <a href="verify.php?filter=pending" class="btn btn-sm rounded-1 <?php echo $filter === 'pending' ? 'btn-warning' : 'btn-outline-warning'; ?> py-2 px-3 fw-semibold">
                        Pending
                    </a>
                    <a href="verify.php?filter=verified" class="btn btn-sm rounded-1 <?php echo $filter === 'verified' ? 'btn-success' : 'btn-outline-success'; ?> py-2 px-3 fw-semibold">
                        Verified
                    </a>
                    <a href="verify.php?filter=rejected" class="btn btn-sm rounded-1 <?php echo $filter === 'rejected' ? 'btn-danger' : 'btn-outline-danger'; ?> py-2 px-3 fw-semibold">
                        Rejected
                    </a>
                </div>

                <!-- Posts List -->
                <?php if (empty($posts)): ?>
                    <div class="border border-secondary-subtle rounded-1 bg-white p-4 text-center">
                        <i class="bi bi-inbox fs-1 text-secondary d-block mb-2"></i>
                        <p class="text-secondary mb-0">No posts found for this filter.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($posts as $post): ?>
                        <?php
                            $isOffer = ($post['post_type'] ?? '') === 'offer';
                            $typeBadge = $isOffer ? 'bg-success' : 'bg-primary';
                            $typeLabel = $isOffer ? 'Offer' : 'Request';
                            $status = $post['status'] ?? 'Pending Verification';
                            $statusBadge = match($status) {
                                'Pending Verification' => 'bg-warning text-dark',
                                'Verified' => 'bg-success',
                                'Rejected' => 'bg-danger',
                                default => 'bg-secondary',
                            };
                        ?>
                        <div class="border border-secondary-subtle rounded-1 bg-white mb-3">
                            <div class="bg-light border-bottom border-secondary-subtle px-3 py-2 d-flex justify-content-between align-items-center flex-wrap gap-2">
                                <div>
                                    <span class="badge <?php echo $typeBadge; ?> rounded-1 me-2"><?php echo $typeLabel; ?></span>
                                    <span class="badge <?php echo $statusBadge; ?> rounded-1"><?php echo htmlspecialchars($status, ENT_QUOTES); ?></span>
                                </div>
                                <span class="small text-secondary"><?php echo htmlspecialchars($post['created_at'] ?? '', ENT_QUOTES); ?></span>
                            </div>
                            <div class="p-3">
                                <h4 class="h6 fw-bold mb-1"><?php echo htmlspecialchars($post['title'] ?? '', ENT_QUOTES); ?></h4>
                                <p class="small text-secondary mb-2">
                                    <i class="bi bi-person me-1"></i><?php echo htmlspecialchars($post['full_name'] ?? '', ENT_QUOTES); ?>
                                    <span class="ms-2"><i class="bi bi-geo-alt me-1"></i><?php echo htmlspecialchars($post['district'] ?? '', ENT_QUOTES); ?></span>
                                </p>
                                <p class="small mb-3"><?php echo nl2br(htmlspecialchars($post['description'] ?? '', ENT_QUOTES)); ?></p>

                                <?php if ($status === 'Pending Verification'): ?>
                                    <div class="d-flex gap-2">
                                        <form method="POST" action="verify.php" class="d-inline">
                                            <input type="hidden" name="post_id" value="<?php echo htmlspecialchars($post['id'], ENT_QUOTES); ?>">
                                            <input type="hidden" name="action" value="verify">
                                            <button type="submit" class="btn btn-success btn-sm rounded-1 py-1 px-3 fw-semibold">
                                                <i class="bi bi-check-lg me-1"></i>Verify
                                            </button>
                                        </form>
                                        <form method="POST" action="verify.php" class="d-inline">
                                            <input type="hidden" name="post_id" value="<?php echo htmlspecialchars($post['id'], ENT_QUOTES); ?>">
                                            <input type="hidden" name="action" value="reject">
                                            <button type="submit" class="btn btn-danger btn-sm rounded-1 py-1 px-3 fw-semibold">
                                                <i class="bi bi-x-lg me-1"></i>Reject
                                            </button>
                                        </form>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>

                <!-- Footer -->
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
