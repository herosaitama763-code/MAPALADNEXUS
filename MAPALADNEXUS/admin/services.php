<?php
session_start();

require_once __DIR__ . '/../config/database.php';

/* =========================================================
   ADMIN SECURITY
========================================================= */

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

if (($_SESSION['role'] ?? '') !== 'Admin') {
    header("Location: ../resident/user_dashboard.php");
    exit;
}

$message = '';
$error = '';

/* =========================================================
   ADD SERVICE
========================================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? '';

    /* =========================
       ADD
    ========================= */

    if ($action === 'add') {

        $service_name = trim($_POST['service_name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $fee = trim($_POST['fee'] ?? '0');
        $processing_time = trim($_POST['processing_time'] ?? '');
        $status = trim($_POST['status'] ?? 'Active');

        if ($service_name === '') {

            $error = "Service name is required.";

        } else {

            if ($fee === '' || !is_numeric($fee)) {
                $fee = 0;
            }

            $fee = (float)$fee;

            $stmt = $conn->prepare("
                INSERT INTO services
                (
                    service_name,
                    description,
                    fee,
                    processing_time,
                    status
                )
                VALUES (?, ?, ?, ?, ?)
            ");

            if (!$stmt) {

                $error = "Database error: " . $conn->error;

            } else {

                $stmt->bind_param(
                    "ssdss",
                    $service_name,
                    $description,
                    $fee,
                    $processing_time,
                    $status
                );

                if ($stmt->execute()) {

                    $message = "Service successfully added.";

                } else {

                    $error = "Unable to add service: " . $stmt->error;
                }

                $stmt->close();
            }
        }
    }


    /* =========================
       EDIT
    ========================= */

    elseif ($action === 'edit') {

        $service_id = (int)($_POST['service_id'] ?? 0);

        $service_name = trim($_POST['service_name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $fee = trim($_POST['fee'] ?? '0');
        $processing_time = trim($_POST['processing_time'] ?? '');
        $status = trim($_POST['status'] ?? 'Active');

        if ($service_id <= 0) {

            $error = "Invalid service ID.";

        } elseif ($service_name === '') {

            $error = "Service name is required.";

        } else {

            if ($fee === '' || !is_numeric($fee)) {
                $fee = 0;
            }

            $fee = (float)$fee;

            $stmt = $conn->prepare("
                UPDATE services
                SET
                    service_name = ?,
                    description = ?,
                    fee = ?,
                    processing_time = ?,
                    status = ?
                WHERE id = ?
            ");

            if (!$stmt) {

                $error = "Database error: " . $conn->error;

            } else {

                $stmt->bind_param(
                    "ssdssi",
                    $service_name,
                    $description,
                    $fee,
                    $processing_time,
                    $status,
                    $service_id
                );

                if ($stmt->execute()) {

                    $message = "Service successfully updated.";

                } else {

                    $error = "Unable to update service: " . $stmt->error;
                }

                $stmt->close();
            }
        }
    }


    /* =========================
       DELETE
    ========================= */

    elseif ($action === 'delete') {

        $service_id = (int)($_POST['service_id'] ?? 0);

        if ($service_id <= 0) {

            $error = "Invalid service ID.";

        } else {

            /*
             * Check if service is already used by requests.
             */

            $check = $conn->prepare("
                SELECT COUNT(*) AS total
                FROM service_requests
                WHERE service_id = ?
            ");

            if ($check) {

                $check->bind_param(
                    "i",
                    $service_id
                );

                $check->execute();

                $check_result = $check->get_result();

                $check_row = $check_result->fetch_assoc();

                $used = (int)($check_row['total'] ?? 0);

                $check->close();

            } else {

                $used = 0;
            }


            if ($used > 0) {

                /*
                 * Instead of deleting a service already
                 * referenced by requests, deactivate it.
                 */

                $stmt = $conn->prepare("
                    UPDATE services
                    SET status = 'Inactive'
                    WHERE id = ?
                ");

                if ($stmt) {

                    $stmt->bind_param(
                        "i",
                        $service_id
                    );

                    if ($stmt->execute()) {

                        $message =
                            "Service is already used by requests, so it was set to Inactive.";

                    } else {

                        $error =
                            "Unable to deactivate service: " .
                            $stmt->error;
                    }

                    $stmt->close();

                } else {

                    $error =
                        "Database error: " .
                        $conn->error;
                }

            } else {

                $stmt = $conn->prepare("
                    DELETE FROM services
                    WHERE id = ?
                ");

                if (!$stmt) {

                    $error =
                        "Database error: " .
                        $conn->error;

                } else {

                    $stmt->bind_param(
                        "i",
                        $service_id
                    );

                    if ($stmt->execute()) {

                        $message =
                            "Service successfully deleted.";

                    } else {

                        $error =
                            "Unable to delete service: " .
                            $stmt->error;
                    }

                    $stmt->close();
                }
            }
        }
    }
}


/* =========================================================
   LOAD SERVICES
========================================================= */

$services = [];

$sql = "
    SELECT
        id,
        service_name,
        description,
        fee,
        processing_time,
        status
    FROM services
    ORDER BY id DESC
";

$result = $conn->query($sql);

if ($result) {

    while ($row = $result->fetch_assoc()) {
        $services[] = $row;
    }

    $result->free();

} else {

    $error =
        "Unable to load services: " .
        $conn->error;
}


/* =========================================================
   COUNTS
========================================================= */

$total_services = count($services);

$active_services = 0;
$inactive_services = 0;
$free_services = 0;

foreach ($services as $service) {

    $status = strtolower(
        trim($service['status'] ?? '')
    );

    if ($status === 'active') {
        $active_services++;
    }

    if ($status === 'inactive') {
        $inactive_services++;
    }

    if ((float)($service['fee'] ?? 0) <= 0) {
        $free_services++;
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
    Services | MAPALADNEXUS
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


/* =========================================================
   ROOT
========================================================= */

:root {

    --bg: #050816;

    --panel: rgba(255,255,255,.055);

    --panel2: rgba(15,23,42,.88);

    --border: rgba(255,255,255,.08);

    --text: #f8fafc;

    --muted: #94a3b8;

    --blue: #2563eb;

    --blue-light: #60a5fa;

    --purple: #7c3aed;

    --green: #10b981;

    --yellow: #f59e0b;

    --red: #ef4444;
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
            rgba(37,99,235,.22),
            transparent 30%
        ),

        radial-gradient(
            circle at 90% 90%,
            rgba(124,58,237,.20),
            transparent 30%
        ),

        var(--bg);

    overflow-x: hidden;
}


/* =========================================================
   GRID
========================================================= */

.background {

    position: fixed;

    inset: 0;

    z-index: -1;

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

    pointer-events: none;
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

    background:
        rgba(10,15,32,.82);

    border:
        1px solid
        var(--border);

    border-radius: 26px;

    backdrop-filter: blur(25px);

    -webkit-backdrop-filter: blur(25px);

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


/* =========================================================
   NAV
========================================================= */

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


/* =========================================================
   LOGOUT
========================================================= */

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

    transition: .25s;
}


.logout:hover {

    color: white;

    background:
        rgba(239,68,68,.16);
}


/* =========================================================
   MAIN
========================================================= */

.main {

    margin-left: 295px;

    min-height: 100vh;

    padding: 30px;
}


/* =========================================================
   HEADER
========================================================= */

.header {

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 20px;

    margin-bottom: 25px;
}


.header h1 {

    font-size: 28px;

    letter-spacing: -.7px;
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


/* =========================================================
   STATS
========================================================= */

.stats {

    display: grid;

    grid-template-columns:
        repeat(4, 1fr);

    gap: 14px;

    margin-bottom: 22px;
}


.stat {

    padding: 18px;

    background:
        var(--panel);

    border:
        1px solid
        var(--border);

    border-radius: 19px;

    backdrop-filter:
        blur(20px);

    box-shadow:
        10px 18px 40px
        rgba(0,0,0,.20);

    transform:
        perspective(1000px)
        rotateX(1deg);

    transition: .25s;
}


.stat:hover {

    transform:
        perspective(1000px)
        rotateX(0)
        translateY(-4px);
}


.stat-icon {

    margin-bottom: 10px;

    font-size: 19px;
}


.stat-number {

    font-size: 22px;

    font-weight: bold;
}


.stat-label {

    margin-top: 5px;

    color: var(--muted);

    font-size: 8px;

    text-transform: uppercase;

    letter-spacing: .5px;
}


/* =========================================================
   ALERT
========================================================= */

.alert {

    margin-bottom: 18px;

    padding: 14px 16px;

    border-radius: 13px;

    font-size: 9px;
}


.alert.success {

    color: #a7f3d0;

    background:
        rgba(16,185,129,.10);

    border:
        1px solid
        rgba(16,185,129,.15);
}


.alert.error {

    color: #fecaca;

    background:
        rgba(239,68,68,.10);

    border:
        1px solid
        rgba(239,68,68,.15);
}


/* =========================================================
   SERVICES PANEL
========================================================= */

.panel {

    background:
        var(--panel);

    border:
        1px solid
        var(--border);

    border-radius: 25px;

    overflow: hidden;

    backdrop-filter:
        blur(22px);

    box-shadow:
        15px 25px 60px
        rgba(0,0,0,.25);

    transform:
        perspective(1300px)
        rotateX(1deg);
}


.panel-header {

    padding:
        21px 23px;

    border-bottom:
        1px solid
        var(--border);

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 15px;
}


.panel-header h2 {

    font-size: 15px;
}


.panel-header span {

    display: block;

    margin-top: 5px;

    color: var(--muted);

    font-size: 8px;
}


/* =========================================================
   ADD BUTTON
========================================================= */

.add-btn {

    border: none;

    padding:
        11px 16px;

    color: white;

    background:
        linear-gradient(
            145deg,
            #2563eb,
            #4f46e5
        );

    border-radius: 11px;

    font-size: 8px;

    font-weight: bold;

    cursor: pointer;

    box-shadow:
        0 8px 20px
        rgba(37,99,235,.22);

    transition: .2s;

    white-space: nowrap;
}


.add-btn:hover {

    transform:
        translateY(-2px);

    box-shadow:
        0 12px 28px
        rgba(37,99,235,.32);
}


/* =========================================================
   SEARCH
========================================================= */

.search-area {

    padding:
        17px 20px;

    border-bottom:
        1px solid
        rgba(255,255,255,.05);
}


.search {

    width: 100%;

    max-width: 360px;

    padding:
        11px 13px;

    color: white;

    background:
        rgba(0,0,0,.20);

    border:
        1px solid
        var(--border);

    border-radius: 11px;

    outline: none;

    font-size: 9px;
}


.search:focus {

    border-color:
        rgba(96,165,250,.40);
}


/* =========================================================
   TABLE
========================================================= */

.table-wrap {

    overflow-x: auto;
}


table {

    width: 100%;

    min-width: 1000px;

    border-collapse: collapse;
}


thead {

    background:
        rgba(255,255,255,.025);
}


th {

    padding:
        14px 16px;

    color: #64748b;

    text-align: left;

    font-size: 7px;

    letter-spacing: .7px;

    text-transform: uppercase;

    white-space: nowrap;
}


td {

    padding:
        16px;

    border-top:
        1px solid
        rgba(255,255,255,.05);

    color: #cbd5e1;

    font-size: 8px;

    vertical-align: middle;
}


tbody tr {

    transition: .2s;
}


tbody tr:hover {

    background:
        rgba(255,255,255,.025);
}


/* =========================================================
   SERVICE NAME
========================================================= */

.service-name {

    color: white;

    font-size: 9px;

    font-weight: bold;

    max-width: 220px;
}


.service-id {

    margin-top: 5px;

    color: #64748b;

    font-size: 7px;
}


/* =========================================================
   DESCRIPTION
========================================================= */

.description {

    max-width: 270px;

    color: #94a3b8;

    line-height: 1.5;
}


/* =========================================================
   FEE
========================================================= */

.fee {

    color: #6ee7b7;

    font-weight: bold;

    white-space: nowrap;
}


/* =========================================================
   PROCESSING
========================================================= */

.processing {

    color: #c4b5fd;

    white-space: nowrap;
}


/* =========================================================
   STATUS
========================================================= */

.status {

    display: inline-block;

    padding:
        7px 10px;

    border-radius: 9px;

    font-size: 7px;

    font-weight: bold;
}


.status.active {

    color: #6ee7b7;

    background:
        rgba(16,185,129,.10);

    border:
        1px solid
        rgba(16,185,129,.08);
}


.status.inactive {

    color: #fca5a5;

    background:
        rgba(239,68,68,.10);

    border:
        1px solid
        rgba(239,68,68,.08);
}


/* =========================================================
   ACTIONS
========================================================= */

.actions {

    display: flex;

    gap: 7px;
}


.action {

    padding:
        8px 10px;

    border-radius: 8px;

    border:
        1px solid
        var(--border);

    cursor: pointer;

    color: white;

    font-size: 7px;

    transition: .2s;
}


.edit {

    background:
        rgba(37,99,235,.12);
}


.edit:hover {

    background:
        rgba(37,99,235,.25);
}


.delete {

    background:
        rgba(239,68,68,.09);
}


.delete:hover {

    background:
        rgba(239,68,68,.20);
}


/* =========================================================
   EMPTY
========================================================= */

.empty {

    padding:
        70px 20px;

    text-align: center;
}


.empty-icon {

    margin-bottom: 15px;

    font-size: 45px;
}


.empty h3 {

    margin-bottom: 7px;

    font-size: 15px;
}


.empty p {

    color: var(--muted);

    font-size: 8px;
}


/* =========================================================
   MODAL
========================================================= */

.modal {

    position: fixed;

    inset: 0;

    display: none;

    align-items: center;

    justify-content: center;

    padding: 20px;

    background:
        rgba(0,0,0,.70);

    backdrop-filter:
        blur(8px);

    z-index: 999;
}


.modal.show {

    display: flex;
}


.modal-box {

    width: 100%;

    max-width: 570px;

    max-height: 90vh;

    overflow-y: auto;

    padding: 25px;

    background:
        #0b1124;

    border:
        1px solid
        var(--border);

    border-radius: 23px;

    box-shadow:
        20px 30px 80px
        rgba(0,0,0,.55);

    transform:
        perspective(1000px)
        rotateX(1deg);
}


/* =========================================================
   MODAL HEADER
========================================================= */

.modal-header {

    display: flex;

    align-items: center;

    justify-content: space-between;

    margin-bottom: 22px;
}


.modal-header h2 {

    font-size: 17px;
}


.close {

    width: 34px;

    height: 34px;

    border: none;

    color: #94a3b8;

    background:
        rgba(255,255,255,.05);

    border-radius: 10px;

    font-size: 21px;

    cursor: pointer;
}


.close:hover {

    color: white;

    background:
        rgba(239,68,68,.15);
}


/* =========================================================
   FORM
========================================================= */

.form-group {

    margin-bottom: 16px;
}


.form-group label {

    display: block;

    margin-bottom: 7px;

    color: #94a3b8;

    font-size: 8px;

    font-weight: bold;

    text-transform: uppercase;

    letter-spacing: .5px;
}


.form-control {

    width: 100%;

    padding: 12px;

    color: white;

    background:
        rgba(0,0,0,.25);

    border:
        1px solid
        var(--border);

    border-radius: 11px;

    outline: none;

    font-family: inherit;

    font-size: 9px;
}


.form-control:focus {

    border-color:
        rgba(96,165,250,.45);

    box-shadow:
        0 0 0 3px
        rgba(37,99,235,.08);
}


textarea.form-control {

    min-height: 105px;

    resize: vertical;
}


select.form-control option {

    color: #111827;

    background: white;
}


/* =========================================================
   FORM ROW
========================================================= */

.form-row {

    display: grid;

    grid-template-columns:
        1fr 1fr;

    gap: 12px;
}


/* =========================================================
   MODAL ACTIONS
========================================================= */

.modal-actions {

    display: flex;

    justify-content: flex-end;

    gap: 9px;

    margin-top: 22px;
}


.btn {

    padding:
        11px 17px;

    border-radius: 11px;

    font-size: 8px;

    font-weight: bold;

    cursor: pointer;
}


.cancel {

    color: #cbd5e1;

    background:
        rgba(255,255,255,.06);

    border:
        1px solid
        var(--border);
}


.save {

    color: white;

    border: none;

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

@media (max-width: 1100px) {

    .stats {

        grid-template-columns:
            repeat(2, 1fr);
    }
}


@media (max-width: 900px) {

    .sidebar {

        display: none;
    }

    .main {

        margin-left: 0;

        padding: 20px;
    }
}


@media (max-width: 600px) {

    .main {

        padding: 15px;
    }

    .stats {

        grid-template-columns: 1fr;
    }

    .header {

        flex-direction: column;

        align-items: flex-start;
    }

    .panel-header {

        flex-direction: column;

        align-items: flex-start;
    }

    .add-btn {

        width: 100%;
    }

    .form-row {

        grid-template-columns: 1fr;
    }

    .modal-actions {

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


<!-- =========================================================
     SIDEBAR
========================================================= -->

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
        Administration
    </div>


    <nav class="nav">

        <a href="dashboard.php">
            <span class="icon">🏠</span>
            Dashboard
        </a>

        <a href="residents.php">
            <span class="icon">👥</span>
            Residents
        </a>

        <a href="services.php" class="active">
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

    </nav>


    <a
        href="logout.php"
        class="logout"
        onclick="return confirm('Logout from MAPALADNEXUS?');"
    >
        🚪 Logout
    </a>

</aside>


<!-- =========================================================
     MAIN
========================================================= -->

<main class="main">


    <!-- HEADER -->

    <div class="header">

        <div>

            <h1>
                Barangay Services
            </h1>

            <p>
                Manage the services available to residents of Barangay Mapalad.
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


    <!-- =====================================================
         STATISTICS
    ===================================================== -->

    <section class="stats">

        <div class="stat">

            <div class="stat-icon">
                🛠️
            </div>

            <div class="stat-number">
                <?= $total_services ?>
            </div>

            <div class="stat-label">
                Total Services
            </div>

        </div>


        <div class="stat">

            <div class="stat-icon">
                🟢
            </div>

            <div class="stat-number">
                <?= $active_services ?>
            </div>

            <div class="stat-label">
                Active Services
            </div>

        </div>


        <div class="stat">

            <div class="stat-icon">
                🔴
            </div>

            <div class="stat-number">
                <?= $inactive_services ?>
            </div>

            <div class="stat-label">
                Inactive Services
            </div>

        </div>


        <div class="stat">

            <div class="stat-icon">
                🎁
            </div>

            <div class="stat-number">
                <?= $free_services ?>
            </div>

            <div class="stat-label">
                Free Services
            </div>

        </div>

    </section>


    <!-- =====================================================
         ALERT
    ===================================================== -->

    <?php if ($message !== ''): ?>

        <div class="alert success">

            ✅

            <?= htmlspecialchars(
                $message,
                ENT_QUOTES,
                'UTF-8'
            ) ?>

        </div>

    <?php endif; ?>


    <?php if ($error !== ''): ?>

        <div class="alert error">

            ⚠️

            <?= htmlspecialchars(
                $error,
                ENT_QUOTES,
                'UTF-8'
            ) ?>

        </div>

    <?php endif; ?>


    <!-- =====================================================
         SERVICES PANEL
    ===================================================== -->

    <section class="panel">


        <div class="panel-header">

            <div>

                <h2>
                    Available Services
                </h2>

                <span>
                    Add, edit, and manage barangay services.
                </span>

            </div>


            <button
                type="button"
                class="add-btn"
                onclick="openAddModal()"
            >

                ＋ Add Service

            </button>

        </div>


        <!-- SEARCH -->

        <div class="search-area">

            <input
                type="text"
                id="serviceSearch"
                class="search"
                placeholder="🔎 Search service..."
                onkeyup="searchServices()"
            >

        </div>


        <!-- TABLE -->

        <div class="table-wrap">


            <?php if (empty($services)): ?>


                <div class="empty">

                    <div class="empty-icon">
                        🛠️
                    </div>

                    <h3>
                        No Services Yet
                    </h3>

                    <p>
                        Click "Add Service" to create the first barangay service.
                    </p>

                </div>


            <?php else: ?>


                <table id="servicesTable">


                    <thead>

                        <tr>

                            <th>
                                Service
                            </th>

                            <th>
                                Description
                            </th>

                            <th>
                                Fee
                            </th>

                            <th>
                                Processing
                            </th>

                            <th>
                                Status
                            </th>

                            <th>
                                Actions
                            </th>

                        </tr>

                    </thead>


                    <tbody>


                    <?php foreach ($services as $service): ?>


                        <?php

                        $service_id =
                            (int)$service['id'];

                        $service_name =
                            $service['service_name'] ?? '';

                        $description =
                            $service['description'] ?? '';

                        $fee =
                            (float)($service['fee'] ?? 0);

                        $processing =
                            $service['processing_time']
                            ?? '';

                        $status =
                            $service['status']
                            ?? 'Active';

                        $status_class =
                            strtolower($status);

                        ?>


                        <tr class="service-row">


                            <!-- SERVICE -->

                            <td>

                                <div class="service-name">

                                    <?= htmlspecialchars(
                                        $service_name,
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>

                                </div>

                                <div class="service-id">

                                    Service #<?= $service_id ?>

                                </div>

                            </td>


                            <!-- DESCRIPTION -->

                            <td>

                                <div class="description">

                                    <?php if ($description !== ''): ?>

                                        <?= htmlspecialchars(
                                            $description,
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>

                                    <?php else: ?>

                                        <span style="color:#64748b;">
                                            No description
                                        </span>

                                    <?php endif; ?>

                                </div>

                            </td>


                            <!-- FEE -->

                            <td>

                                <span class="fee">

                                    <?php if ($fee > 0): ?>

                                        ₱<?= number_format(
                                            $fee,
                                            2
                                        ) ?>

                                    <?php else: ?>

                                        FREE

                                    <?php endif; ?>

                                </span>

                            </td>


                            <!-- PROCESSING -->

                            <td>

                                <span class="processing">

                                    <?= $processing !== ''
                                        ? htmlspecialchars(
                                            $processing,
                                            ENT_QUOTES,
                                            'UTF-8'
                                        )
                                        : 'N/A'
                                    ?>

                                </span>

                            </td>


                            <!-- STATUS -->

                            <td>

                                <span
                                    class="status <?= htmlspecialchars(
                                        $status_class,
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>"
                                >

                                    <?= htmlspecialchars(
                                        $status,
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>

                                </span>

                            </td>


                            <!-- ACTIONS -->

                            <td>

                                <div class="actions">


                                    <button
                                        type="button"
                                        class="action edit"
                                        onclick='openEditModal(
                                            <?= json_encode(
                                                $service,
                                                JSON_HEX_TAG |
                                                JSON_HEX_APOS |
                                                JSON_HEX_QUOT |
                                                JSON_HEX_AMP
                                            ) ?>
                                        )'
                                    >

                                        ✏ Edit

                                    </button>


                                    <form
                                        method="POST"
                                        style="display:inline;"
                                        onsubmit="
                                            return confirm(
                                                'Are you sure you want to delete this service?'
                                            );
                                        "
                                    >

                                        <input
                                            type="hidden"
                                            name="action"
                                            value="delete"
                                        >

                                        <input
                                            type="hidden"
                                            name="service_id"
                                            value="<?= $service_id ?>"
                                        >

                                        <button
                                            type="submit"
                                            class="action delete"
                                        >

                                            🗑 Delete

                                        </button>

                                    </form>


                                </div>

                            </td>


                        </tr>


                    <?php endforeach; ?>


                    </tbody>

                </table>


            <?php endif; ?>


        </div>


    </section>


</main>


<!-- =========================================================
     ADD / EDIT MODAL
========================================================= -->

<div
    class="modal"
    id="serviceModal"
>


    <div class="modal-box">


        <div class="modal-header">

            <h2 id="modalTitle">
                Add Service
            </h2>


            <button
                type="button"
                class="close"
                onclick="closeModal()"
            >
                ×
            </button>

        </div>


        <form
            method="POST"
            action="services.php"
        >


            <input
                type="hidden"
                name="action"
                id="formAction"
                value="add"
            >


            <input
                type="hidden"
                name="service_id"
                id="serviceId"
                value=""
            >


            <!-- SERVICE NAME -->

            <div class="form-group">

                <label>
                    Service Name
                </label>

                <input
                    type="text"
                    name="service_name"
                    id="serviceName"
                    class="form-control"
                    placeholder="Example: Barangay Clearance"
                    required
                >

            </div>


            <!-- DESCRIPTION -->

            <div class="form-group">

                <label>
                    Description
                </label>

                <textarea
                    name="description"
                    id="serviceDescription"
                    class="form-control"
                    placeholder="Describe the service..."
                ></textarea>

            </div>


            <!-- FEE + PROCESSING -->

            <div class="form-row">


                <div class="form-group">

                    <label>
                        Fee
                    </label>

                    <input
                        type="number"
                        name="fee"
                        id="serviceFee"
                        class="form-control"
                        min="0"
                        step="0.01"
                        value="0"
                    >

                </div>


                <div class="form-group">

                    <label>
                        Processing Time
                    </label>

                    <input
                        type="text"
                        name="processing_time"
                        id="processingTime"
                        class="form-control"
                        placeholder="Example: 1-2 working days"
                    >

                </div>


            </div>


            <!-- STATUS -->

            <div class="form-group">

                <label>
                    Status
                </label>

                <select
                    name="status"
                    id="serviceStatus"
                    class="form-control"
                >

                    <option value="Active">
                        Active
                    </option>

                    <option value="Inactive">
                        Inactive
                    </option>

                </select>

            </div>


            <!-- BUTTONS -->

            <div class="modal-actions">

                <button
                    type="button"
                    class="btn cancel"
                    onclick="closeModal()"
                >

                    Cancel

                </button>


                <button
                    type="submit"
                    class="btn save"
                >

                    💾 Save Service

                </button>

            </div>


        </form>


    </div>

</div>


<script>

/* =========================================================
   OPEN ADD MODAL
========================================================= */

function openAddModal() {

    document.getElementById(
        'modalTitle'
    ).textContent =
        'Add Service';


    document.getElementById(
        'formAction'
    ).value =
        'add';


    document.getElementById(
        'serviceId'
    ).value =
        '';


    document.getElementById(
        'serviceName'
    ).value =
        '';


    document.getElementById(
        'serviceDescription'
    ).value =
        '';


    document.getElementById(
        'serviceFee'
    ).value =
        '0';


    document.getElementById(
        'processingTime'
    ).value =
        '';


    document.getElementById(
        'serviceStatus'
    ).value =
        'Active';


    document.getElementById(
        'serviceModal'
    ).classList.add(
        'show'
    );
}


/* =========================================================
   OPEN EDIT MODAL
========================================================= */

function openEditModal(service) {

    document.getElementById(
        'modalTitle'
    ).textContent =
        'Edit Service';


    document.getElementById(
        'formAction'
    ).value =
        'edit';


    document.getElementById(
        'serviceId'
    ).value =
        service.id || '';


    document.getElementById(
        'serviceName'
    ).value =
        service.service_name || '';


    document.getElementById(
        'serviceDescription'
    ).value =
        service.description || '';


    document.getElementById(
        'serviceFee'
    ).value =
        service.fee || '0';


    document.getElementById(
        'processingTime'
    ).value =
        service.processing_time || '';


    document.getElementById(
        'serviceStatus'
    ).value =
        service.status || 'Active';


    document.getElementById(
        'serviceModal'
    ).classList.add(
        'show'
    );
}


/* =========================================================
   CLOSE MODAL
========================================================= */

function closeModal() {

    document.getElementById(
        'serviceModal'
    ).classList.remove(
        'show'
    );
}


/* =========================================================
   SEARCH
========================================================= */

function searchServices() {

    const input =
        document.getElementById(
            'serviceSearch'
        );

    const filter =
        input.value.toLowerCase();


    const rows =
        document.querySelectorAll(
            '.service-row'
        );


    rows.forEach(function(row) {

        const text =
            row.textContent.toLowerCase();


        if (
            text.includes(filter)
        ) {

            row.style.display = '';

        } else {

            row.style.display = 'none';
        }

    });
}


/* =========================================================
   CLOSE OUTSIDE MODAL
========================================================= */

document.getElementById(
    'serviceModal'
).addEventListener(
    'click',
    function(event) {

        if (
            event.target === this
        ) {

            closeModal();

        }

    }
);


/* =========================================================
   ESC KEY
========================================================= */

document.addEventListener(
    'keydown',
    function(event) {

        if (
            event.key === 'Escape'
        ) {

            closeModal();

        }

    }
);

</script>


</body>
</html>