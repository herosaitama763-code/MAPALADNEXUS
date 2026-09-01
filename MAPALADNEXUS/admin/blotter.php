<?php
session_start();

error_reporting(E_ALL);
ini_set('display_errors', 1);

/*
|--------------------------------------------------------------------------
| MAPALADNEXUS
| ADMIN - BLOTTER MANAGEMENT
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
| ADMIN LOGIN CHECK
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
| MESSAGE
|--------------------------------------------------------------------------
*/

$message = '';
$messageType = '';

/*
|--------------------------------------------------------------------------
| ADD BLOTTER
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['action']) &&
    $_POST['action'] === 'add'
) {

    $complainant_name =
        trim($_POST['complainant_name'] ?? '');

    $respondent_name =
        trim($_POST['respondent_name'] ?? '');

    $incident_type =
        trim($_POST['incident_type'] ?? '');

    $incident_date =
        trim($_POST['incident_date'] ?? '');

    $incident_location =
        trim($_POST['incident_location'] ?? '');

    $description =
        trim($_POST['description'] ?? '');

    $status =
        trim($_POST['status'] ?? 'Pending');


    /*
    |--------------------------------------------------------------
    | VALIDATION
    |--------------------------------------------------------------
    */

    if (
        $complainant_name === '' ||
        $respondent_name === '' ||
        $incident_type === ''
    ) {

        $message =
            "Please fill in all required fields.";

        $messageType = 'error';

    } else {

        $allowedStatuses = [
            'Pending',
            'Under Review',
            'Resolved',
            'Referred',
            'Closed'
        ];

        if (
            !in_array(
                $status,
                $allowedStatuses,
                true
            )
        ) {
            $status = 'Pending';
        }


        if ($incident_date === '') {
            $incident_date = null;
        }


        $sql = "
            INSERT INTO blotter (
                complainant_name,
                respondent_name,
                incident_type,
                incident_date,
                incident_location,
                description,
                status
            )
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ";


        $stmt =
            $conn->prepare($sql);


        if (!$stmt) {

            $message =
                "Database error: " .
                $conn->error;

            $messageType = 'error';

        } else {

            $stmt->bind_param(
                "sssssss",
                $complainant_name,
                $respondent_name,
                $incident_type,
                $incident_date,
                $incident_location,
                $description,
                $status
            );


            if ($stmt->execute()) {

                $message =
                    "Blotter record successfully added.";

                $messageType = 'success';

            } else {

                $message =
                    "Failed to add blotter record: " .
                    $stmt->error;

                $messageType = 'error';
            }


            $stmt->close();
        }
    }
}

/*
|--------------------------------------------------------------------------
| EDIT BLOTTER
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['action']) &&
    $_POST['action'] === 'edit'
) {

    $id =
        (int)($_POST['id'] ?? 0);

    $complainant_name =
        trim($_POST['complainant_name'] ?? '');

    $respondent_name =
        trim($_POST['respondent_name'] ?? '');

    $incident_type =
        trim($_POST['incident_type'] ?? '');

    $incident_date =
        trim($_POST['incident_date'] ?? '');

    $incident_location =
        trim($_POST['incident_location'] ?? '');

    $description =
        trim($_POST['description'] ?? '');

    $status =
        trim($_POST['status'] ?? 'Pending');


    $allowedStatuses = [
        'Pending',
        'Under Review',
        'Resolved',
        'Referred',
        'Closed'
    ];


    if (
        $id <= 0 ||
        $complainant_name === '' ||
        $respondent_name === '' ||
        $incident_type === ''
    ) {

        $message =
            "Please complete the required fields.";

        $messageType = 'error';

    } else {

        if (
            !in_array(
                $status,
                $allowedStatuses,
                true
            )
        ) {
            $status = 'Pending';
        }


        if ($incident_date === '') {
            $incident_date = null;
        }


        $sql = "
            UPDATE blotter
            SET
                complainant_name = ?,
                respondent_name = ?,
                incident_type = ?,
                incident_date = ?,
                incident_location = ?,
                description = ?,
                status = ?
            WHERE id = ?
        ";


        $stmt =
            $conn->prepare($sql);


        if (!$stmt) {

            $message =
                "Database error: " .
                $conn->error;

            $messageType = 'error';

        } else {

            $stmt->bind_param(
                "sssssssi",
                $complainant_name,
                $respondent_name,
                $incident_type,
                $incident_date,
                $incident_location,
                $description,
                $status,
                $id
            );


            if ($stmt->execute()) {

                $message =
                    "Blotter record successfully updated.";

                $messageType = 'success';

            } else {

                $message =
                    "Failed to update record: " .
                    $stmt->error;

                $messageType = 'error';
            }


            $stmt->close();
        }
    }
}

/*
|--------------------------------------------------------------------------
| DELETE BLOTTER
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['action']) &&
    $_POST['action'] === 'delete'
) {

    $id =
        (int)($_POST['id'] ?? 0);


    if ($id > 0) {

        $stmt =
            $conn->prepare(
                "DELETE FROM blotter WHERE id = ?"
            );


        if ($stmt) {

            $stmt->bind_param(
                "i",
                $id
            );


            if ($stmt->execute()) {

                $message =
                    "Blotter record deleted.";

                $messageType = 'success';

            } else {

                $message =
                    "Unable to delete record.";

                $messageType = 'error';
            }


            $stmt->close();

        } else {

            $message =
                "Database error: " .
                $conn->error;

            $messageType = 'error';
        }

    } else {

        $message =
            "Invalid blotter ID.";

        $messageType = 'error';
    }
}

/*
|--------------------------------------------------------------------------
| FETCH RECORD FOR EDIT
|--------------------------------------------------------------------------
*/

