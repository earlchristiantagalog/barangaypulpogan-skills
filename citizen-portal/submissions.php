<?php
/**
 * submissions.php
 * Dedicated My Active Submissions Page
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
// 2. LOAD SUBMISSIONS FROM DATABASE + FLASH
// ------------------------------------------------------------------------
$myPosts = [];
$totalPosts    = 0;
$pendingPosts  = 0;
$approvedPosts = 0;

try {
    $pdo  = getDB();
    $stmt = $pdo->prepare(
        'SELECT id, post_type, title, status, created_at
         FROM posts
         WHERE resident_id = :rid
         ORDER BY created_at DESC'
    );
    $stmt->execute([':rid' => $_SESSION['user_id']]);
    $myPosts = $stmt->fetchAll();

    $totalPosts = count($myPosts);
    foreach ($myPosts as $p) {
        $st = $p['status'] ?? '';
        if ($st === 'Pending Verification') {
            $pendingPosts++;
        } elseif ($st === 'Verified') {
            $approvedPosts++;
        }
    }
} catch (Throwable $e) {
    error_log('[SUBMISSIONS_ERROR] ' . date('Y-m-d H:i:s') . ' — ' . $e->getMessage());
}

$submitMessage = '';
$submitSuccess = false;
if (isset($_SESSION['flash_msg'])) {
    $submitMessage = $_SESSION['flash_msg'];
    $submitSuccess = ($_SESSION['flash_type'] ?? 'danger') === 'success';
    unset($_SESSION['flash_msg'], $_SESSION['flash_type']);
}

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
    <meta name="description" content="View your active submissions on the Barangay Bayanihan Mutual Aid System.">
    <title>My Submissions — Barangay Bayanihan Portal</title>
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
                        <a href="post.php" class="nav-link p-0 text-dark text-decoration-none d-flex align-items-center border-start border-3 border-transparent ps-2">
                            <i class="bi bi-pencil-square fs-3 me-3 text-dark"></i>
                            <span class="fw-bold text-uppercase small">Post</span>
                        </a>
                    </li>
                    <li class="nav-item mb-3">
                        <a href="submissions.php" class="nav-link p-0 text-dark text-decoration-none d-flex align-items-center border-start border-3 border-dark ps-2">
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
                        <li class="breadcrumb-item active" aria-current="page">My Submissions</li>
                    </ol>
                </nav>

                <h2 class="h5 fw-bold text-uppercase mb-1">My Active Submissions</h2>
                <p class="text-secondary mb-4">
                    Track the status of all your published and pending community listings.
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

                <!-- Summary cards -->
                <div class="row row-cols-1 row-cols-sm-3 g-3 mb-4">
                    <div class="col">
                        <div class="border border-secondary-subtle rounded-1 bg-white p-3">
                            <p class="small text-secondary text-uppercase mb-1">Total Submissions</p>
                            <p class="h3 fw-bold mb-0"><?php echo $totalPosts; ?></p>
                        </div>
                    </div>
                    <div class="col">
                        <div class="border border-secondary-subtle rounded-1 bg-white p-3">
                            <p class="small text-secondary text-uppercase mb-1">Pending Verification</p>
                            <p class="h3 fw-bold mb-0"><?php echo $pendingPosts; ?></p>
                        </div>
                    </div>
                    <div class="col">
                        <div class="border border-secondary-subtle rounded-1 bg-white p-3">
                            <p class="small text-secondary text-uppercase mb-1">Published</p>
                            <p class="h3 fw-bold mb-0"><?php echo $approvedPosts; ?></p>
                        </div>
                    </div>
                </div>

                <!-- Submissions table -->
                <div class="border border-secondary-subtle rounded-1 bg-white mb-4">
                    <div class="bg-light border-bottom border-secondary-subtle px-3 py-2">
                        <h3 class="h6 fw-bold mb-0">
                            <i class="bi bi-list-check me-2"></i>All Submissions
                        </h3>
                    </div>
                    <div class="p-3">
                        <?php if ($totalPosts === 0): ?>
                            <div class="text-center py-4">
                                <i class="bi bi-inbox fs-1 text-secondary d-block mb-2"></i>
                                <p class="text-secondary mb-2">You have not submitted any listings yet.</p>
                                <a href="post.php" class="btn btn-success rounded-1 py-2 px-4 fw-semibold">
                                    <i class="bi bi-plus-circle me-2"></i>Create Your First Post
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-bordered border-secondary-subtle mb-0 align-middle">
                                    <thead class="table-light">
                                        <tr>
                                            <th scope="col" class="small text-uppercase">Title</th>
                                            <th scope="col" class="small text-uppercase">Type</th>
                                            <th scope="col" class="small text-uppercase">Status</th>
                                            <th scope="col" class="small text-uppercase">Posted</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($myPosts as $post): ?>
                                            <?php
                                                $isOffer = ($post['post_type'] ?? '') === 'offer';
                                                $typeBadge = $isOffer ? 'bg-success' : 'bg-primary';
                                                $typeLabel = $isOffer ? 'Offer' : 'Request';
                                                $status = $post['status'] ?? 'Pending Verification';
                                                $statusBadge = ($status === 'Pending Verification') ? 'bg-warning text-dark' : 'bg-success';
                                            ?>
                                            <tr>
                                                <td class="fw-semibold"><?php echo htmlspecialchars($post['title'] ?? '', ENT_QUOTES); ?></td>
                                                <td><span class="badge <?php echo $typeBadge; ?> rounded-1"><?php echo $typeLabel; ?></span></td>
                                                <td><span class="badge <?php echo $statusBadge; ?> rounded-1"><?php echo htmlspecialchars($status, ENT_QUOTES); ?></span></td>
                                                <td class="small text-secondary"><?php echo htmlspecialchars($post['created_at'] ?? '', ENT_QUOTES); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
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
