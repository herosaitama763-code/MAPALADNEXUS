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
   GET RESIDENT ID
========================= */

$id = isset($_GET['id'])
    ? (int) $_GET['id']
    : 0;

if ($id <= 0) {
    header("Location: residents.php");
    exit;
}

/* =========================
   UPDATE RESIDENT
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
            UPDATE residents
            SET
                first_name = ?,
                middle_name = ?,
                last_name = ?,
                suffix = ?,
                birth_date = ?,
                gender = ?,
                civil_status = ?,
                purok = ?,
                address = ?
            WHERE id = ?
        ");

        if ($stmt) {

            $stmt->bind_param(
                "sssssssssi",
                $first_name,
                $middle_name,
                $last_name,
                $suffix,
                $birth_date,
                $gender,
                $civil_status,
                $purok,
                $address,
                $id
            );

            if ($stmt->execute()) {

                $stmt->close();

                header(
                    "Location: residents.php?updated=1"
                );

                exit;

            } else {

                $error =
                    "Unable to update resident: " .
                    $stmt->error;

                $stmt->close();
            }

        } else {

            $error =
                "Database error: " .
                $conn->error;
        }
    }
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
    die(
        "Database error: " .
        htmlspecialchars(
            $conn->error,
            ENT_QUOTES,
            'UTF-8'
        )
    );
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
    Edit Resident | MAPALADNEXUS
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
            rgba(37,99,235,.16),
            transparent 30%
        ),
        radial-gradient(
            circle at 90% 90%,
            rgba(124,58,237,.14),
            transparent 30%
        ),
        var(--bg);

    overflow-x: hidden;
}

/* =========================
   GRID
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
   NAV
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

/* =========================
   BACK BUTTON
========================= */

.back {

    display: inline-flex;

    align-items: center;

    gap: 8px;

    margin-bottom: 18px;

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

    background:
        rgba(255,255,255,.08);

    transform:
        translateX(-3px);
}

/* =========================
   FORM CARD
========================= */

.card {

    max-width: 950px;

    padding: 30px;

    border:
        1px solid
        var(--border);

    border-radius: 26px;

    background:
        var(--panel);

    backdrop-filter:
        blur(22px);

    box-shadow:
        15px 25px 65px
        rgba(0,0,0,.25);

    transform:
        perspective(1200px)
        rotateX(1deg);
}

.card-header {

    display: flex;

    align-items: center;

    gap: 15px;

    margin-bottom: 25px;

    padding-bottom: 20px;

    border-bottom:
        1px solid
        var(--border);
}

.avatar {

    width: 58px;
    height: 58px;

    display: flex;

    align-items: center;

    justify-content: center;

    border-radius: 18px;

    background:
        linear-gradient(
            145deg,
            #2563eb,
            #7c3aed
        );

    font-size: 25px;

    box-shadow:
        8px 12px 25px
        rgba(37,99,235,.22);
}

.card-header h2 {

    font-size: 16px;
}

.card-header p {

    margin-top: 5px;

    color: var(--muted);

    font-size: 9px;
}

/* =========================
   ALERT
========================= */

.alert {

    margin-bottom: 20px;

    padding: 14px;

    border-radius: 13px;

    font-size: 9px;
}

.alert.error {

    color: #fecaca;

    background:
        rgba(239,68,68,.10);

    border:
        1px solid
        rgba(239,68,68,.18);
}

/* =========================
   FORM
========================= */

.section-title {

    margin:
        24px 0 14px;

    color: #93c5fd;

    font-size: 9px;

    font-weight: bold;

    letter-spacing: .8px;

    text-transform: uppercase;
}

.form-grid {

    display: grid;

    grid-template-columns:
        repeat(2, 1fr);

    gap: 15px;
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

    padding: 13px;

    color: white;

    background:
        rgba(0,0,0,.25);

    border:
        1px solid
        var(--border);

    border-radius: 12px;

    outline: none;

    font-family: inherit;

    font-size: 9px;

    transition: .2s;
}

.field input:focus,
.field select:focus,
.field textarea:focus {

    border-color:
        rgba(96,165,250,.65);

    box-shadow:
        0 0 0 3px
        rgba(37,99,235,.08);
}

.field select option {

    color: black;
}

.field textarea {

    min-height: 100px;

    resize: vertical;
}

/* =========================
   FOOTER
========================= */

.form-footer {

    display: flex;

    justify-content: flex-end;

    gap: 10px;

    margin-top: 28px;

    padding-top: 20px;

    border-top:
        1px solid
        var(--border);
}

.btn {

    padding:
        13px 20px;

    border: none;

    border-radius: 12px;

    cursor: pointer;

    color: white;

    text-decoration: none;

    font-size: 9px;

    font-weight: bold;

    transition: .25s;
}

