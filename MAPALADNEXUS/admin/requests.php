<?php
session_start();

error_reporting(E_ALL);
ini_set('display_errors', 1);

/*
|--------------------------------------------------------------------------
| DATABASE
|--------------------------------------------------------------------------
*/

$database_file = __DIR__ . '/../config/database.php';

if (!file_exists($database_file)) {
    die("Database file not found: " . htmlspecialchars($database_file));
}

require_once $database_file;

if (!isset($conn) || !($conn instanceof mysqli)) {
    die("Database connection error. Check config/database.php");
}


/*
|--------------------------------------------------------------------------
| ADMIN SECURITY
|--------------------------------------------------------------------------
*/

if (
    !isset($_SESSION['username']) ||
    empty($_SESSION['username'])
) {
    header("Location: login.php");
    exit();
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

function h($value)
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}


/*
|--------------------------------------------------------------------------
| CHECK TABLE
|--------------------------------------------------------------------------
*/

function tableExists($conn, $table)
{
    $table = $conn->real_escape_string($table);

    $result = $conn->query(
        "SHOW TABLES LIKE '$table'"
    );

    return $result && $result->num_rows > 0;
}


/*
|--------------------------------------------------------------------------
| GET TABLE COLUMNS
|--------------------------------------------------------------------------
*/

function getColumns($conn, $table)
{
    $columns = [];

    if (!tableExists($conn, $table)) {
        return $columns;
    }

    $result = $conn->query(
        "SHOW COLUMNS FROM `$table`"
    );

    if ($result) {

        while ($row = $result->fetch_assoc()) {
            $columns[] = $row['Field'];
        }

        $result->free();
    }

    return $columns;
}


/*
|--------------------------------------------------------------------------
| FIND FIRST EXISTING COLUMN
|--------------------------------------------------------------------------
*/

function firstExistingColumn($columns, $possible)
{
    foreach ($possible as $column) {

        if (in_array($column, $columns, true)) {
            return $column;
        }
    }

    return null;
}


/*
|--------------------------------------------------------------------------
| REQUEST TABLE
|--------------------------------------------------------------------------
|
| Main table:
| service_requests
|
*/

$request_table = 'service_requests';

if (!tableExists($conn, $request_table)) {

    die(
        '<div style="
            font-family:Arial;
            padding:40px;
            background:#050816;
            color:white;
            min-height:100vh;
        ">
            <h2>Service Requests table not found</h2>

            <p>
                Hindi makita ang
                <b>service_requests</b>
                table sa MAPALADNEXUS database.
            </p>

            <p>
                Paki-check muna sa SQLyog kung mayroon kang
                <b>service_requests</b> table.
            </p>
        </div>'
    );
}


/*
|--------------------------------------------------------------------------
| DETECT ACTUAL COLUMNS
|--------------------------------------------------------------------------
|
| Important:
| Hindi tayo gumagamit ng fixed "notes" column.
|
*/

$columns = getColumns(
    $conn,
    $request_table
);


/*
|--------------------------------------------------------------------------
| COLUMN MAPPING
|--------------------------------------------------------------------------
*/

$idColumn = firstExistingColumn(
    $columns,
    [
        'id',
        'request_id',
        'service_request_id'
    ]
);

$serviceIdColumn = firstExistingColumn(
    $columns,
    [
        'service_id',
        'serviceId'
    ]
);

$residentIdColumn = firstExistingColumn(
    $columns,
    [
        'resident_id',
        'user_id',
        'residentId'
    ]
);

$statusColumn = firstExistingColumn(
    $columns,
    [
        'status',
        'request_status'
    ]
);

$purposeColumn = firstExistingColumn(
    $columns,
    [
        'purpose',
        'reason',
        'request_purpose'
    ]
);

$requestDateColumn = firstExistingColumn(
    $columns,
    [
        'request_date',
        'date_requested',
        'requested_at',
        'created_at',
        'date_created'
    ]
);

$remarksColumn = firstExistingColumn(
    $columns,
    [
        'remarks',
        'remark',
        'admin_remarks',
        'comment',
        'comments'
    ]
);


/*
|--------------------------------------------------------------------------
| CHECK REQUIRED ID COLUMN
|--------------------------------------------------------------------------
*/

