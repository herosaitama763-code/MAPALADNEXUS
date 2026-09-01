<?php
session_start();

require_once __DIR__ . '/../config/database.php';

/* =========================
   SECURITY
========================= */

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

if (!isset($conn) || !($conn instanceof mysqli)) {
    die("Database connection error.");
}

/* =========================
   HELPERS
========================= */

function h($value)
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}

function tableExists($conn, $table)
{
    $table = $conn->real_escape_string($table);

    $result = $conn->query(
        "SHOW TABLES LIKE '$table'"
    );

    return $result && $result->num_rows > 0;
}

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

function findColumn($columns, $possible)
{
    foreach ($possible as $column) {
        if (in_array($column, $columns, true)) {
            return $column;
        }
    }

    return null;
}

/* =========================
   GET SERVICE ID
========================= */

$serviceId = (int)($_GET['service_id'] ?? $_POST['service_id'] ?? 0);

if ($serviceId <= 0) {
    header("Location: services.php");
    exit;
}

/* =========================
   SERVICES TABLE
========================= */

if (!tableExists($conn, 'services')) {
    die("The services table does not exist.");
}

$serviceColumns = getColumns(
    $conn,
    'services'
);

$serviceIdColumn = findColumn(
    $serviceColumns,
    [
        'id',
        'service_id'
    ]
);

$serviceNameColumn = findColumn(
    $serviceColumns,
    [
        'service_name',
        'name',
        'title'
    ]
);

$descriptionColumn = findColumn(
    $serviceColumns,
    [
        'description',
        'details'
    ]
);

$feeColumn = findColumn(
    $serviceColumns,
    [
        'fee',
        'price',
        'amount'
    ]
);

$statusColumn = findColumn(
    $serviceColumns,
    [
        'status'
    ]
);

if (!$serviceIdColumn || !$serviceNameColumn) {
    die("Service table structure is incomplete.");
}

/* =========================
   GET SERVICE
========================= */

$sql = "
    SELECT *
    FROM services
    WHERE `$serviceIdColumn` = ?
    LIMIT 1
";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("Database error: " . $conn->error);
}

$stmt->bind_param(
    "i",
    $serviceId
);

$stmt->execute();

$result = $stmt->get_result();

$service = $result->fetch_assoc();

$stmt->close();

if (!$service) {
    die("Service not found.");
}

/* =========================
   SERVICE STATUS
========================= */

if ($statusColumn) {

    $serviceStatus =
        strtolower(
            trim(
                $service[$statusColumn] ?? ''
            )
        );

    if (
        $serviceStatus !== '' &&
        $serviceStatus !== 'active'
    ) {
        die("This service is currently unavailable.");
    }
}

/* =========================
   SERVICE DETAILS
========================= */

$serviceName =
    $service[$serviceNameColumn] ?? 'Barangay Service';

$serviceDescription =
    $descriptionColumn
    ? ($service[$descriptionColumn] ?? '')
    : '';

$serviceFee =
    $feeColumn
    ? (float)($service[$feeColumn] ?? 0)
    : 0;

/* =========================
   REQUEST TABLE
========================= */

if (!tableExists($conn, 'service_requests')) {
    die("The service_requests table does not exist.");
}

$requestColumns = getColumns(
    $conn,
    'service_requests'
);

/* =========================
   REQUEST COLUMNS
========================= */

$requestIdColumn = findColumn(
    $requestColumns,
    [
        'id',
        'request_id',
        'service_request_id'
    ]
);

$requestServiceColumn = findColumn(
    $requestColumns,
    [
        'service_id'
    ]
);

$requestResidentColumn = findColumn(
    $requestColumns,
    [
        'resident_id',
        'user_id'
    ]
);

$requestPurposeColumn = findColumn(
    $requestColumns,
    [
        'purpose',
        'reason',
        'request_purpose'
    ]
);

$requestStatusColumn = findColumn(
    $requestColumns,
    [
        'status',
        'request_status'
    ]
);

