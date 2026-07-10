<?php

/**
 * index.php
 * Public-Facing Bulletin Board Homepage
 * Barangay Mutual Aid & Skills Directory
 *
 * Stack: Pure PHP 8.x + Bootstrap 5.3 (utility classes only, no custom CSS).
 */

// ------------------------------------------------------------------------
// 1. MOCK DATA SOURCE (structured array simulating a persistence layer)
// ------------------------------------------------------------------------
$listings = [
    [
        'id'        => 'L-1001',
        'type'      => 'offer',
        'title'     => 'Free Carpentry & Furniture Repair',
        'desc'      => 'Retired carpenter offering basic furniture repair and minor woodwork for elderly residents.',
        'location'  => 'Purok 2, Sitio Mabini',
        'category'  => 'Skilled Trade',
        'timestamp' => '2026-07-08 08:14',
    ],
    [
        'id'        => 'L-1002',
        'type'      => 'request',
        'title'     => 'Need Help With Grocery Errands',
        'desc'      => 'Senior resident recovering from surgery requesting assistance with weekly market errands.',
        'location'  => 'Purok 5, Sitio Liwayway',
        'category'  => 'Errands',
        'timestamp' => '2026-07-08 09:40',
    ],
    [
        'id'        => 'L-1003',
        'type'      => 'offer',
        'title'     => 'Tutoring for Public School Students',
        'desc' => 'Licensed teacher volunteering free Math and English tutorials for Grade 4 to 6 learners every Saturday.',
        'location'  => 'Purok 1, Sitio Uno',
        'category'  => 'Education',
        'timestamp' => '2026-07-07 16:05',
    ],
    [
        'id'        => 'L-1004',
        'type'      => 'request',
        'title' => 'Requesting Medical Transport Assistance',
        'desc' => 'Resident needs accompaniment to rural health unit for dialysis sessions twice weekly.',
        'location'  => 'Purok 7, Sitio Bagong Buhay',
        'category'  => 'Health',
        'timestamp' => '2026-07-07 11:22',
    ],
    [
        'id'        => 'L-1005',
        'type'      => 'offer',
        'title' => 'Community Vegetable Harvest Sharing',
        'desc' => 'Backyard gardener sharing excess squash, eggplant, and leafy greens with neighboring households.',
        'location'  => 'Purok 3, Sitio Masagana',
        'category'  => 'Food',
        'timestamp' => '2026-07-06 14:48',
    ],
    [
        'id'        => 'L-1006',
        'type'      => 'request',
        'title' => 'Borrow Ladder for Roof Repair',
        'desc' => 'Household requesting to borrow a stable aluminum ladder for a one-day minor roof patching.',
        'location'  => 'Purok 4, Sitio Mapalad',
        'category'  => 'Household',
        'timestamp' => '2026-07-06 10:03',
    ],
    [
        'id'        => 'L-1007',
        'type'      => 'offer',
        'title' => 'Free Basic Computer Literacy Session',
        'desc' => 'IT student teaching seniors how to use Messenger, GCash, and government online forms.',
        'location'  => 'Purok 6, Sitio Silangan',
        'category'  => 'Education',
        'timestamp' => '2026-07-05 13:30',
    ],
    [
        'id'        => 'L-1008',
        'type'      => 'request',
        'title' => 'Need Childcare Cover for Half Day',
        'desc' => 'Solo parent attending barangay livelihood training seeks trusted neighbor to watch a toddler.',
        'location'  => 'Purok 2, Sitio Mabini',
        'category'  => 'Family',
        'timestamp' => '2026-07-05 08:55',
    ],
    [
        'id'        => 'L-1009',
        'type'      => 'offer',
        'title' => 'Volunteer Barangay Clean-up Coordination',
        'desc' => 'Resident organizing weekly estero clean-up and lending gloves, rakes, and sacks to volunteers.',
        'location'  => 'Purok 8, Sitio Pagsibol',
        'category'  => 'Environment',
        'timestamp' => '2026-07-04 17:12',
    ],
];

// ------------------------------------------------------------------------
// 2. FILTER HANDLING (GET-based listing filter)
// ------------------------------------------------------------------------
$allowedFilters = ['all', 'offer', 'request'];
$filter = (isset($_GET['filter']) && in_array($_GET['filter'], $allowedFilters, true))
    ? $_GET['filter']
    : 'all';

$filteredListings = [];
foreach ($listings as $item) {
    if ($filter === 'all' || $item['type'] === $filter) {
        $filteredListings[] = $item;
    }
}