if (!$idColumn) {

    die(
        '<div style="
            font-family:Arial;
            padding:40px;
            background:#050816;
            color:white;
            min-height:100vh;
        ">
            <h2>Request ID column not found</h2>

            <p>
                Walang compatible ID column sa
                <b>service_requests</b> table.
            </p>
        </div>'
    );
}


/*
|--------------------------------------------------------------------------
| UPDATE REQUEST STATUS
|--------------------------------------------------------------------------
*/

$message = '';
$error = '';

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['action'])
) {

    $action = $_POST['action'];

    $requestId = (int)(
        $_POST['request_id'] ?? 0
    );


    if ($requestId <= 0) {

        $error = "Invalid request ID.";

    } elseif (!$statusColumn) {

        $error =
            "Status column was not found in service_requests.";

    } else {

        /*
        |----------------------------------------------------------
        | APPROVE
        |----------------------------------------------------------
        */

        if ($action === 'approve') {

            $newStatus = 'Approved';

            $stmt = $conn->prepare(
                "UPDATE `$request_table`
                 SET `$statusColumn` = ?
                 WHERE `$idColumn` = ?"
            );

            if ($stmt) {

                $stmt->bind_param(
                    "si",
                    $newStatus,
                    $requestId
                );

                if ($stmt->execute()) {

                    $message =
                        "Request successfully approved.";

                } else {

                    $error =
                        "Unable to approve request: " .
                        $stmt->error;
                }

                $stmt->close();

            } else {

                $error =
                    "Database error: " .
                    $conn->error;
            }
        }


        /*
        |----------------------------------------------------------
        | REJECT
        |----------------------------------------------------------
        */

        elseif ($action === 'reject') {

            $newStatus = 'Rejected';

            $stmt = $conn->prepare(
                "UPDATE `$request_table`
                 SET `$statusColumn` = ?
                 WHERE `$idColumn` = ?"
            );

            if ($stmt) {

                $stmt->bind_param(
                    "si",
                    $newStatus,
                    $requestId
                );

                if ($stmt->execute()) {

                    $message =
                        "Request successfully rejected.";

                } else {

                    $error =
                        "Unable to reject request: " .
                        $stmt->error;
                }

                $stmt->close();

            } else {

                $error =
                    "Database error: " .
                    $conn->error;
            }
        }


        /*
        |----------------------------------------------------------
        | COMPLETE
        |----------------------------------------------------------
        */

        elseif ($action === 'complete') {

            $newStatus = 'Completed';

            $stmt = $conn->prepare(
                "UPDATE `$request_table`
                 SET `$statusColumn` = ?
                 WHERE `$idColumn` = ?"
            );

            if ($stmt) {

                $stmt->bind_param(
                    "si",
                    $newStatus,
                    $requestId
                );

                if ($stmt->execute()) {

                    $message =
                        "Request marked as completed.";

                } else {

                    $error =
                        "Unable to complete request: " .
                        $stmt->error;
                }

                $stmt->close();

            } else {

                $error =
                    "Database error: " .
                    $conn->error;
            }
        }


        /*
        |----------------------------------------------------------
        | CANCEL
        |----------------------------------------------------------
        */

        elseif ($action === 'cancel') {

            $newStatus = 'Cancelled';

            $stmt = $conn->prepare(
                "UPDATE `$request_table`
                 SET `$statusColumn` = ?
                 WHERE `$idColumn` = ?"
            );

            if ($stmt) {

                $stmt->bind_param(
                    "si",
                    $newStatus,
                    $requestId
                );

                if ($stmt->execute()) {

                    $message =
                        "Request cancelled.";

                } else {

                    $error =
                        "Unable to cancel request: " .
                        $stmt->error;
                }

                $stmt->close();

            } else {

                $error =
                    "Database error: " .
                    $conn->error;
            }
        }
    }
}


/*
|--------------------------------------------------------------------------
| BUILD SELECT
|--------------------------------------------------------------------------
*/

$selectParts = [];

$selectParts[] =
    "sr.`$idColumn` AS request_id";


/*
|--------------------------------------------------------------------------
| SERVICE
|--------------------------------------------------------------------------
*/

$serviceNameColumn = null;

if (
    $serviceIdColumn &&
    tableExists($conn, 'services')
) {

    $serviceColumns =
        getColumns(
            $conn,
            'services'
        );

    $serviceNameColumn =
        firstExistingColumn(
            $serviceColumns,
            [
                'service_name',
                'name',
                'title'
            ]
        );
}


