<?php

session_start();

require_once __DIR__ . '/../config/database.php';

if (
    !isset($_SESSION['user_id']) ||
    ($_SESSION['role'] ?? '') !== 'Admin'
) {
    header("Location: login.php");
    exit;
}

/* =========================
   DELETE RESIDENT
========================= */

if (isset($_GET['delete'])) {

    $id = (int) $_GET['delete'];

    if ($id > 0) {

        $stmt = $conn->prepare(
            "DELETE FROM residents WHERE id = ?"
        );

        if ($stmt) {
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $stmt->close();
        }
    }

    header("Location: residents.php");
    exit;
}

/* =========================
   ADD RESIDENT
========================= */

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $first_name   = trim($_POST['first_name'] ?? '');
    $middle_name  = trim($_POST['middle_name'] ?? '');
    $last_name    = trim($_POST['last_name'] ?? '');
    $suffix       = trim($_POST['suffix'] ?? '');
    $birth_date   = trim($_POST['birth_date'] ?? '');
    $gender       = trim($_POST['gender'] ?? '');
    $civil_status = trim($_POST['civil_status'] ?? '');
    $purok        = trim($_POST['purok'] ?? '');
    $address      = trim($_POST['address'] ?? '');

    if (
        $first_name === '' ||
        $last_name === '' ||
        $birth_date === '' ||
        $gender === '' ||
        $purok === '' ||
        $address === ''
    ) {

        $error = "Please complete all required fields.";

    } else {

        $stmt = $conn->prepare("
            INSERT INTO residents
            (
                first_name,
                middle_name,
                last_name,
                suffix,
                birth_date,
                gender,
                civil_status,
                purok,
                address
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        if ($stmt) {

            $stmt->bind_param(
                "sssssssss",
                $first_name,
                $middle_name,
                $last_name,
                $suffix,
                $birth_date,
                $gender,
                $civil_status,
                $purok,
                $address
            );

            if ($stmt->execute()) {

                $message =
                    "Resident successfully added.";

            } else {

                $error =
                    "Unable to add resident: " .
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

/* =========================
   SEARCH
========================= */

$search = trim($_GET['search'] ?? '');

$residents = [];

if ($search !== '') {

    $like = "%" . $search . "%";

    $stmt = $conn->prepare("
        SELECT *
        FROM residents
        WHERE
            first_name LIKE ?
            OR middle_name LIKE ?
            OR last_name LIKE ?
            OR purok LIKE ?
            OR address LIKE ?
        ORDER BY id DESC
    ");

    if ($stmt) {

        $stmt->bind_param(
            "sssss",
            $like,
            $like,
            $like,
            $like,
            $like
        );

        $stmt->execute();

        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $residents[] = $row;
        }

        $stmt->close();
    }

} else {

    $result = $conn->query("
        SELECT *
        FROM residents
        ORDER BY id DESC
    ");

    if ($result) {

        while ($row = $result->fetch_assoc()) {
            $residents[] = $row;
        }
    }
}

$total_residents = count($residents);

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
    Residents | MAPALADNEXUS
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
    --red: #ef4444;
    --green: #10b981;
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
            rgba(37,99,235,.16),
            transparent 28%
        ),
        radial-gradient(
            circle at 90% 80%,
            rgba(124,58,237,.14),
            transparent 30%
        ),
        var(--bg);
}

/* =========================
   BACKGROUND
========================= */

.grid {

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

    border:
        1px solid
        var(--border);

    border-radius: 26px;

    background:
        rgba(10,15,32,.82);

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

    box-shadow:
        8px 12px 25px
        rgba(37,99,235,.25);

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

    text-transform: uppercase;

    letter-spacing: 1px;
}

/* =========================
   NAV
========================= */

.nav-title {

    margin:
        20px 10px 10px;

    color: #64748b;

    font-size: 8px;

    font-weight: bold;

    text-transform: uppercase;

    letter-spacing: 1px;
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

    color: #94a3b8;

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

/* =========================
   MAIN
========================= */

.main {

    margin-left: 295px;

    padding: 30px;

    min-height: 100vh;
}

/* =========================
   HEADER
========================= */

.header {

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 20px;

    margin-bottom: 25px;
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

    padding: 10px 15px;

    border:
        1px solid
        var(--border);

    border-radius: 14px;

    background:
        var(--panel);

    color: #93c5fd;

    font-size: 9px;
}

/* =========================
   TOP CARDS
========================= */

.top-grid {

    display: grid;

    grid-template-columns:
        1fr 2fr;

    gap: 18px;

    margin-bottom: 20px;
}

.total-card {

    padding: 25px;

    border:
        1px solid
        var(--border);

    border-radius: 22px;

    background:
        linear-gradient(
            145deg,
            rgba(37,99,235,.18),
            rgba(255,255,255,.04)
        );

    box-shadow:
        10px 18px 45px
        rgba(0,0,0,.20);

    transform:
        perspective(900px)
        rotateX(1deg);
}

.total-card small {

    color: var(--muted);

    font-size: 9px;
}

.total-card h2 {

    margin-top: 10px;

    font-size: 35px;
}

.total-card p {

    margin-top: 7px;

    color: #60a5fa;

    font-size: 9px;
}

/* =========================
   SEARCH
========================= */

.search-box {

    padding: 20px;

    border:
        1px solid
        var(--border);

    border-radius: 22px;

    background:
        var(--panel);

    backdrop-filter:
        blur(18px);
}

.search-form {

    display: flex;

    gap: 10px;
}

.search-input {

    flex: 1;

    padding: 14px 16px;

    color: white;

    background:
        rgba(0,0,0,.25);

    border:
        1px solid
        var(--border);

    border-radius: 13px;

    outline: none;

    font-size: 10px;
}

.search-input:focus {

    border-color:
        rgba(96,165,250,.6);
}

.btn {

    padding:
        12px 18px;

    border: none;

    border-radius: 13px;

    cursor: pointer;

    color: white;

    font-size: 9px;

    font-weight: bold;
}

.btn-search {

    background:
        linear-gradient(
            145deg,
            #2563eb,
            #4f46e5
        );
}

.btn-add {

    background:
        linear-gradient(
            145deg,
            #059669,
            #10b981
        );
}

/* =========================
   MESSAGES
========================= */

.message {

    margin-bottom: 18px;

    padding: 14px 16px;

    border-radius: 14px;

    font-size: 9px;
}

.success {

    color: #a7f3d0;

    background:
        rgba(16,185,129,.10);

    border:
        1px solid
        rgba(16,185,129,.18);
}

.error {

    color: #fecaca;

    background:
        rgba(239,68,68,.10);

    border:
        1px solid
        rgba(239,68,68,.18);
}

/* =========================
   TABLE PANEL
========================= */

.table-panel {

    padding: 20px;

    border:
        1px solid
        var(--border);

    border-radius: 22px;

    background:
        var(--panel);

    backdrop-filter:
        blur(18px);

    box-shadow:
        10px 18px 45px
        rgba(0,0,0,.16);
}

.table-header {

    display: flex;

    align-items: center;

    justify-content: space-between;

    margin-bottom: 17px;
}

.table-header h3 {

    font-size: 12px;
}

.table-header span {

    color: #64748b;

    font-size: 8px;
}

.table-wrapper {

    overflow-x: auto;
}

table {

    width: 100%;

    min-width: 900px;

    border-collapse: collapse;
}

th {

    padding:
        13px 12px;

    color: #64748b;

    text-align: left;

    background:
        rgba(255,255,255,.025);

    border-bottom:
        1px solid
        var(--border);

    font-size: 8px;

    text-transform: uppercase;

    letter-spacing: .5px;
}

td {

    padding:
        15px 12px;

    color: #cbd5e1;

    border-bottom:
        1px solid
        rgba(255,255,255,.045);

    font-size: 9px;
}

tr {

    transition: .2s;
}

tbody tr:hover {

    background:
        rgba(255,255,255,.035);

    transform:
        translateX(2px);
}

/* =========================
   BADGES
========================= */

.badge {

    display: inline-block;

    padding:
        6px 9px;

    border-radius: 8px;

    font-size: 7px;

    font-weight: bold;
}

.badge-purok {

    color: #93c5fd;

    background:
        rgba(37,99,235,.10);
}

.badge-male {

    color: #bfdbfe;

    background:
        rgba(59,130,246,.10);
}

.badge-female {

    color: #fbcfe8;

    background:
        rgba(236,72,153,.10);
}

/* =========================
   ACTIONS
========================= */

.actions {

    display: flex;

    gap: 6px;
}

.action {

    display: inline-flex;

    align-items: center;

    justify-content: center;

    width: 30px;
    height: 30px;

    color: white;

    text-decoration: none;

    border-radius: 9px;

    font-size: 12px;

    transition: .2s;
}

.action-edit {

    background:
        rgba(37,99,235,.15);
}

.action-delete {

    background:
        rgba(239,68,68,.12);
}

.action:hover {

    transform:
        translateY(-2px);
}

/* =========================
   MODAL
========================= */

.modal {

    position: fixed;

    inset: 0;

    z-index: 500;

    display: none;

    align-items: center;

    justify-content: center;

    padding: 20px;

    background:
        rgba(0,0,0,.72);

    backdrop-filter:
        blur(8px);
}

.modal.show {

    display: flex;
}

.modal-card {

    width: 100%;

    max-width: 650px;

    max-height: 90vh;

    overflow-y: auto;

    padding: 25px;

    border:
        1px solid
        var(--border);

    border-radius: 24px;

    background:
        #0b1020;

    box-shadow:
        20px 30px 80px
        rgba(0,0,0,.55);
}

.modal-header {

    display: flex;

    align-items: center;

    justify-content: space-between;

    margin-bottom: 20px;
}

.modal-header h2 {

    font-size: 16px;
}

.close {

    width: 34px;
    height: 34px;

    border: none;

    border-radius: 10px;

    color: white;

    background:
        rgba(255,255,255,.07);

    cursor: pointer;
}

.form-grid {

    display: grid;

    grid-template-columns:
        repeat(2, 1fr);

    gap: 13px;
}

.field {

    margin-bottom: 5px;
}

.field.full {

    grid-column:
        1 / -1;
}

.field label {

    display: block;

    margin-bottom: 7px;

    color: var(--muted);

    font-size: 8px;

    font-weight: bold;
}

.field input,
.field select,
.field textarea {

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

.field textarea {

    min-height: 75px;

    resize: vertical;
}

.field option {

    color: black;
}

.modal-footer {

    display: flex;

    justify-content: flex-end;

    gap: 9px;

    margin-top: 20px;
}

.btn-cancel {

    color: #cbd5e1;

    background:
        rgba(255,255,255,.07);
}

/* =========================
   EMPTY
========================= */

.empty {

    padding: 60px 20px;

    text-align: center;

    color: #64748b;

    font-size: 10px;
}

/* =========================
   MOBILE
========================= */

@media(max-width: 1000px) {

    .sidebar {

        display: none;
    }

    .main {

        margin-left: 0;

        padding: 20px;
    }

    .top-grid {

        grid-template-columns: 1fr;
    }
}

@media(max-width: 600px) {

    .header {

        align-items: flex-start;

        flex-direction: column;
    }

    .search-form {

        flex-direction: column;
    }

    .form-grid {

        grid-template-columns: 1fr;
    }

    .field.full {

        grid-column: auto;
    }
}

</style>

</head>

<body>

<div class="grid"></div>

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
                Residents
            </h1>

            <p>
                Manage registered residents of Barangay Mapalad.
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


    <?php if ($message !== ''): ?>

        <div class="message success">
            ✅
            <?= htmlspecialchars(
                $message,
                ENT_QUOTES,
                'UTF-8'
            ) ?>
        </div>

    <?php endif; ?>


    <?php if ($error !== ''): ?>

        <div class="message error">
            ⚠️
            <?= htmlspecialchars(
                $error,
                ENT_QUOTES,
                'UTF-8'
            ) ?>
        </div>

    <?php endif; ?>


    <div class="top-grid">

        <div class="total-card">

            <small>
                RESIDENT RECORDS
            </small>

            <h2>
                <?= number_format(
                    $total_residents
                ) ?>
            </h2>

            <p>
                👥 Registered Residents
            </p>

        </div>


        <div class="search-box">

            <form
                method="GET"
                class="search-form"
            >

                <input
                    type="text"
                    name="search"
                    class="search-input"
                    placeholder="Search name, purok or address..."
                    value="<?= htmlspecialchars(
                        $search,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>"
                >

                <button
                    type="submit"
                    class="btn btn-search"
                >
                    🔍 Search
                </button>

                <button
                    type="button"
                    class="btn btn-add"
                    onclick="openModal()"
                >
                    ＋ Add Resident
                </button>

            </form>

        </div>

    </div>


    <!-- =========================
         TABLE
    ========================= -->

    <div class="table-panel">

        <div class="table-header">

            <h3>
                👥 Resident Directory
            </h3>

            <span>
                <?= number_format(
                    $total_residents
                ) ?>
                records
            </span>

        </div>


        <?php if (
            count($residents) > 0
        ): ?>

            <div class="table-wrapper">

                <table>

                    <thead>

                        <tr>

                            <th>
                                ID
                            </th>

                            <th>
                                Full Name
                            </th>

                            <th>
                                Birth Date
                            </th>

                            <th>
                                Gender
                            </th>

                            <th>
                                Civil Status
                            </th>

                            <th>
                                Purok
                            </th>

                            <th>
                                Address
                            </th>

                            <th>
                                Actions
                            </th>

                        </tr>

                    </thead>


                    <tbody>

                    <?php foreach (
                        $residents
                        as $resident
                    ): ?>

                        <tr>

                            <td>
                                #<?= (int)
                                    $resident['id'] ?>
                            </td>


                            <td>

                                <strong>

                                    <?= htmlspecialchars(
                                        trim(
                                            $resident['first_name']
                                            . ' ' .
                                            ($resident['middle_name'] ?? '')
                                            . ' ' .
                                            $resident['last_name']
                                            . ' ' .
                                            ($resident['suffix'] ?? '')
                                        ),
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>

                                </strong>

                            </td>


                            <td>

                                <?= htmlspecialchars(
                                    $resident['birth_date']
                                    ?? '',
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>

                            </td>


                            <td>

                                <?php if (
                                    strtolower(
                                        $resident['gender'] ?? ''
                                    ) === 'female'
                                ): ?>

                                    <span class="badge badge-female">
                                        Female
                                    </span>

                                <?php else: ?>

                                    <span class="badge badge-male">
                                        <?= htmlspecialchars(
                                            $resident['gender']
                                            ?? '',
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </span>

                                <?php endif; ?>

                            </td>


                            <td>

                                <?= htmlspecialchars(
                                    $resident['civil_status']
                                    ?? '',
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>

                            </td>


                            <td>

                                <span class="badge badge-purok">

                                    <?= htmlspecialchars(
                                        $resident['purok']
                                        ?? '',
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>

                                </span>

                            </td>


                            <td>

                                <?= htmlspecialchars(
                                    $resident['address']
                                    ?? '',
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>

                            </td>


                            <td>

                                <div class="actions">

                                    <a
                                        href="edit_resident.php?id=<?= (int)$resident['id'] ?>"
                                        class="action action-edit"
                                        title="Edit"
                                    >
                                        ✏️
                                    </a>


                                    <a
                                        href="residents.php?delete=<?= (int)$resident['id'] ?>"
                                        class="action action-delete"
                                        title="Delete"
                                        onclick="
                                            return confirm(
                                                'Delete this resident record?'
                                            );
                                        "
                                    >
                                        🗑️
                                    </a>

                                </div>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        <?php else: ?>

            <div class="empty">

                👥

                <br><br>

                No resident records found.

                <?php if ($search !== ''): ?>

                    <br><br>

                    Search:
                    <strong>
                        <?= htmlspecialchars(
                            $search,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>
                    </strong>

                <?php endif; ?>

            </div>

        <?php endif; ?>

    </div>

</main>


<!-- =========================
     ADD RESIDENT MODAL
========================= -->

<div
    class="modal"
    id="residentModal"
>

    <div class="modal-card">

        <div class="modal-header">

            <h2>
                ➕ Add New Resident
            </h2>

            <button
                class="close"
                type="button"
                onclick="closeModal()"
            >
                ✕
            </button>

        </div>


        <form
            method="POST"
            action=""
        >

            <div class="form-grid">


                <div class="field">

                    <label>
                        FIRST NAME *
                    </label>

                    <input
                        type="text"
                        name="first_name"
                        required
                    >

                </div>


                <div class="field">

                    <label>
                        MIDDLE NAME
                    </label>

                    <input
                        type="text"
                        name="middle_name"
                    >

                </div>


                <div class="field">

                    <label>
                        LAST NAME *
                    </label>

                    <input
                        type="text"
                        name="last_name"
                        required
                    >

                </div>


                <div class="field">

                    <label>
                        SUFFIX
                    </label>

                    <input
                        type="text"
                        name="suffix"
                        placeholder="Jr., Sr., III"
                    >

                </div>


                <div class="field">

                    <label>
                        BIRTH DATE *
                    </label>

                    <input
                        type="date"
                        name="birth_date"
                        required
                    >

                </div>


                <div class="field">

                    <label>
                        GENDER *
                    </label>

                    <select
                        name="gender"
                        required
                    >

                        <option value="">
                            Select Gender
                        </option>

                        <option value="Male">
                            Male
                        </option>

                        <option value="Female">
                            Female
                        </option>

                    </select>

                </div>


                <div class="field">

                    <label>
                        CIVIL STATUS
                    </label>

                    <select
                        name="civil_status"
                    >

                        <option value="">
                            Select Status
                        </option>

                        <option value="Single">
                            Single
                        </option>

                        <option value="Married">
                            Married
                        </option>

                        <option value="Widowed">
                            Widowed
                        </option>

                        <option value="Separated">
                            Separated
                        </option>

                    </select>

                </div>


                <div class="field">

                    <label>
                        PUROK *
                    </label>

                    <select
                        name="purok"
                        required
                    >

                        <option value="">
                            Select Purok
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


                <div class="field full">

                    <label>
                        COMPLETE ADDRESS *
                    </label>

                    <textarea
                        name="address"
                        required
                        placeholder="Enter complete address"
                    ></textarea>

                </div>


            </div>


            <div class="modal-footer">

                <button
                    type="button"
                    class="btn btn-cancel"
                    onclick="closeModal()"
                >
                    Cancel
                </button>


                <button
                    type="submit"
                    class="btn btn-add"
                >
                    💾 Save Resident
                </button>

            </div>

        </form>

    </div>

</div>


<script>

function openModal() {

    document
        .getElementById('residentModal')
        .classList
        .add('show');
}

function closeModal() {

    document
        .getElementById('residentModal')
        .classList
        .remove('show');
}

document
    .getElementById('residentModal')
    .addEventListener(
        'click',
        function(event) {

            if (
                event.target === this
            ) {

                closeModal();
            }

        }
    );

</script>

</body>

</html>