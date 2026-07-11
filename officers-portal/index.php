<?php

/**
 * officers-portal/index.php
 * Officers Dashboard — Barangay Pulpogan Officers Portal
 *
 * Stack: Pure PHP 8.x (native sessions) + Bootstrap 5.3 (utility classes only).
 */

// ------------------------------------------------------------------------
// 1. SESSION SECURITY GATE
// ------------------------------------------------------------------------
session_start();

if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_unset();
    session_destroy();
    header('Location: ../login.php?action=login');
    exit;
}

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? 'citizen') !== 'officer') {
    header('Location: ../login.php?action=login');
    exit;
}

require_once __DIR__ . '/../db.php';

// ------------------------------------------------------------------------
// 2. LOAD DASHBOARD METRICS
// ------------------------------------------------------------------------
$userName     = htmlspecialchars($_SESSION['user_name'] ?? 'Officer', ENT_QUOTES);
$district     = htmlspecialchars($_SESSION['district'] ?? 'Unassigned District', ENT_QUOTES);
$purokAddress = htmlspecialchars($_SESSION['purok_address'] ?? '', ENT_QUOTES);
$purok        = $district . ($purokAddress !== '' ? ', ' . $purokAddress : '');

$totalPosts = 0;
$pendingPosts = 0;
$verifiedPosts = 0;
$totalResidents = 0;

try {
    $pdo = getDB();

    $stmt = $pdo->query("SELECT status, COUNT(*) AS cnt FROM posts GROUP BY status");
    $statusCounts = $stmt->fetchAll();
    foreach ($statusCounts as $row) {
        $totalPosts += $row['cnt'];
        if ($row['status'] === 'Pending Verification') {
            $pendingPosts = $row['cnt'];
        } elseif ($row['status'] === 'Verified') {
            $verifiedPosts = $row['cnt'];
        }
    }

    $resStmt = $pdo->query("SELECT COUNT(*) AS cnt FROM residents");
    $totalResidents = $resStmt->fetch()['cnt'] ?? 0;

} catch (Throwable $e) {
    error_log('[OFFICERS_DASH_ERROR] ' . date('Y-m-d H:i:s') . ' — ' . $e->getMessage());
}

// Recent posts for the table
$recentPosts = [];
try {
    $stmt = $pdo->prepare(
        'SELECT p.id, p.post_type, p.title, p.status, p.created_at, r.full_name
         FROM posts p
         JOIN residents r ON r.id = p.resident_id
         ORDER BY p.created_at DESC
         LIMIT 10'
    );
    $stmt->execute();
    $recentPosts = $stmt->fetchAll();
} catch (Throwable $e) {
    error_log('[OFFICERS_DASH_ERROR] ' . date('Y-m-d H:i:s') . ' — ' . $e->getMessage());
}