/*
|--------------------------------------------------------------------------
| RESIDENT
|--------------------------------------------------------------------------
*/

$residentNameColumn = null;

if (
    $residentIdColumn &&
    tableExists($conn, 'residents')
) {

    $residentColumns =
        getColumns(
            $conn,
            'residents'
        );

    $residentNameColumn =
        firstExistingColumn(
            $residentColumns,
            [
                'full_name',
                'resident_name',
                'name',
                'first_name'
            ]
        );
}


/*
|--------------------------------------------------------------------------
| QUERY
|--------------------------------------------------------------------------
*/

$rows = [];


/*
|--------------------------------------------------------------------------
| BEST QUERY
|--------------------------------------------------------------------------
*/

try {

    /*
    |--------------------------------------------------------------
    | SERVICE REQUESTS + SERVICES + RESIDENTS
    |--------------------------------------------------------------
    */

    if (
        $serviceIdColumn &&
        $serviceNameColumn &&
        $residentIdColumn &&
        $residentNameColumn
    ) {

        $sql = "
            SELECT

                sr.`$idColumn` AS request_id,

                sr.`$serviceIdColumn` AS service_id,

                s.`$serviceNameColumn` AS service_name,

                sr.`$residentIdColumn` AS resident_id,

                r.`$residentNameColumn` AS resident_name
        ";


        if ($purposeColumn) {

            $sql .= ",
                sr.`$purposeColumn` AS purpose";
        } else {

            $sql .= ",
                '' AS purpose";
        }


        if ($statusColumn) {

            $sql .= ",
                sr.`$statusColumn` AS status";
        } else {

            $sql .= ",
                'Pending' AS status";
        }


        if ($requestDateColumn) {

            $sql .= ",
                sr.`$requestDateColumn` AS request_date";
        } else {

            $sql .= ",
                NULL AS request_date";
        }


        if ($remarksColumn) {

            $sql .= ",
                sr.`$remarksColumn` AS remarks";
        } else {

            $sql .= ",
                '' AS remarks";
        }


        $sql .= "

            FROM `$request_table` sr

            LEFT JOIN services s
                ON sr.`$serviceIdColumn`
                = s.id

            LEFT JOIN residents r
                ON sr.`$residentIdColumn`
                = r.id

            ORDER BY
                sr.`$idColumn` DESC
        ";


        $result =
            $conn->query($sql);


        if ($result) {

            while (
                $row =
                $result->fetch_assoc()
            ) {

                $rows[] = $row;
            }

            $result->free();
        }

    }

    /*
    |--------------------------------------------------------------
    | FALLBACK QUERY
    |--------------------------------------------------------------
    */

    else {

        $sql = "
            SELECT *
            FROM `$request_table`
            ORDER BY `$idColumn` DESC
        ";


        $result =
            $conn->query($sql);


        if ($result) {

            while (
                $row =
                $result->fetch_assoc()
            ) {

                $rows[] = [

                    'request_id' =>
                        $row[$idColumn]
                        ?? '',

                    'service_id' =>
                        $serviceIdColumn
                        ? ($row[$serviceIdColumn] ?? '')
                        : '',

                    'service_name' =>
                        'Barangay Service',

                    'resident_id' =>
                        $residentIdColumn
                        ? ($row[$residentIdColumn] ?? '')
                        : '',

                    'resident_name' =>
                        'Resident',

                    'purpose' =>
                        $purposeColumn
                        ? ($row[$purposeColumn] ?? '')
                        : '',

                    'status' =>
                        $statusColumn
                        ? ($row[$statusColumn] ?? 'Pending')
                        : 'Pending',

                    'request_date' =>
                        $requestDateColumn
                        ? ($row[$requestDateColumn] ?? '')
                        : '',

                    'remarks' =>
                        $remarksColumn
                        ? ($row[$remarksColumn] ?? '')
                        : ''
                ];
            }

            $result->free();
        }
    }

} catch (Throwable $e) {

    $error =
        "Unable to load service requests: " .
        $e->getMessage();
}


/*
|--------------------------------------------------------------------------
| STATISTICS
|--------------------------------------------------------------------------
*/

$totalRequests = count($rows);

$pendingRequests = 0;
$approvedRequests = 0;
$rejectedRequests = 0;
$completedRequests = 0;

