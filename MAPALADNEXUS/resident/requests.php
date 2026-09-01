<?php

session_start();

require_once __DIR__ . '/../config/database.php';

/*
|--------------------------------------------------------------------------
| MAPALADNEXUS
| RESIDENT - MY REQUESTS
|--------------------------------------------------------------------------
*/

/*
|--------------------------------------------------------------------------
| SECURITY
|--------------------------------------------------------------------------
*/

if (
    !isset($_SESSION['user_id']) ||
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'Resident'
) {
    header("Location: login.php");
    exit;
}

$user_id = (int) $_SESSION['user_id'];

/*
|--------------------------------------------------------------------------
| HELPER
|--------------------------------------------------------------------------
*/

function e($value)
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
}

/*
|--------------------------------------------------------------------------
| GET RESIDENT
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT
        id,
        first_name,
        middle_name,
        last_name,
        suffix,
        purok,
        address
    FROM residents
    WHERE user_id = ?
    LIMIT 1
");

$stmt->bind_param(
    "i",
    $user_id
);

$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows !== 1) {

    session_unset();
    session_destroy();

    header("Location: login.php");
    exit;
}

$resident = $result->fetch_assoc();

$resident_id =
    (int) $resident['id'];

$first_name =
    $resident['first_name'];

$last_name =
    $resident['last_name'];

$purok =
    $resident['purok'];

/*
|--------------------------------------------------------------------------
| FILTER
|--------------------------------------------------------------------------
*/

$filter =
    $_GET['status'] ?? 'All';

/*
|--------------------------------------------------------------------------
| VALID STATUS FILTERS
|--------------------------------------------------------------------------
*/

$allowed_filters = [
    'All',
    'Pending',
    'Under Review',
    'Approved',
    'Rejected'
];

if (
    !in_array(
        $filter,
        $allowed_filters,
        true
    )
) {
    $filter = 'All';
}

/*
|--------------------------------------------------------------------------
| GET REQUESTS
|--------------------------------------------------------------------------
*/

$requests = [];

