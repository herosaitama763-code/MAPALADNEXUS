<?php
session_start();

error_reporting(E_ALL);
ini_set('display_errors', 1);

/*
|--------------------------------------------------------------------------
| DATABASE
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/../config/database.php';

if (!isset($conn) || !($conn instanceof mysqli)) {
    die("Database connection error.");
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
| HELPER
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
| TABLE CHECK
|--------------------------------------------------------------------------
*/

$tableCheck = $conn->query(
    "SHOW TABLES LIKE 'announcements'"
);

if (
    !$tableCheck ||
    $tableCheck->num_rows === 0
) {

    die("
        <div style='
            font-family:Arial;
            padding:40px;
            background:#050816;
            color:white;
            min-height:100vh;
        '>

            <h2>
                Announcements table not found
            </h2>

            <p>
                Hindi makita ang
                <b>announcements</b>
                table sa MAPALADNEXUS database.
            </p>

        </div>
    ");
}


/*
|--------------------------------------------------------------------------
| GET TABLE COLUMNS
|--------------------------------------------------------------------------
*/

$columns = [];

$columnResult = $conn->query(
    "SHOW COLUMNS FROM announcements"
);

if ($columnResult) {

    while ($column = $columnResult->fetch_assoc()) {
        $columns[] = $column['Field'];
    }

    $columnResult->free();
}


/*
|--------------------------------------------------------------------------
| DETECT COLUMNS
|--------------------------------------------------------------------------
*/

$idColumn = null;

foreach (
    ['id', 'announcement_id']
    as $possible
) {

    if (
        in_array(
            $possible,
            $columns,
            true
        )
    ) {

        $idColumn = $possible;
        break;
    }
}


$titleColumn = null;

foreach (
    ['title', 'announcement_title']
    as $possible
) {

    if (
        in_array(
            $possible,
            $columns,
            true
        )
    ) {

        $titleColumn = $possible;
        break;
    }
}


$descriptionColumn = null;

foreach (
    [
        'description',
        'message',
        'content',
        'details'
    ]
    as $possible
) {

    if (
        in_array(
            $possible,
            $columns,
            true
        )
    ) {

        $descriptionColumn = $possible;
        break;
    }
}


$purokColumn = null;

foreach (
    ['purok', 'target_purok', 'area']
    as $possible
) {

    if (
        in_array(
            $possible,
            $columns,
            true
        )
    ) {

        $purokColumn = $possible;
        break;
    }
}


$createdByColumn = null;

foreach (
    ['created_by', 'posted_by', 'author']
    as $possible
) {

    if (
        in_array(
            $possible,
            $columns,
            true
        )
    ) {

        $createdByColumn = $possible;
        break;
    }
}


$dateColumn = null;

foreach (
    [
        'created_at',
        'date_posted',
        'date'
    ]
    as $possible
) {

    if (
        in_array(
            $possible,
            $columns,
            true
        )
    ) {

        $dateColumn = $possible;
        break;
    }
}


$statusColumn = null;

foreach (
    ['status', 'announcement_status']
    as $possible
) {

    if (
        in_array(
            $possible,
            $columns,
            true
        )
    ) {

        $statusColumn = $possible;
        break;
    }
}


/*
|--------------------------------------------------------------------------
| VALIDATION
|--------------------------------------------------------------------------
*/

if (!$idColumn) {
    die("Announcement ID column not found.");
}

if (!$titleColumn) {
    die("Announcement title column not found.");
}

if (!$descriptionColumn) {
    die("Announcement description/content column not found.");
}


/*
|--------------------------------------------------------------------------
| VARIABLES
|--------------------------------------------------------------------------
*/

$success = '';
$error = '';

$title = '';
$description = '';
$purok = 'All';
$status = 'Active';


/*
|--------------------------------------------------------------------------
| DELETE ANNOUNCEMENT
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    ($_POST['action'] ?? '') === 'delete'
) {

    $announcementId =
        (int)($_POST['announcement_id'] ?? 0);


    if ($announcementId <= 0) {

        $error =
            "Invalid announcement ID.";

    } else {

        $stmt = $conn->prepare(
            "DELETE FROM announcements
             WHERE `$idColumn` = ?"
        );


        if (!$stmt) {

            $error =
                "Database error: " .
                $conn->error;

        } else {

            $stmt->bind_param(
                "i",
                $announcementId
            );


            if ($stmt->execute()) {

                $success =
                    "Announcement deleted successfully.";

            } else {

                $error =
                    "Unable to delete announcement: " .
                    $stmt->error;
            }


            $stmt->close();
        }
    }
}


/*
|--------------------------------------------------------------------------
| EDIT ANNOUNCEMENT
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    ($_POST['action'] ?? '') === 'edit'
) {

    $announcementId =
        (int)($_POST['announcement_id'] ?? 0);

    $title =
        trim($_POST['title'] ?? '');

    $description =
        trim($_POST['description'] ?? '');

    $purok =
        trim($_POST['purok'] ?? 'All');

    $status =
        trim($_POST['status'] ?? 'Active');


    if ($announcementId <= 0) {

        $error =
            "Invalid announcement ID.";

    } elseif ($title === '') {

        $error =
            "Please enter an announcement title.";

    } elseif ($description === '') {

        $error =
            "Please enter announcement details.";

    } else {


        /*
        |--------------------------------------------------------------
        | BUILD UPDATE
        |--------------------------------------------------------------
        */

        $setParts = [];

        $types = '';

        $values = [];


        /*
        | TITLE
        */

        $setParts[] =
            "`$titleColumn` = ?";

        $types .= 's';

        $values[] =
            $title;


        /*
        | DESCRIPTION
        */

        $setParts[] =
            "`$descriptionColumn` = ?";

        $types .= 's';

        $values[] =
            $description;


        /*
        | PUROK
        */

        if ($purokColumn) {

            $setParts[] =
                "`$purokColumn` = ?";

            $types .= 's';

            $values[] =
                $purok;
        }


        /*
        | STATUS
        */

        if ($statusColumn) {

            $setParts[] =
                "`$statusColumn` = ?";

            $types .= 's';

            $values[] =
                $status;
        }


        $types .= 'i';

        $values[] =
            $announcementId;


        $sql = "
            UPDATE announcements
            SET
                " .
                implode(
                    ', ',
                    $setParts
                ) .
            "
            WHERE `$idColumn` = ?
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
                    "Announcement updated successfully.";

                $title = '';
                $description = '';
                $purok = 'All';
                $status = 'Active';

            } else {

                $error =
                    "Unable to update announcement: " .
                    $stmt->error;
            }


            $stmt->close();
        }
    }
}