$requestDateColumn = findColumn(
    $requestColumns,
    [
        'request_date',
        'date_requested',
        'requested_at',
        'created_at',
        'date_created'
    ]
);

/*
|--------------------------------------------------------------------------
| IMPORTANT
|--------------------------------------------------------------------------
| We intentionally do NOT use:
| notes
|
| para maiwasan ang Unknown column 'notes'
|--------------------------------------------------------------------------
*/

/* =========================
   VALIDATION
========================= */

$message = '';
$error = '';

$purpose = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $purpose = trim(
        $_POST['purpose'] ?? ''
    );

    if (!$requestServiceColumn) {

        $error =
            "The service_id column is missing from service_requests.";

    } elseif (!$requestResidentColumn) {

        $error =
            "The resident_id column is missing from service_requests.";

    } elseif (!$requestStatusColumn) {

        $error =
            "The status column is missing from service_requests.";

    } elseif ($purpose === '') {

        $error =
            "Please enter the purpose of your request.";

    } elseif (strlen($purpose) < 5) {

        $error =
            "Please provide a more detailed purpose.";

    } else {

        /* =========================
           RESIDENT ID
        ========================= */

        $residentId = (int)(
            $_SESSION['resident_id']
            ?? $_SESSION['user_id']
            ?? 0
        );

        if ($residentId <= 0) {

            $error =
                "Resident account could not be identified.";

        } else {

            /*
            |--------------------------------------------------------------------------
            | INSERT
            |--------------------------------------------------------------------------
            */

            $insertColumns = [];

            $insertValues = [];

            $types = '';

            /*
            | service_id
            */

            $insertColumns[] =
                $requestServiceColumn;

            $insertValues[] =
                $serviceId;

            $types .= 'i';


            /*
            | resident_id
            */

            $insertColumns[] =
                $requestResidentColumn;

            $insertValues[] =
                $residentId;

            $types .= 'i';


            /*
            | purpose
            */

            if ($requestPurposeColumn) {

                $insertColumns[] =
                    $requestPurposeColumn;

                $insertValues[] =
                    $purpose;

                $types .= 's';
            }


            /*
            | status
            */

            $insertColumns[] =
                $requestStatusColumn;

            $insertValues[] =
                'Pending';

            $types .= 's';


            /*
            | date
            */

            if ($requestDateColumn) {

                $insertColumns[] =
                    $requestDateColumn;

                $insertValues[] =
                    date('Y-m-d H:i:s');

                $types .= 's';
            }


            /*
            | BUILD QUERY
            */

            $columnList = '';

            foreach ($insertColumns as $index => $column) {

                if ($index > 0) {
                    $columnList .= ', ';
                }

                $columnList .=
                    "`$column`";
            }


            $placeholders =
                implode(
                    ', ',
                    array_fill(
                        0,
                        count($insertValues),
                        '?'
                    )
                );


            $sql = "
                INSERT INTO service_requests
                (
                    $columnList
                )
                VALUES
                (
                    $placeholders
                )
            ";


            $stmt =
                $conn->prepare($sql);


            if (!$stmt) {

                $error =
                    "Unable to prepare request: " .
                    $conn->error;

            } else {

                $bindParams = [];

                $bindParams[] =
                    $types;

                foreach (
                    $insertValues
                    as $key => $value
                ) {

                    $bindParams[] =
                        &$insertValues[$key];
                }

                call_user_func_array(
                    [$stmt, 'bind_param'],
                    $bindParams
                );


                if ($stmt->execute()) {

                    $stmt->close();

                    /*
                    | Redirect para maiwasan
                    | duplicate submit kapag refresh.
                    */

                    header(
                        "Location: requests.php?submitted=1"
                    );

                    exit;

                } else {

                    $error =
                        "Unable to submit request: " .
                        $stmt->error;

                    $stmt->close();
                }
            }
        }
    }
}

/* =========================
   SUCCESS MESSAGE
========================= */

if (
    isset($_GET['submitted']) &&
    $_GET['submitted'] == '1'
) {

    $message =
        "Your service request was submitted successfully.";
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
MAPALADNEXUS | Request Service
</title>

<style>

* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}

