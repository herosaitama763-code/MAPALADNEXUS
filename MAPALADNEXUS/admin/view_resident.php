<?php

session_start();

require_once __DIR__ . '/../config/database.php';

/* =========================
   ADMIN SECURITY
========================= */

if (
    !isset($_SESSION['user_id']) ||
    ($_SESSION['role'] ?? '') !== 'Admin'
) {
    header("Location: login.php");
    exit;
}

/* =========================
   GET ID
========================= */

$id = isset($_GET['id'])
    ? (int) $_GET['id']
    : 0;

if ($id <= 0) {
    header("Location: residents.php");
    exit;
}

/* =========================
   GET RESIDENT
========================= */

$stmt = $conn->prepare("
    SELECT *
    FROM residents
    WHERE id = ?
    LIMIT 1
");

if (!$stmt) {
    die("Database error: " . htmlspecialchars($conn->error));
}

$stmt->bind_param("i", $id);
$stmt->execute();

$result = $stmt->get_result();
$resident = $result->fetch_assoc();

$stmt->close();

if (!$resident) {
    header("Location: residents.php");
    exit;
}

/* =========================
   FULL NAME
========================= */

$full_name = trim(
    ($resident['first_name'] ?? '') . ' ' .
    ($resident['middle_name'] ?? '') . ' ' .
    ($resident['last_name'] ?? '') . ' ' .
    ($resident['suffix'] ?? '')
);

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
    Resident Profile | MAPALADNEXUS
</title>

<style>

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

:root {
    --bg: #050816;
    --panel: rgba(255,255,255,.055);
    --border: rgba(255,255,255,.08);
    --text: #f8fafc;
    --muted: #94a3b8;
    --blue: #2563eb;
    --purple: #7c3aed;
    --green: #10b981;
    --red: #ef4444;
}

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
            rgba(37,99,235,.18),
            transparent 30%
        ),
        radial-gradient(
            circle at 90% 85%,
            rgba(124,58,237,.16),
            transparent 30%
        ),
        var(--bg);

    overflow-x: hidden;
}

/* =========================
   BACKGROUND
========================= */