.btn:hover {

    transform:
        translateY(-2px);
}

.btn-save {

    background:
        linear-gradient(
            145deg,
            #2563eb,
            #4f46e5
        );

    box-shadow:
        8px 12px 25px
        rgba(37,99,235,.20);
}

.btn-cancel {

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
}

@media(max-width: 600px) {

    .form-grid {

        grid-template-columns: 1fr;
    }

    .field.full {

        grid-column: auto;
    }

    .card {

        padding: 20px;
    }

    .header {

        align-items: flex-start;

        flex-direction: column;
    }

    .form-footer {

        flex-direction: column;
    }

    .btn {

        width: 100%;

        text-align: center;
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


<!-- =========================
     MAIN
========================= -->

<main class="main">

    <div class="header">

        <div>

            <h1>
                Edit Resident
            </h1>

            <p>
                Update the resident information below.
            </p>

        </div>

    </div>


    <a
        href="residents.php"
        class="back"
    >
        ← Back to Residents
    </a>


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


    <div class="card">


        <div class="card-header">

            <div class="avatar">
                👤
            </div>

            <div>

                <h2>
                    Resident Information
                </h2>

                <p>
                    Resident ID #<?= (int)$resident['id'] ?>
                </p>

            </div>

        </div>


        <form
            method="POST"
            action="edit_resident.php?id=<?= (int)$id ?>"
        >


            <div class="section-title">
                Personal Information
            </div>


            <div class="form-grid">


                <div class="field">

                    <label>
                        FIRST NAME *
                    </label>

                    <input
                        type="text"
                        name="first_name"
                        required
                        value="<?= htmlspecialchars(
                            $resident['first_name'] ?? '',
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"
                    >

                </div>


                <div class="field">

                    <label>
                        MIDDLE NAME
                    </label>

                    <input
                        type="text"
                        name="middle_name"
                        value="<?= htmlspecialchars(
                            $resident['middle_name'] ?? '',
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"
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
                        value="<?= htmlspecialchars(
                            $resident['last_name'] ?? '',
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"
                    >

                </div>


                <div class="field">

                    <label>
                        SUFFIX
                    </label>

                    <input
                        type="text"
                        name="suffix"
                        value="<?= htmlspecialchars(
                            $resident['suffix'] ?? '',
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"
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
                        value="<?= htmlspecialchars(
                            $resident['birth_date'] ?? '',
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"
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

                        <option
                            value="Male"
                            <?= (($resident['gender'] ?? '') === 'Male')
                                ? 'selected'
                                : '' ?>
                        >
                            Male
                        </option>

                        <option
                            value="Female"
                            <?= (($resident['gender'] ?? '') === 'Female')
                                ? 'selected'
                                : '' ?>
                        >
                            Female
                        </option>

                    </select>

                </div>


                <div class="field">

                    <label>
                        CIVIL STATUS
                    </label>

                    <select name="civil_status">

                        <option value="">
                            Select Status
                        </option>

                        <option
                            value="Single"
                            <?= (($resident['civil_status'] ?? '') === 'Single')
                                ? 'selected'
                                : '' ?>
                        >
                            Single
                        </option>

                        <option
                            value="Married"
                            <?= (($resident['civil_status'] ?? '') === 'Married')
                                ? 'selected'
                                : '' ?>
                        >
                            Married
                        </option>

                        <option
                            value="Widowed"
                            <?= (($resident['civil_status'] ?? '') === 'Widowed')
                                ? 'selected'
                                : '' ?>
                        >
                            Widowed
                        </option>

                        <option
                            value="Separated"
                            <?= (($resident['civil_status'] ?? '') === 'Separated')
                                ? 'selected'
                                : '' ?>
                        >
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

                        <?php

                        $puroks = [
                            'Purok 1',
                            'Purok 2',
                            'Purok 3',
                            'Purok 4',
                            'Purok 5',
                            'Purok 6',
                            'Purok 7'
                        ];

                        foreach ($puroks as $purok):

                        ?>

                            <option
                                value="<?= htmlspecialchars(
                                    $purok,
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>"
                                <?= (($resident['purok'] ?? '') === $purok)
                                    ? 'selected'
                                    : '' ?>
                            >

                                <?= htmlspecialchars(
                                    $purok,
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <div class="field full">

                    <label>
                        COMPLETE ADDRESS *
                    </label>

                    <textarea
                        name="address"
                        required
                    ><?= htmlspecialchars(
                        $resident['address'] ?? '',
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?></textarea>

                </div>


            </div>


            <div class="form-footer">

                <a
                    href="residents.php"
                    class="btn btn-cancel"
                >
                    Cancel
                </a>


                <button
                    type="submit"
                    class="btn btn-save"
                >
                    💾 Update Resident
                </button>

            </div>


        </form>

    </div>

</main>

</body>

</html>