/*
|--------------------------------------------------------------------------
| CREATE ANNOUNCEMENT
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    ($_POST['action'] ?? '') === 'create'
) {

    $title =
        trim($_POST['title'] ?? '');

    $description =
        trim($_POST['description'] ?? '');

    $purok =
        trim($_POST['purok'] ?? 'All');

    $status =
        trim($_POST['status'] ?? 'Active');


    /*
    | VALIDATION
    */

    if ($title === '') {

        $error =
            "Please enter an announcement title.";

    } elseif ($description === '') {

        $error =
            "Please enter announcement details.";

    } else {


        /*
        |--------------------------------------------------------------------------
        | BUILD INSERT DYNAMICALLY
        |--------------------------------------------------------------------------
        */

        $insertColumns = [];

        $placeholders = [];

        $types = '';

        $values = [];


        /*
        | TITLE
        */

        $insertColumns[] =
            $titleColumn;

        $placeholders[] =
            '?';

        $types .= 's';

        $values[] =
            $title;


        /*
        | DESCRIPTION
        */

        $insertColumns[] =
            $descriptionColumn;

        $placeholders[] =
            '?';

        $types .= 's';

        $values[] =
            $description;


        /*
        | PUROK
        */

        if ($purokColumn) {

            $insertColumns[] =
                $purokColumn;

            $placeholders[] =
                '?';

            $types .= 's';

            $values[] =
                $purok;
        }


        /*
        | CREATED BY
        */

        if ($createdByColumn) {

            $insertColumns[] =
                $createdByColumn;

            $placeholders[] =
                '?';

            $types .= 's';

            $values[] =
                $_SESSION['username'];
        }


        /*
        | STATUS
        */

        if ($statusColumn) {

            $insertColumns[] =
                $statusColumn;

            $placeholders[] =
                '?';

            $types .= 's';

            $values[] =
                $status;
        }


        /*
        | CREATED AT
        */

        if ($dateColumn) {

            $insertColumns[] =
                $dateColumn;

            $placeholders[] =
                'NOW()';
        }


        /*
        | QUERY
        */

        $sql = "
            INSERT INTO announcements
            (
                `" .
                implode(
                    '`, `',
                    $insertColumns
                ) .
                "`
            )
            VALUES
            (
                " .
                implode(
                    ', ',
                    $placeholders
                ) .
                "
            )
        ";


        $stmt =
            $conn->prepare($sql);


        if (!$stmt) {

            $error =
                "Unable to prepare announcement: " .
                $conn->error;

        } else {


            if (!empty($values)) {

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
            }


            if ($stmt->execute()) {

                $success =
                    "Announcement successfully published.";

                $title = '';
                $description = '';
                $purok = 'All';
                $status = 'Active';

            } else {

                $error =
                    "Unable to publish announcement: " .
                    $stmt->error;
            }


            $stmt->close();
        }
    }
}


