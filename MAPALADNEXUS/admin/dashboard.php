<?php

session_start();

require_once __DIR__ . '/../config/database.php';

/*
|--------------------------------------------------------------------------
| MAPALADNEXUS
| ADMIN DASHBOARD
| Barangay Mapalad
|--------------------------------------------------------------------------
*/

/*
|--------------------------------------------------------------------------
| ADMIN SECURITY
|--------------------------------------------------------------------------
*/

if (
    !isset($_SESSION['user_id']) ||
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'Admin'
) {
    header("Location: login.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| ADMIN INFORMATION
|--------------------------------------------------------------------------
*/

$admin_username = $_SESSION['username'] ?? 'Admin';

/*
|--------------------------------------------------------------------------
| SAFE TABLE CHECK
|--------------------------------------------------------------------------
*/

function tableExists($conn, $table)
{
    $table = $conn->real_escape_string($table);

    $result = $conn->query("
        SELECT COUNT(*) AS total
        FROM information_schema.tables
        WHERE table_schema = DATABASE()
        AND table_name = '$table'
    ");

    if (!$result) {
        return false;
    }

    $row = $result->fetch_assoc();

    return ((int)$row['total'] > 0);
}

/*
|--------------------------------------------------------------------------
| COUNT RECORDS
|--------------------------------------------------------------------------
*/

function getCount($conn, $table)
{
    if (!tableExists($conn, $table)) {
        return 0;
    }

    $result = $conn->query(
        "SELECT COUNT(*) AS total FROM `$table`"
    );

    if (!$result) {
        return 0;
    }

    $row = $result->fetch_assoc();

    return (int)$row['total'];
}

/*
|--------------------------------------------------------------------------
| DASHBOARD COUNTS
|--------------------------------------------------------------------------
*/

$residents_count =
    getCount($conn, 'residents');

$users_count =
    getCount($conn, 'users');

$services_count =
    getCount($conn, 'services');

$requests_count =
    getCount($conn, 'service_requests');

$complaints_count =
    getCount($conn, 'complaints');

$announcements_count =
    getCount($conn, 'announcements');

$officials_count = 0;

if (tableExists($conn, 'users')) {

    $official_result = $conn->query("
        SELECT COUNT(*) AS total
        FROM users
        WHERE role = 'Official'
    ");

    if ($official_result) {

        $official_row =
            $official_result->fetch_assoc();

        $officials_count =
            (int)$official_row['total'];
    }
}

/*
|--------------------------------------------------------------------------
| PENDING REQUESTS
|--------------------------------------------------------------------------
*/

$pending_requests = 0;

if (tableExists($conn, 'service_requests')) {

    $result = $conn->query("
        SELECT COUNT(*) AS total
        FROM service_requests
        WHERE status = 'Pending'
    ");

    if ($result) {

        $row = $result->fetch_assoc();

        $pending_requests =
            (int)$row['total'];
    }
}

/*
|--------------------------------------------------------------------------
| PENDING COMPLAINTS
|--------------------------------------------------------------------------
*/

$pending_complaints = 0;

if (tableExists($conn, 'complaints')) {

    $result = $conn->query("
        SELECT COUNT(*) AS total
        FROM complaints
        WHERE status IN ('Pending','Under Review')
    ");

    if ($result) {

        $row = $result->fetch_assoc();

        $pending_complaints =
            (int)$row['total'];
    }
}

/*
|--------------------------------------------------------------------------
| RECENT ANNOUNCEMENTS
|--------------------------------------------------------------------------
*/

$recent_announcements = [];

if (tableExists($conn, 'announcements')) {

    $result = $conn->query("
        SELECT
            id,
            title,
            target_purok,
            created_at
        FROM announcements
        ORDER BY created_at DESC
        LIMIT 5
    ");

    if ($result) {

        while ($row = $result->fetch_assoc()) {

            $recent_announcements[] =
                $row;
        }
    }
}

/*
|--------------------------------------------------------------------------
| CURRENT DATE
|--------------------------------------------------------------------------
*/

$current_date =
    date('l, F d, Y');

$current_time =
    date('h:i A');

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
    Admin Dashboard | MAPALADNEXUS
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

:root {

    --bg:
        #050816;

    --panel:
        rgba(255,255,255,.055);

    --panel-hover:
        rgba(255,255,255,.09);

    --border:
        rgba(255,255,255,.08);

    --text:
        #f8fafc;

    --muted:
        #94a3b8;

    --dim:
        #64748b;

    --blue:
        #2563eb;

    --purple:
        #7c3aed;

    --green:
        #10b981;

    --orange:
        #f59e0b;

    --red:
        #ef4444;
}

/* =========================================================
   BODY
========================================================= */

body {

    min-height: 100vh;

    font-family:
        Arial,
        Helvetica,
        sans-serif;

    color: var(--text);

    background:
        radial-gradient(
            circle at 10% 10%,
            rgba(37,99,235,.15),
            transparent 28%
        ),
        radial-gradient(
            circle at 90% 80%,
            rgba(124,58,237,.14),
            transparent 30%
        ),
        var(--bg);

    overflow-x: hidden;
}

/* =========================================================
   BACKGROUND GRID
========================================================= */

.background-grid {

    position: fixed;

    inset: 0;

    z-index: -5;

    opacity: .55;

    background-image:
        linear-gradient(
            rgba(255,255,255,.018) 1px,
            transparent 1px
        ),
        linear-gradient(
            90deg,
            rgba(255,255,255,.018) 1px,
            transparent 1px
        );

    background-size: 60px 60px;
}

.background-orb {

    position: fixed;

    z-index: -4;

    border-radius: 50%;

    filter: blur(70px);

    opacity: .13;

    pointer-events: none;
}

.orb-one {

    width: 420px;
    height: 420px;

    left: -200px;
    top: 10%;

    background:
        #2563eb;
}

.orb-two {

    width: 450px;
    height: 450px;

    right: -220px;
    bottom: -100px;

    background:
        #7c3aed;
}

/* =========================================================
   SIDEBAR
========================================================= */

.sidebar {

    position: fixed;

    left: 20px;

    top: 20px;

    bottom: 20px;

    width: 255px;

    padding: 22px 16px;

    border:
        1px solid
        var(--border);

    border-radius: 26px;

    background:
        rgba(10,15,32,.78);

    backdrop-filter:
        blur(24px);

    box-shadow:
        18px 25px 60px
        rgba(0,0,0,.35);

    z-index: 100;

    display: flex;

    flex-direction: column;
}

/* =========================================================
   BRAND
========================================================= */

.brand {

    display: flex;

    align-items: center;

    gap: 12px;

    padding:
        4px 8px
        20px;

    border-bottom:
        1px solid
        var(--border);
}

.brand-logo {

    width: 48px;
    height: 48px;

    display: flex;

    align-items: center;

    justify-content: center;

    border-radius: 15px;

    background:
        linear-gradient(
            145deg,
            #2563eb,
            #4f46e5
        );

    box-shadow:
        8px 12px 25px
        rgba(37,99,235,.25);

    font-size: 23px;

    transform:
        perspective(500px)
        rotateX(5deg);
}

.brand-text h2 {

    font-size: 15px;

    letter-spacing: .4px;
}

.brand-text span {

    display: block;

    margin-top: 4px;

    color:
        var(--dim);

    font-size: 8px;

    letter-spacing: 1px;

    text-transform: uppercase;
}

/* =========================================================
   NAVIGATION
========================================================= */

.nav-title {

    padding:
        22px 10px 10px;

    color:
        var(--dim);

    font-size: 8px;

    font-weight: bold;

    letter-spacing: 1.2px;

    text-transform: uppercase;
}

.nav {

    flex: 1;

    overflow-y: auto;

    padding-right: 3px;
}

.nav::-webkit-scrollbar {

    width: 3px;
}

.nav::-webkit-scrollbar-thumb {

    background:
        rgba(255,255,255,.10);

    border-radius: 20px;
}

.nav-item {

    display: flex;

    align-items: center;

    gap: 12px;

    margin-bottom: 6px;

    padding:
        12px 12px;

    color:
        var(--muted);

    text-decoration: none;

    border:
        1px solid
        transparent;

    border-radius: 13px;

    font-size: 10px;

    transition:
        .25s ease;
}

.nav-item:hover {

    color: white;

    background:
        rgba(255,255,255,.055);

    border-color:
        rgba(255,255,255,.06);

    transform:
        translateX(3px);
}

.nav-item.active {

    color: white;

    background:
        linear-gradient(
            135deg,
            rgba(37,99,235,.25),
            rgba(79,70,229,.16)
        );

    border-color:
        rgba(96,165,250,.15);

    box-shadow:
        inset 3px 0 0
        #3b82f6;
}

.nav-icon {

    width: 24px;

    text-align: center;

    font-size: 15px;
}

/* =========================================================
   LOGOUT
========================================================= */

.logout {

    display: flex;

    align-items: center;

    gap: 12px;

    margin-top: 12px;

    padding:
        13px;

    color:
        #fca5a5;

    text-decoration: none;

    background:
        rgba(239,68,68,.07);

    border:
        1px solid
        rgba(239,68,68,.12);

    border-radius: 13px;

    font-size: 10px;

    transition:
        .25s;
}

.logout:hover {

    color: white;

    background:
        rgba(239,68,68,.15);

    transform:
        translateY(-2px);
}

/* =========================================================
   MAIN
========================================================= */

.main {

    margin-left: 295px;

    padding:
        30px;

    min-height: 100vh;
}

/* =========================================================
   TOP BAR
========================================================= */

.topbar {

    display: flex;

    justify-content: space-between;

    align-items: center;

    gap: 20px;

    margin-bottom: 28px;
}

.page-title h1 {

    font-size: 26px;

    letter-spacing: -.5px;
}

.page-title p {

    margin-top: 7px;

    color:
        var(--muted);

    font-size: 10px;
}

.admin-profile {

    display: flex;

    align-items: center;

    gap: 11px;

    padding:
        9px 12px;

    background:
        var(--panel);

    border:
        1px solid
        var(--border);

    border-radius: 15px;

    backdrop-filter:
        blur(15px);
}

.admin-avatar {

    width: 35px;
    height: 35px;

    display: flex;

    align-items: center;

    justify-content: center;

    border-radius: 11px;

    background:
        linear-gradient(
            145deg,
            #2563eb,
            #7c3aed
        );

    font-size: 14px;
}

.admin-info strong {

    display: block;

    font-size: 10px;
}

.admin-info span {

    display: block;

    margin-top: 3px;

    color:
        #60a5fa;

    font-size: 7px;

    text-transform: uppercase;
}

/* =========================================================
   HERO
========================================================= */

.hero {

    position: relative;

    overflow: hidden;

    margin-bottom: 25px;

    padding: 28px;

    border:
        1px solid
        var(--border);

    border-radius: 25px;

    background:
        linear-gradient(
            120deg,
            rgba(37,99,235,.16),
            rgba(79,70,229,.08),
            rgba(255,255,255,.035)
        );

    box-shadow:
        15px 22px 55px
        rgba(0,0,0,.20);
}

.hero::after {

    content: "";

    position: absolute;

    width: 220px;
    height: 220px;

    right: -80px;
    top: -100px;

    border-radius: 50%;

    background:
        rgba(96,165,250,.13);

    filter:
        blur(10px);
}

.hero-content {

    position: relative;

    z-index: 2;
}

.hero h2 {

    font-size: 19px;

    margin-bottom: 7px;
}

.hero p {

    max-width: 650px;

    color:
        var(--muted);

    font-size: 10px;

    line-height: 1.7;
}

.hero-date {

    margin-top: 17px;

    color:
        #60a5fa;

    font-size: 8px;

    letter-spacing: .6px;
}

/* =========================================================
   STATS GRID
========================================================= */

.stats-grid {

    display: grid;

    grid-template-columns:
        repeat(4, 1fr);

    gap: 15px;

    margin-bottom: 25px;
}

.stat-card {

    position: relative;

    overflow: hidden;

    padding: 20px;

    min-height: 145px;

    border:
        1px solid
        var(--border);

    border-radius: 20px;

    background:
        var(--panel);

    backdrop-filter:
        blur(18px);

    box-shadow:
        10px 15px 35px
        rgba(0,0,0,.16);

    transition:
        .3s ease;

    transform:
        perspective(800px)
        rotateX(1deg);
}

.stat-card:hover {

    transform:
        perspective(800px)
        rotateX(0deg)
        translateY(-5px);

    background:
        var(--panel-hover);

    box-shadow:
        14px 22px 45px
        rgba(0,0,0,.25);
}

.stat-icon {

    width: 40px;
    height: 40px;

    display: flex;

    align-items: center;

    justify-content: center;

    margin-bottom: 15px;

    border-radius: 12px;

    background:
        rgba(255,255,255,.07);

    font-size: 17px;
}

.stat-card h3 {

    font-size: 25px;

    letter-spacing: -.5px;
}

.stat-card p {

    margin-top: 5px;

    color:
        var(--muted);

    font-size: 9px;
}

.stat-line {

    position: absolute;

    left: 20px;

    right: 20px;

    bottom: 12px;

    height: 2px;

    border-radius: 20px;

    background:
        rgba(255,255,255,.07);
}

.stat-line span {

    display: block;

    width: 45%;

    height: 100%;

    border-radius: 20px;

    background:
        currentColor;
}

.blue {
    color: #60a5fa;
}

.green {
    color: #34d399;
}

.orange {
    color: #fbbf24;
}

.purple {
    color: #a78bfa;
}

/* =========================================================
   CONTENT GRID
========================================================= */

.content-grid {

    display: grid;

    grid-template-columns:
        1.4fr
        .8fr;

    gap: 20px;
}

/* =========================================================
   PANEL
========================================================= */

.panel {

    padding: 21px;

    border:
        1px solid
        var(--border);

    border-radius: 21px;

    background:
        var(--panel);

    backdrop-filter:
        blur(18px);

    box-shadow:
        10px 15px 35px
        rgba(0,0,0,.15);
}

.panel-header {

    display: flex;

    justify-content: space-between;

    align-items: center;

    margin-bottom: 18px;
}

.panel-header h3 {

    font-size: 12px;
}

.panel-header span {

    color:
        var(--dim);

    font-size: 8px;
}

/* =========================================================
   ANNOUNCEMENTS
========================================================= */

.announcement {

    display: flex;

    align-items: center;

    gap: 12px;

    padding:
        12px 0;

    border-bottom:
        1px solid
        rgba(255,255,255,.055);
}

.announcement:last-child {

    border-bottom:
        none;
}

.announcement-icon {

    flex-shrink: 0;

    width: 34px;
    height: 34px;

    display: flex;

    align-items: center;

    justify-content: center;

    border-radius: 10px;

    background:
        rgba(37,99,235,.12);

    font-size: 14px;
}

.announcement-info {

    min-width: 0;

    flex: 1;
}

.announcement-info strong {

    display: block;

    overflow: hidden;

    color:
        #e2e8f0;

    font-size: 9px;

    text-overflow: ellipsis;

    white-space: nowrap;
}

.announcement-info span {

    display: block;

    margin-top: 4px;

    color:
        var(--dim);

    font-size: 7px;
}

.purok {

    padding:
        5px 8px;

    color:
        #93c5fd;

    background:
        rgba(37,99,235,.08);

    border-radius: 8px;

    font-size: 7px;
}

.empty {

    padding: 35px 10px;

    text-align: center;

    color:
        var(--dim);

    font-size: 9px;
}

/* =========================================================
   QUICK ACTIONS
========================================================= */

.quick-grid {

    display: grid;

    grid-template-columns:
        repeat(2, 1fr);

    gap: 10px;
}

.quick-action {

    padding:
        16px 12px;

    color:
        var(--muted);

    text-decoration: none;

    text-align: center;

    border:
        1px solid
        rgba(255,255,255,.06);

    border-radius: 14px;

    background:
        rgba(255,255,255,.025);

    transition:
        .25s;
}

.quick-action:hover {

    color: white;

    transform:
        translateY(-3px);

    background:
        rgba(255,255,255,.07);

    border-color:
        rgba(96,165,250,.15);
}

.quick-action div {

    margin-bottom: 8px;

    font-size: 19px;
}

.quick-action span {

    font-size: 8px;
}

/* =========================================================
   ALERT BOXES
========================================================= */

.alert-list {

    margin-top: 20px;
}

.alert-item {

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 12px;

    margin-bottom: 10px;

    padding: 12px;

    border-radius: 12px;

    background:
        rgba(255,255,255,.025);

    border:
        1px solid
        rgba(255,255,255,.05);
}

.alert-left {

    display: flex;

    align-items: center;

    gap: 10px;
}

.alert-icon {

    font-size: 14px;
}

.alert-left span {

    color:
        var(--muted);

    font-size: 8px;
}

.alert-number {

    font-size: 13px;

    font-weight: bold;
}

/* =========================================================
   MOBILE BUTTON
========================================================= */

.mobile-menu {

    display: none;

    border: none;

    color: white;

    background:
        var(--panel);

    border:
        1px solid
        var(--border);

    border-radius: 12px;

    padding: 10px 12px;

    cursor: pointer;
}

/* =========================================================
   RESPONSIVE
========================================================= */

@media (max-width: 1200px) {

    .stats-grid {

        grid-template-columns:
            repeat(2, 1fr);
    }
}

@media (max-width: 900px) {

    .sidebar {

        transform:
            translateX(-120%);

        transition:
            .3s ease;
    }

    .sidebar.show {

        transform:
            translateX(0);
    }

    .main {

        margin-left: 0;

        padding: 20px;
    }

    .mobile-menu {

        display: block;
    }

    .topbar {

        align-items: flex-start;
    }

    .content-grid {

        grid-template-columns:
            1fr;
    }
}

@media (max-width: 600px) {

    .stats-grid {

        grid-template-columns:
            1fr;
    }

    .topbar {

        flex-wrap: wrap;
    }

    .admin-profile {

        width: 100%;
    }

    .hero {

        padding: 21px;
    }

    .hero h2 {

        font-size: 17px;
    }
}

</style>

</head>

<body>

<!-- =====================================================
     BACKGROUND
====================================================== -->

<div class="background-grid"></div>

<div class="background-orb orb-one"></div>

<div class="background-orb orb-two"></div>


<!-- =====================================================
     SIDEBAR
====================================================== -->

<aside class="sidebar" id="sidebar">

    <div class="brand">

        <div class="brand-logo">
            🏛️
        </div>

        <div class="brand-text">

            <h2>
                MAPALADNEXUS
            </h2>

            <span>
                Barangay Mapalad
            </span>

        </div>

    </div>


    <div class="nav-title">
        Main Navigation
    </div>


    <nav class="nav">

        <a
            href="dashboard.php"
            class="nav-item active"
        >

            <span class="nav-icon">
                🏠
            </span>

            Dashboard

        </a>


        <a
            href="residents.php"
            class="nav-item"
        >

            <span class="nav-icon">
                👥
            </span>

            Residents

        </a>


        <a
            href="services.php"
            class="nav-item"
        >

            <span class="nav-icon">
                🛠️
            </span>

            Services

        </a>


        <a
            href="requests.php"
            class="nav-item"
        >

            <span class="nav-icon">
                📋
            </span>

            Service Requests

        </a>


        <a
            href="announcements.php"
            class="nav-item"
        >

            <span class="nav-icon">
                📢
            </span>

            Announcements

        </a>


        <a
            href="complaints.php"
            class="nav-item"
        >

            <span class="nav-icon">
                💬
            </span>

            Complaints

        </a>


        <a
            href="reports.php"
            class="nav-item"
        >

            <span class="nav-icon">
                📊
            </span>

            Reports

        </a>


        <a
            href="blotter.php"
            class="nav-item"
        >

            <span class="nav-icon">
                📝
            </span>

            Blotter

        </a>


        <div class="nav-title">
            Account
        </div>


        <a
            href="profile.php"
            class="nav-item"
        >

            <span class="nav-icon">
                👤
            </span>

            My Profile

        </a>


        <a
            href="settings.php"
            class="nav-item"
        >

            <span class="nav-icon">
                ⚙️
            </span>

            Settings

        </a>

    </nav>


    <a
        href="logout.php"
        class="logout"
        onclick="
            return confirm(
                'Are you sure you want to logout?'
            );
        "
    >

        <span>
            🚪
        </span>

        Logout

    </a>

</aside>


<!-- =====================================================
     MAIN
====================================================== -->

<main class="main">


    <!-- =================================================
         TOP BAR
    ================================================== -->

    <div class="topbar">

        <div>

            <button
                class="mobile-menu"
                onclick="
                    document
                    .getElementById('sidebar')
                    .classList.toggle('show')
                "
            >
                ☰
            </button>

        </div>


        <div class="page-title">

            <h1>
                Admin Dashboard
            </h1>

            <p>
                MAPALADNEXUS Management Center
            </p>

        </div>


        <div class="admin-profile">

            <div class="admin-avatar">
                👑
            </div>

            <div class="admin-info">

                <strong>
                    <?= htmlspecialchars(
                        $admin_username,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>
                </strong>

                <span>
                    Administrator
                </span>

            </div>

        </div>

    </div>


    <!-- =================================================
         HERO
    ================================================== -->

    <section class="hero">

        <div class="hero-content">

            <h2>
                Welcome back, Administrator 👋
            </h2>

            <p>
                Manage the digital services,
                residents, requests, announcements
                and community concerns of
                Barangay Mapalad through
                MAPALADNEXUS.
            </p>

            <div class="hero-date">

                📅 <?= htmlspecialchars(
                    $current_date,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>

                &nbsp; • &nbsp;

                🕐 <?= htmlspecialchars(
                    $current_time,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>

            </div>

        </div>

    </section>


    <!-- =================================================
         STATISTICS
    ================================================== -->

    <section class="stats-grid">


        <div class="stat-card">

            <div class="stat-icon blue">
                👥
            </div>

            <h3>
                <?= number_format(
                    $residents_count
                ) ?>
            </h3>

            <p>
                Registered Residents
            </p>

            <div class="stat-line blue">
                <span></span>
            </div>

        </div>


        <div class="stat-card">

            <div class="stat-icon green">
                🛠️
            </div>

            <h3>
                <?= number_format(
                    $services_count
                ) ?>
            </h3>

            <p>
                Available Services
            </p>

            <div class="stat-line green">
                <span></span>
            </div>

        </div>


        <div class="stat-card">

            <div class="stat-icon orange">
                📋
            </div>

            <h3>
                <?= number_format(
                    $requests_count
                ) ?>
            </h3>

            <p>
                Service Requests
            </p>

            <div class="stat-line orange">
                <span></span>
            </div>

        </div>


        <div class="stat-card">

            <div class="stat-icon purple">
                💬
            </div>

            <h3>
                <?= number_format(
                    $complaints_count
                ) ?>
            </h3>

            <p>
                Community Complaints
            </p>

            <div class="stat-line purple">
                <span></span>
            </div>

        </div>

    </section>


    <!-- =================================================
         CONTENT
    ================================================== -->

    <section class="content-grid">


        <!-- =============================================
             RECENT ANNOUNCEMENTS
        ============================================== -->

        <div class="panel">

            <div class="panel-header">

                <h3>
                    📢 Recent Announcements
                </h3>

                <span>
                    Latest updates
                </span>

            </div>


            <?php if (
                count($recent_announcements) > 0
            ): ?>

                <?php foreach (
                    $recent_announcements
                    as $announcement
                ): ?>

                    <div class="announcement">

                        <div class="announcement-icon">
                            📢
                        </div>

                        <div class="announcement-info">

                            <strong>

                                <?= htmlspecialchars(
                                    $announcement['title'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>

                            </strong>

                            <span>

                                <?= htmlspecialchars(
                                    $announcement['created_at'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>

                            </span>

                        </div>


                        <div class="purok">

                            <?= htmlspecialchars(
                                $announcement['target_purok']
                                ?: 'All Purok',
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>

                        </div>

                    </div>

                <?php endforeach; ?>

            <?php else: ?>

                <div class="empty">

                    📢

                    <br><br>

                    No announcements available yet.

                </div>

            <?php endif; ?>

        </div>


        <!-- =============================================
             QUICK ACTIONS
        ============================================== -->

        <div class="panel">

            <div class="panel-header">

                <h3>
                    ⚡ Quick Actions
                </h3>

                <span>
                    Manage
                </span>

            </div>


            <div class="quick-grid">


                <a
                    href="residents.php"
                    class="quick-action"
                >

                    <div>
                        👥
                    </div>

                    <span>
                        Residents
                    </span>

                </a>


                <a
                    href="services.php"
                    class="quick-action"
                >

                    <div>
                        🛠️
                    </div>

                    <span>
                        Services
                    </span>

                </a>


                <a
                    href="requests.php"
                    class="quick-action"
                >

                    <div>
                        📋
                    </div>

                    <span>
                        Requests
                    </span>

                </a>


                <a
                    href="announcements.php"
                    class="quick-action"
                >

                    <div>
                        📢
                    </div>

                    <span>
                        Announcement
                    </span>

                </a>


                <a
                    href="complaints.php"
                    class="quick-action"
                >

                    <div>
                        💬
                    </div>

                    <span>
                        Complaints
                    </span>

                </a>


                <a
                    href="reports.php"
                    class="quick-action"
                >

                    <div>
                        📊
                    </div>

                    <span>
                        Reports
                    </span>

                </a>

            </div>


            <!-- =========================================
                 PENDING
            ========================================== -->

            <div class="alert-list">


                <div class="alert-item">

                    <div class="alert-left">

                        <div class="alert-icon">
                            📋
                        </div>

                        <span>
                            Pending Requests
                        </span>

                    </div>

                    <div class="alert-number">
                        <?= number_format(
                            $pending_requests
                        ) ?>
                    </div>

                </div>


                <div class="alert-item">

                    <div class="alert-left">

                        <div class="alert-icon">
                            💬
                        </div>

                        <span>
                            Active Complaints
                        </span>

                    </div>

                    <div class="alert-number">
                        <?= number_format(
                            $pending_complaints
                        ) ?>
                    </div>

                </div>


                <div class="alert-item">

                    <div class="alert-left">

                        <div class="alert-icon">
                            📢
                        </div>

                        <span>
                            Announcements
                        </span>

                    </div>

                    <div class="alert-number">
                        <?= number_format(
                            $announcements_count
                        ) ?>
                    </div>

                </div>


                <div class="alert-item">

                    <div class="alert-left">

                        <div class="alert-icon">
                            👤
                        </div>

                        <span>
                            System Users
                        </span>

                    </div>

                    <div class="alert-number">
                        <?= number_format(
                            $users_count
                        ) ?>
                    </div>

                </div>

            </div>

        </div>

    </section>

</main>


</body>

</html>