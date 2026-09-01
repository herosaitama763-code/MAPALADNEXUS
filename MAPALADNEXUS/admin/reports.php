<?php
session_start();

error_reporting(E_ALL);
ini_set('display_errors', 1);

/*
|--------------------------------------------------------------------------
| MAPALADNEXUS - ADMIN REPORTS
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/../config/database.php';

if (!isset($conn) || !($conn instanceof mysqli)) {
    die("Database connection failed.");
}

/*
|--------------------------------------------------------------------------
| ADMIN ACCESS
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
| HELPER FUNCTIONS
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

function tableExists($conn, $table)
{
    $safe = $conn->real_escape_string($table);

    $result = $conn->query(
        "SHOW TABLES LIKE '$safe'"
    );

    return $result && $result->num_rows > 0;
}

function getColumns($conn, $table)
{
    $columns = [];

    if (!tableExists($conn, $table)) {
        return $columns;
    }

    $safe = str_replace('`', '``', $table);

    $result = $conn->query(
        "SHOW COLUMNS FROM `$safe`"
    );

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $columns[] = $row['Field'];
        }

        $result->free();
    }

    return $columns;
}

function findColumn($columns, $possible)
{
    foreach ($possible as $column) {
        if (in_array($column, $columns, true)) {
            return $column;
        }
    }

    return null;
}

function getCount($conn, $table)
{
    if (!tableExists($conn, $table)) {
        return 0;
    }

    $safe = str_replace('`', '``', $table);

    $result = $conn->query(
        "SELECT COUNT(*) AS total
         FROM `$safe`"
    );

    if (!$result) {
        return 0;
    }

    $row = $result->fetch_assoc();

    $result->free();

    return (int)($row['total'] ?? 0);
}

function getStatusCounts($conn, $table, $statusColumn)
{
    $data = [];

    if (
        !$statusColumn ||
        !tableExists($conn, $table)
    ) {
        return $data;
    }

    $safeTable =
        str_replace('`', '``', $table);

    $safeColumn =
        str_replace('`', '``', $statusColumn);

    $sql = "
        SELECT
            `$safeColumn` AS status_value,
            COUNT(*) AS total
        FROM `$safeTable`
        GROUP BY `$safeColumn`
        ORDER BY total DESC
    ";

    $result = $conn->query($sql);

    if (!$result) {
        return $data;
    }

    while ($row = $result->fetch_assoc()) {
        $status = trim(
            (string)($row['status_value'] ?? '')
        );

        if ($status === '') {
            $status = 'No Status';
        }

        $data[] = [
            'status' => $status,
            'total'  => (int)$row['total']
        ];
    }

    $result->free();

    return $data;
}

/*
|--------------------------------------------------------------------------
| TABLE INFORMATION
|--------------------------------------------------------------------------
*/

$tables = [
    'residents',
    'users',
    'officials',
    'services',
    'service_requests',
    'requests',
    'announcements',
    'complaints',
    'blotter',
    'blotter_records',
    'certificates',
    'certificate_requests'
];

/*
|--------------------------------------------------------------------------
| COUNTS
|--------------------------------------------------------------------------
*/

$residents =
    getCount($conn, 'residents');

$users =
    getCount($conn, 'users');

$officials =
    getCount($conn, 'officials');

$services =
    getCount($conn, 'services');

$serviceRequests = 0;

if (tableExists($conn, 'service_requests')) {
    $serviceRequests =
        getCount($conn, 'service_requests');
} elseif (tableExists($conn, 'requests')) {
    $serviceRequests =
        getCount($conn, 'requests');
}

$announcements =
    getCount($conn, 'announcements');

$complaints =
    getCount($conn, 'complaints');

$blotter = 0;

if (tableExists($conn, 'blotter')) {
    $blotter =
        getCount($conn, 'blotter');
} elseif (tableExists($conn, 'blotter_records')) {
    $blotter =
        getCount($conn, 'blotter_records');
}

$certificates =
    getCount($conn, 'certificates');

$certificateRequests =
    getCount($conn, 'certificate_requests');

/*
|--------------------------------------------------------------------------
| REQUEST STATUS
|--------------------------------------------------------------------------
*/

$requestStatusData = [];

$requestTable = null;

if (tableExists($conn, 'service_requests')) {
    $requestTable = 'service_requests';
} elseif (tableExists($conn, 'requests')) {
    $requestTable = 'requests';
}