if ($filter === 'All') {

    $stmt = $conn->prepare("
        SELECT
            sr.id,
            sr.reference_number,
            sr.purpose,
            sr.remarks,
            sr.status,
            sr.requested_at,
            sr.updated_at,

            s.service_name,
            s.description,
            s.processing_time,
            s.fee

        FROM service_requests sr

        INNER JOIN services s
            ON s.id = sr.service_id

        WHERE sr.resident_id = ?

        ORDER BY
            sr.requested_at DESC
    ");

    $stmt->bind_param(
        "i",
        $resident_id
    );

} else {

    $stmt = $conn->prepare("
        SELECT
            sr.id,
            sr.reference_number,
            sr.purpose,
            sr.remarks,
            sr.status,
            sr.requested_at,
            sr.updated_at,

            s.service_name,
            s.description,
            s.processing_time,
            s.fee

        FROM service_requests sr

        INNER JOIN services s
            ON s.id = sr.service_id

        WHERE
            sr.resident_id = ?
            AND sr.status = ?

        ORDER BY
            sr.requested_at DESC
    ");

    $stmt->bind_param(
        "is",
        $resident_id,
        $filter
    );
}

$stmt->execute();

$request_result =
    $stmt->get_result();

while (
    $row =
    $request_result->fetch_assoc()
) {

    $requests[] = $row;
}

/*
|--------------------------------------------------------------------------
| COUNT STATUS
|--------------------------------------------------------------------------
*/

$pending_count = 0;
$review_count = 0;
$approved_count = 0;
$rejected_count = 0;

$count_stmt = $conn->prepare("
    SELECT
        status,
        COUNT(*) AS total
    FROM service_requests
    WHERE resident_id = ?
    GROUP BY status
");

$count_stmt->bind_param(
    "i",
    $resident_id
);

$count_stmt->execute();

$count_result =
    $count_stmt->get_result();

while (
    $count =
    $count_result->fetch_assoc()
) {

    if ($count['status'] === 'Pending') {

        $pending_count =
            (int) $count['total'];

    } elseif (
        $count['status'] === 'Under Review'
    ) {

        $review_count =
            (int) $count['total'];

    } elseif (
        $count['status'] === 'Approved'
    ) {

        $approved_count =
            (int) $count['total'];

    } elseif (
        $count['status'] === 'Rejected'
    ) {

        $rejected_count =
            (int) $count['total'];
    }
}

$total_requests =
    $pending_count +
    $review_count +
    $approved_count +
    $rejected_count;

/*
|--------------------------------------------------------------------------
| STATUS STYLE
|--------------------------------------------------------------------------
*/

function statusClass($status)
{
    switch ($status) {

        case 'Approved':
            return 'approved';

        case 'Rejected':
            return 'rejected';

        case 'Under Review':
            return 'review';

        case 'Pending':
        default:
            return 'pending';
    }
}

function statusIcon($status)
{
    switch ($status) {

        case 'Approved':
            return '✓';

        case 'Rejected':
            return '×';

        case 'Under Review':
            return '◉';

        case 'Pending':
        default:
            return '◷';
    }
}

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>
    My Requests | MAPALADNEXUS
</title>

<style>

/* =========================================================
   RESET
========================================================= */

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {

    min-height: 100vh;

    font-family:
        Arial,
        Helvetica,
        sans-serif;

    color: white;

    background:
        radial-gradient(
            circle at 10% 10%,
            rgba(37,99,235,.25),
            transparent 30%
        ),
        radial-gradient(
            circle at 90% 20%,
            rgba(124,58,237,.20),
            transparent 30%
        ),
        #050816;

    overflow-x: hidden;
}

/* =========================================================
   BACKGROUND
========================================================= */

.background {

    position: fixed;

    inset: 0;

    z-index: -1;

    overflow: hidden;
}

.grid {

    position: absolute;

    inset: 0;

    background-image:
        linear-gradient(
            rgba(255,255,255,.025) 1px,
            transparent 1px
        ),
        linear-gradient(
            90deg,
            rgba(255,255,255,.025) 1px,
            transparent 1px
        );

    background-size: 55px 55px;
}

.orb {

    position: absolute;

    border-radius: 50%;

    filter: blur(10px);

    opacity: .20;

    animation:
        floating 9s
        ease-in-out
        infinite;
}

.orb.one {

    width: 330px;
    height: 330px;

    background: #2563eb;

    top: 5%;
    left: -140px;
}

.orb.two {

    width: 360px;
    height: 360px;

    background: #7c3aed;

    right: -160px;
    bottom: 5%;

    animation-delay: 2s;
}

@keyframes floating {

    0%,100% {
        transform:
            translate(0,0);
    }

    50% {
        transform:
            translate(25px,-25px);
    }
}

/* =========================================================
   LAYOUT
========================================================= */

.layout {

    min-height: 100vh;

    display: flex;
}

/* =========================================================
   SIDEBAR
========================================================= */

.sidebar {

    width: 265px;

    position: fixed;

    inset: 0 auto 0 0;

    padding: 22px 16px;

    background:
        rgba(5,8,22,.86);

    border-right:
        1px solid
        rgba(255,255,255,.08);

    backdrop-filter:
        blur(25px);

    z-index: 100;
}

.brand {

    display: flex;

    align-items: center;

    gap: 12px;

    padding:
        5px 8px 24px;

    border-bottom:
        1px solid
        rgba(255,255,255,.08);
}

.logo {

    width: 45px;
    height: 45px;

    display: flex;

    align-items: center;

    justify-content: center;

    border-radius: 14px;

    font-size: 21px;

    background:
        linear-gradient(
            145deg,
            rgba(37,99,235,.35),
            rgba(79,70,229,.12)
        );

    box-shadow:
        10px 10px 25px
        rgba(0,0,0,.30);
}

.brand strong {

    display: block;

    font-size: 15px;

    letter-spacing: 1px;
}

.brand small {

    display: block;

    margin-top: 4px;

    color: #64748b;

    font-size: 10px;
}

.section-title {

    margin:
        25px 10px 10px;

    color: #64748b;

    font-size: 10px;

    letter-spacing: 1.5px;

    font-weight: bold;
}

.menu {

    display: flex;

    flex-direction: column;

    gap: 6px;
}

.menu a {

    display: flex;

    align-items: center;

    gap: 12px;

    padding: 13px 14px;

    border-radius: 14px;

    color: #94a3b8;

    text-decoration: none;

    font-size: 13px;

    transition: .25s;
}

.menu a:hover {

    color: white;

    background:
        rgba(255,255,255,.07);

    transform:
        translateX(4px);
}

.menu a.active {

    color: white;

    background:
        linear-gradient(
            145deg,
            rgba(37,99,235,.82),
            rgba(79,70,229,.72)
        );

    box-shadow:
        0 12px 25px
        rgba(37,99,235,.20);
}

.icon {

    width: 22px;

    text-align: center;

    font-size: 17px;
}

.logout-box {

    position: absolute;

    left: 16px;
    right: 16px;

    bottom: 20px;
}

.logout {

    display: flex;

    align-items: center;

    justify-content: center;

    gap: 8px;

    padding: 13px;

    border-radius: 14px;

    color: #fca5a5;

    text-decoration: none;

    font-size: 13px;

    background:
        rgba(239,68,68,.08);

    border:
        1px solid
        rgba(239,68,68,.15);
}

/* =========================================================
   MAIN
========================================================= */

.main {

    width:
        calc(100% - 265px);

    margin-left: 265px;

    padding:
        25px 30px 60px;
}

/* =========================================================
   TOPBAR
========================================================= */

.topbar {

    display: flex;

    align-items: center;

    justify-content: space-between;

    margin-bottom: 25px;

    padding: 14px 18px;

    border-radius: 20px;

    background:
        rgba(255,255,255,.055);

    border:
        1px solid
        rgba(255,255,255,.09);

    backdrop-filter:
        blur(20px);
}

.welcome {

    display: flex;

    align-items: center;

    gap: 12px;
}

.avatar {

    width: 43px;
    height: 43px;

    display: flex;

    align-items: center;

    justify-content: center;

    border-radius: 14px;

    background:
        linear-gradient(
            145deg,
            #2563eb,
            #4f46e5
        );

    font-weight: bold;
}

.welcome strong {

    display: block;

    font-size: 13px;
}

.welcome span {

    display: block;

    margin-top: 4px;

    color: #64748b;

    font-size: 10px;
}

.back {

    padding: 10px 14px;

    border-radius: 12px;

    color: #93c5fd;

    text-decoration: none;

    background:
        rgba(255,255,255,.05);

    border:
        1px solid
        rgba(255,255,255,.08);

    font-size: 11px;
}

/* =========================================================
   HEADER
========================================================= */

.page-header {

    margin-bottom: 25px;
}

.page-header small {

    color: #93c5fd;

    font-size: 10px;

    letter-spacing: 1.5px;

    text-transform: uppercase;
}

.page-header h1 {

    margin-top: 8px;

    font-size:
        clamp(28px,4vw,40px);
}

.page-header p {

    margin-top: 8px;

    max-width: 700px;

    color: #94a3b8;

    font-size: 13px;

    line-height: 1.7;
}

/* =========================================================
   STAT CARDS
========================================================= */

.stats {

    display: grid;

    grid-template-columns:
        repeat(4,1fr);

    gap: 16px;

    margin-bottom: 25px;
}

.stat {

    position: relative;

    overflow: hidden;

    padding: 20px;

    border-radius: 20px;

    background:
        rgba(255,255,255,.055);

    border:
        1px solid
        rgba(255,255,255,.08);

    box-shadow:
        15px 18px 35px
        rgba(0,0,0,.18);

    transition: .3s;
}

.stat:hover {

    transform:
        translateY(-5px);
}

.stat-icon {

    width: 38px;
    height: 38px;

    display: flex;

    align-items: center;

    justify-content: center;

    border-radius: 12px;

    background:
        rgba(255,255,255,.07);

    font-size: 17px;
}

.stat-number {

    margin-top: 14px;

    font-size: 25px;

    font-weight: bold;
}

.stat-label {

    margin-top: 4px;

    color: #64748b;

    font-size: 10px;
}

/* =========================================================
   FILTER BAR
========================================================= */

.filter-card {

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 15px;

    margin-bottom: 18px;

    padding: 16px 18px;

    border-radius: 18px;

    background:
        rgba(255,255,255,.05);

    border:
        1px solid
        rgba(255,255,255,.08);
}

.filter-title {

    color: #cbd5e1;

    font-size: 12px;

    font-weight: bold;
}

.filters {

    display: flex;

    gap: 7px;

    flex-wrap: wrap;
}

.filters a {

    padding: 9px 12px;

    border-radius: 10px;

    color: #64748b;

    text-decoration: none;

    font-size: 10px;

    background:
        rgba(255,255,255,.035);

    border:
        1px solid
        rgba(255,255,255,.06);

    transition: .2s;
}

.filters a:hover {

    color: white;

    background:
        rgba(255,255,255,.08);
}

.filters a.active {

    color: white;

    background:
        linear-gradient(
            145deg,
            #2563eb,
            #4f46e5
        );

    border-color:
        transparent;
}

/* =========================================================
   REQUEST LIST
========================================================= */

.requests {

    display: grid;

    gap: 16px;
}

.request-card {

    position: relative;

    overflow: hidden;

    padding: 22px;

    border-radius: 22px;

    background:
        linear-gradient(
            145deg,
            rgba(255,255,255,.065),
            rgba(255,255,255,.035)
        );

    border:
        1px solid
        rgba(255,255,255,.09);

    box-shadow:
        17px 20px 40px
        rgba(0,0,0,.20);

    transition:
        .3s ease;
}

.request-card:hover {

    transform:
        translateY(-5px);

    border-color:
        rgba(96,165,250,.20);

    box-shadow:
        20px 25px 50px
        rgba(0,0,0,.30);
}

.request-top {

    display: flex;

    align-items: flex-start;

    justify-content: space-between;

    gap: 15px;
}

.service {

    display: flex;

    align-items: center;

    gap: 13px;
}

.service-icon {

    width: 50px;
    height: 50px;

    display: flex;

    align-items: center;

    justify-content: center;

    flex-shrink: 0;

    border-radius: 15px;

    background:
        linear-gradient(
            145deg,
            rgba(37,99,235,.18),
            rgba(124,58,237,.10)
        );

    border:
        1px solid
        rgba(96,165,250,.14);

    font-size: 22px;
}

.service-name {

    font-size: 14px;

    font-weight: bold;
}

.reference {

    margin-top: 5px;

    color: #64748b;

    font-size: 9px;

    letter-spacing: .5px;
}

.reference strong {

    color: #93c5fd;

    font-size: 10px;
}

/* =========================================================
   STATUS
========================================================= */

.status {

    display: inline-flex;

    align-items: center;

    gap: 6px;

    padding: 8px 11px;

    border-radius: 10px;

    font-size: 9px;

    font-weight: bold;

    white-space: nowrap;
}

.status.pending {

    color: #fcd34d;

    background:
        rgba(245,158,11,.09);

    border:
        1px solid
        rgba(245,158,11,.14);
}

.status.review {

    color: #93c5fd;

    background:
        rgba(37,99,235,.10);

    border:
        1px solid
        rgba(96,165,250,.14);
}

.status.approved {

    color: #86efac;

    background:
        rgba(34,197,94,.09);

    border:
        1px solid
        rgba(34,197,94,.14);
}

.status.rejected {

    color: #fca5a5;

    background:
        rgba(239,68,68,.09);

    border:
        1px solid
        rgba(239,68,68,.14);
}

/* =========================================================
   REQUEST DETAILS
========================================================= */

.request-details {

    display: grid;

    grid-template-columns:
        1.4fr
        1fr
        1fr;

    gap: 12px;

    margin-top: 20px;
}

.detail {

    padding: 13px;

    border-radius: 13px;

    background:
        rgba(0,0,0,.14);

    border:
        1px solid
        rgba(255,255,255,.05);
}

.detail small {

    display: block;

    color: #64748b;

    font-size: 8px;

    text-transform: uppercase;

    letter-spacing: 1px;
}

.detail strong {

    display: block;

    margin-top: 5px;

    color: #cbd5e1;

    font-size: 11px;

    line-height: 1.5;
}

/* =========================================================
   FOOTER
========================================================= */

.request-footer {

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 15px;

    margin-top: 17px;

    padding-top: 15px;

    border-top:
        1px solid
        rgba(255,255,255,.06);
}

.date {

    color: #475569;

    font-size: 9px;
}

.view-btn {

    padding: 9px 13px;

    border-radius: 10px;

    color: #93c5fd;

    text-decoration: none;

    font-size: 9px;

    background:
        rgba(37,99,235,.08);

    border:
        1px solid
        rgba(96,165,250,.12);
}

.view-btn:hover {

    color: white;

    background:
        rgba(37,99,235,.18);
}

/* =========================================================
   EMPTY
========================================================= */

.empty {

    padding: 70px 20px;

    text-align: center;

    border-radius: 25px;

    background:
        rgba(255,255,255,.04);

    border:
        1px solid
        rgba(255,255,255,.08);
}

.empty-icon {

    font-size: 48px;

    margin-bottom: 16px;
}

.empty h2 {

    font-size: 17px;
}

.empty p {

    margin-top: 8px;

    color: #64748b;

    font-size: 11px;
}

.empty a {

    display: inline-block;

    margin-top: 20px;

    padding: 12px 16px;

    border-radius: 12px;

    color: white;

    text-decoration: none;

    font-size: 10px;

    font-weight: bold;

    background:
        linear-gradient(
            145deg,
            #2563eb,
            #4f46e5
        );
}

/* =========================================================
   MOBILE
========================================================= */

.mobile-menu {

    display: none;

    padding: 9px 12px;

    border: none;

    border-radius: 10px;

    color: white;

    background:
        rgba(255,255,255,.08);

    font-size: 18px;

    cursor: pointer;
}

.mobile-top {

    display: none;
}

@media(max-width:1050px) {

    .stats {

        grid-template-columns:
            repeat(2,1fr);
    }
}

@media(max-width:800px) {

    .sidebar {

        transform:
            translateX(-100%);

        transition: .3s;
    }

    .sidebar.open {

        transform:
            translateX(0);
    }

    .main {

        width: 100%;

        margin-left: 0;

        padding: 15px;
    }

    .mobile-top {

        display: flex;

        align-items: center;

        justify-content: space-between;

        margin-bottom: 15px;
    }

    .mobile-menu {

        display: block;
    }
}

@media(max-width:650px) {

    .stats {

        grid-template-columns: 1fr 1fr;

        gap: 10px;
    }

    .request-top {

        flex-direction: column;
    }

    .request-details {

        grid-template-columns: 1fr;
    }

    .filter-card {

        align-items: flex-start;

        flex-direction: column;
    }

    .back {

        display: none;
    }
}

@media(max-width:450px) {

    .stats {

        grid-template-columns: 1fr;
    }

    .request-card {

        padding: 17px;
    }

    .request-footer {

        align-items: flex-start;

        flex-direction: column;
    }
}

</style>

</head>

<body>

<div class="background">

    <div class="grid"></div>

    <div class="orb one"></div>

    <div class="orb two"></div>

</div>

<div class="layout">

<!-- =====================================================
     SIDEBAR
====================================================== -->

<aside
    class="sidebar"
    id="sidebar"
>

    <div class="brand">

        <div class="logo">
            🏛️
        </div>

        <div>

            <strong>
                MAPALADNEXUS
            </strong>

            <small>
                Barangay Mapalad
            </small>

        </div>

    </div>

    <div class="section-title">
        Resident Portal
    </div>

    <nav class="menu">

        <a href="user_dashboard.php">

            <span class="icon">
                ⌂
            </span>

            Dashboard

        </a>

        <a href="services.php">

            <span class="icon">
                📄
            </span>

            Services

        </a>

        <a
            href="requests.php"
            class="active"
        >

            <span class="icon">
                📋
            </span>

            My Requests

        </a>

        <a href="complaints.php">

            <span class="icon">
                💬
            </span>

            Complaints

        </a>

        <a href="announcements.php">

            <span class="icon">
                📢
            </span>

            Announcements

        </a>

        <a href="profile.php">

            <span class="icon">
                👤
            </span>

            My Profile

        </a>

    </nav>

    <div class="logout-box">

        <a
            href="logout.php"
            class="logout"
        >

            🚪 Logout

        </a>

    </div>

</aside>

<!-- =====================================================
     MAIN
====================================================== -->

<main class="main">

    <div class="mobile-top">

        <strong>
            MAPALADNEXUS
        </strong>

        <button
            class="mobile-menu"
            onclick="toggleMenu()"
        >
            ☰
        </button>

    </div>

    <!-- =================================================
         TOPBAR
    ================================================== -->

    <div class="topbar">

        <div class="welcome">

            <div class="avatar">

                <?= e(
                    strtoupper(
                        substr(
                            $first_name,
                            0,
                            1
                        )
                    )
                ) ?>

            </div>

            <div>

                <strong>

                    <?= e(
                        $first_name .
                        ' ' .
                        $last_name
                    ) ?>

                </strong>

                <span>

                    <?= e($purok) ?>
                    · Resident

                </span>

            </div>

        </div>

        <a
            href="services.php"
            class="back"
        >

            + New Request

        </a>

    </div>

    <!-- =================================================
         PAGE HEADER
    ================================================== -->

    <section class="page-header">

        <small>
            Request Management
        </small>

        <h1>
            My Requests
        </h1>

        <p>
            Track all your barangay service requests
            and monitor their current processing status.
        </p>

    </section>

    <!-- =================================================
         STATS
    ================================================== -->

    <section class="stats">

        <div class="stat">

            <div class="stat-icon">
                📋
            </div>

            <div class="stat-number">
                <?= $total_requests ?>
            </div>

            <div class="stat-label">
                Total Requests
            </div>

        </div>

        <div class="stat">

            <div class="stat-icon">
                ◷
            </div>

            <div class="stat-number">
                <?= $pending_count ?>
            </div>

            <div class="stat-label">
                Pending
            </div>

        </div>

        <div class="stat">

            <div class="stat-icon">
                ◉
            </div>

            <div class="stat-number">
                <?= $review_count ?>
            </div>

            <div class="stat-label">
                Under Review
            </div>

        </div>

        <div class="stat">

            <div class="stat-icon">
                ✓
            </div>

            <div class="stat-number">
                <?= $approved_count ?>
            </div>

            <div class="stat-label">
                Approved
            </div>

        </div>

    </section>

    <!-- =================================================
         FILTER
    ================================================== -->

    <div class="filter-card">

        <div class="filter-title">
            Filter Requests
        </div>

        <div class="filters">

            <a
                href="requests.php"
                class="<?= $filter === 'All' ? 'active' : '' ?>"
            >
                All
            </a>

            <a
                href="requests.php?status=Pending"
                class="<?= $filter === 'Pending' ? 'active' : '' ?>"
            >
                Pending
            </a>

            <a
                href="requests.php?status=Under%20Review"
                class="<?= $filter === 'Under Review' ? 'active' : '' ?>"
            >
                Under Review
            </a>

            <a
                href="requests.php?status=Approved"
                class="<?= $filter === 'Approved' ? 'active' : '' ?>"
            >
                Approved
            </a>

            <a
                href="requests.php?status=Rejected"
                class="<?= $filter === 'Rejected' ? 'active' : '' ?>"
            >
                Rejected
            </a>

        </div>

    </div>

    <!-- =================================================
         REQUEST LIST
    ================================================== -->

    <section class="requests">

        <?php if (count($requests) > 0): ?>

            <?php foreach ($requests as $request): ?>

                <article class="request-card">

                    <div class="request-top">

                        <div class="service">

                            <div class="service-icon">
                                📄
                            </div>

                            <div>

                                <div class="service-name">

                                    <?= e(
                                        $request['service_name']
                                    ) ?>

                                </div>

                                <div class="reference">

                                    Reference:

                                    <strong>

                                        <?= e(
                                            $request['reference_number']
                                        ) ?>

                                    </strong>

                                </div>

                            </div>

                        </div>

                        <div
                            class="status <?= statusClass(
                                $request['status']
                            ) ?>"
                        >

                            <?= statusIcon(
                                $request['status']
                            ) ?>

                            <?= e(
                                $request['status']
                            ) ?>

                        </div>

                    </div>

                    <div class="request-details">

                        <div class="detail">

                            <small>
                                Purpose
                            </small>

                            <strong>

                                <?= e(
                                    $request['purpose']
                                ) ?>

                            </strong>

                        </div>

                        <div class="detail">

                            <small>
                                Service Fee
                            </small>

                            <strong>

                                <?php

                                if (
                                    $request['fee'] !== null &&
                                    $request['fee'] !== ''
                                ) {

                                    echo '₱' .
                                        number_format(
                                            (float)
                                            $request['fee'],
                                            2
                                        );

                                } else {

                                    echo 'Free';
                                }

                                ?>

                            </strong>

                        </div>

                        <div class="detail">

                            <small>
                                Processing Time
                            </small>

                            <strong>

                                <?= e(
                                    $request['processing_time']
                                    ?: 'To be advised'
                                ) ?>

                            </strong>

                        </div>

                    </div>

                    <div class="request-footer">

                        <div class="date">

                            Requested:

                            <?= e(
                                date(
                                    'M d, Y • h:i A',
                                    strtotime(
                                        $request['requested_at']
                                    )
                                )
                            ) ?>

                        </div>

                        <a
                            href="request_details.php?id=<?= (int)$request['id'] ?>"
                            class="view-btn"
                        >

                            View Details →

                        </a>

                    </div>

                </article>

            <?php endforeach; ?>

        <?php else: ?>

            <div class="empty">

                <div class="empty-icon">
                    📭
                </div>

                <h2>
                    No Requests Found
                </h2>

                <p>

                    You don't have any
                    <?= $filter !== 'All'
                        ? e(strtolower($filter))
                        : ''
                    ?>
                    service requests yet.

                </p>

                <a href="services.php">
                    + Request a Service
                </a>

            </div>

        <?php endif; ?>

    </section>

</main>

</div>

<script>

function toggleMenu()
{
    const sidebar =
        document.getElementById('sidebar');

    sidebar.classList.toggle('open');
}

</script>

</body>

</html>