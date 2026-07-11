<?php

/**
 * officers-portal/residents.php
 * View All Registered Residents — Officers Portal
 *
 * Stack: Pure PHP 8.x (native sessions) + Bootstrap 5.3 (utility classes only).
 */

// ------------------------------------------------------------------------
// 1. SESSION SECURITY GATE
// ------------------------------------------------------------------------
session_start();

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? 'citizen') !== 'officer') {
    header('Location: ../login.php?action=login');
    exit;
}

require_once __DIR__ . '/../db.php';

// ------------------------------------------------------------------------
// 2. LOAD RESIDENTS
// ------------------------------------------------------------------------
$residents = [];
$totalResidents = 0;

try {
    $pdo = getDB();
    $stmt = $pdo->query(
        "SELECT id, full_name, district, purok_address, mobile, email, role, created_at
         FROM residents
         ORDER BY created_at DESC"
    );
    $residents = $stmt->fetchAll();
    $totalResidents = count($residents);
} catch (Throwable $e) {
    error_log('[RESIDENTS_ERROR] ' . date('Y-m-d H:i:s') . ' — ' . $e->getMessage());
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
    <meta name="description" content="View all registered residents — Officers Portal.">
    <title>Residents — Barangay Pulpogan Officers Portal</title>
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
                        <a href="residents.php" class="nav-link p-0 text-dark text-decoration-none d-flex align-items-center border-start border-3 border-dark ps-2">
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
                        <li class="breadcrumb-item active" aria-current="page">Residents</li>
                    </ol>
                </nav>

                <h2 class="h5 fw-bold text-uppercase mb-1">Registered Residents</h2>
                <p class="text-secondary mb-4">
                    Directory of all verified residents in the Barangay Pulpogan Mutual Aid System.
                </p>

                <!-- Summary -->
                <div class="row row-cols-1 row-cols-sm-3 g-3 mb-4">
                    <div class="col">
                        <div class="border border-secondary-subtle rounded-1 bg-white p-3">
                            <p class="small text-secondary text-uppercase mb-1">Total Residents</p>
                            <p class="h3 fw-bold mb-0"><?php echo $totalResidents; ?></p>
                        </div>
                    </div>
                    <div class="col">
                        <div class="border border-secondary-subtle rounded-1 bg-white p-3">
                            <p class="small text-secondary text-uppercase mb-1">Officers</p>
                            <p class="h3 fw-bold mb-0">
                                <?php echo count(array_filter($residents, fn($r) => $r['role'] === 'officer')); ?>
                            </p>
                        </div>
                    </div>
                    <div class="col">
                        <div class="border border-secondary-subtle rounded-1 bg-white p-3">
                            <p class="small text-secondary text-uppercase mb-1">Citizens</p>
                            <p class="h3 fw-bold mb-0">
                                <?php echo count(array_filter($residents, fn($r) => $r['role'] === 'citizen')); ?>
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Residents Table -->
                <div class="border border-secondary-subtle rounded-1 bg-white mb-4">
                    <div class="bg-light border-bottom border-secondary-subtle px-3 py-2">
                        <h3 class="h6 fw-bold mb-0">
                            <i class="bi bi-people me-2"></i>All Residents
                        </h3>
                    </div>
                    <div class="p-3">
                        <?php if (empty($residents)): ?>
                            <div class="text-center py-4">
                                <i class="bi bi-inbox fs-1 text-secondary d-block mb-2"></i>
                                <p class="text-secondary mb-0">No residents registered yet.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-bordered border-secondary-subtle mb-0 align-middle">
                                    <thead class="table-light">
                                        <tr>
                                            <th scope="col" class="small text-uppercase">ID</th>
                                            <th scope="col" class="small text-uppercase">Name</th>
                                            <th scope="col" class="small text-uppercase">District</th>
                                            <th scope="col" class="small text-uppercase">Purok / Address</th>
                                            <th scope="col" class="small text-uppercase">Mobile</th>
                                            <th scope="col" class="small text-uppercase">Role</th>
                                            <th scope="col" class="small text-uppercase">Joined</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($residents as $res): ?>
                                            <tr>
                                                <td class="small fw-semibold"><?php echo htmlspecialchars($res['id'], ENT_QUOTES); ?></td>
                                                <td class="fw-semibold"><?php echo htmlspecialchars($res['full_name'], ENT_QUOTES); ?></td>
                                                <td class="small"><?php echo htmlspecialchars($res['district'], ENT_QUOTES); ?></td>
                                                <td class="small"><?php echo htmlspecialchars($res['purok_address'], ENT_QUOTES); ?></td>
                                                <td class="small"><?php echo htmlspecialchars($res['mobile'], ENT_QUOTES); ?></td>
                                                <td>
                                                    <?php if ($res['role'] === 'officer'): ?>
                                                        <span class="badge bg-dark rounded-1">Officer</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary-subtle text-dark rounded-1">Citizen</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="small text-secondary"><?php echo htmlspecialchars($res['created_at'], ENT_QUOTES); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

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