if ($requestTable) {

    $requestColumns =
        getColumns(
            $conn,
            $requestTable
        );

    $requestStatusColumn =
        findColumn(
            $requestColumns,
            [
                'status',
                'request_status',
                'service_status'
            ]
        );

    $requestStatusData =
        getStatusCounts(
            $conn,
            $requestTable,
            $requestStatusColumn
        );
}

/*
|--------------------------------------------------------------------------
| COMPLAINT STATUS
|--------------------------------------------------------------------------
*/

$complaintStatusData = [];

if (tableExists($conn, 'complaints')) {

    $complaintColumns =
        getColumns(
            $conn,
            'complaints'
        );

    $complaintStatusColumn =
        findColumn(
            $complaintColumns,
            [
                'status',
                'complaint_status'
            ]
        );

    $complaintStatusData =
        getStatusCounts(
            $conn,
            'complaints',
            $complaintStatusColumn
        );
}

/*
|--------------------------------------------------------------------------
| CERTIFICATE REQUEST STATUS
|--------------------------------------------------------------------------
*/

$certificateStatusData = [];

if (
    tableExists(
        $conn,
        'certificate_requests'
    )
) {

    $certificateColumns =
        getColumns(
            $conn,
            'certificate_requests'
        );

    $certificateStatusColumn =
        findColumn(
            $certificateColumns,
            [
                'status',
                'request_status',
                'certificate_status'
            ]
        );

    $certificateStatusData =
        getStatusCounts(
            $conn,
            'certificate_requests',
            $certificateStatusColumn
        );
}

/*
|--------------------------------------------------------------------------
| RECENT DATA
|--------------------------------------------------------------------------
*/

$recentComplaints = [];

if (tableExists($conn, 'complaints')) {

    $columns =
        getColumns(
            $conn,
            'complaints'
        );

    $idColumn =
        findColumn(
            $columns,
            [
                'id',
                'complaint_id'
            ]
        );

    $messageColumn =
        findColumn(
            $columns,
            [
                'title',
                'subject',
                'complaint_title',
                'description',
                'message',
                'complaint',
                'details'
            ]
        );

    $statusColumn =
        findColumn(
            $columns,
            [
                'status',
                'complaint_status'
            ]
        );

    $dateColumn =
        findColumn(
            $columns,
            [
                'created_at',
                'submitted_at',
                'date_submitted',
                'date_created',
                'date'
            ]
        );

    if ($idColumn && $messageColumn) {

        $select = [];

        $select[] =
            "`$idColumn` AS record_id";

        $select[] =
            "`$messageColumn` AS record_text";

        if ($statusColumn) {
            $select[] =
                "`$statusColumn` AS record_status";
        } else {
            $select[] =
                "'Pending' AS record_status";
        }

        if ($dateColumn) {
            $select[] =
                "`$dateColumn` AS record_date";
        } else {
            $select[] =
                "NULL AS record_date";
        }

        $order =
            $dateColumn
            ? "`$dateColumn` DESC"
            : "`$idColumn` DESC";

        $sql = "
            SELECT
                " . implode(', ', $select) . "
            FROM complaints
            ORDER BY $order
            LIMIT 5
        ";

        $result =
            $conn->query($sql);

        if ($result) {

            while (
                $row =
                $result->fetch_assoc()
            ) {

                $recentComplaints[] =
                    $row;
            }

            $result->free();
        }
    }
}

/*
|--------------------------------------------------------------------------
| DATE
|--------------------------------------------------------------------------
*/

$reportDate =
    date('F d, Y');

$reportTime =
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
MAPALADNEXUS | Reports
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
        rgba(10,15,32,.88);

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

/* REPORT HEADER */

.report-header{

    display:flex;

    justify-content:space-between;

    align-items:center;

    gap:20px;

    margin-bottom:20px;

    padding:20px;

    background:
        rgba(255,255,255,.045);

    border:
        1px solid rgba(255,255,255,.08);

    border-radius:20px;

    backdrop-filter:blur(20px);
}

.report-header h2{
    font-size:16px;
}

.report-header p{

    margin-top:5px;

    color:#64748b;

    font-size:8px;
}

.print-btn{

    padding:10px 14px;

    color:#fff;

    border:0;

    border-radius:10px;

    background:
        linear-gradient(
            145deg,
            #2563eb,
            #4f46e5
        );

    cursor:pointer;

    font-size:8px;

    font-weight:bold;
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

    background:
        rgba(255,255,255,.06);
}