/*
|--------------------------------------------------------------------------
| LOAD ANNOUNCEMENTS
|--------------------------------------------------------------------------
*/

$announcements = [];


$selectParts = [];


$selectParts[] =
    "`$idColumn` AS announcement_id";


$selectParts[] =
    "`$titleColumn` AS title";


$selectParts[] =
    "`$descriptionColumn` AS description";


if ($purokColumn) {

    $selectParts[] =
        "`$purokColumn` AS purok";

} else {

    $selectParts[] =
        "'All' AS purok";
}


if ($createdByColumn) {

    $selectParts[] =
        "`$createdByColumn` AS created_by";

} else {

    $selectParts[] =
        "'' AS created_by";
}


if ($dateColumn) {

    $selectParts[] =
        "`$dateColumn` AS created_at";

} else {

    $selectParts[] =
        "NULL AS created_at";
}


if ($statusColumn) {

    $selectParts[] =
        "`$statusColumn` AS status";

} else {

    $selectParts[] =
        "'Active' AS status";
}


$sql = "
    SELECT
        " .
        implode(
            ', ',
            $selectParts
        ) .
    "
    FROM announcements
    ORDER BY
        " .
        (
            $dateColumn
            ? "`$dateColumn` DESC"
            : "`$idColumn` DESC"
        ) .
    "
";


$result =
    $conn->query($sql);


if ($result) {

    while (
        $row =
        $result->fetch_assoc()
    ) {

        $announcements[] =
            $row;
    }

    $result->free();
}


/*
|--------------------------------------------------------------------------
| STATISTICS
|--------------------------------------------------------------------------
*/

$totalAnnouncements =
    count($announcements);

$activeAnnouncements = 0;

$inactiveAnnouncements = 0;