// ------------------------------------------------------------------------
// 3. SEARCH HANDLING (simple keyword match against title/desc/location)
// ------------------------------------------------------------------------
$searchTerm = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
if ($searchTerm !== '') {
    $searchLower = strtolower($searchTerm);
    $matched = [];
    foreach ($filteredListings as $item) {
        $haystack = strtolower($item['title'] . ' ' . $item['desc'] . ' ' . $item['location'] . ' ' . $item['category']);
        if (str_contains($haystack, $searchLower)) {
            $matched[] = $item;
        }
    }
    $filteredListings = $matched;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Official Barangay Mutual Aid & Skills Directory — a public bayanihan bulletin board for residents.">
    <title>Barangay Pulpogan Mutual Aid & Skills Directory</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-white">

    <!-- ============================================================
         GOVPH TOP BAR
         ============================================================ -->
    <div class="bg-dark text-light">
        <div class="container-fluid px-3 px-md-4">
            <div class="row align-items-center py-1">
                <div class="col-12 col-md-6 small">
                    <span class="fw-bold">GOVPH</span> <span class="text-secondary">|</span> Republic of the Philippines
                </div>
                <div class="col-12 col-md-6 small text-md-end text-secondary">
                    Official Barangay Pulpogan Mutual Aid System
                </div>
            </div>
        </div>
    </div>

    <!-- ============================================================
         MASTHEAD & NAVIGATION
         ============================================================ -->
    <header class="bg-white border-bottom border-secondary-subtle">
        <div class="container-fluid px-3 px-md-4">
            <div class="row align-items-center py-3">
                <div class="col-12 col-md-7 d-flex align-items-center mb-3 mb-md-0">
                    <div class="border border-secondary-subtle rounded-1 d-flex align-items-center justify-content-center bg-light me-3 p-3"
                        aria-hidden="true">
                        <img src="logo.png" alt="Barangay Pulpogan Logo">
                    </div>
                    <div>
                        <h1 class="h5 fw-bold mb-0 text-uppercase">Barangay Pulpogan Mutual Aid &amp; Skills Directory</h1>
                        <p class="small text-secondary mb-0">Local Government Unit &middot; Bayanihan Public Service</p>
                    </div>
                </div>
                <div class="col-12 col-md-5">
                    <nav class="navbar navbar-expand p-0">
                        <ul class="navbar-nav flex-wrap flex-md-nowrap w-100 justify-content-md-end">
                            <li class="nav-item"><a class="nav-link px-2 py-2 fw-semibold" href="index.php">Home</a></li>
                            <li class="nav-item"><a class="nav-link px-2 py-2 fw-semibold" href="#contact">Contact</a></li>
                            <li class="nav-item">
                                <a class="nav-link px-2 py-2 fw-semibold" href="login.php">Citizen Portal</a>
                            </li>
                        </ul>
                    </nav>
                </div>
            </div>
        </div>
    </header>

    <!-- ============================================================
         HERO SECTION
         ============================================================ -->
    <section class="bg-light border-bottom border-secondary-subtle py-5">
        <div class="container px-3 px-md-4">
            <div class="row justify-content-center text-center text-md-start">
                <div class="col-12 col-md-10 col-lg-8">
                    <h2 class="display-6 fw-bold mb-2">Bayanihan Digital Bulletin Board</h2>
                    <p class="text-secondary mb-4">
                        Search verified resident listings for offered skills and requested assistance within your barangay.
                        Built for community, transparency, and neighborly support.
                    </p>

                    <!-- Search input group -->
                    <form method="GET" action="index.php" class="mb-4" role="search">
                        <div class="input-group">
                            <input type="text" name="q" class="form-control py-2"
                                placeholder="Search by skill, need, or purok (e.g. tutoring, Purok 2)"
                                aria-label="Search listings" value="<?php echo htmlspecialchars($searchTerm, ENT_QUOTES); ?>">
                            <button class="btn btn-dark rounded-0 py-2 px-3 fw-semibold" type="submit">Search</button>
                        </div>
                    </form>

                    <!-- Filter toggle group: vertical stack on mobile, row on md+ -->
                    <div class="d-flex flex-column flex-md-row gap-2 w-100 w-md-auto" role="group" aria-label="Filter listings">
                        <a href="index.php?filter=all<?php echo $searchTerm !== '' ? '&amp;q=' . urlencode($searchTerm) : ''; ?>"
                            class="btn <?php echo $filter === 'all' ? 'btn-dark' : 'btn-outline-dark'; ?> rounded-1 py-2 fw-semibold flex-fill">
                            Show All
                        </a>
                        <a href="index.php?filter=offer<?php echo $searchTerm !== '' ? '&amp;q=' . urlencode($searchTerm) : ''; ?>"
                            class="btn <?php echo $filter === 'offer' ? 'btn-dark' : 'btn-outline-dark'; ?> rounded-1 py-2 fw-semibold flex-fill">
                            Offers Only
                        </a>
                        <a href="index.php?filter=request<?php echo $searchTerm !== '' ? '&amp;q=' . urlencode($searchTerm) : ''; ?>"
                            class="btn <?php echo $filter === 'request' ? 'btn-dark' : 'btn-outline-dark'; ?> rounded-1 py-2 fw-semibold flex-fill">
                            Requests Only
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ============================================================
         BULLETIN BOARD GRID
         ============================================================ -->
    <main class="container px-3 px-md-4 py-5" id="directory">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4">
            <h3 class="h4 fw-bold mb-2 mb-md-0">Community Listings</h3>
            <span class="badge border border-secondary-subtle text-secondary rounded-1 py-2 px-3">
                <?php echo count($filteredListings); ?> active <?php echo $filter === 'all' ? 'listings' : $filter . ' listings'; ?>
            </span>
        </div>

        <?php if (count($filteredListings) === 0): ?>
            <div class="alert alert-secondary border rounded-1 py-4 text-center" role="alert">
                No listings match your current search or filter. Please adjust your criteria.
            </div>
        <?php else: ?>
            <div class="row row-cols-1 row-cols-md-3 g-4">
                <?php foreach ($filteredListings as $item): ?>
                    <?php
                    $isOffer = $item['type'] === 'offer';
                    $badgeClass = $isOffer ? 'bg-success' : 'bg-primary';
                    $typeLabel  = $isOffer ? 'Offer' : 'Request';
                    ?>
                    <div class="col">
                        <article class="card h-100 rounded-1 border border-secondary-subtle">
                            <div class="card-body d-flex flex-column">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <span class="badge <?php echo $badgeClass; ?> rounded-1 px-2 py-1 text-uppercase small">
                                        <?php echo $typeLabel; ?>
                                    </span>
                                    <span class="small text-secondary"><?php echo htmlspecialchars($item['category'], ENT_QUOTES); ?></span>
                                </div>
                                <h4 class="h6 fw-bold card-title mb-2"><?php echo htmlspecialchars($item['title'], ENT_QUOTES); ?></h4>
                                <p class="small card-text mb-3"><?php echo htmlspecialchars($item['desc'], ENT_QUOTES); ?></p>
                                <ul class="list-unstyled small mb-3">
                                    <li class="mb-1"><span class="fw-semibold">Location:</span> <?php echo htmlspecialchars($item['location'], ENT_QUOTES); ?></li>
                                    <li class="text-secondary">Posted: <?php echo htmlspecialchars($item['timestamp'], ENT_QUOTES); ?></li>
                                </ul>
                                <a href="portal.php" class="btn btn-outline-primary btn-sm rounded-1 py-2 mt-auto fw-semibold">Respond to Listing</a>
                            </div>
                        </article>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>

    <!-- ============================================================
         FOOTER
         ============================================================ -->
    <footer class="bg-dark text-light pt-5 pb-4" id="contact">
        <div class="container px-3 px-md-4">
            <div class="row row-cols-1 row-cols-md-3 g-4">
                <div class="col">
                    <h5 class="h6 text-uppercase fw-bold border-bottom border-secondary pb-2 mb-3">Local Government Directory</h5>
                    <ul class="list-unstyled small">
                        <li class="mb-2">Office of the Punong Barangay</li>
                        <li class="mb-2">Barangay Council &amp; Sangguniang Kabataan</li>
                        <li class="mb-2">Barangay Secretary &amp; Treasurer</li>
                        <li class="mb-2">Local Disaster Risk Reduction Office</li>
                    </ul>
                </div>
                <div class="col">
                    <h5 class="h6 text-uppercase fw-bold border-bottom border-secondary pb-2 mb-3">Official Hotlines</h5>
                    <ul class="list-unstyled small">
                        <li class="mb-2">Barangay Hall: (02) 8000-1000</li>
                        <li class="mb-2">Rural Health Unit: (02) 8000-2000</li>
                        <li class="mb-2">Emergency / 911: 911</li>
                        <li class="mb-2">PNP Substation: (02) 8000-3000</li>
                    </ul>
                </div>
                <div class="col">
                    <h5 class="h6 text-uppercase fw-bold border-bottom border-secondary pb-2 mb-3">Data Privacy &amp; Legal</h5>
                    <p class="small text-secondary mb-2">
                        This system is governed by the Data Privacy Act of 2012 (Republic Act 10173).
                        Personal data is processed solely for public service and mutual aid coordination.
                    </p>
                    <p class="small text-secondary mb-0">
                        &copy; <?php echo date('Y'); ?> Barangay Mutual Aid &amp; Skills Directory. All rights reserved.
                    </p>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>