.stat-top{

    display:flex;

    justify-content:space-between;

    align-items:center;
}

.stat-icon{
    font-size:20px;
}

.stat-number{

    margin-top:12px;

    font-size:25px;

    font-weight:bold;
}

.stat-label{

    margin-top:5px;

    color:#64748b;

    font-size:7px;

    text-transform:uppercase;

    letter-spacing:.5px;
}

/* GRID */

.grid{

    display:grid;

    grid-template-columns:
        1fr 1fr;

    gap:18px;

    margin-bottom:18px;
}

.card{

    padding:20px;

    background:
        rgba(255,255,255,.045);

    border:
        1px solid rgba(255,255,255,.08);

    border-radius:20px;

    backdrop-filter:blur(20px);
}

.card h3{

    font-size:13px;

    margin-bottom:16px;
}

.card-sub{

    color:#64748b;

    font-size:7px;

    margin-top:-10px;

    margin-bottom:15px;
}

/* STATUS */

.status-row{

    display:flex;

    align-items:center;

    gap:10px;

    margin-bottom:12px;
}

.status-row:last-child{
    margin-bottom:0;
}

.status-name{

    min-width:100px;

    color:#94a3b8;

    font-size:8px;
}

.progress{

    flex:1;

    height:8px;

    overflow:hidden;

    border-radius:20px;

    background:
        rgba(255,255,255,.06);
}

.progress span{

    display:block;

    height:100%;

    min-width:3px;

    border-radius:20px;

    background:
        linear-gradient(
            90deg,
            #2563eb,
            #7c3aed
        );
}

.status-number{

    min-width:25px;

    color:#fff;

    text-align:right;

    font-size:8px;

    font-weight:bold;
}

/* TABLE */

.table-wrap{

    overflow-x:auto;
}

table{

    width:100%;

    border-collapse:collapse;
}

th{

    padding:
        11px 10px;

    color:#64748b;

    text-align:left;

    font-size:7px;

    text-transform:uppercase;

    border-bottom:
        1px solid rgba(255,255,255,.07);
}

td{

    padding:
        12px 10px;

    color:#cbd5e1;

    font-size:8px;

    border-bottom:
        1px solid rgba(255,255,255,.05);
}

tr:last-child td{
    border-bottom:0;
}

.badge{

    display:inline-block;

    padding:5px 7px;

    border-radius:7px;

    font-size:6px;

    font-weight:bold;

    background:
        rgba(37,99,235,.12);

    color:#93c5fd;
}

/* SUMMARY */

.summary-grid{

    display:grid;

    grid-template-columns:
        repeat(3,1fr);

    gap:12px;
}

.summary{

    padding:14px;

    background:
        rgba(255,255,255,.035);

    border:
        1px solid rgba(255,255,255,.06);

    border-radius:14px;
}

.summary-title{

    color:#64748b;

    font-size:7px;

    text-transform:uppercase;
}

.summary-value{

    margin-top:7px;

    color:#fff;

    font-size:18px;

    font-weight:bold;
}

/* EMPTY */

.empty{

    padding:25px;

    text-align:center;

    color:#64748b;

    font-size:8px;
}

/* PRINT */

@media print{

    body{
        background:#fff !important;
        color:#000 !important;
    }

    body:before,
    .sidebar,
    .admin,
    .print-btn{
        display:none !important;
    }

    .main{

        margin:0;

        padding:20px;
    }

    .card,
    .stat,
    .report-header{

        color:#000;

        background:#fff;

        border:1px solid #ddd;

        box-shadow:none;
    }

    .header p,
    .report-header p,
    .card-sub,
    .stat-label,
    .status-name{

        color:#555;
    }

    td{
        color:#222;
    }
}

/* RESPONSIVE */

@media(max-width:1100px){

    .stats{

        grid-template-columns:
            repeat(2,1fr);
    }
}