:root {

    --bg: #050816;

    --panel: rgba(255,255,255,.055);

    --border: rgba(255,255,255,.09);

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
            rgba(37,99,235,.22),
            transparent 30%
        ),

        radial-gradient(
            circle at 90% 90%,
            rgba(124,58,237,.20),
            transparent 30%
        ),

        var(--bg);
}

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
}

/* SIDEBAR */

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
        1px solid var(--border);

    border-radius: 26px;

    backdrop-filter: blur(25px);

    box-shadow:
        20px 30px 70px
        rgba(0,0,0,.40);

    display: flex;

    flex-direction: column;

    z-index: 10;
}

.brand {

    display: flex;

    align-items: center;

    gap: 12px;

    padding:
        4px 8px 20px;

    border-bottom:
        1px solid var(--border);
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
}

.nav-title {

    margin:
        20px 10px 10px;

    color: #64748b;

    font-size: 8px;

    text-transform: uppercase;
}

.nav {

    flex: 1;
}

.nav a {

    display: flex;

    align-items: center;

    gap: 12px;

    padding: 12px;

    margin-bottom: 6px;

    color: var(--muted);

    text-decoration: none;

    border-radius: 13px;

    font-size: 10px;

    transition: .25s;
}

.nav a:hover {

    color: white;

    background:
        rgba(255,255,255,.06);
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

/* MAIN */

.main {

    margin-left: 295px;

    min-height: 100vh;

    padding: 35px;
}

.header {

    margin-bottom: 25px;
}

.header h1 {

    font-size: 29px;
}

.header p {

    margin-top: 7px;

    color: var(--muted);

    font-size: 10px;
}

/* CARD */

.request-wrapper {

    max-width: 900px;

    margin: auto;

    display: grid;

    grid-template-columns:
        1fr 1.4fr;

    gap: 20px;
}

.service-card,
.form-card {

    background:
        var(--panel);

    border:
        1px solid var(--border);

    border-radius: 25px;

    backdrop-filter: blur(22px);

    box-shadow:
        15px 25px 60px
        rgba(0,0,0,.25);
}

/* SERVICE */

.service-card {

    padding: 28px;
}

.service-icon {

    width: 65px;

    height: 65px;

    display: flex;

    align-items: center;

    justify-content: center;

    margin-bottom: 20px;

    border-radius: 20px;

    background:
        linear-gradient(
            145deg,
            rgba(37,99,235,.28),
            rgba(124,58,237,.25)
        );

    font-size: 28px;
}

.service-card h2 {

    font-size: 20px;

    margin-bottom: 12px;
}

.service-card p {

    color: var(--muted);

    font-size: 10px;

    line-height: 1.7;
}

.fee {

    display: inline-block;

    margin-top: 20px;

    padding: 9px 12px;

    color: #6ee7b7;

    background:
        rgba(16,185,129,.10);

    border:
        1px solid
        rgba(16,185,129,.15);

    border-radius: 10px;

    font-size: 9px;
}

/* FORM */

.form-card {

    padding: 28px;
}

.form-card h2 {

    font-size: 18px;

    margin-bottom: 7px;
}

.form-card .sub {

    color: var(--muted);

    font-size: 9px;

    margin-bottom: 25px;
}

label {

    display: block;

    margin-bottom: 8px;

    color: #cbd5e1;

    font-size: 9px;

    font-weight: bold;
}

textarea {

    width: 100%;

    min-height: 180px;

    resize: vertical;

    padding: 14px;

    color: white;

    background:
        rgba(0,0,0,.20);

    border:
        1px solid var(--border);

    border-radius: 13px;

    outline: none;

    font-family: inherit;

    font-size: 10px;

    line-height: 1.6;
}

textarea:focus {

    border-color:
        rgba(96,165,250,.50);

    box-shadow:
        0 0 0 3px
        rgba(37,99,235,.08);
}

.help {

    margin-top: 8px;

    color: #64748b;

    font-size: 8px;
}

/* BUTTONS */

.buttons {

    display: flex;

    gap: 10px;

    margin-top: 20px;
}

button,
.back-btn {

    flex: 1;

    padding: 13px;

    border-radius: 12px;

    font-size: 9px;

    font-weight: bold;

    text-align: center;

    text-decoration: none;

    cursor: pointer;
}

.submit {

    border: none;

    color: white;

    background:
        linear-gradient(
            145deg,
            #2563eb,
            #4f46e5
        );
}

.submit:hover {

    transform:
        translateY(-2px);

    box-shadow:
        0 12px 30px
        rgba(37,99,235,.25);
}

.back-btn {

    color: #cbd5e1;

    background:
        rgba(255,255,255,.04);

    border:
        1px solid var(--border);
}

/* ALERT */

.alert {

    max-width: 900px;

    margin:
        0 auto 20px;

    padding: 14px 17px;

    border-radius: 13px;

    font-size: 9px;
}

.success {

    color: #a7f3d0;

    background:
        rgba(16,185,129,.10);

    border:
        1px solid
        rgba(16,185,129,.16);
}

.error {

    color: #fecaca;

    background:
        rgba(239,68,68,.10);

    border:
        1px solid
        rgba(239,68,68,.16);
}

/* MOBILE */

@media(max-width:850px) {

    .sidebar {
        display: none;
    }

    .main {

        margin-left: 0;

        padding: 20px;
    }

    .request-wrapper {

        grid-template-columns: 1fr;
    }
}

@media(max-width:550px) {

    .buttons {

        flex-direction: column;
    }
}

</style>

</head>

<body>

<div class="background"></div>


<!-- SIDEBAR -->

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
        Resident Portal
    </div>


    <nav class="nav">

        <a href="user_dashboard.php">

            <span class="icon">
                🏠
            </span>

            Dashboard

        </a>


        <a href="services.php"
           class="active">

            <span class="icon">
                🛠️
            </span>

            Services

        </a>


        <a href="requests.php">

            <span class="icon">
                📋
            </span>

            My Requests

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


<!-- MAIN -->

<main class="main">


    <header class="header">

        <h1>
            Request Barangay Service
        </h1>

        <p>
            Complete the form below to submit your request.
        </p>

    </header>


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


    <div class="request-wrapper">


        <!-- SERVICE INFORMATION -->

        <section class="service-card">

            <div class="service-icon">
                🏛️
            </div>


            <h2>
                <?= h($serviceName) ?>
            </h2>


            <p>

                <?= $serviceDescription !== ''
                    ? h($serviceDescription)
                    : 'Official Barangay Mapalad service.'
                ?>

            </p>


            <div class="fee">

                <?php if ($serviceFee > 0): ?>

                    💰 Fee:
                    ₱<?= number_format(
                        $serviceFee,
                        2
                    ) ?>

                <?php else: ?>

                    🎁 Free Service

                <?php endif; ?>

            </div>

        </section>


        <!-- REQUEST FORM -->

        <section class="form-card">

            <h2>
                Request Details
            </h2>

            <p class="sub">
                Tell the barangay why you need this service.
            </p>


            <form
                method="POST"
                action=""
            >

                <input
                    type="hidden"
                    name="service_id"
                    value="<?= $serviceId ?>"
                >


                <label for="purpose">

                    Purpose / Reason

                </label>


                <textarea
                    id="purpose"
                    name="purpose"
                    placeholder="Example: I am requesting this certificate for employment purposes..."
                    required
                ><?= h($purpose) ?></textarea>


                <div class="help">

                    Please provide enough information
                    so the barangay can properly process
                    your request.

                </div>


                <div class="buttons">

                    <a
                        href="services.php"
                        class="back-btn"
                    >
                        ← Back to Services
                    </a>


                    <button
                        type="submit"
                        class="submit"
                        onclick="
                            return confirm(
                                'Submit this service request?'
                            );
                        "
                    >

                        📤 Submit Request

                    </button>

                </div>


            </form>

        </section>


    </div>


</main>

</body>

</html>