$editRecord = null;

if (
    isset($_GET['edit']) &&
    is_numeric($_GET['edit'])
) {

    $editId =
        (int)$_GET['edit'];


    $stmt =
        $conn->prepare(
            "SELECT
                id,
                complainant_name,
                respondent_name,
                incident_type,
                incident_date,
                incident_location,
                description,
                status,
                created_at
             FROM blotter
             WHERE id = ?"
        );


    if ($stmt) {

        $stmt->bind_param(
            "i",
            $editId
        );

        $stmt->execute();

        $result =
            $stmt->get_result();

        $editRecord =
            $result->fetch_assoc();

        $stmt->close();
    }
}

/*
|--------------------------------------------------------------------------
| VIEW RECORD
|--------------------------------------------------------------------------
*/

$viewRecord = null;

if (
    isset($_GET['view']) &&
    is_numeric($_GET['view'])
) {

    $viewId =
        (int)$_GET['view'];


    $stmt =
        $conn->prepare(
            "SELECT
                id,
                complainant_name,
                respondent_name,
                incident_type,
                incident_date,
                incident_location,
                description,
                status,
                created_at
             FROM blotter
             WHERE id = ?"
        );


    if ($stmt) {

        $stmt->bind_param(
            "i",
            $viewId
        );

        $stmt->execute();

        $result =
            $stmt->get_result();

        $viewRecord =
            $result->fetch_assoc();

        $stmt->close();
    }
}

/*
|--------------------------------------------------------------------------
| SEARCH
|--------------------------------------------------------------------------
*/

$search =
    trim($_GET['search'] ?? '');


/*
|--------------------------------------------------------------------------
| FETCH BLOTTER RECORDS
|--------------------------------------------------------------------------
*/

$records = [];


if ($search !== '') {

    $like =
        '%' . $search . '%';


    $sql = "
        SELECT
            id,
            complainant_name,
            respondent_name,
            incident_type,
            incident_date,
            incident_location,
            description,
            status,
            created_at
        FROM blotter
        WHERE
            complainant_name LIKE ?
            OR respondent_name LIKE ?
            OR incident_type LIKE ?
            OR incident_location LIKE ?
            OR description LIKE ?
            OR status LIKE ?
        ORDER BY id DESC
    ";


    $stmt =
        $conn->prepare($sql);


    if ($stmt) {

        $stmt->bind_param(
            "ssssss",
            $like,
            $like,
            $like,
            $like,
            $like,
            $like
        );

        $stmt->execute();

        $result =
            $stmt->get_result();


        while (
            $row =
            $result->fetch_assoc()
        ) {

            $records[] =
                $row;
        }


        $stmt->close();
    }

} else {

    $sql = "
        SELECT
            id,
            complainant_name,
            respondent_name,
            incident_type,
            incident_date,
            incident_location,
            description,
            status,
            created_at
        FROM blotter
        ORDER BY id DESC
    ";


    $result =
        $conn->query($sql);


    if ($result) {

        while (
            $row =
            $result->fetch_assoc()
        ) {

            $records[] =
                $row;
        }

        $result->free();
    }
}


/*
|--------------------------------------------------------------------------
| TOTAL
|--------------------------------------------------------------------------
*/