@media(max-width:900px){

    .grid{

        grid-template-columns:1fr;
    }

    .summary-grid{

        grid-template-columns:
            repeat(2,1fr);
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

    .summary-grid{

        grid-template-columns:1fr;
    }

    .header,
    .report-header{

        flex-direction:column;

        align-items:flex-start;
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


        <a href="complaints.php">

            <span class="nav-icon">
                💬
            </span>

            Complaints

        </a>


        <a
            href="reports.php"
            class="active"
        >

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
                Reports
            </h1>

            <p>
                System-wide reports and administrative statistics.
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
         REPORT HEADER
    ====================================================== -->

    <section class="report-header">

        <div>

            <h2>
                📊 MAPALADNEXUS System Report
            </h2>

            <p>
                Generated <?= e($reportDate) ?>
                at <?= e($reportTime) ?>
            </p>

        </div>


        <button
            type="button"
            class="print-btn"
            onclick="window.print()"
        >

            🖨️ Print Report

        </button>

    </section>


    <!-- =====================================================
         STATISTICS
    ====================================================== -->

    <section class="stats">


        <div class="stat">

            <div class="stat-top">

                <span class="stat-icon">
                    👥
                </span>

            </div>

            <div class="stat-number">
                <?= $residents ?>
            </div>

            <div class="stat-label">
                Total Residents
            </div>

        </div>


        <div class="stat">

            <div class="stat-top">

                <span class="stat-icon">
                    🛠️
                </span>

            </div>

            <div class="stat-number">
                <?= $services ?>
            </div>

            <div class="stat-label">
                Available Services
            </div>

        </div>


        <div class="stat">

            <div class="stat-top">

                <span class="stat-icon">
                    📋
                </span>

            </div>

            <div class="stat-number">
                <?= $serviceRequests ?>
            </div>

            <div class="stat-label">
                Service Requests
            </div>

        </div>


        <div class="stat">

            <div class="stat-top">

                <span class="stat-icon">
                    📢
                </span>

            </div>

            <div class="stat-number">
                <?= $announcements ?>
            </div>

            <div class="stat-label">
                Announcements
            </div>

        </div>


        <div class="stat">

            <div class="stat-top">

                <span class="stat-icon">
                    💬
                </span>

            </div>

            <div class="stat-number">
                <?= $complaints ?>
            </div>

            <div class="stat-label">
                Complaints
            </div>

        </div>


        <div class="stat">

            <div class="stat-top">

                <span class="stat-icon">
                    📝
                </span>

            </div>

            <div class="stat-number">
                <?= $blotter ?>
            </div>

            <div class="stat-label">
                Blotter Records
            </div>

        </div>


        <div class="stat">

            <div class="stat-top">

                <span class="stat-icon">
                    📜
                </span>

            </div>

            <div class="stat-number">
                <?= $certificates ?>
            </div>

            <div class="stat-label">
                Certificates
            </div>

        </div>


        <div class="stat">

            <div class="stat-top">

                <span class="stat-icon">
                    👤
                </span>

            </div>

            <div class="stat-number">
                <?= $users ?>
            </div>

            <div class="stat-label">
                System Users
            </div>

        </div>


    </section>


    <!-- =====================================================
         STATUS REPORTS
    ====================================================== -->

    <section class="grid">


        <div class="card">

            <h3>
                📋 Service Request Status
            </h3>

            <div class="card-sub">
                Current request distribution
            </div>


            <?php if (
                empty($requestStatusData)
            ): ?>

                <div class="empty">
                    No request status data available.
                </div>

            <?php else: ?>


                <?php

                $maxRequest =
                    max(
                        array_column(
                            $requestStatusData,
                            'total'
                        )
                    );

                ?>


                <?php foreach (
                    $requestStatusData
                    as $item
                ): ?>

                    <?php

                    $percent =
                        $maxRequest > 0
                        ? (
                            $item['total']
                            /
                            $maxRequest
                        ) * 100
                        : 0;

                    ?>

                    <div class="status-row">

                        <div class="status-name">

                            <?= e(
                                $item['status']
                            ) ?>

                        </div>

                        <div class="progress">

                            <span
                                style="
                                    width:
                                    <?= $percent ?>%;
                                "
                            ></span>

                        </div>

                        <div class="status-number">

                            <?= $item['total'] ?>

                        </div>

                    </div>

                <?php endforeach; ?>


            <?php endif; ?>

        </div>


        <div class="card">

            <h3>
                💬 Complaint Status
            </h3>

            <div class="card-sub">
                Current complaint distribution
            </div>


            <?php if (
                empty($complaintStatusData)
            ): ?>

                <div class="empty">
                    No complaint status data available.
                </div>

            <?php else: ?>


                <?php

                $maxComplaint =
                    max(
                        array_column(
                            $complaintStatusData,
                            'total'
                        )
                    );

                ?>


                <?php foreach (
                    $complaintStatusData
                    as $item
                ): ?>

                    <?php

                    $percent =
                        $maxComplaint > 0
                        ? (
                            $item['total']
                            /
                            $maxComplaint
                        ) * 100
                        : 0;

                    ?>

                    <div class="status-row">

                        <div class="status-name">

                            <?= e(
                                $item['status']
                            ) ?>

                        </div>

                        <div class="progress">

                            <span
                                style="
                                    width:
                                    <?= $percent ?>%;
                                "
                            ></span>

                        </div>

                        <div class="status-number">

                            <?= $item['total'] ?>

                        </div>

                    </div>

                <?php endforeach; ?>


            <?php endif; ?>

        </div>


    </section>


    <!-- =====================================================
         SYSTEM SUMMARY
    ====================================================== -->

    <section class="card">

        <h3>
            🏛️ System Summary
        </h3>

        <div class="card-sub">
            Overall MAPALADNEXUS records
        </div>


        <div class="summary-grid">


            <div class="summary">

                <div class="summary-title">
                    Residents
                </div>

                <div class="summary-value">
                    <?= $residents ?>
                </div>

            </div>


            <div class="summary">

                <div class="summary-title">
                    Officials
                </div>

                <div class="summary-value">
                    <?= $officials ?>
                </div>

            </div>


            <div class="summary">

                <div class="summary-title">
                    Users
                </div>

                <div class="summary-value">
                    <?= $users ?>
                </div>

            </div>


            <div class="summary">

                <div class="summary-title">
                    Services
                </div>

                <div class="summary-value">
                    <?= $services ?>
                </div>

            </div>


            <div class="summary">

                <div class="summary-title">
                    Announcements
                </div>

                <div class="summary-value">
                    <?= $announcements ?>
                </div>

            </div>


            <div class="summary">

                <div class="summary-title">
                    Complaints
                </div>

                <div class="summary-value">
                    <?= $complaints ?>
                </div>

            </div>


            <div class="summary">

                <div class="summary-title">
                    Blotter
                </div>

                <div class="summary-value">
                    <?= $blotter ?>
                </div>

            </div>


            <div class="summary">

                <div class="summary-title">
                    Certificates
                </div>

                <div class="summary-value">
                    <?= $certificates ?>
                </div>

            </div>


            <div class="summary">

                <div class="summary-title">
                    Certificate Requests
                </div>

                <div class="summary-value">
                    <?= $certificateRequests ?>
                </div>

            </div>


        </div>

    </section>


    <br>


    <!-- =====================================================
         RECENT COMPLAINTS
    ====================================================== -->

    <section class="card">

        <h3>
            💬 Recent Complaints
        </h3>

        <div class="card-sub">
            Latest complaint records
        </div>


        <?php if (
            empty($recentComplaints)
        ): ?>

            <div class="empty">

                No complaint records available.

            </div>

        <?php else: ?>


            <div class="table-wrap">

                <table>

                    <thead>

                        <tr>

                            <th>
                                ID
                            </th>

                            <th>
                                Complaint
                            </th>

                            <th>
                                Status
                            </th>

                            <th>
                                Date
                            </th>

                        </tr>

                    </thead>


                    <tbody>


                    <?php foreach (
                        $recentComplaints
                        as $item
                    ): ?>

                        <tr>

                            <td>
                                #<?= e(
                                    $item['record_id']
                                ) ?>
                            </td>


                            <td>

                                <?= e(
                                    mb_strimwidth(
                                        $item[
                                            'record_text'
                                        ] ?? '',
                                        0,
                                        80,
                                        '...'
                                    )
                                ) ?>

                            </td>


                            <td>

                                <span class="badge">

                                    <?= e(
                                        $item[
                                            'record_status'
                                        ] ?? 'Pending'
                                    ) ?>

                                </span>

                            </td>


                            <td>

                                <?php

                                if (
                                    !empty(
                                        $item[
                                            'record_date'
                                        ]
                                    )
                                ) {

                                    echo e(
                                        date(
                                            'M d, Y',
                                            strtotime(
                                                $item[
                                                    'record_date'
                                                ]
                                            )
                                        )
                                    );

                                } else {

                                    echo '—';

                                }

                                ?>

                            </td>

                        </tr>

                    <?php endforeach; ?>


                    </tbody>

                </table>

            </div>


        <?php endif; ?>


    </section>


</main>

</body>
</html>