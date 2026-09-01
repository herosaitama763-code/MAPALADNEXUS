<?php
session_start();

error_reporting(E_ALL);
ini_set('display_errors', 1);

/*
|--------------------------------------------------------------------------
| MAPALADNEXUS - ADMIN COMPLAINTS
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/../config/database.php';

/*
|--------------------------------------------------------------------------
| DATABASE CHECK
|--------------------------------------------------------------------------
*/

if (!isset($conn) || !($conn instanceof mysqli)) {
    die("Database connection failed.");
}

/*
|--------------------------------------------------------------------------
| ADMIN CHECK
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION['username']) || empty($_SESSION['username'])) {
    header("Location: login.php");
    exit;
}

if (
    isset($_SESSION['role']) &&
    strtolower(trim($_SESSION['role'])) !== 'admin'
) {
    http_response_code(403);
    die("Access denied. Admin only.");
}

/*
|--------------------------------------------------------------------------
| HELPER
|--------------------------------------------------------------------------
*/

function e($value)
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}

/*
|--------------------------------------------------------------------------
| CHECK COMPLAINTS TABLE
|--------------------------------------------------------------------------
*/

$tableCheck = $conn->query(
    "SHOW TABLES LIKE 'complaints'"
);

if (!$tableCheck || $tableCheck->num_rows === 0) {
    die("
        <!DOCTYPE html>
        <html>
        <head>
            <title>MAPALADNEXUS</title>
            <style>
                body{
                    margin:0;
                    min-height:100vh;
                    display:flex;
                    align-items:center;
                    justify-content:center;
                    background:#050816;
                    color:white;
                    font-family:Arial,sans-serif;
                }
                .box{
                    max-width:600px;
                    padding:35px;
                    background:#10182b;
                    border:1px solid rgba(255,255,255,.1);
                    border-radius:20px;
                    text-align:center;
                }
                h2{margin-top:0;}
                p{color:#94a3b8;line-height:1.6;}
            </style>
        </head>
        <body>
            <div class='box'>
                <h2>⚠️ Complaints Table Not Found</h2>
                <p>
                    Hindi makita ng MAPALADNEXUS ang
                    <b>complaints</b> table sa database.
                </p>
                <p>
                    I-check muna ang database bago tayo magdagdag
                    ng bagong table.
                </p>
            </div>
        </body>
        </html>
    ");
}

/*
|--------------------------------------------------------------------------
| GET ACTUAL COLUMNS
|--------------------------------------------------------------------------
*/

$columns = [];

$columnResult = $conn->query(
    "SHOW COLUMNS FROM complaints"
);

if ($columnResult) {
    while ($row = $columnResult->fetch_assoc()) {
        $columns[] = $row['Field'];
    }

    $columnResult->free();
}

/*
|--------------------------------------------------------------------------
| FIND COLUMNS DYNAMICALLY
|--------------------------------------------------------------------------
*/

function findColumn($columns, $possibleColumns)
{
    foreach ($possibleColumns as $column) {
        if (in_array($column, $columns, true)) {
            return $column;
        }
    }

    return null;
}

$idColumn = findColumn(
    $columns,
    [
        'id',
        'complaint_id',
        'id_complaint'
    ]
);

$titleColumn = findColumn(
    $columns,
    [
        'title',
        'subject',
        'complaint_title',
        'complaint_subject'
    ]
);

$messageColumn = findColumn(
    $columns,
    [
        'description',
        'message',
        'complaint',
        'details',
        'content'
    ]
);

$statusColumn = findColumn(
    $columns,
    [
        'status',
        'complaint_status'
    ]
);

$residentColumn = findColumn(
    $columns,
    [
        'resident_id',
        'user_id',
        'submitted_by',
        'created_by'
    ]
);

$nameColumn = findColumn(
    $columns,
    [
        'resident_name',
        'name',
        'full_name'
    ]
);

$purokColumn = findColumn(
    $columns,
    [
        'purok',
        'resident_purok',
        'area'
    ]
);

$responseColumn = findColumn(
    $columns,
    [
        'admin_response',
        'response',
        'reply'
    ]
);

$dateColumn = findColumn(
    $columns,
    [
        'created_at',
        'submitted_at',
        'date_submitted',
        'date_created',
        'date'
    ]
);

/*
|--------------------------------------------------------------------------
| MINIMUM REQUIRED COLUMNS
|--------------------------------------------------------------------------
*/

if (!$idColumn) {
    die("
        <h2 style='font-family:Arial;padding:30px'>
            Complaint ID column not found.
        </h2>
    ");
}

if (!$messageColumn) {
    die("
        <h2 style='font-family:Arial;padding:30px'>
            Complaint message/description column not found.
        </h2>
    ");
}

/*
|--------------------------------------------------------------------------
| VARIABLES
|--------------------------------------------------------------------------
*/

$success = '';
$error = '';

$filterStatus = trim(
    $_GET['status'] ?? ''
);

/*
|--------------------------------------------------------------------------
| UPDATE STATUS / RESPONSE
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    ($_POST['action'] ?? '') === 'update'
) {

    $complaintId = (int)(
        $_POST['complaint_id'] ?? 0
    );

    $newStatus = trim(
        $_POST['status'] ?? 'Pending'
    );

    $adminResponse = trim(
        $_POST['admin_response'] ?? ''
    );

    if ($complaintId <= 0) {

        $error =
            "Invalid complaint ID.";

    } else {

        $sets = [];
        $types = '';
        $values = [];

        /*
        | STATUS
        */

        if ($statusColumn) {

            $sets[] =
                "`$statusColumn` = ?";

            $types .= 's';

            $values[] =
                $newStatus;
        }

        /*
        | ADMIN RESPONSE
        */

        if ($responseColumn) {

            $sets[] =
                "`$responseColumn` = ?";

            $types .= 's';

            $values[] =
                $adminResponse;
        }

        /*
        | NOTHING TO UPDATE
        */

        if (empty($sets)) {

            $error =
                "Walang status/response column sa complaints table.";

        } else {

            $types .= 'i';

            $values[] =
                $complaintId;

            $sql = "
                UPDATE complaints
                SET
                    " . implode(', ', $sets) . "
                WHERE
                    `$idColumn` = ?
            ";

            $stmt =
                $conn->prepare($sql);

            if (!$stmt) {

                $error =
                    "Database error: " .
                    $conn->error;

            } else {

                $bind = [];

                $bind[] =
                    $types;

                foreach (
                    $values
                    as $key => $value
                ) {

                    $bind[] =
                        &$values[$key];
                }

                call_user_func_array(
                    [$stmt, 'bind_param'],
                    $bind
                );

                if ($stmt->execute()) {

                    $success =
                        "Complaint updated successfully.";

                } else {

                    $error =
                        "Unable to update complaint: " .
                        $stmt->error;
                }

                $stmt->close();
            }
        }
    }
}

/*
|--------------------------------------------------------------------------
| DELETE COMPLAINT
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    ($_POST['action'] ?? '') === 'delete'
) {

    $complaintId = (int)(
        $_POST['complaint_id'] ?? 0
    );

    if ($complaintId <= 0) {

        $error =
            "Invalid complaint ID.";

    } else {

        $stmt = $conn->prepare(
            "DELETE FROM complaints
             WHERE `$idColumn` = ?"
        );

        if (!$stmt) {

            $error =
                "Database error: " .
                $conn->error;

        } else {

            $stmt->bind_param(
                "i",
                $complaintId
            );

            if ($stmt->execute()) {

                $success =
                    "Complaint deleted successfully.";

            } else {

                $error =
                    "Unable to delete complaint: " .
                    $stmt->error;
            }

            $stmt->close();
        }
    }
}

/*
|--------------------------------------------------------------------------
| SELECT COLUMNS
|--------------------------------------------------------------------------
*/

$select = [];

$select[] =
    "`$idColumn` AS complaint_id";

$select[] =
    "`$messageColumn` AS complaint_message";

if ($titleColumn) {

    $select[] =
        "`$titleColumn` AS complaint_title";

} else {

    $select[] =
        "'' AS complaint_title";
}

if ($statusColumn) {

    $select[] =
        "`$statusColumn` AS complaint_status";

} else {

    $select[] =
        "'Pending' AS complaint_status";
}

if ($residentColumn) {

    $select[] =
        "`$residentColumn` AS resident_id";

} else {

    $select[] =
        "NULL AS resident_id";
}

if ($nameColumn) {

    $select[] =
        "`$nameColumn` AS resident_name";

} else {

    $select[] =
        "'' AS resident_name";
}

if ($purokColumn) {

    $select[] =
        "`$purokColumn` AS purok";

} else {

    $select[] =
        "'' AS purok";
}

if ($responseColumn) {

    $select[] =
        "`$responseColumn` AS admin_response";

} else {

    $select[] =
        "'' AS admin_response";
}

if ($dateColumn) {

    $select[] =
        "`$dateColumn` AS created_at";

} else {

    $select[] =
        "NULL AS created_at";
}

/*
|--------------------------------------------------------------------------
| LOAD DATA
|--------------------------------------------------------------------------
*/

$where = '';

if (
    $filterStatus !== '' &&
    $statusColumn
) {

    $safeStatus =
        $conn->real_escape_string(
            $filterStatus
        );

    $where =
        " WHERE `$statusColumn` = '$safeStatus' ";
}

$orderBy =
    $dateColumn
    ? "`$dateColumn` DESC"
    : "`$idColumn` DESC";

$sql = "
    SELECT
        " . implode(', ', $select) . "
    FROM complaints
    $where
    ORDER BY
        $orderBy
";

$result =
    $conn->query($sql);

$complaints = [];

if ($result) {

    while (
        $row =
        $result->fetch_assoc()
    ) {

        $complaints[] =
            $row;
    }

    $result->free();
}

/*
|--------------------------------------------------------------------------
| STATISTICS
|--------------------------------------------------------------------------
*/

$total = count($complaints);

$pending = 0;
$review = 0;
$resolved = 0;
$rejected = 0;

foreach ($complaints as $complaint) {

    $status =
        strtolower(
            trim(
                $complaint['complaint_status']
                ?? 'Pending'
            )
        );

    if ($status === 'pending') {
        $pending++;
    }

    elseif (
        $status === 'under review' ||
        $status === 'review'
    ) {
        $review++;
    }

    elseif ($status === 'resolved') {
        $resolved++;
    }

    elseif ($status === 'rejected') {
        $rejected++;
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
MAPALADNEXUS | Complaints
</title>

<style>

*{
    box-sizing:border-box;
    margin:0;
    padding:0;
}

body{

    min-height:100vh;

    font-family:
        Arial,
        Helvetica,
        sans-serif;

    color:#f8fafc;

    background:

        radial-gradient(
            circle at 10% 10%,
            rgba(37,99,235,.20),
            transparent 30%
        ),

        radial-gradient(
            circle at 90% 90%,
            rgba(124,58,237,.18),
            transparent 30%
        ),

        #050816;
}

body:before{

    content:"";

    position:fixed;

    inset:0;

    pointer-events:none;

    background-image:

        linear-gradient(
            rgba(255,255,255,.015) 1px,
            transparent 1px
        ),

        linear-gradient(
            90deg,
            rgba(255,255,255,.015) 1px,
            transparent 1px
        );

    background-size:60px 60px;
}

/* SIDEBAR */

.sidebar{

    position:fixed;

    left:20px;
    top:20px;
    bottom:20px;

    width:250px;

    padding:20px 15px;

    background:
        rgba(10,15,32,.86);

    border:
        1px solid rgba(255,255,255,.08);

    border-radius:25px;

    backdrop-filter:blur(25px);

    box-shadow:
        15px 25px 70px rgba(0,0,0,.40);

    z-index:10;

    display:flex;
    flex-direction:column;
}

.brand{

    display:flex;

    align-items:center;

    gap:11px;

    padding:5px 8px 20px;

    border-bottom:
        1px solid rgba(255,255,255,.07);
}

.logo{

    width:45px;
    height:45px;

    display:flex;

    align-items:center;
    justify-content:center;

    border-radius:14px;

    background:
        linear-gradient(
            145deg,
            #2563eb,
            #4f46e5
        );

    font-size:21px;
}

.brand h2{
    font-size:14px;
}

.brand small{

    display:block;

    margin-top:4px;

    color:#64748b;

    font-size:7px;

    letter-spacing:1px;
}

.nav-title{

    margin:
        20px 9px 9px;

    color:#64748b;

    font-size:8px;

    font-weight:bold;

    text-transform:uppercase;
}

.nav{

    flex:1;

    overflow-y:auto;
}

.nav a{

    display:flex;

    align-items:center;

    gap:11px;

    padding:11px;

    margin-bottom:5px;

    color:#94a3b8;

    text-decoration:none;

    border-radius:12px;

    font-size:9px;

    transition:.2s;
}

.nav a:hover{

    color:#fff;

    background:
        rgba(255,255,255,.05);
}

.nav a.active{

    color:#fff;

    background:
        rgba(37,99,235,.18);

    box-shadow:
        inset 3px 0 #3b82f6;
}

.nav-icon{

    width:23px;

    text-align:center;

    font-size:14px;
}

.logout{

    display:block;

    padding:12px;

    color:#fca5a5;

    text-decoration:none;

    text-align:center;

    background:
        rgba(239,68,68,.07);

    border:
        1px solid rgba(239,68,68,.12);

    border-radius:12px;

    font-size:9px;
}

/* MAIN */

.main{

    margin-left:290px;

    padding:30px;

    min-height:100vh;
}

.header{

    display:flex;

    justify-content:space-between;

    align-items:center;

    margin-bottom:25px;
}

.header h1{
    font-size:28px;
}

.header p{

    margin-top:7px;

    color:#94a3b8;

    font-size:9px;
}

.admin{

    padding:10px 14px;

    color:#93c5fd;

    background:
        rgba(255,255,255,.04);

    border:
        1px solid rgba(255,255,255,.08);

    border-radius:13px;

    font-size:9px;
}

/* STATS */

.stats{

    display:grid;

    grid-template-columns:
        repeat(4,1fr);

    gap:14px;

    margin-bottom:20px;
}

.stat{

    padding:18px;

    background:
        rgba(255,255,255,.045);

    border:
        1px solid rgba(255,255,255,.08);

    border-radius:19px;

    backdrop-filter:blur(20px);

    transition:.2s;
}

.stat:hover{
    transform:translateY(-3px);
}

.stat-icon{
    font-size:18px;
}

.stat-number{

    margin-top:9px;

    font-size:23px;

    font-weight:bold;
}

.stat-label{

    margin-top:5px;

    color:#64748b;

    font-size:7px;

    text-transform:uppercase;
}

/* ALERT */

.alert{

    padding:13px 16px;

    margin-bottom:18px;

    border-radius:12px;

    font-size:9px;
}

.success{

    color:#a7f3d0;

    background:
        rgba(16,185,129,.10);

    border:
        1px solid rgba(16,185,129,.15);
}

.error{

    color:#fecaca;

    background:
        rgba(239,68,68,.10);

    border:
        1px solid rgba(239,68,68,.15);
}

/* PANEL */

.panel{

    background:
        rgba(255,255,255,.045);

    border:
        1px solid rgba(255,255,255,.08);

    border-radius:22px;

    backdrop-filter:blur(20px);

    overflow:hidden;
}

.toolbar{

    display:flex;

    justify-content:space-between;

    align-items:center;

    gap:12px;

    padding:18px;

    border-bottom:
        1px solid rgba(255,255,255,.07);
}

.search{

    flex:1;

    max-width:450px;

    padding:11px 13px;

    color:#fff;

    background:
        rgba(0,0,0,.20);

    border:
        1px solid rgba(255,255,255,.08);

    border-radius:10px;

    outline:none;

    font-size:9px;
}

.filters{

    display:flex;

    gap:6px;

    flex-wrap:wrap;
}

.filter{

    padding:9px 11px;

    color:#94a3b8;

    text-decoration:none;

    background:
        rgba(255,255,255,.035);

    border:
        1px solid rgba(255,255,255,.07);

    border-radius:9px;

    font-size:7px;
}

.filter.active{

    color:#fff;

    background:
        rgba(37,99,235,.20);

    border-color:
        rgba(59,130,246,.25);
}

/* COMPLAINT LIST */

.list{
    padding:18px;
}

.complaint{

    margin-bottom:12px;

    padding:18px;

    background:
        rgba(255,255,255,.035);

    border:
        1px solid rgba(255,255,255,.07);

    border-radius:17px;

    transition:.2s;
}

.complaint:hover{

    background:
        rgba(255,255,255,.055);

    transform:translateY(-2px);
}

.complaint:last-child{
    margin-bottom:0;
}

.top{

    display:flex;

    justify-content:space-between;

    align-items:flex-start;

    gap:15px;

    margin-bottom:10px;
}

.title{

    color:#fff;

    font-size:14px;

    font-weight:bold;
}

.message{

    color:#94a3b8;

    font-size:9px;

    line-height:1.7;

    white-space:pre-line;

    word-break:break-word;
}

.badge{

    display:inline-block;

    padding:6px 8px;

    border-radius:8px;

    font-size:7px;

    font-weight:bold;

    white-space:nowrap;
}

.pending{

    color:#fcd34d;

    background:
        rgba(245,158,11,.10);
}

.review{

    color:#93c5fd;

    background:
        rgba(37,99,235,.10);
}

.resolved{

    color:#6ee7b7;

    background:
        rgba(16,185,129,.10);
}

.rejected{

    color:#fca5a5;

    background:
        rgba(239,68,68,.10);
}

.other{

    color:#c4b5fd;

    background:
        rgba(124,58,237,.10);
}

.meta{

    display:flex;

    gap:14px;

    flex-wrap:wrap;

    margin-top:14px;

    padding-top:11px;

    border-top:
        1px solid rgba(255,255,255,.05);

    color:#64748b;

    font-size:7px;
}

.actions{

    display:flex;

    gap:7px;

    margin-top:13px;
}

.btn{

    padding:8px 11px;

    color:#fff;

    background:
        rgba(255,255,255,.05);

    border:
        1px solid rgba(255,255,255,.08);

    border-radius:8px;

    cursor:pointer;

    font-size:7px;
}

.btn:hover{
    background:rgba(255,255,255,.10);
}

.btn.delete{
    color:#fca5a5;
}

/* EMPTY */

.empty{

    padding:60px 20px;

    text-align:center;

    color:#64748b;
}

.empty-icon{
    font-size:40px;
    margin-bottom:10px;
}

.empty h3{

    margin-bottom:6px;

    color:#fff;

    font-size:14px;
}

.empty p{
    font-size:8px;
}

/* MODAL */

.modal{

    position:fixed;

    inset:0;

    display:none;

    align-items:center;

    justify-content:center;

    padding:20px;

    background:
        rgba(0,0,0,.70);

    backdrop-filter:blur(8px);

    z-index:1000;
}

.modal.show{
    display:flex;
}

.modal-box{

    width:100%;

    max-width:520px;

    max-height:90vh;

    overflow-y:auto;

    background:#0b1120;

    border:
        1px solid rgba(255,255,255,.10);

    border-radius:20px;

    box-shadow:
        0 30px 100px rgba(0,0,0,.65);
}

.modal-head{

    display:flex;

    justify-content:space-between;

    align-items:center;

    padding:18px;

    border-bottom:
        1px solid rgba(255,255,255,.07);
}

.modal-head h2{
    font-size:15px;
}

.close{

    width:30px;
    height:30px;

    border:0;

    border-radius:8px;

    color:#fff;

    background:
        rgba(255,255,255,.06);

    cursor:pointer;
}

.form{
    padding:20px;
}

.field{
    margin-bottom:15px;
}

.field label{

    display:block;

    margin-bottom:7px;

    color:#cbd5e1;

    font-size:8px;

    font-weight:bold;

    text-transform:uppercase;
}

.field input,
.field textarea,
.field select{

    width:100%;

    padding:11px;

    color:#fff;

    background:
        rgba(0,0,0,.25);

    border:
        1px solid rgba(255,255,255,.08);

    border-radius:10px;

    outline:none;

    font-size:9px;

    font-family:Arial,sans-serif;
}

.field textarea{

    min-height:110px;

    resize:vertical;

    line-height:1.6;
}

.field select option{
    background:#111827;
}

.save{

    width:100%;

    padding:12px;

    border:0;

    border-radius:10px;

    color:#fff;

    background:
        linear-gradient(
            145deg,
            #2563eb,
            #4f46e5
        );

    cursor:pointer;

    font-size:9px;

    font-weight:bold;
}

/* RESPONSIVE */

@media(max-width:1100px){

    .stats{
        grid-template-columns:repeat(2,1fr);
    }
}

@media(max-width:850px){

    .sidebar{
        display:none;
    }

    .main{
        margin-left:0;
        padding:20px;
    }
}

@media(max-width:600px){

    .stats{
        grid-template-columns:1fr;
    }

    .header{
        flex-direction:column;
        align-items:flex-start;
        gap:12px;
    }

    .toolbar{
        flex-direction:column;
        align-items:stretch;
    }

    .search{
        max-width:none;
    }
}

</style>

</head>

<body>

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

            <small>
                BARANGAY MAPALAD
            </small>

        </div>

    </div>


    <div class="nav-title">
        Administration
    </div>


    <nav class="nav">

        <a href="dashboard.php">

            <span class="nav-icon">
                🏠
            </span>

            Dashboard

        </a>


        <a href="residents.php">

            <span class="nav-icon">
                👥
            </span>

            Residents

        </a>


        <a href="services.php">

            <span class="nav-icon">
                🛠️
            </span>

            Services

        </a>


        <a href="requests.php">

            <span class="nav-icon">
                📋
            </span>

            Service Requests

        </a>


        <a href="announcements.php">

            <span class="nav-icon">
                📢
            </span>

            Announcements

        </a>


        <a
            href="complaints.php"
            class="active"
        >

            <span class="nav-icon">
                💬
            </span>

            Complaints

        </a>


        <a href="reports.php">

            <span class="nav-icon">
                📊
            </span>

            Reports

        </a>


        <a href="blotter.php">

            <span class="nav-icon">
                📝
            </span>

            Blotter

        </a>


        <div class="nav-title">
            Account
        </div>


        <a href="profile.php">

            <span class="nav-icon">
                👤
            </span>

            My Profile

        </a>

    </nav>


    <a
        href="logout.php"
        class="logout"
        onclick="
            return confirm(
                'Logout from MAPALADNEXUS?'
            );
        "
    >
        🚪 Logout
    </a>

</aside>


<!-- =========================================================
     MAIN
========================================================= -->

<main class="main">


    <header class="header">

        <div>

            <h1>
                Complaints
            </h1>

            <p>
                Manage and respond to complaints submitted by residents.
            </p>

        </div>


        <div class="admin">

            👑

            <?= e(
                $_SESSION['username']
                ?? 'Admin'
            ) ?>

        </div>

    </header>


    <!-- =====================================================
         STATISTICS
    ====================================================== -->

    <section class="stats">


        <div class="stat">

            <div class="stat-icon">
                💬
            </div>

            <div class="stat-number">
                <?= $total ?>
            </div>

            <div class="stat-label">
                Total Complaints
            </div>

        </div>


        <div class="stat">

            <div class="stat-icon">
                🟡
            </div>

            <div class="stat-number">
                <?= $pending ?>
            </div>

            <div class="stat-label">
                Pending
            </div>

        </div>


        <div class="stat">

            <div class="stat-icon">
                🔵
            </div>

            <div class="stat-number">
                <?= $review ?>
            </div>

            <div class="stat-label">
                Under Review
            </div>

        </div>


        <div class="stat">

            <div class="stat-icon">
                🟢
            </div>

            <div class="stat-number">
                <?= $resolved ?>
            </div>

            <div class="stat-label">
                Resolved
            </div>

        </div>


    </section>


    <?php if ($success !== ''): ?>

        <div class="alert success">

            ✅

            <?= e($success) ?>

        </div>

    <?php endif; ?>


    <?php if ($error !== ''): ?>

        <div class="alert error">

            ⚠️

            <?= e($error) ?>

        </div>

    <?php endif; ?>


    <!-- =====================================================
         COMPLAINTS PANEL
    ====================================================== -->

    <section class="panel">


        <div class="toolbar">


            <input
                type="text"
                class="search"
                id="searchInput"
                placeholder="🔎 Search complaints..."
                onkeyup="searchComplaints()"
            >


            <div class="filters">

                <a
                    href="complaints.php"
                    class="
                        filter
                        <?= $filterStatus === ''
                            ? 'active'
                            : '' ?>"
                >
                    All
                </a>


                <?php if ($statusColumn): ?>

                    <a
                        href="complaints.php?status=Pending"
                        class="
                            filter
                            <?= strtolower(
                                $filterStatus
                            ) === 'pending'
                                ? 'active'
                                : '' ?>"
                    >
                        Pending
                    </a>


                    <a
                        href="complaints.php?status=Under Review"
                        class="
                            filter
                            <?= strtolower(
                                $filterStatus
                            ) === 'under review'
                                ? 'active'
                                : '' ?>"
                    >
                        Under Review
                    </a>


                    <a
                        href="complaints.php?status=Resolved"
                        class="
                            filter
                            <?= strtolower(
                                $filterStatus
                            ) === 'resolved'
                                ? 'active'
                                : '' ?>"
                    >
                        Resolved
                    </a>


                    <a
                        href="complaints.php?status=Rejected"
                        class="
                            filter
                            <?= strtolower(
                                $filterStatus
                            ) === 'rejected'
                                ? 'active'
                                : '' ?>"
                    >
                        Rejected
                    </a>

                <?php endif; ?>

            </div>


        </div>


        <div class="list">


            <?php if (empty($complaints)): ?>


                <div class="empty">

                    <div class="empty-icon">
                        💬
                    </div>

                    <h3>
                        No Complaints Found
                    </h3>

                    <p>
                        Wala pang complaint na makikita sa database.
                    </p>

                </div>


            <?php else: ?>


                <?php foreach (
                    $complaints
                    as $complaint
                ): ?>


                    <?php

                    $status =
                        trim(
                            $complaint[
                                'complaint_status'
                            ] ?? 'Pending'
                        );

                    $statusLower =
                        strtolower($status);

                    $statusClass = 'other';

                    if ($statusLower === 'pending') {
                        $statusClass = 'pending';
                    }

                    elseif (
                        $statusLower === 'under review' ||
                        $statusLower === 'review'
                    ) {
                        $statusClass = 'review';
                    }

                    elseif (
                        $statusLower === 'resolved'
                    ) {
                        $statusClass = 'resolved';
                    }

                    elseif (
                        $statusLower === 'rejected'
                    ) {
                        $statusClass = 'rejected';
                    }

                    $title =
                        trim(
                            $complaint[
                                'complaint_title'
                            ] ?? ''
                        );

                    if ($title === '') {
                        $title = 'Resident Complaint';
                    }

                    $message =
                        $complaint[
                            'complaint_message'
                        ] ?? '';

                    $resident =
                        trim(
                            $complaint[
                                'resident_name'
                            ] ?? ''
                        );

                    if ($resident === '') {
                        $resident = 'Resident';
                    }

                    $purok =
                        trim(
                            $complaint['purok']
                            ?? ''
                        );

                    $response =
                        $complaint[
                            'admin_response'
                        ] ?? '';

                    $createdAt =
                        $complaint[
                            'created_at'
                        ] ?? '';

                    ?>


                    <article
                        class="complaint"
                        data-search="<?= e(
                            strtolower(
                                $title .
                                ' ' .
                                $message .
                                ' ' .
                                $resident .
                                ' ' .
                                $purok .
                                ' ' .
                                $status
                            )
                        ) ?>"
                    >


                        <div class="top">

                            <div class="title">

                                <?= e($title) ?>

                            </div>


                            <span
                                class="
                                    badge
                                    <?= e(
                                        $statusClass
                                    ) ?>
                                "
                            >

                                <?php

                                if (
                                    $statusLower ===
                                    'pending'
                                ) {

                                    echo '🟡 Pending';

                                }

                                elseif (
                                    $statusLower ===
                                    'under review' ||
                                    $statusLower ===
                                    'review'
                                ) {

                                    echo '🔵 Under Review';

                                }

                                elseif (
                                    $statusLower ===
                                    'resolved'
                                ) {

                                    echo '🟢 Resolved';

                                }

                                elseif (
                                    $statusLower ===
                                    'rejected'
                                ) {

                                    echo '🔴 Rejected';

                                }

                                else {

                                    echo e(
                                        $status
                                    );

                                }

                                ?>

                            </span>

                        </div>


                        <div class="message">

                            <?= nl2br(
                                e($message)
                            ) ?>

                        </div>


                        <div class="meta">


                            <span>

                                👤

                                <?= e(
                                    $resident
                                ) ?>

                            </span>


                            <?php if (
                                $purok !== ''
                            ): ?>

                                <span>

                                    📍

                                    <?= e(
                                        $purok
                                    ) ?>

                                </span>

                            <?php endif; ?>


                            <?php if (
                                $createdAt !== ''
                            ): ?>

                                <span>

                                    📅

                                    <?= e(
                                        date(
                                            'M d, Y h:i A',
                                            strtotime(
                                                $createdAt
                                            )
                                        )
                                    ) ?>

                                </span>

                            <?php endif; ?>


                        </div>


                        <?php if (
                            trim($response) !== ''
                        ): ?>

                            <div
                                style="
                                    margin-top:13px;
                                    padding:12px;
                                    background:rgba(37,99,235,.06);
                                    border-left:3px solid #3b82f6;
                                    border-radius:8px;
                                "
                            >

                                <div
                                    style="
                                        color:#93c5fd;
                                        font-size:7px;
                                        font-weight:bold;
                                        text-transform:uppercase;
                                        margin-bottom:6px;
                                    "
                                >

                                    🛡️ Admin Response

                                </div>


                                <div
                                    style="
                                        color:#94a3b8;
                                        font-size:8px;
                                        line-height:1.6;
                                    "
                                >

                                    <?= nl2br(
                                        e($response)
                                    ) ?>

                                </div>

                            </div>

                        <?php endif; ?>


                        <div class="actions">


                            <button
                                type="button"
                                class="btn"
                                onclick='openComplaintModal(
                                    <?= json_encode(
                                        $complaint[
                                            'complaint_id'
                                        ]
                                    ) ?>,
                                    <?= json_encode(
                                        $title
                                    ) ?>,
                                    <?= json_encode(
                                        $status
                                    ) ?>,
                                    <?= json_encode(
                                        $response
                                    ) ?>
                                )'
                            >

                                ✏️ Manage

                            </button>


                            <form
                                method="POST"
                                style="display:inline"
                                onsubmit="
                                    return confirm(
                                        'Delete this complaint permanently?'
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
                                    name="complaint_id"
                                    value="<?= e(
                                        $complaint[
                                            'complaint_id'
                                        ]
                                    ) ?>"
                                >

                                <button
                                    type="submit"
                                    class="btn delete"
                                >

                                    🗑️ Delete

                                </button>

                            </form>


                        </div>


                    </article>


                <?php endforeach; ?>


            <?php endif; ?>


        </div>


    </section>


</main>


<!-- =========================================================
     MANAGE MODAL
========================================================= -->

<div
    class="modal"
    id="complaintModal"
>


    <div class="modal-box">


        <div class="modal-head">

            <h2>
                💬 Manage Complaint
            </h2>

            <button
                type="button"
                class="close"
                onclick="closeComplaintModal()"
            >

                ✕

            </button>

        </div>


        <form
            method="POST"
            class="form"
        >


            <input
                type="hidden"
                name="action"
                value="update"
            >


            <input
                type="hidden"
                name="complaint_id"
                id="editComplaintId"
            >


            <div class="field">

                <label>
                    Complaint
                </label>

                <input
                    type="text"
                    id="editComplaintTitle"
                    readonly
                >

            </div>


            <?php if ($statusColumn): ?>

                <div class="field">

                    <label>
                        Status
                    </label>

                    <select
                        name="status"
                        id="editComplaintStatus"
                    >

                        <option value="Pending">
                            🟡 Pending
                        </option>

                        <option value="Under Review">
                            🔵 Under Review
                        </option>

                        <option value="Resolved">
                            🟢 Resolved
                        </option>

                        <option value="Rejected">
                            🔴 Rejected
                        </option>

                    </select>

                </div>

            <?php endif; ?>


            <?php if ($responseColumn): ?>

                <div class="field">

                    <label>
                        Admin Response
                    </label>

                    <textarea
                        name="admin_response"
                        id="editAdminResponse"
                        placeholder="Maglagay ng response para sa resident..."
                    ></textarea>

                </div>

            <?php endif; ?>


            <?php if (
                $statusColumn ||
                $responseColumn
            ): ?>

                <button
                    type="submit"
                    class="save"
                >

                    💾 Save Complaint Update

                </button>

            <?php endif; ?>


        </form>


    </div>


</div>


<script>

/*
|--------------------------------------------------------------------------
| SEARCH
|--------------------------------------------------------------------------
*/

function searchComplaints()
{
    const input =
        document.getElementById(
            'searchInput'
        );

    const search =
        input.value
            .toLowerCase()
            .trim();

    const complaints =
        document.querySelectorAll(
            '.complaint'
        );

    complaints.forEach(
        function(item)
        {
            const text =
                item.getAttribute(
                    'data-search'
                ) || '';

            if (
                text.includes(search)
            ) {

                item.style.display =
                    '';

            } else {

                item.style.display =
                    'none';
            }
        }
    );
}


/*
|--------------------------------------------------------------------------
| OPEN MODAL
|--------------------------------------------------------------------------
*/

function openComplaintModal(
    id,
    title,
    status,
    response
)
{

    document.getElementById(
        'editComplaintId'
    ).value = id;


    document.getElementById(
        'editComplaintTitle'
    ).value = title;


    const statusElement =
        document.getElementById(
            'editComplaintStatus'
        );

    if (statusElement) {

        statusElement.value =
            status || 'Pending';
    }


    const responseElement =
        document.getElementById(
            'editAdminResponse'
        );

    if (responseElement) {

        responseElement.value =
            response || '';
    }


    document.getElementById(
        'complaintModal'
    ).classList.add(
        'show'
    );
}


/*
|--------------------------------------------------------------------------
| CLOSE MODAL
|--------------------------------------------------------------------------
*/

function closeComplaintModal()
{

    document.getElementById(
        'complaintModal'
    ).classList.remove(
        'show'
    );
}


/*
|--------------------------------------------------------------------------
| CLICK OUTSIDE
|--------------------------------------------------------------------------
*/

document.getElementById(
    'complaintModal'
).addEventListener(
    'click',
    function(event)
    {

        if (
            event.target === this
        ) {

            closeComplaintModal();
        }

    }
);


/*
|--------------------------------------------------------------------------
| ESC
|--------------------------------------------------------------------------
*/

document.addEventListener(
    'keydown',
    function(event)
    {

        if (
            event.key === 'Escape'
        ) {

            closeComplaintModal();
        }

    }
);

</script>

</body>
</html>