.background {

    position: fixed;

    inset: 0;

    z-index: -2;

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

/* =========================
   SIDEBAR
========================= */

.sidebar {

    position: fixed;

    left: 20px;
    top: 20px;
    bottom: 20px;

    width: 255px;

    padding: 22px 16px;

    background:
        rgba(10,15,32,.82);

    border:
        1px solid
        var(--border);

    border-radius: 26px;

    backdrop-filter:
        blur(25px);

    box-shadow:
        18px 25px 60px
        rgba(0,0,0,.35);

    z-index: 100;

    display: flex;

    flex-direction: column;
}

/* =========================
   BRAND
========================= */

.brand {

    display: flex;

    align-items: center;

    gap: 12px;

    padding:
        4px 8px 20px;

    border-bottom:
        1px solid
        var(--border);
}

.logo {

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

    font-size: 22px;

    box-shadow:
        8px 12px 25px
        rgba(37,99,235,.25);
}

.brand h2 {
    font-size: 15px;
}

.brand span {

    display: block;

    margin-top: 4px;

    color: #64748b;

    font-size: 8px;

    letter-spacing: 1px;

    text-transform: uppercase;
}

/* =========================
   NAVIGATION
========================= */

.nav-title {

    margin:
        20px 10px 10px;

    color: #64748b;

    font-size: 8px;

    font-weight: bold;

    letter-spacing: 1px;

    text-transform: uppercase;
}

.nav {

    flex: 1;

    overflow-y: auto;
}

.nav a {

    display: flex;

    align-items: center;

    gap: 12px;

    margin-bottom: 6px;

    padding: 12px;

    color: var(--muted);

    text-decoration: none;

    border-radius: 13px;

    font-size: 10px;

    transition: .25s;
}

.nav a:hover {

    color: white;

    background:
        rgba(255,255,255,.055);

    transform:
        translateX(3px);
}

.nav a.active {

    color: white;

    background:
        rgba(37,99,235,.20);

    box-shadow:
        inset 3px 0 #3b82f6;
}

.icon {

    width: 24px;

    text-align: center;

    font-size: 15px;
}

/* =========================
   LOGOUT
========================= */

.logout {

    display: flex;

    align-items: center;

    gap: 12px;

    padding: 13px;

    color: #fca5a5;

    text-decoration: none;

    background:
        rgba(239,68,68,.07);

    border:
        1px solid
        rgba(239,68,68,.12);

    border-radius: 13px;

    font-size: 10px;
}

/* =========================
   MAIN
========================= */

.main {

    margin-left: 295px;

    min-height: 100vh;

    padding: 30px;
}

/* =========================
   HEADER
========================= */

.header {

    display: flex;

    justify-content: space-between;

    align-items: center;

    margin-bottom: 22px;
}

.header h1 {
    font-size: 25px;
}

.header p {

    margin-top: 7px;

    color: var(--muted);

    font-size: 10px;
}

.admin {

    padding:
        10px 15px;

    color: #93c5fd;

    background:
        var(--panel);

    border:
        1px solid
        var(--border);

    border-radius: 14px;

    font-size: 9px;
}

/* =========================
   BACK
========================= */

.back {

    display: inline-flex;

    align-items: center;

    gap: 8px;

    margin-bottom: 20px;

    padding:
        10px 14px;

    color: #cbd5e1;

    text-decoration: none;

    background:
        rgba(255,255,255,.045);

    border:
        1px solid
        var(--border);

    border-radius: 12px;

    font-size: 9px;

    transition: .25s;
}

.back:hover {

    color: white;

    transform:
        translateX(-3px);

    background:
        rgba(255,255,255,.08);
}

/* =========================
   PROFILE CONTAINER
========================= */

.profile {

    max-width: 1050px;

    border:
        1px solid
        var(--border);

    border-radius: 28px;

    overflow: hidden;

    background:
        var(--panel);

    backdrop-filter:
        blur(22px);

    box-shadow:
        18px 30px 70px
        rgba(0,0,0,.30);

    transform:
        perspective(1400px)
        rotateX(1deg);
}

/* =========================
   PROFILE HERO
========================= */

.profile-hero {

    position: relative;

    padding:
        35px;

    background:
        linear-gradient(
            125deg,
            rgba(37,99,235,.24),
            rgba(124,58,237,.16),
            rgba(255,255,255,.03)
        );

    border-bottom:
        1px solid
        var(--border);
}

.profile-hero::after {

    content: "";

    position: absolute;

    width: 260px;
    height: 260px;

    right: -90px;
    top: -120px;

    border-radius: 50%;

    background:
        rgba(96,165,250,.13);

    filter:
        blur(20px);
}

.profile-top {

    position: relative;

    z-index: 2;

    display: flex;

    align-items: center;

    gap: 22px;
}

/* =========================
   AVATAR
========================= */

.avatar {

    width: 100px;
    height: 100px;

    flex-shrink: 0;

    display: flex;

    align-items: center;

    justify-content: center;

    border-radius: 30px;

    background:
        linear-gradient(
            145deg,
            #2563eb,
            #7c3aed
        );

    border:
        4px solid
        rgba(255,255,255,.10);

    box-shadow:
        12px 20px 35px
        rgba(0,0,0,.25);

    font-size: 42px;

    transform:
        perspective(500px)
        rotateY(-5deg);
}

/* =========================
   NAME
========================= */

.profile-name h2 {

    font-size: 25px;

    letter-spacing: -.5px;
}

.profile-name p {

    margin-top: 7px;

    color: var(--muted);

    font-size: 10px;
}

.status {

    display: inline-flex;

    align-items: center;

    gap: 6px;

    margin-top: 13px;

    padding:
        7px 10px;

    color: #6ee7b7;

    background:
        rgba(16,185,129,.10);

    border:
        1px solid
        rgba(16,185,129,.18);

    border-radius: 9px;

    font-size: 7px;

    text-transform: uppercase;

    letter-spacing: .5px;
}

.status-dot {

    width: 6px;
    height: 6px;

    border-radius: 50%;

    background:
        #34d399;

    box-shadow:
        0 0 10px
        #34d399;
}

/* =========================
   CONTENT
========================= */

.profile-content {

    padding: 30px;
}

.section-title {

    margin-bottom: 15px;

    color: #93c5fd;

    font-size: 9px;

    font-weight: bold;

    letter-spacing: 1px;

    text-transform: uppercase;
}

.info-grid {

    display: grid;

    grid-template-columns:
        repeat(3, 1fr);

    gap: 13px;

    margin-bottom: 28px;
}

.info-card {

    padding: 18px;

    border:
        1px solid
        rgba(255,255,255,.06);

    border-radius: 16px;

    background:
        rgba(255,255,255,.025);

    transition: .25s;
}

.info-card:hover {

    transform:
        translateY(-3px);

    background:
        rgba(255,255,255,.05);

    border-color:
        rgba(96,165,250,.13);
}

.info-icon {

    margin-bottom: 11px;

    font-size: 17px;
}

.info-label {

    color: #64748b;

    font-size: 7px;

    text-transform: uppercase;

    letter-spacing: .7px;
}

.info-value {

    margin-top: 6px;

    color: #e2e8f0;

    font-size: 10px;

    font-weight: bold;

    word-break: break-word;
}

/* =========================
   ADDRESS
========================= */

.address-card {

    padding: 20px;

    border:
        1px solid
        rgba(255,255,255,.06);

    border-radius: 17px;

    background:
        rgba(255,255,255,.025);

    margin-bottom: 25px;
}

.address-title {

    margin-bottom: 9px;

    color: #64748b;

    font-size: 7px;

    text-transform: uppercase;

    letter-spacing: .7px;
}

.address-text {

    color: #e2e8f0;

    font-size: 10px;

    line-height: 1.7;
}

/* =========================
   BUTTONS
========================= */

.actions {

    display: flex;

    justify-content: flex-end;

    gap: 10px;

    padding-top: 20px;

    border-top:
        1px solid
        var(--border);
}

.btn {

    display: inline-flex;

    align-items: center;

    justify-content: center;

    gap: 7px;

    padding:
        12px 17px;

    color: white;

    text-decoration: none;

    border-radius: 12px;

    font-size: 9px;

    font-weight: bold;

    transition: .25s;
}

.btn:hover {

    transform:
        translateY(-2px);
}

.btn-edit {

    background:
        linear-gradient(
            145deg,
            #2563eb,
            #4f46e5
        );
}

.btn-back {

    background:
        rgba(255,255,255,.07);

    border:
        1px solid
        var(--border);
}

/* =========================
   RESPONSIVE
========================= */

@media(max-width: 900px) {

    .sidebar {
        display: none;
    }

    .main {

        margin-left: 0;

        padding: 20px;
    }

    .info-grid {

        grid-template-columns:
            repeat(2, 1fr);
    }
}

@media(max-width: 600px) {

    .profile-hero {

        padding: 25px 20px;
    }

    .profile-top {

        align-items: flex-start;

        flex-direction: column;
    }

    .profile-content {

        padding: 20px;
    }

    .info-grid {

        grid-template-columns: 1fr;
    }

    .actions {

        flex-direction: column;
    }

    .btn {

        width: 100%;
    }
}

</style>

</head>

<body>

<div class="background"></div>


<!-- =========================
     SIDEBAR
========================= -->

<aside class="sidebar">

    <div class="brand">

        <div class="logo">
            🏛️
        </div>

        <div>

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

        <a href="dashboard.php">
            <span class="icon">🏠</span>
            Dashboard
        </a>

        <a
            href="residents.php"
            class="active"
        >
            <span class="icon">👥</span>
            Residents
        </a>

        <a href="services.php">
            <span class="icon">🛠️</span>
            Services
        </a>

        <a href="requests.php">
            <span class="icon">📋</span>
            Service Requests
        </a>

        <a href="announcements.php">
            <span class="icon">📢</span>
            Announcements
        </a>

        <a href="complaints.php">
            <span class="icon">💬</span>
            Complaints
        </a>

        <a href="reports.php">
            <span class="icon">📊</span>
            Reports
        </a>

        <a href="blotter.php">
            <span class="icon">📝</span>
            Blotter
        </a>

        <div class="nav-title">
            Account
        </div>

        <a href="profile.php">
            <span class="icon">👤</span>
            My Profile
        </a>

        <a href="settings.php">
            <span class="icon">⚙️</span>
            Settings
        </a>

    </nav>


    <a
        href="logout.php"
        class="logout"
        onclick="return confirm('Logout from MAPALADNEXUS?')"
    >
        🚪
        Logout
    </a>

</aside>


<!-- =========================
     MAIN
========================= -->

<main class="main">

    <div class="header">

        <div>

            <h1>
                Resident Profile
            </h1>

            <p>
                Complete resident information
            </p>

        </div>

        <div class="admin">

            👑
            <?= htmlspecialchars(
                $_SESSION['username'] ?? 'Admin',
                ENT_QUOTES,
                'UTF-8'
            ) ?>

        </div>

    </div>


    <a
        href="residents.php"
        class="back"
    >
        ← Back to Residents
    </a>


    <!-- =========================
         PROFILE
    ========================= -->

    <section class="profile">


        <!-- HERO -->

        <div class="profile-hero">

            <div class="profile-top">


                <div class="avatar">
                    👤
                </div>


                <div class="profile-name">

                    <h2>
                        <?= htmlspecialchars(
                            $full_name,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>
                    </h2>

                    <p>
                        Resident ID #<?= (int)$resident['id'] ?>
                    </p>

                    <div class="status">

                        <span class="status-dot"></span>

                        Registered Resident

                    </div>

                </div>

            </div>

        </div>


        <!-- CONTENT -->

        <div class="profile-content">


            <div class="section-title">
                Personal Information
            </div>


            <div class="info-grid">


                <div class="info-card">

                    <div class="info-icon">
                        👤
                    </div>

                    <div class="info-label">
                        First Name
                    </div>

                    <div class="info-value">

                        <?= htmlspecialchars(
                            $resident['first_name'] ?? '',
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>

                    </div>

                </div>


                <div class="info-card">

                    <div class="info-icon">
                        🪪
                    </div>

                    <div class="info-label">
                        Middle Name
                    </div>

                    <div class="info-value">

                        <?= htmlspecialchars(
                            $resident['middle_name']
                            ?: '—',
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>

                    </div>

                </div>


                <div class="info-card">

                    <div class="info-icon">
                        👤
                    </div>

                    <div class="info-label">
                        Last Name
                    </div>

                    <div class="info-value">

                        <?= htmlspecialchars(
                            $resident['last_name'] ?? '',
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>

                    </div>

                </div>


                <div class="info-card">

                    <div class="info-icon">
                        📅
                    </div>

                    <div class="info-label">
                        Birth Date
                    </div>

                    <div class="info-value">

                        <?= htmlspecialchars(
                            $resident['birth_date'] ?? '',
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>

                    </div>

                </div>


                <div class="info-card">

                    <div class="info-icon">
                        ⚧️
                    </div>

                    <div class="info-label">
                        Gender
                    </div>

                    <div class="info-value">

                        <?= htmlspecialchars(
                            $resident['gender'] ?? '',
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>

                    </div>

                </div>


                <div class="info-card">

                    <div class="info-icon">
                        💍
                    </div>

                    <div class="info-label">
                        Civil Status
                    </div>

                    <div class="info-value">

                        <?= htmlspecialchars(
                            $resident['civil_status']
                            ?: '—',
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>

                    </div>

                </div>


            </div>


            <div class="section-title">
                Barangay Information
            </div>


            <div class="info-grid">


                <div class="info-card">

                    <div class="info-icon">
                        📍
                    </div>

                    <div class="info-label">
                        Purok
                    </div>

                    <div class="info-value">

                        <?= htmlspecialchars(
                            $resident['purok'] ?? '',
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>

                    </div>

                </div>


                <div class="info-card">

                    <div class="info-icon">
                        🏛️
                    </div>

                    <div class="info-label">
                        Barangay
                    </div>

                    <div class="info-value">
                        Barangay Mapalad
                    </div>

                </div>


                <div class="info-card">

                    <div class="info-icon">
                        🗺️
                    </div>

                    <div class="info-label">
                        Municipality
                    </div>

                    <div class="info-value">
                        San Agustin
                    </div>

                </div>


            </div>


            <div class="address-card">

                <div class="address-title">
                    Complete Address
                </div>

                <div class="address-text">

                    📍

                    <?= htmlspecialchars(
                        $resident['address'] ?? '',
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>

                </div>

            </div>


            <div class="actions">

                <a
                    href="residents.php"
                    class="btn btn-back"
                >
                    ← Back
                </a>


                <a
                    href="edit_resident.php?id=<?= (int)$resident['id'] ?>"
                    class="btn btn-edit"
                >
                    ✏️ Edit Resident
                </a>

            </div>


        </div>

    </section>

</main>

</body>

</html>