$totalRecords =
    count($records);


/*
|--------------------------------------------------------------------------
| STATUS COUNTS
|--------------------------------------------------------------------------
*/

$pendingCount = 0;
$reviewCount = 0;
$resolvedCount = 0;
$closedCount = 0;


foreach (
    $records as $record
) {

    $recordStatus =
        trim(
            $record['status'] ?? ''
        );


    if ($recordStatus === 'Pending') {

        $pendingCount++;

    } elseif (
        $recordStatus === 'Under Review'
    ) {

        $reviewCount++;

    } elseif (
        $recordStatus === 'Resolved'
    ) {

        $resolvedCount++;

    } elseif (
        $recordStatus === 'Closed'
    ) {

        $closedCount++;
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
MAPALADNEXUS | Blotter Management
</title>


<style>

/* =========================================================
   RESET
========================================================= */

*{
    box-sizing:border-box;
    margin:0;
    padding:0;
}


/* =========================================================
   BODY
========================================================= */

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


body::before{

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


/* =========================================================
   SIDEBAR
========================================================= */

.sidebar{

    position:fixed;

    left:20px;
    top:20px;
    bottom:20px;

    width:250px;

    padding:20px 15px;

    background:
        rgba(10,15,32,.90);

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

    padding:
        5px 8px 20px;

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


/* =========================================================
   MAIN
========================================================= */

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

    padding:
        10px 14px;

    color:#93c5fd;

    background:
        rgba(255,255,255,.04);

    border:
        1px solid rgba(255,255,255,.08);

    border-radius:13px;

    font-size:9px;
}


/* =========================================================
   TOP CARDS
========================================================= */

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


/* =========================================================
   PANEL
========================================================= */

.panel{

    padding:20px;

    margin-bottom:20px;

    background:
        rgba(255,255,255,.045);

    border:
        1px solid rgba(255,255,255,.08);

    border-radius:20px;

    backdrop-filter:blur(20px);
}


.panel-title{

    display:flex;

    align-items:center;

    justify-content:space-between;

    gap:15px;

    margin-bottom:18px;
}


.panel-title h2{

    font-size:14px;
}


.panel-title p{

    margin-top:5px;

    color:#64748b;

    font-size:7px;
}


/* =========================================================
   BUTTONS
========================================================= */

.btn{

    display:inline-flex;

    align-items:center;

    justify-content:center;

    gap:6px;

    padding:
        10px 14px;

    border:0;

    border-radius:10px;

    cursor:pointer;

    text-decoration:none;

    font-size:8px;

    font-weight:bold;

    transition:.2s;
}


.btn:hover{

    transform:translateY(-1px);
}


.btn-primary{

    color:#fff;

    background:
        linear-gradient(
            145deg,
            #2563eb,
            #4f46e5
        );
}


.btn-view{

    color:#bfdbfe;

    background:
        rgba(37,99,235,.12);

    border:
        1px solid rgba(37,99,235,.18);
}


.btn-edit{

    color:#fde68a;

    background:
        rgba(234,179,8,.10);

    border:
        1px solid rgba(234,179,8,.15);
}


.btn-delete{

    color:#fecaca;

    background:
        rgba(239,68,68,.10);

    border:
        1px solid rgba(239,68,68,.15);
}


/* =========================================================
   SEARCH
========================================================= */

.search-box{

    display:flex;

    gap:10px;

    margin-bottom:18px;
}


.search-box input{

    flex:1;

    min-width:0;

    padding:12px 14px;

    color:#fff;

    background:
        rgba(255,255,255,.04);

    border:
        1px solid rgba(255,255,255,.09);

    border-radius:11px;

    outline:none;

    font-size:9px;
}


.search-box input:focus{

    border-color:
        rgba(59,130,246,.60);
}


.search-box input::placeholder{

    color:#64748b;
}


/* =========================================================
   TABLE
========================================================= */

.table-wrap{

    overflow-x:auto;
}


table{

    width:100%;

    min-width:950px;

    border-collapse:collapse;
}


th{

    padding:
        12px 10px;

    color:#64748b;

    text-align:left;

    font-size:7px;

    text-transform:uppercase;

    border-bottom:
        1px solid rgba(255,255,255,.08);
}


td{

    padding:
        13px 10px;

    color:#cbd5e1;

    font-size:8px;

    border-bottom:
        1px solid rgba(255,255,255,.05);

    vertical-align:middle;
}


tr:hover td{

    background:
        rgba(255,255,255,.02);
}


.actions{

    display:flex;

    gap:5px;

    flex-wrap:wrap;
}


/* =========================================================
   BADGES
========================================================= */

.badge{

    display:inline-block;

    padding:
        5px 8px;

    border-radius:8px;

    font-size:6px;

    font-weight:bold;

    white-space:nowrap;
}


.badge-pending{

    color:#fde68a;

    background:
        rgba(234,179,8,.10);
}


.badge-review{

    color:#bfdbfe;

    background:
        rgba(37,99,235,.12);
}


.badge-resolved{

    color:#bbf7d0;

    background:
        rgba(34,197,94,.10);
}


.badge-referred{

    color:#ddd6fe;

    background:
        rgba(124,58,237,.12);
}


.badge-closed{

    color:#cbd5e1;

    background:
        rgba(100,116,139,.12);
}


/* =========================================================
   MESSAGE
========================================================= */

.message{

    margin-bottom:18px;

    padding:13px 15px;

    border-radius:12px;

    font-size:8px;
}


.message.success{

    color:#bbf7d0;

    background:
        rgba(34,197,94,.09);

    border:
        1px solid rgba(34,197,94,.16);
}


.message.error{

    color:#fecaca;

    background:
        rgba(239,68,68,.09);

    border:
        1px solid rgba(239,68,68,.16);
}


/* =========================================================
   MODAL
========================================================= */

.modal{

    position:fixed;

    inset:0;

    display:flex;

    align-items:center;

    justify-content:center;

    padding:20px;

    background:
        rgba(0,0,0,.70);

    backdrop-filter:blur(8px);

    z-index:100;

    overflow-y:auto;
}


.modal-box{

    width:100%;

    max-width:650px;

    max-height:90vh;

    overflow-y:auto;

    padding:25px;

    background:
        #0b1122;

    border:
        1px solid rgba(255,255,255,.10);

    border-radius:22px;

    box-shadow:
        0 30px 100px rgba(0,0,0,.60);
}


.modal-header{

    display:flex;

    align-items:center;

    justify-content:space-between;

    margin-bottom:20px;
}


.modal-header h2{

    font-size:16px;
}


.close{

    width:32px;
    height:32px;

    display:flex;

    align-items:center;
    justify-content:center;

    color:#94a3b8;

    background:
        rgba(255,255,255,.05);

    border:0;

    border-radius:9px;

    cursor:pointer;

    font-size:16px;
}


/* =========================================================
   FORM
========================================================= */

.form-grid{

    display:grid;

    grid-template-columns:
        1fr 1fr;

    gap:14px;
}


.form-group{

    display:flex;

    flex-direction:column;

    gap:6px;
}


.form-group.full{

    grid-column:
        1 / -1;
}


.form-group label{

    color:#94a3b8;

    font-size:8px;

    font-weight:bold;
}


.form-group input,
.form-group select,
.form-group textarea{

    width:100%;

    padding:11px 12px;

    color:#fff;

    background:
        rgba(255,255,255,.045);

    border:
        1px solid rgba(255,255,255,.09);

    border-radius:10px;

    outline:none;

    font-family:inherit;

    font-size:8px;
}


.form-group select option{

    color:#111827;

    background:#fff;
}


.form-group textarea{

    min-height:100px;

    resize:vertical;
}


.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus{

    border-color:
        rgba(59,130,246,.60);
}


.form-actions{

    display:flex;

    justify-content:flex-end;

    gap:8px;

    margin-top:20px;
}


.btn-secondary{

    color:#cbd5e1;

    background:
        rgba(255,255,255,.06);

    border:
        1px solid rgba(255,255,255,.08);
}


/* =========================================================
   VIEW DETAILS
========================================================= */

.detail-grid{

    display:grid;

    grid-template-columns:
        1fr 1fr;

    gap:10px;
}


.detail{

    padding:13px;

    background:
        rgba(255,255,255,.035);

    border:
        1px solid rgba(255,255,255,.06);

    border-radius:12px;
}


.detail.full{

    grid-column:
        1 / -1;
}


.detail-label{

    margin-bottom:5px;

    color:#64748b;

    font-size:7px;

    text-transform:uppercase;
}


.detail-value{

    color:#e2e8f0;

    font-size:9px;

    line-height:1.5;

    word-break:break-word;
}


/* =========================================================
   EMPTY
========================================================= */

.empty{

    padding:45px 20px;

    color:#64748b;

    text-align:center;

    font-size:9px;
}


/* =========================================================
   RESPONSIVE
========================================================= */

@media(max-width:1100px){

    .stats{

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

    .form-grid{

        grid-template-columns:1fr;
    }

    .form-group.full{

        grid-column:auto;
    }

    .detail-grid{

        grid-template-columns:1fr;
    }

    .detail.full{

        grid-column:auto;
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

    .panel-title{

        flex-direction:column;

        align-items:flex-start;
    }

    .search-box{

        flex-direction:column;
    }
}


/* =========================================================
   PRINT
========================================================= */

@media print{

    body{

        background:#fff !important;

        color:#000 !important;
    }

    body::before,
    .sidebar,
    .admin,
    .btn,
    .search-box,
    .actions,
    .message{

        display:none !important;
    }

    .main{

        margin:0;

        padding:20px;
    }

    .panel,
    .stat{

        color:#000;

        background:#fff;

        border:1px solid #ddd;

        box-shadow:none;
    }

    th,
    td{

        color:#222;

        border-color:#ddd;
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


        <a href="reports.php">

            <span class="nav-icon">
                📊
            </span>

            Reports

        </a>


        <a
            href="blotter.php"
            class="active"
        >

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


    <!-- HEADER -->

    <header class="header">

        <div>

            <h1>
                Blotter Management
            </h1>

            <p>
                Manage and monitor barangay blotter records.
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


    <!-- MESSAGE -->

    <?php if ($message !== ''): ?>

        <div
            class="message <?= e($messageType) ?>"
        >

            <?= e($message) ?>

        </div>

    <?php endif; ?>


    <!-- =====================================================
         STATISTICS
    ====================================================== -->

    <section class="stats">


        <div class="stat">

            <div class="stat-icon">
                📝
            </div>

            <div class="stat-number">
                <?= $totalRecords ?>
            </div>

            <div class="stat-label">
                Total Records
            </div>

        </div>


        <div class="stat">

            <div class="stat-icon">
                ⏳
            </div>

            <div class="stat-number">
                <?= $pendingCount ?>
            </div>

            <div class="stat-label">
                Pending
            </div>

        </div>


        <div class="stat">

            <div class="stat-icon">
                🔍
            </div>

            <div class="stat-number">
                <?= $reviewCount ?>
            </div>

            <div class="stat-label">
                Under Review
            </div>

        </div>


        <div class="stat">

            <div class="stat-icon">
                ✅
            </div>

            <div class="stat-number">
                <?= $resolvedCount ?>
            </div>

            <div class="stat-label">
                Resolved
            </div>

        </div>


    </section>


    <!-- =====================================================
         BLOTTER TABLE
    ====================================================== -->

    <section class="panel">


        <div class="panel-title">

            <div>

                <h2>
                    📝 Blotter Records
                </h2>

                <p>
                    View, add, update, or delete blotter records.
                </p>

            </div>


            <button
                type="button"
                class="btn btn-primary"
                onclick="openAddModal()"
            >

                ➕ Add Blotter

            </button>

        </div>


        <!-- SEARCH -->

        <form
            method="GET"
            class="search-box"
        >

            <input
                type="text"
                name="search"
                value="<?= e($search) ?>"
                placeholder="Search complainant, respondent, incident, location..."
            >

            <button
                type="submit"
                class="btn btn-primary"
            >

                🔎 Search

            </button>


            <?php if ($search !== ''): ?>

                <a
                    href="blotter.php"
                    class="btn btn-secondary"
                >

                    ✕ Clear

                </a>

            <?php endif; ?>

        </form>


        <!-- TABLE -->

        <div class="table-wrap">

            <?php if (
                empty($records)
            ): ?>

                <div class="empty">

                    <div
                        style="
                            font-size:35px;
                            margin-bottom:10px;
                        "
                    >
                        📝
                    </div>

                    <?php if ($search !== ''): ?>

                        No blotter records found
                        for
                        <strong>
                            <?= e($search) ?>
                        </strong>.

                    <?php else: ?>

                        No blotter records yet.

                        <br>

                        Click
                        <strong>
                            Add Blotter
                        </strong>
                        to create the first record.

                    <?php endif; ?>

                </div>

            <?php else: ?>


                <table>

                    <thead>

                        <tr>

                            <th>
                                ID
                            </th>

                            <th>
                                Complainant
                            </th>

                            <th>
                                Respondent
                            </th>

                            <th>
                                Incident
                            </th>

                            <th>
                                Date
                            </th>

                            <th>
                                Location
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


                    <?php foreach (
                        $records
                        as $record
                    ): ?>


                        <?php

                        $recordStatus =
                            trim(
                                $record['status']
                                ?? 'Pending'
                            );


                        $badgeClass =
                            'badge-pending';


                        if (
                            $recordStatus
                            === 'Under Review'
                        ) {

                            $badgeClass =
                                'badge-review';

                        } elseif (
                            $recordStatus
                            === 'Resolved'
                        ) {

                            $badgeClass =
                                'badge-resolved';

                        } elseif (
                            $recordStatus
                            === 'Referred'
                        ) {

                            $badgeClass =
                                'badge-referred';

                        } elseif (
                            $recordStatus
                            === 'Closed'
                        ) {

                            $badgeClass =
                                'badge-closed';
                        }

                        ?>


                        <tr>


                            <td>

                                #<?= e(
                                    $record['id']
                                ) ?>

                            </td>


                            <td>

                                <?= e(
                                    $record[
                                        'complainant_name'
                                    ]
                                ) ?>

                            </td>


                            <td>

                                <?= e(
                                    $record[
                                        'respondent_name'
                                    ]
                                ) ?>

                            </td>


                            <td>

                                <?= e(
                                    $record[
                                        'incident_type'
                                    ]
                                ) ?>

                            </td>


                            <td>

                                <?php

                                if (
                                    !empty(
                                        $record[
                                            'incident_date'
                                        ]
                                    )
                                ) {

                                    echo e(
                                        date(
                                            'M d, Y',
                                            strtotime(
                                                $record[
                                                    'incident_date'
                                                ]
                                            )
                                        )
                                    );

                                } else {

                                    echo '—';

                                }

                                ?>

                            </td>


                            <td>

                                <?= e(
                                    $record[
                                        'incident_location'
                                    ] ?: '—'
                                ) ?>

                            </td>


                            <td>

                                <span
                                    class="
                                        badge
                                        <?= e(
                                            $badgeClass
                                        ) ?>
                                    "
                                >

                                    <?= e(
                                        $recordStatus
                                    ) ?>

                                </span>

                            </td>


                            <td>

                                <div class="actions">


                                    <a
                                        href="
                                            blotter.php?view=
                                            <?= (int)$record['id'] ?>
                                        "
                                        class="btn btn-view"
                                    >

                                        👁 View

                                    </a>


                                    <a
                                        href="
                                            blotter.php?edit=
                                            <?= (int)$record['id'] ?>
                                        "
                                        class="btn btn-edit"
                                    >

                                        ✏ Edit

                                    </a>


                                    <form
                                        method="POST"
                                        style="display:inline;"
                                        onsubmit="
                                            return confirm(
                                                'Are you sure you want to delete this blotter record?'
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
                                            name="id"
                                            value="<?= (int)$record['id'] ?>"
                                        >

                                        <button
                                            type="submit"
                                            class="btn btn-delete"
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
     ADD MODAL
========================================================= -->

<div
    id="addModal"
    class="modal"
    style="display:none;"
>

    <div class="modal-box">


        <div class="modal-header">

            <h2>
                ➕ Add Blotter Record
            </h2>

            <button
                type="button"
                class="close"
                onclick="closeAddModal()"
            >

                ×

            </button>

        </div>


        <form
            method="POST"
        >

            <input
                type="hidden"
                name="action"
                value="add"
            >


            <div class="form-grid">


                <div class="form-group">

                    <label>
                        Complainant Name *
                    </label>

                    <input
                        type="text"
                        name="complainant_name"
                        required
                        maxlength="150"
                        placeholder="Enter complainant name"
                    >

                </div>


                <div class="form-group">

                    <label>
                        Respondent Name *
                    </label>

                    <input
                        type="text"
                        name="respondent_name"
                        required
                        maxlength="150"
                        placeholder="Enter respondent name"
                    >

                </div>


                <div class="form-group">

                    <label>
                        Incident Type *
                    </label>

                    <input
                        type="text"
                        name="incident_type"
                        required
                        maxlength="150"
                        placeholder="Example: Dispute, Theft, Harassment"
                    >

                </div>


                <div class="form-group">

                    <label>
                        Incident Date
                    </label>

                    <input
                        type="date"
                        name="incident_date"
                    >

                </div>


                <div class="form-group full">

                    <label>
                        Incident Location
                    </label>

                    <input
                        type="text"
                        name="incident_location"
                        maxlength="255"
                        placeholder="Enter incident location"
                    >

                </div>


                <div class="form-group full">

                    <label>
                        Description
                    </label>

                    <textarea
                        name="description"
                        placeholder="Enter complete incident description..."
                    ></textarea>

                </div>


                <div class="form-group">

                    <label>
                        Status
                    </label>

                    <select name="status">

                        <option value="Pending">
                            Pending
                        </option>

                        <option value="Under Review">
                            Under Review
                        </option>

                        <option value="Resolved">
                            Resolved
                        </option>

                        <option value="Referred">
                            Referred
                        </option>

                        <option value="Closed">
                            Closed
                        </option>

                    </select>

                </div>


            </div>


            <div class="form-actions">

                <button
                    type="button"
                    class="btn btn-secondary"
                    onclick="closeAddModal()"
                >

                    Cancel

                </button>


                <button
                    type="submit"
                    class="btn btn-primary"
                >

                    💾 Save Record

                </button>

            </div>


        </form>

    </div>

</div>


<!-- =========================================================
     EDIT MODAL
========================================================= -->

<?php if ($editRecord): ?>

<div
    class="modal"
    id="editModal"
>

    <div class="modal-box">


        <div class="modal-header">

            <h2>
                ✏ Edit Blotter Record #<?= e(
                    $editRecord['id']
                ) ?>
            </h2>


            <a
                href="blotter.php"
                class="close"
                style="
                    text-decoration:none;
                "
            >

                ×

            </a>

        </div>


        <form
            method="POST"
        >

            <input
                type="hidden"
                name="action"
                value="edit"
            >

            <input
                type="hidden"
                name="id"
                value="<?= (int)$editRecord['id'] ?>"
            >


            <div class="form-grid">


                <div class="form-group">

                    <label>
                        Complainant Name *
                    </label>

                    <input
                        type="text"
                        name="complainant_name"
                        required
                        maxlength="150"
                        value="<?= e(
                            $editRecord[
                                'complainant_name'
                            ]
                        ) ?>"
                    >

                </div>


                <div class="form-group">

                    <label>
                        Respondent Name *
                    </label>

                    <input
                        type="text"
                        name="respondent_name"
                        required
                        maxlength="150"
                        value="<?= e(
                            $editRecord[
                                'respondent_name'
                            ]
                        ) ?>"
                    >

                </div>


                <div class="form-group">

                    <label>
                        Incident Type *
                    </label>

                    <input
                        type="text"
                        name="incident_type"
                        required
                        maxlength="150"
                        value="<?= e(
                            $editRecord[
                                'incident_type'
                            ]
                        ) ?>"
                    >

                </div>


                <div class="form-group">

                    <label>
                        Incident Date
                    </label>

                    <input
                        type="date"
                        name="incident_date"
                        value="<?= e(
                            $editRecord[
                                'incident_date'
                            ]
                        ) ?>"
                    >

                </div>


                <div class="form-group full">

                    <label>
                        Incident Location
                    </label>

                    <input
                        type="text"
                        name="incident_location"
                        maxlength="255"
                        value="<?= e(
                            $editRecord[
                                'incident_location'
                            ]
                        ) ?>"
                    >

                </div>


                <div class="form-group full">

                    <label>
                        Description
                    </label>

                    <textarea
                        name="description"
                    ><?= e(
                        $editRecord[
                            'description'
                        ]
                    ) ?></textarea>

                </div>


                <div class="form-group">

                    <label>
                        Status
                    </label>

                    <select name="status">

                        <?php

                        $statuses = [
                            'Pending',
                            'Under Review',
                            'Resolved',
                            'Referred',
                            'Closed'
                        ];

                        foreach (
                            $statuses as $status
                        ):

                        ?>

                            <option
                                value="<?= e($status) ?>"
                                <?= (
                                    $editRecord[
                                        'status'
                                    ] === $status
                                )
                                ? 'selected'
                                : ''
                                ?>
                            >

                                <?= e($status) ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


            </div>


            <div class="form-actions">

                <a
                    href="blotter.php"
                    class="btn btn-secondary"
                >

                    Cancel

                </a>


                <button
                    type="submit"
                    class="btn btn-primary"
                >

                    💾 Update Record

                </button>

            </div>


        </form>

    </div>

</div>

<?php endif; ?>


<!-- =========================================================
     VIEW MODAL
========================================================= -->

<?php if ($viewRecord): ?>

<div
    class="modal"
    id="viewModal"
>

    <div class="modal-box">


        <div class="modal-header">

            <h2>
                👁 Blotter Details
            </h2>


            <a
                href="blotter.php"
                class="close"
                style="
                    text-decoration:none;
                "
            >

                ×

            </a>

        </div>


        <div class="detail-grid">


            <div class="detail">

                <div class="detail-label">
                    Record ID
                </div>

                <div class="detail-value">

                    #<?= e(
                        $viewRecord['id']
                    ) ?>

                </div>

            </div>


            <div class="detail">

                <div class="detail-label">
                    Status
                </div>

                <div class="detail-value">

                    <?= e(
                        $viewRecord['status']
                    ) ?>

                </div>

            </div>


            <div class="detail">

                <div class="detail-label">
                    Complainant
                </div>

                <div class="detail-value">

                    <?= e(
                        $viewRecord[
                            'complainant_name'
                        ]
                    ) ?>

                </div>

            </div>


            <div class="detail">

                <div class="detail-label">
                    Respondent
                </div>

                <div class="detail-value">

                    <?= e(
                        $viewRecord[
                            'respondent_name'
                        ]
                    ) ?>

                </div>

            </div>


            <div class="detail">

                <div class="detail-label">
                    Incident Type
                </div>

                <div class="detail-value">

                    <?= e(
                        $viewRecord[
                            'incident_type'
                        ]
                    ) ?>

                </div>

            </div>


            <div class="detail">

                <div class="detail-label">
                    Incident Date
                </div>

                <div class="detail-value">

                    <?php

                    if (
                        !empty(
                            $viewRecord[
                                'incident_date'
                            ]
                        )
                    ) {

                        echo e(
                            date(
                                'F d, Y',
                                strtotime(
                                    $viewRecord[
                                        'incident_date'
                                    ]
                                )
                            )
                        );

                    } else {

                        echo 'Not specified';

                    }

                    ?>

                </div>

            </div>


            <div class="detail full">

                <div class="detail-label">
                    Incident Location
                </div>

                <div class="detail-value">

                    <?= e(
                        $viewRecord[
                            'incident_location'
                        ] ?: 'Not specified'
                    ) ?>

                </div>

            </div>


            <div class="detail full">

                <div class="detail-label">
                    Description
                </div>

                <div class="detail-value">

                    <?= nl2br(
                        e(
                            $viewRecord[
                                'description'
                            ] ?: 'No description provided.'
                        )
                    ) ?>

                </div>

            </div>


            <div class="detail">

                <div class="detail-label">
                    Created At
                </div>

                <div class="detail-value">

                    <?php

                    if (
                        !empty(
                            $viewRecord[
                                'created_at'
                            ]
                        )
                    ) {

                        echo e(
                            date(
                                'F d, Y h:i A',
                                strtotime(
                                    $viewRecord[
                                        'created_at'
                                    ]
                                )
                            )
                        );

                    } else {

                        echo '—';

                    }

                    ?>

                </div>

            </div>


        </div>


        <div class="form-actions">

            <a
                href="blotter.php"
                class="btn btn-secondary"
            >

                Close

            </a>


            <a
                href="
                    blotter.php?edit=
                    <?= (int)$viewRecord['id'] ?>
                "
                class="btn btn-edit"
            >

                ✏ Edit

            </a>

        </div>


    </div>

</div>

<?php endif; ?>


<script>

/*
|--------------------------------------------------------------------------
| ADD MODAL
|--------------------------------------------------------------------------
*/

function openAddModal()
{
    document.getElementById(
        'addModal'
    ).style.display = 'flex';
}


function closeAddModal()
{
    document.getElementById(
        'addModal'
    ).style.display = 'none';
}


/*
|--------------------------------------------------------------------------
| CLOSE MODAL WHEN CLICKING OUTSIDE
|--------------------------------------------------------------------------
*/

const addModal =
    document.getElementById(
        'addModal'
    );

if (addModal) {

    addModal.addEventListener(
        'click',
        function(event) {

            if (
                event.target === addModal
            ) {

                closeAddModal();

            }

        }
    );
}


/*
|--------------------------------------------------------------------------
| ESCAPE KEY
|--------------------------------------------------------------------------
*/

document.addEventListener(
    'keydown',
    function(event) {

        if (
            event.key === 'Escape'
        ) {

            const add =
                document.getElementById(
                    'addModal'
                );

            if (add) {
                add.style.display =
                    'none';
            }

        }

    }
);

</script>


</body>
</html>