foreach ($rows as $row) {

    $status =
        strtolower(
            trim(
                $row['status'] ?? ''
            )
        );


    if ($status === 'pending') {
        $pendingRequests++;
    }


    if ($status === 'approved') {
        $approvedRequests++;
    }


    if ($status === 'rejected') {
        $rejectedRequests++;
    }


    if ($status === 'completed') {
        $completedRequests++;
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
MAPALADNEXUS | Service Requests
</title>


<style>

/* =========================================================
   RESET
========================================================= */

* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}


/* =========================================================
   ROOT
========================================================= */

:root {

    --bg: #050816;

    --panel: rgba(255,255,255,.055);

    --border: rgba(255,255,255,.09);

    --text: #f8fafc;

    --muted: #94a3b8;

    --blue: #2563eb;

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
   GRID BACKGROUND
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

    background-size:
        60px 60px;

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
        rgba(10,15,32,.84);

    border:
        1px solid
        var(--border);

    border-radius: 26px;

    backdrop-filter:
        blur(25px);

    box-shadow:
        20px 30px 70px
        rgba(0,0,0,.40);

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
        0 10px 30px
        rgba(37,99,235,.30);
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
   NAVIGATION
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
}


.logout:hover {

    color: white;

    background:
        rgba(239,68,68,.18);
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

    transition: .25s;
}


.stat:hover {

    transform:
        translateY(-4px);
}


.stat-icon {

    margin-bottom: 9px;

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
}


/* =========================================================
   ALERT
========================================================= */

.alert {

    padding:
        14px 16px;

    margin-bottom: 18px;

    border-radius: 13px;

    font-size: 9px;
}


.alert.success {

    color: #a7f3d0;

    background:
        rgba(16,185,129,.10);

    border:
        1px solid
        rgba(16,185,129,.16);
}


.alert.error {

    color: #fecaca;

    background:
        rgba(239,68,68,.10);

    border:
        1px solid
        rgba(239,68,68,.16);
}


/* =========================================================
   PANEL
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
}


.panel-header {

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 15px;

    padding:
        21px 23px;

    border-bottom:
        1px solid
        var(--border);
}


.panel-header h2 {

    font-size: 15px;
}


.panel-header p {

    margin-top: 5px;

    color: var(--muted);

    font-size: 8px;
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

    max-width: 400px;

    padding:
        11px 13px;

    color: white;

    background:
        rgba(0,0,0,.22);

    border:
        1px solid
        var(--border);

    border-radius: 11px;

    outline: none;

    font-size: 9px;
}


.search:focus {

    border-color:
        rgba(96,165,250,.45);
}


/* =========================================================
   TABLE
========================================================= */

.table-wrap {

    overflow-x: auto;
}


table {

    width: 100%;

    min-width: 1100px;

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
   REQUEST
========================================================= */

.request-id {

    color: #60a5fa;

    font-weight: bold;
}


.service {

    color: white;

    font-weight: bold;
}


.resident {

    color: #e2e8f0;

    font-weight: bold;
}


.purpose {

    max-width: 220px;

    color: var(--muted);

    line-height: 1.5;
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


.status.pending {

    color: #fcd34d;

    background:
        rgba(245,158,11,.10);
}


.status.approved {

    color: #6ee7b7;

    background:
        rgba(16,185,129,.10);
}


.status.rejected {

    color: #fca5a5;

    background:
        rgba(239,68,68,.10);
}


.status.completed {

    color: #93c5fd;

    background:
        rgba(37,99,235,.10);
}


.status.cancelled {

    color: #cbd5e1;

    background:
        rgba(148,163,184,.10);
}


/* =========================================================
   ACTIONS
========================================================= */

.actions {

    display: flex;

    gap: 6px;

    flex-wrap: wrap;
}


.action {

    padding:
        8px 10px;

    color: white;

    border:
        1px solid
        var(--border);

    border-radius: 8px;

    cursor: pointer;

    font-size: 7px;

    transition: .2s;
}


.approve {

    background:
        rgba(16,185,129,.10);
}


.approve:hover {

    background:
        rgba(16,185,129,.22);
}


.reject {

    background:
        rgba(239,68,68,.10);
}


.reject:hover {

    background:
        rgba(239,68,68,.22);
}


.complete {

    background:
        rgba(37,99,235,.10);
}


.complete:hover {

    background:
        rgba(37,99,235,.22);
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

    margin-bottom: 14px;

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
   RESPONSIVE
========================================================= */

@media(max-width:1100px) {

    .stats {

        grid-template-columns:
            repeat(2, 1fr);
    }
}


@media(max-width:850px) {

    .sidebar {

        display: none;
    }

    .main {

        margin-left: 0;

        padding: 20px;
    }
}


@media(max-width:600px) {

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

            <span class="icon">
                🏠
            </span>

            Dashboard

        </a>


        <a href="residents.php">

            <span class="icon">
                👥
            </span>

            Residents

        </a>


        <a href="services.php">

            <span class="icon">
                🛠️
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

            Service Requests

        </a>


        <a href="announcements.php">

            <span class="icon">
                📢
            </span>

            Announcements

        </a>


        <a href="complaints.php">

            <span class="icon">
                💬
            </span>

            Complaints

        </a>


        <a href="reports.php">

            <span class="icon">
                📊
            </span>

            Reports

        </a>


        <a href="blotter.php">

            <span class="icon">
                📝
            </span>

            Blotter

        </a>


        <div class="nav-title">
            Account
        </div>


        <a href="profile.php">

            <span class="icon">
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

        🚪

        Logout

    </a>


</aside>


<!-- =========================================================
     MAIN
========================================================= -->

<main class="main">


    <header class="header">

        <div>

            <h1>
                Service Requests
            </h1>

            <p>
                Manage service requests submitted by Barangay Mapalad residents.
            </p>

        </div>


        <div class="admin">

            👑

            <?= h(
                $_SESSION['username']
                ?? 'Admin'
            ) ?>

        </div>

    </header>


    <!-- =====================================================
         STATISTICS
    ===================================================== -->

    <section class="stats">


        <div class="stat">

            <div class="stat-icon">
                📋
            </div>

            <div class="stat-number">
                <?= $totalRequests ?>
            </div>

            <div class="stat-label">
                Total Requests
            </div>

        </div>


        <div class="stat">

            <div class="stat-icon">
                🟡
            </div>

            <div class="stat-number">
                <?= $pendingRequests ?>
            </div>

            <div class="stat-label">
                Pending
            </div>

        </div>


        <div class="stat">

            <div class="stat-icon">
                🟢
            </div>

            <div class="stat-number">
                <?= $approvedRequests ?>
            </div>

            <div class="stat-label">
                Approved
            </div>

        </div>


        <div class="stat">

            <div class="stat-icon">
                🔵
            </div>

            <div class="stat-number">
                <?= $completedRequests ?>
            </div>

            <div class="stat-label">
                Completed
            </div>

        </div>


    </section>


    <!-- =====================================================
         ALERT
    ===================================================== -->

    <?php if ($message !== ''): ?>

        <div class="alert success">

            ✅

            <?= h($message) ?>

        </div>

    <?php endif; ?>


    <?php if ($error !== ''): ?>

        <div class="alert error">

            ⚠️

            <?= h($error) ?>

        </div>

    <?php endif; ?>


    <!-- =====================================================
         REQUEST PANEL
    ===================================================== -->

    <section class="panel">


        <div class="panel-header">

            <div>

                <h2>
                    Resident Service Requests
                </h2>

                <p>
                    Review and manage submitted requests.
                </p>

            </div>

        </div>


        <!-- SEARCH -->

        <div class="search-area">

            <input
                type="text"
                id="search"
                class="search"
                placeholder="🔎 Search resident, service, status..."
                onkeyup="searchRequests()"
            >

        </div>


        <!-- TABLE -->

        <div class="table-wrap">


            <?php if (empty($rows)): ?>


                <div class="empty">

                    <div class="empty-icon">
                        📋
                    </div>

                    <h3>
                        No Service Requests
                    </h3>

                    <p>
                        Wala pang service request na makikita.
                    </p>

                </div>


            <?php else: ?>


                <table id="requestsTable">


                    <thead>

                        <tr>

                            <th>
                                Request #
                            </th>

                            <th>
                                Resident
                            </th>

                            <th>
                                Service
                            </th>

                            <th>
                                Purpose
                            </th>

                            <th>
                                Date
                            </th>

                            <th>
                                Status
                            </th>

                            <th>
                                Action
                            </th>

                        </tr>

                    </thead>


                    <tbody>


                    <?php foreach ($rows as $row): ?>


                        <?php

                        $requestId =
                            $row['request_id']
                            ?? '';

                        $resident =
                            $row['resident_name']
                            ?? 'Resident';

                        $service =
                            $row['service_name']
                            ?? 'Barangay Service';

                        $purpose =
                            $row['purpose']
                            ?? '';

                        $date =
                            $row['request_date']
                            ?? '';

                        $status =
                            $row['status']
                            ?? 'Pending';


                        $statusClass =
                            strtolower(
                                preg_replace(
                                    '/[^a-zA-Z0-9]+/',
                                    '-',
                                    $status
                                )
                            );

                        ?>


                        <tr class="request-row">


                            <td>

                                <span class="request-id">

                                    #<?= h($requestId) ?>

                                </span>

                            </td>


                            <td>

                                <div class="resident">

                                    <?= h($resident) ?>

                                </div>

                            </td>


                            <td>

                                <div class="service">

                                    <?= h($service) ?>

                                </div>

                            </td>


                            <td>

                                <div class="purpose">

                                    <?= $purpose !== ''
                                        ? h($purpose)
                                        : 'No purpose provided.'
                                    ?>

                                </div>

                            </td>


                            <td>

                                <?= $date !== ''
                                    ? h(
                                        date(
                                            'M d, Y h:i A',
                                            strtotime($date)
                                        )
                                    )
                                    : 'N/A'
                                ?>

                            </td>


                            <td>

                                <span
                                    class="
                                        status
                                        <?= h($statusClass) ?>
                                    "
                                >

                                    <?= h($status) ?>

                                </span>

                            </td>


                            <td>

                                <div class="actions">


                                    <?php
                                    $normalizedStatus =
                                        strtolower(
                                            trim($status)
                                        );
                                    ?>


                                    <?php if (
                                        $normalizedStatus ===
                                        'pending'
                                    ): ?>


                                        <!-- APPROVE -->

                                        <form
                                            method="POST"
                                            style="display:inline;"
                                            onsubmit="
                                                return confirm(
                                                    'Approve this service request?'
                                                );
                                            "
                                        >

                                            <input
                                                type="hidden"
                                                name="action"
                                                value="approve"
                                            >

                                            <input
                                                type="hidden"
                                                name="request_id"
                                                value="<?= h($requestId) ?>"
                                            >

                                            <button
                                                type="submit"
                                                class="action approve"
                                            >

                                                ✓ Approve

                                            </button>

                                        </form>


                                        <!-- REJECT -->

                                        <form
                                            method="POST"
                                            style="display:inline;"
                                            onsubmit="
                                                return confirm(
                                                    'Reject this service request?'
                                                );
                                            "
                                        >

                                            <input
                                                type="hidden"
                                                name="action"
                                                value="reject"
                                            >

                                            <input
                                                type="hidden"
                                                name="request_id"
                                                value="<?= h($requestId) ?>"
                                            >

                                            <button
                                                type="submit"
                                                class="action reject"
                                            >

                                                ✕ Reject

                                            </button>

                                        </form>


                                    <?php elseif (
                                        $normalizedStatus ===
                                        'approved'
                                    ): ?>


                                        <!-- COMPLETE -->

                                        <form
                                            method="POST"
                                            style="display:inline;"
                                            onsubmit="
                                                return confirm(
                                                    'Mark this request as completed?'
                                                );
                                            "
                                        >

                                            <input
                                                type="hidden"
                                                name="action"
                                                value="complete"
                                            >

                                            <input
                                                type="hidden"
                                                name="request_id"
                                                value="<?= h($requestId) ?>"
                                            >

                                            <button
                                                type="submit"
                                                class="action complete"
                                            >

                                                ✓ Complete

                                            </button>

                                        </form>


                                    <?php else: ?>


                                        <span
                                            style="
                                                color:#64748b;
                                                font-size:8px;
                                            "
                                        >
                                            No action
                                        </span>


                                    <?php endif; ?>


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


<script>

/* =========================================================
   SEARCH REQUESTS
========================================================= */

function searchRequests() {

    const input =
        document.getElementById(
            'search'
        );

    const filter =
        input.value.toLowerCase();

    const rows =
        document.querySelectorAll(
            '.request-row'
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

</script>


</body>

</html>