foreach (
    $announcements
    as $announcement
) {

    $announcementStatus =
        strtolower(
            trim(
                $announcement['status']
                ?? 'Active'
            )
        );


    if (
        $announcementStatus ===
        'active'
    ) {

        $activeAnnouncements++;

    } else {

        $inactiveAnnouncements++;
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
MAPALADNEXUS | Announcements
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

    --yellow: #f59e0b;

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

    overflow-x: hidden;
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
        1px solid var(--border);

    border-radius: 26px;

    backdrop-filter: blur(25px);

    box-shadow:
        20px 30px 70px
        rgba(0,0,0,.40);

    display: flex;

    flex-direction: column;

    z-index: 100;
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

    box-shadow:
        0 10px 30px
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


/* =========================================================
   MAIN
========================================================= */

.main {

    margin-left: 295px;

    min-height: 100vh;

    padding: 30px;
}

.header {

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 20px;

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

.admin-user {

    padding:
        10px 15px;

    color: #93c5fd;

    background:
        var(--panel);

    border:
        1px solid var(--border);

    border-radius: 14px;

    font-size: 9px;
}


/* =========================================================
   STATS
========================================================= */

.stats {

    display: grid;

    grid-template-columns:
        repeat(3, 1fr);

    gap: 15px;

    margin-bottom: 22px;
}

.stat {

    padding: 19px;

    background:
        var(--panel);

    border:
        1px solid var(--border);

    border-radius: 20px;

    backdrop-filter:
        blur(20px);

    box-shadow:
        10px 20px 45px
        rgba(0,0,0,.22);

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

    font-size: 23px;

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

    padding:
        14px 17px;

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
   GRID
========================================================= */

.content-grid {

    display: grid;

    grid-template-columns:
        380px minmax(0,1fr);

    gap: 20px;

    align-items: start;
}


/* =========================================================
   PANELS
========================================================= */

.panel {

    background:
        var(--panel);

    border:
        1px solid var(--border);

    border-radius: 24px;

    backdrop-filter:
        blur(22px);

    box-shadow:
        15px 25px 60px
        rgba(0,0,0,.25);

    overflow: hidden;
}

.panel-header {

    padding:
        21px 22px;

    border-bottom:
        1px solid var(--border);
}

.panel-header h2 {

    font-size: 15px;
}

.panel-header p {

    margin-top: 6px;

    color: var(--muted);

    font-size: 8px;

    line-height: 1.5;
}


/* =========================================================
   FORM
========================================================= */

.form {

    padding: 22px;
}

.field {

    margin-bottom: 17px;
}

.field label {

    display: block;

    margin-bottom: 7px;

    color: #cbd5e1;

    font-size: 8px;

    font-weight: bold;

    text-transform: uppercase;
}

.field input,
.field textarea,
.field select {

    width: 100%;

    padding:
        11px 12px;

    color: white;

    background:
        rgba(0,0,0,.20);

    border:
        1px solid var(--border);

    border-radius: 11px;

    outline: none;

    font-family: inherit;

    font-size: 9px;
}

.field textarea {

    min-height: 135px;

    resize: vertical;

    line-height: 1.6;
}

.field input:focus,
.field textarea:focus,
.field select:focus {

    border-color:
        rgba(96,165,250,.45);

    box-shadow:
        0 0 0 3px
        rgba(37,99,235,.08);
}

.field select option {

    background: #111827;

    color: white;
}


/* =========================================================
   CREATE BUTTON
========================================================= */

.publish-btn {

    width: 100%;

    padding: 13px;

    border: none;

    border-radius: 12px;

    color: white;

    background:
        linear-gradient(
            145deg,
            #2563eb,
            #4f46e5
        );

    font-size: 9px;

    font-weight: bold;

    cursor: pointer;

    transition: .25s;
}

.publish-btn:hover {

    transform:
        translateY(-2px);

    box-shadow:
        0 12px 30px
        rgba(37,99,235,.25);
}


/* =========================================================
   ANNOUNCEMENT LIST
========================================================= */

.announcement-list {

    padding: 18px;
}

.search {

    width: 100%;

    margin-bottom: 15px;

    padding:
        11px 13px;

    color: white;

    background:
        rgba(0,0,0,.20);

    border:
        1px solid var(--border);

    border-radius: 11px;

    outline: none;

    font-size: 9px;
}

.announcement-card {

    position: relative;

    margin-bottom: 13px;

    padding: 18px;

    background:
        rgba(255,255,255,.035);

    border:
        1px solid
        rgba(255,255,255,.07);

    border-radius: 17px;

    transition: .25s;
}

.announcement-card:hover {

    transform:
        translateY(-3px);

    background:
        rgba(255,255,255,.055);

    border-color:
        rgba(96,165,250,.20);
}

.announcement-card:last-child {

    margin-bottom: 0;
}

.card-top {

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 10px;

    margin-bottom: 11px;
}

.badges {

    display: flex;

    gap: 6px;

    flex-wrap: wrap;
}

.badge {

    display: inline-block;

    padding:
        6px 8px;

    border-radius: 8px;

    font-size: 7px;

    font-weight: bold;
}

.badge.active {

    color: #6ee7b7;

    background:
        rgba(16,185,129,.10);
}

.badge.inactive {

    color: #fca5a5;

    background:
        rgba(239,68,68,.10);
}

.badge.purok {

    color: #93c5fd;

    background:
        rgba(37,99,235,.10);
}

.date {

    color: #64748b;

    font-size: 7px;

    white-space: nowrap;
}

.announcement-title {

    margin-bottom: 8px;

    color: white;

    font-size: 14px;

    font-weight: bold;

    line-height: 1.4;
}

.announcement-description {

    color: var(--muted);

    font-size: 9px;

    line-height: 1.7;

    white-space: pre-line;

    word-break: break-word;
}

.meta {

    display: flex;

    justify-content: space-between;

    gap: 10px;

    margin-top: 14px;

    padding-top: 11px;

    border-top:
        1px solid
        rgba(255,255,255,.05);

    color: #64748b;

    font-size: 7px;
}

.card-actions {

    display: flex;

    gap: 7px;

    margin-top: 13px;
}

.action-btn {

    padding:
        8px 10px;

    border-radius: 8px;

    color: white;

    background:
        rgba(255,255,255,.04);

    border:
        1px solid var(--border);

    font-size: 7px;

    cursor: pointer;
}

.action-btn:hover {

    background:
        rgba(255,255,255,.10);
}

.delete-btn {

    color: #fca5a5;

    background:
        rgba(239,68,68,.07);
}

.delete-btn:hover {

    background:
        rgba(239,68,68,.17);
}


/* =========================================================
   EMPTY
========================================================= */

.empty {

    padding:
        60px 20px;

    text-align: center;

    color: var(--muted);
}

.empty-icon {

    margin-bottom: 12px;

    font-size: 42px;
}

.empty h3 {

    margin-bottom: 6px;

    color: white;

    font-size: 14px;
}

.empty p {

    font-size: 8px;
}


/* =========================================================
   EDIT MODAL
========================================================= */

.modal {

    position: fixed;

    inset: 0;

    display: none;

    align-items: center;

    justify-content: center;

    padding: 20px;

    background:
        rgba(0,0,0,.65);

    backdrop-filter:
        blur(8px);

    z-index: 1000;
}

.modal.show {

    display: flex;
}

.modal-box {

    width: 100%;

    max-width: 520px;

    max-height: 90vh;

    overflow-y: auto;

    background:
        #0b1120;

    border:
        1px solid var(--border);

    border-radius: 22px;

    box-shadow:
        0 30px 100px
        rgba(0,0,0,.60);
}

.modal-header {

    display: flex;

    justify-content: space-between;

    align-items: center;

    padding:
        20px;

    border-bottom:
        1px solid var(--border);
}

.modal-header h2 {

    font-size: 15px;
}

.close {

    width: 30px;

    height: 30px;

    border: none;

    border-radius: 8px;

    color: white;

    background:
        rgba(255,255,255,.06);

    cursor: pointer;
}


/* =========================================================
   RESPONSIVE
========================================================= */

@media(max-width:1100px) {

    .content-grid {

        grid-template-columns: 1fr;
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

    .stats {

        grid-template-columns: 1fr;
    }

    .header {

        flex-direction: column;

        align-items: flex-start;
    }

    .card-top {

        align-items: flex-start;

        flex-direction: column;
    }

    .date {

        white-space: normal;
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


        <a href="requests.php">

            <span class="icon">
                📋
            </span>

            Service Requests

        </a>


        <a
            href="announcements.php"
            class="active"
        >

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
                Announcements
            </h1>

            <p>
                Create and manage official Barangay Mapalad announcements.
            </p>

        </div>


        <div class="admin-user">

            👑

            <?= h(
                $_SESSION['username']
                ?? 'Admin'
            ) ?>

        </div>

    </header>


    <!-- =====================================================
         STATS
    ====================================================== -->

    <section class="stats">


        <div class="stat">

            <div class="stat-icon">
                📢
            </div>

            <div class="stat-number">
                <?= $totalAnnouncements ?>
            </div>

            <div class="stat-label">
                Total Announcements
            </div>

        </div>


        <div class="stat">

            <div class="stat-icon">
                🟢
            </div>

            <div class="stat-number">
                <?= $activeAnnouncements ?>
            </div>

            <div class="stat-label">
                Active
            </div>

        </div>


        <div class="stat">

            <div class="stat-icon">
                🔴
            </div>

            <div class="stat-number">
                <?= $inactiveAnnouncements ?>
            </div>

            <div class="stat-label">
                Inactive
            </div>

        </div>


    </section>


    <!-- =====================================================
         ALERTS
    ====================================================== -->

    <?php if ($success !== ''): ?>

        <div class="alert success">

            ✅

            <?= h($success) ?>

        </div>

    <?php endif; ?>


    <?php if ($error !== ''): ?>

        <div class="alert error">

            ⚠️

            <?= h($error) ?>

        </div>

    <?php endif; ?>


    <!-- =====================================================
         CONTENT
    ====================================================== -->

    <div class="content-grid">


        <!-- =================================================
             CREATE
        ================================================== -->

        <section class="panel">


            <div class="panel-header">

                <h2>
                    📢 Create Announcement
                </h2>

                <p>
                    Mag-post ng official announcement
                    para sa residents ng Barangay Mapalad.
                </p>

            </div>


            <form
                method="POST"
                class="form"
            >

                <input
                    type="hidden"
                    name="action"
                    value="create"
                >


                <!-- TITLE -->

                <div class="field">

                    <label>
                        Announcement Title
                    </label>

                    <input
                        type="text"
                        name="title"
                        value="<?= h($title) ?>"
                        placeholder="Example: Barangay Clean-Up Drive"
                        maxlength="255"
                        required
                    >

                </div>


                <!-- DESCRIPTION -->

                <div class="field">

                    <label>
                        Announcement Details
                    </label>

                    <textarea
                        name="description"
                        placeholder="Ilagay dito ang buong announcement..."
                        required
                    ><?= h($description) ?></textarea>

                </div>


                <!-- PUROK -->

                <?php if ($purokColumn): ?>

                    <div class="field">

                        <label>
                            Target Area
                        </label>

                        <select name="purok">

                            <option value="All">
                                📢 All Purok
                            </option>

                            <option value="Purok 1">
                                Purok 1
                            </option>

                            <option value="Purok 2">
                                Purok 2
                            </option>

                            <option value="Purok 3">
                                Purok 3
                            </option>

                            <option value="Purok 4">
                                Purok 4
                            </option>

                            <option value="Purok 5">
                                Purok 5
                            </option>

                            <option value="Purok 6">
                                Purok 6
                            </option>

                            <option value="Purok 7">
                                Purok 7
                            </option>

                        </select>

                    </div>

                <?php endif; ?>


                <!-- STATUS -->

                <?php if ($statusColumn): ?>

                    <div class="field">

                        <label>
                            Status
                        </label>

                        <select name="status">

                            <option value="Active">
                                🟢 Active
                            </option>

                            <option value="Inactive">
                                🔴 Inactive
                            </option>

                        </select>

                    </div>

                <?php endif; ?>


                <button
                    type="submit"
                    class="publish-btn"
                    onclick="
                        return confirm(
                            'Publish this announcement?'
                        );
                    "
                >

                    📢 Publish Announcement

                </button>


            </form>


        </section>


        <!-- =================================================
             ANNOUNCEMENTS
        ================================================== -->

        <section class="panel">


            <div class="panel-header">

                <h2>
                    📣 Published Announcements
                </h2>

                <p>
                    Mga announcement na ginawa sa MAPALADNEXUS.
                </p>

            </div>


            <div class="announcement-list">


                <input
                    type="text"
                    id="searchInput"
                    class="search"
                    placeholder="🔎 Search announcements..."
                    onkeyup="searchAnnouncements()"
                >


                <?php if (empty($announcements)): ?>


                    <div class="empty">

                        <div class="empty-icon">
                            📢
                        </div>

                        <h3>
                            No Announcements Yet
                        </h3>

                        <p>
                            Gumawa ng unang announcement
                            gamit ang form.
                        </p>

                    </div>


                <?php else: ?>


                    <?php foreach (
                        $announcements
                        as $announcement
                    ): ?>


                        <?php

                        $announcementId =
                            $announcement['announcement_id']
                            ?? '';

                        $announcementTitle =
                            $announcement['title']
                            ?? 'Announcement';

                        $announcementDescription =
                            $announcement['description']
                            ?? '';

                        $announcementPurok =
                            $announcement['purok']
                            ?? 'All';

                        $announcementStatus =
                            $announcement['status']
                            ?? 'Active';

                        $createdBy =
                            $announcement['created_by']
                            ?? 'Admin';

                        $createdAt =
                            $announcement['created_at']
                            ?? '';

                        $statusClass =
                            strtolower(
                                trim(
                                    $announcementStatus
                                )
                            ) === 'active'
                            ? 'active'
                            : 'inactive';

                        ?>


                        <article
                            class="announcement-card"
                            data-search="
                                <?= h(
                                    strtolower(
                                        $announcementTitle .
                                        ' ' .
                                        $announcementDescription .
                                        ' ' .
                                        $announcementPurok .
                                        ' ' .
                                        $announcementStatus
                                    )
                                ) ?>
                            "
                        >


                            <div class="card-top">


                                <div class="badges">


                                    <span
                                        class="
                                            badge
                                            <?= $statusClass ?>
                                        "
                                    >

                                        <?= $statusClass === 'active'
                                            ? '🟢 Active'
                                            : '🔴 Inactive'
                                        ?>

                                    </span>


                                    <?php if (
                                        $purokColumn
                                    ): ?>

                                        <span class="badge purok">

                                            📍

                                            <?= h(
                                                $announcementPurok
                                            ) ?>

                                        </span>

                                    <?php endif; ?>


                                </div>


                                <?php if (
                                    $createdAt !== ''
                                ): ?>

                                    <span class="date">

                                        <?= h(
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


                            <div class="announcement-title">

                                <?= h(
                                    $announcementTitle
                                ) ?>

                            </div>


                            <div class="announcement-description">

                                <?= nl2br(
                                    h(
                                        $announcementDescription
                                    )
                                ) ?>

                            </div>


                            <div class="meta">

                                <span>

                                    👤

                                    <?= h(
                                        $createdBy
                                    ) ?>

                                </span>


                                <span>

                                    Barangay Mapalad

                                </span>

                            </div>


                            <div class="card-actions">


                                <button
                                    type="button"
                                    class="action-btn"
                                    onclick='openEditModal(
                                        <?= json_encode(
                                            $announcementId
                                        ) ?>,
                                        <?= json_encode(
                                            $announcementTitle
                                        ) ?>,
                                        <?= json_encode(
                                            $announcementDescription
                                        ) ?>,
                                        <?= json_encode(
                                            $announcementPurok
                                        ) ?>,
                                        <?= json_encode(
                                            $announcementStatus
                                        ) ?>
                                    )'
                                >

                                    ✏️ Edit

                                </button>


                                <form
                                    method="POST"
                                    style="display:inline;"
                                    onsubmit="
                                        return confirm(
                                            'Delete this announcement permanently?'
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
                                        name="announcement_id"
                                        value="<?= h(
                                            $announcementId
                                        ) ?>"
                                    >

                                    <button
                                        type="submit"
                                        class="
                                            action-btn
                                            delete-btn
                                        "
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


    </div>


</main>


<!-- =========================================================
     EDIT MODAL
========================================================= -->

<div
    class="modal"
    id="editModal"
>


    <div class="modal-box">


        <div class="modal-header">

            <h2>
                ✏️ Edit Announcement
            </h2>

            <button
                type="button"
                class="close"
                onclick="closeEditModal()"
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
                value="edit"
            >


            <input
                type="hidden"
                name="announcement_id"
                id="editId"
            >


            <div class="field">

                <label>
                    Announcement Title
                </label>

                <input
                    type="text"
                    name="title"
                    id="editTitle"
                    required
                >

            </div>


            <div class="field">

                <label>
                    Announcement Details
                </label>

                <textarea
                    name="description"
                    id="editDescription"
                    required
                ></textarea>

            </div>


            <?php if ($purokColumn): ?>

                <div class="field">

                    <label>
                        Target Area
                    </label>

                    <select
                        name="purok"
                        id="editPurok"
                    >

                        <option value="All">
                            📢 All Purok
                        </option>

                        <option value="Purok 1">
                            Purok 1
                        </option>

                        <option value="Purok 2">
                            Purok 2
                        </option>

                        <option value="Purok 3">
                            Purok 3
                        </option>

                        <option value="Purok 4">
                            Purok 4
                        </option>

                        <option value="Purok 5">
                            Purok 5
                        </option>

                        <option value="Purok 6">
                            Purok 6
                        </option>

                        <option value="Purok 7">
                            Purok 7
                        </option>

                    </select>

                </div>

            <?php endif; ?>


            <?php if ($statusColumn): ?>

                <div class="field">

                    <label>
                        Status
                    </label>

                    <select
                        name="status"
                        id="editStatus"
                    >

                        <option value="Active">
                            🟢 Active
                        </option>

                        <option value="Inactive">
                            🔴 Inactive
                        </option>

                    </select>

                </div>

            <?php endif; ?>


            <button
                type="submit"
                class="publish-btn"
            >

                💾 Save Changes

            </button>


        </form>


    </div>


</div>


<script>

/* =========================================================
   SEARCH
========================================================= */

function searchAnnouncements() {

    const input =
        document.getElementById(
            'searchInput'
        );

    const search =
        input.value.toLowerCase().trim();

    const cards =
        document.querySelectorAll(
            '.announcement-card'
        );


    cards.forEach(function(card) {

        const text =
            card.getAttribute(
                'data-search'
            ) || '';


        if (
            text.includes(search)
        ) {

            card.style.display = '';

        } else {

            card.style.display = 'none';
        }

    });
}


/* =========================================================
   OPEN EDIT MODAL
========================================================= */

function openEditModal(
    id,
    title,
    description,
    purok,
    status
) {

    document.getElementById(
        'editId'
    ).value = id;


    document.getElementById(
        'editTitle'
    ).value = title;


    document.getElementById(
        'editDescription'
    ).value = description;


    const purokElement =
        document.getElementById(
            'editPurok'
        );


    if (purokElement) {

        purokElement.value =
            purok || 'All';
    }


    const statusElement =
        document.getElementById(
            'editStatus'
        );


    if (statusElement) {

        statusElement.value =
            status || 'Active';
    }


    document.getElementById(
        'editModal'
    ).classList.add(
        'show'
    );
}


/* =========================================================
   CLOSE EDIT MODAL
========================================================= */

function closeEditModal() {

    document.getElementById(
        'editModal'
    ).classList.remove(
        'show'
    );
}


/* =========================================================
   CLICK OUTSIDE MODAL
========================================================= */

document.getElementById(
    'editModal'
).addEventListener(
    'click',
    function(event) {

        if (
            event.target === this
        ) {

            closeEditModal();
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

            closeEditModal();
        }

    }
);

</script>


</body>
</html>