// Mock announcements
$announcements = [
    [
        'title'  => 'New Officer Duty Schedule Posted',
        'body'   => 'The updated verification duty schedule for July 2026 is now available. Please check your assigned shifts.',
        'date'   => '2026-07-10',
        'author' => 'Barangay Captain',
    ],
    [
        'title'  => 'Community Clean-Up Drive — July 20, 2026',
        'body'   => 'All officers are requested to assist in coordinating the scheduled community clean-up across all districts.',
        'date'   => '2026-07-08',
        'author' => 'Barangay Secretary',
    ],
];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Officers Dashboard — Barangay Pulpogan Mutual Aid System.">
    <title>Officers Dashboard — Barangay Pulpogan Portal</title>
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
                    <h1 class="h4 fw-bold text-uppercase mb-0">
                        Barangay Pulpogan Officers Portal
                    </h1>
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
                                    <span class="text-secondary small">ID: <?php echo htmlspecialchars($_SESSION['user_id'] ?? '', ENT_QUOTES); ?></span><br>
                                    <span class="fw-bold"><?php echo $userName; ?></span><br>
                                    <span class="text-secondary"><?php echo $purok; ?></span>
                                </span>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="index.php"><i class="bi bi-grid-3x3-gap me-2"></i>Dashboard</a></li>
                            <li><a class="dropdown-item" href="settings.php"><i class="bi bi-person-gear me-2"></i>Account</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="index.php?action=logout"><i class="bi bi-box-arrow-right me-2"></i>Log Out</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- MAIN GRID: SIDEBAR + WORKSPACE -->
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
                        <a href="index.php" class="nav-link p-0 text-dark text-decoration-none d-flex align-items-center border-start border-3 border-dark ps-2">
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
                        <li class="breadcrumb-item active" aria-current="page">Officers Dashboard</li>
                    </ol>
                </nav>

                <h2 class="h5 fw-bold text-uppercase mb-1">Officers Dashboard</h2>
                <p class="text-secondary mb-4">
                    Overview of community posts, verification status, and registered residents.
                </p>

                <!-- KPI Cards -->
                <div class="row row-cols-2 row-cols-md-4 g-3 mb-4">
                    <div class="col">
                        <div class="border border-secondary-subtle rounded-1 bg-white p-3">
                            <p class="small text-secondary text-uppercase mb-1">Total Posts</p>
                            <p class="h3 fw-bold mb-0"><?php echo $totalPosts; ?></p>
                        </div>
                    </div>
                    <div class="col">
                        <div class="border border-secondary-subtle rounded-1 bg-white p-3">
                            <p class="small text-secondary text-uppercase mb-1">Pending Review</p>
                            <p class="h3 fw-bold mb-0 text-warning"><?php echo $pendingPosts; ?></p>
                        </div>
                    </div>
                    <div class="col">
                        <div class="border border-secondary-subtle rounded-1 bg-white p-3">
                            <p class="small text-secondary text-uppercase mb-1">Verified</p>
                            <p class="h3 fw-bold mb-0 text-success"><?php echo $verifiedPosts; ?></p>
                        </div>
                    </div>
                    <div class="col">
                        <div class="border border-secondary-subtle rounded-1 bg-white p-3">
                            <p class="small text-secondary text-uppercase mb-1">Total Residents</p>
                            <p class="h3 fw-bold mb-0"><?php echo $totalResidents; ?></p>
                        </div>
                    </div>
                </div>

                <!-- Admin Announcements -->
                <section class="border border-secondary-subtle rounded-1 bg-white mb-4">
                    <div class="bg-light border-bottom border-secondary-subtle px-3 py-2">
                        <h3 class="h6 fw-bold mb-0">
                            <i class="bi bi-megaphone me-2"></i>Officer Announcements
                        </h3>
                    </div>
                    <div class="p-3">
                        <?php if (empty($announcements)): ?>
                            <p class="text-secondary small mb-0">No announcements at this time.</p>
                        <?php else: ?>
                            <?php foreach ($announcements as $ann): ?>
                                <div class="border border-secondary-subtle rounded-1 p-3 mb-2 <?php echo $ann !== end($announcements) ? 'mb-3' : ''; ?>">
                                    <div class="d-flex justify-content-between align-items-start mb-1">
                                        <h4 class="h6 fw-bold mb-0"><?php echo htmlspecialchars($ann['title'], ENT_QUOTES); ?></h4>
                                        <span class="small text-secondary"><?php echo htmlspecialchars($ann['date'], ENT_QUOTES); ?></span>
                                    </div>
                                    <p class="small text-secondary mb-0"><?php echo htmlspecialchars($ann['body'], ENT_QUOTES); ?></p>
                                    <p class="small text-secondary mb-0 mt-1"><i class="bi bi-person me-1"></i><?php echo htmlspecialchars($ann['author'], ENT_QUOTES); ?></p>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </section>

                <!-- Recent Posts Table -->
                <section class="border border-secondary-subtle rounded-1 bg-white mb-4">
                    <div class="bg-light border-bottom border-secondary-subtle px-3 py-2 d-flex justify-content-between align-items-center">
                        <h3 class="h6 fw-bold mb-0">
                            <i class="bi bi-clock-history me-2"></i>Recent Submissions
                        </h3>
                        <a href="verify.php" class="small fw-semibold text-decoration-none">View All <i class="bi bi-arrow-right"></i></a>
                    </div>
                    <div class="p-3">
                        <?php if (empty($recentPosts)): ?>
                            <div class="text-center py-4">
                                <i class="bi bi-inbox fs-1 text-secondary d-block mb-2"></i>
                                <p class="text-secondary mb-0">No submissions yet.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-bordered border-secondary-subtle mb-0 align-middle">
                                    <thead class="table-light">
                                        <tr>
                                            <th scope="col" class="small text-uppercase">Title</th>
                                            <th scope="col" class="small text-uppercase">Resident</th>
                                            <th scope="col" class="small text-uppercase">Type</th>
                                            <th scope="col" class="small text-uppercase">Status</th>
                                            <th scope="col" class="small text-uppercase">Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recentPosts as $post): ?>
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
                                            <tr>
                                                <td class="fw-semibold"><?php echo htmlspecialchars($post['title'] ?? '', ENT_QUOTES); ?></td>
                                                <td class="small"><?php echo htmlspecialchars($post['full_name'] ?? '', ENT_QUOTES); ?></td>
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
                </section>

                <!-- Footer -->
                <div class="border-top border-secondary-subtle pt-3">
                    <p class="small text-secondary mb-0">
                        &copy; <?php echo date('Y'); ?> Barangay Pulpogan Portal. Officers access only.
                        Data handled under the Data Privacy Act of 2012 (RA 10173).
                    </p>
                </div>

            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
