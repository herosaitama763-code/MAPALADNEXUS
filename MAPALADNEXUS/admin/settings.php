<?php
session_start();

error_reporting(E_ALL);
ini_set('display_errors', 1);

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

if (
    !isset($_SESSION['username']) ||
    empty($_SESSION['username'])
) {
    header("Location: login.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| ADMIN ROLE CHECK
|--------------------------------------------------------------------------
*/

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
| CURRENT SESSION USERNAME
|--------------------------------------------------------------------------
*/

$currentUsername = $_SESSION['username'];

$message = '';
$messageType = '';

/*
|--------------------------------------------------------------------------
| GET ADMIN ACCOUNT
|--------------------------------------------------------------------------
*/

$admin = null;

$stmt = $conn->prepare("
    SELECT
        id,
        username,
        password,
        role,
        status,
        created_at
    FROM users
    WHERE username = ?
    LIMIT 1
");

if ($stmt) {

    $stmt->bind_param(
        "s",
        $currentUsername
    );

    $stmt->execute();

    $result = $stmt->get_result();

    $admin = $result->fetch_assoc();

    $stmt->close();
}

if (!$admin) {

    session_unset();
    session_destroy();

    header("Location: login.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| UPDATE USERNAME
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['action']) &&
    $_POST['action'] === 'update_username'
) {

    $newUsername =
        trim($_POST['username'] ?? '');

    if ($newUsername === '') {

        $message =
            "Username cannot be empty.";

        $messageType =
            "error";

    } elseif (strlen($newUsername) < 3) {

        $message =
            "Username must contain at least 3 characters.";

        $messageType =
            "error";

    } elseif (strlen($newUsername) > 100) {

        $message =
            "Username is too long.";

        $messageType =
            "error";

    } elseif ($newUsername === $admin['username']) {

        $message =
            "No changes were made.";

        $messageType =
            "info";

    } else {

        /*
        |--------------------------------------------------------------------------
        | CHECK DUPLICATE USERNAME
        |--------------------------------------------------------------------------
        */

        $check = $conn->prepare("
            SELECT id
            FROM users
            WHERE username = ?
            AND id != ?
            LIMIT 1
        ");

        if (!$check) {

            $message =
                "Database error: " .
                $conn->error;

            $messageType =
                "error";

        } else {

            $adminId =
                (int)$admin['id'];

            $check->bind_param(
                "si",
                $newUsername,
                $adminId
            );

            $check->execute();

            $duplicate =
                $check
                    ->get_result()
                    ->fetch_assoc();

            $check->close();


            if ($duplicate) {

                $message =
                    "That username is already being used.";

                $messageType =
                    "error";

            } else {

                /*
                |--------------------------------------------------------------------------
                | UPDATE USERNAME
                |--------------------------------------------------------------------------
                */

                $update = $conn->prepare("
                    UPDATE users
                    SET username = ?
                    WHERE id = ?
                ");

                if (!$update) {

                    $message =
                        "Database error: " .
                        $conn->error;

                    $messageType =
                        "error";

                } else {

                    $update->bind_param(
                        "si",
                        $newUsername,
                        $adminId
                    );

                    if ($update->execute()) {

                        /*
                        | Update session
                        */

                        $_SESSION['username'] =
                            $newUsername;

                        $admin['username'] =
                            $newUsername;

                        $currentUsername =
                            $newUsername;

                        $message =
                            "Username successfully updated.";

                        $messageType =
                            "success";

                    } else {

                        $message =
                            "Unable to update username.";

                        $messageType =
                            "error";
                    }

                    $update->close();
                }
            }
        }
    }
}

/*
|--------------------------------------------------------------------------
| CHANGE PASSWORD
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['action']) &&
    $_POST['action'] === 'change_password'
) {

    $currentPassword =
        $_POST['current_password'] ?? '';

    $newPassword =
        $_POST['new_password'] ?? '';

    $confirmPassword =
        $_POST['confirm_password'] ?? '';


    /*
    |--------------------------------------------------------------------------
    | VALIDATION
    |--------------------------------------------------------------------------
    */

    if (
        $currentPassword === '' ||
        $newPassword === '' ||
        $confirmPassword === ''
    ) {

        $message =
            "Please complete all password fields.";

        $messageType =
            "error";

    } elseif (
        $newPassword !== $confirmPassword
    ) {

        $message =
            "New password and confirmation do not match.";

        $messageType =
            "error";

    } elseif (
        strlen($newPassword) < 6
    ) {

        $message =
            "New password must contain at least 6 characters.";

        $messageType =
            "error";

    } else {

        /*
        |--------------------------------------------------------------------------
        | VERIFY CURRENT PASSWORD
        |--------------------------------------------------------------------------
        */

        $storedPassword =
            $admin['password'];

        $passwordValid =
            false;


        /*
        | Supports:
        | 1. password_hash()
        | 2. Existing plain-text password
        */

        if (
            password_verify(
                $currentPassword,
                $storedPassword
            )
        ) {

            $passwordValid =
                true;

        } elseif (
            $currentPassword ===
            $storedPassword
        ) {

            $passwordValid =
                true;
        }


        if (!$passwordValid) {

            $message =
                "Current password is incorrect.";

            $messageType =
                "error";

        } else {

            /*
            |--------------------------------------------------------------------------
            | HASH NEW PASSWORD
            |--------------------------------------------------------------------------
            */

            $hashedPassword =
                password_hash(
                    $newPassword,
                    PASSWORD_DEFAULT
                );


            $update = $conn->prepare("
                UPDATE users
                SET password = ?
                WHERE id = ?
            ");

            if (!$update) {

                $message =
                    "Database error: " .
                    $conn->error;

                $messageType =
                    "error";

            } else {

                $adminId =
                    (int)$admin['id'];

                $update->bind_param(
                    "si",
                    $hashedPassword,
                    $adminId
                );


                if ($update->execute()) {

                    /*
                    | Update stored password
                    */

                    $admin['password'] =
                        $hashedPassword;

                    $message =
                        "Password successfully changed.";

                    $messageType =
                        "success";

                } else {

                    $message =
                        "Unable to change password.";

                    $messageType =
                        "error";
                }

                $update->close();
            }
        }
    }
}

/*
|--------------------------------------------------------------------------
| REFRESH ACCOUNT DATA
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT
        id,
        username,
        role,
        status,
        created_at
    FROM users
    WHERE id = ?
    LIMIT 1
");

if ($stmt) {

    $adminId =
        (int)$admin['id'];

    $stmt->bind_param(
        "i",
        $adminId
    );

    $stmt->execute();

    $freshAdmin =
        $stmt
            ->get_result()
            ->fetch_assoc();

    if ($freshAdmin) {
        $admin = $freshAdmin;
    }

    $stmt->close();
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
MAPALADNEXUS | Settings
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
   BODY
========================================================= */

body {

    min-height: 100vh;

    font-family:
        Arial,
        Helvetica,
        sans-serif;

    color: #f8fafc;

    background:
        radial-gradient(
            circle at 10% 10%,
            rgba(37, 99, 235, .20),
            transparent 30%
        ),
        radial-gradient(
            circle at 90% 90%,
            rgba(124, 58, 237, .18),
            transparent 30%
        ),
        #050816;
}


body::before {

    content: "";

    position: fixed;

    inset: 0;

    pointer-events: none;

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

    background-size: 60px 60px;
}


/* =========================================================
   SIDEBAR
========================================================= */

.sidebar {

    position: fixed;

    left: 20px;
    top: 20px;
    bottom: 20px;

    width: 250px;

    padding: 20px 15px;

    background:
        rgba(10,15,32,.90);

    border:
        1px solid rgba(255,255,255,.08);

    border-radius: 25px;

    backdrop-filter: blur(25px);

    box-shadow:
        15px 25px 70px rgba(0,0,0,.40);

    display: flex;
    flex-direction: column;

    z-index: 10;
}


/* =========================================================
   BRAND
========================================================= */

.brand {

    display: flex;

    align-items: center;

    gap: 11px;

    padding:
        5px 8px 20px;

    border-bottom:
        1px solid rgba(255,255,255,.07);
}


.logo {

    width: 45px;
    height: 45px;

    display: flex;

    align-items: center;
    justify-content: center;

    border-radius: 14px;

    background:
        linear-gradient(
            145deg,
            #2563eb,
            #4f46e5
        );

    font-size: 21px;
}


.brand h2 {

    font-size: 14px;
}


.brand small {

    display: block;

    margin-top: 4px;

    color: #64748b;

    font-size: 7px;

    letter-spacing: 1px;
}


/* =========================================================
   NAVIGATION
========================================================= */

.nav-title {

    margin:
        20px 9px 9px;

    color: #64748b;

    font-size: 8px;

    font-weight: bold;

    text-transform: uppercase;
}


.nav {

    flex: 1;

    overflow-y: auto;
}


.nav a {

    display: flex;

    align-items: center;

    gap: 11px;

    padding: 11px;

    margin-bottom: 5px;

    color: #94a3b8;

    text-decoration: none;

    border-radius: 12px;

    font-size: 9px;

    transition: .2s;
}


.nav a:hover {

    color: #fff;

    background:
        rgba(255,255,255,.05);
}


.nav a.active {

    color: #fff;

    background:
        rgba(37,99,235,.18);

    box-shadow:
        inset 3px 0 #3b82f6;
}


.nav-icon {

    width: 23px;

    text-align: center;

    font-size: 14px;
}


/* =========================================================
   LOGOUT
========================================================= */

.logout {

    display: block;

    padding: 12px;

    color: #fca5a5;

    text-decoration: none;

    text-align: center;

    background:
        rgba(239,68,68,.07);

    border:
        1px solid rgba(239,68,68,.12);

    border-radius: 12px;

    font-size: 9px;

    transition: .2s;
}


.logout:hover {

    background:
        rgba(239,68,68,.14);

    color: #fecaca;
}


/* =========================================================
   MAIN
========================================================= */

.main {

    margin-left: 290px;

    padding: 30px;

    min-height: 100vh;
}


/* =========================================================
   HEADER
========================================================= */

.header {

    display: flex;

    justify-content: space-between;

    align-items: center;

    margin-bottom: 25px;
}


.header h1 {

    font-size: 28px;
}


.header p {

    margin-top: 7px;

    color: #94a3b8;

    font-size: 9px;
}


.admin-user {

    padding:
        10px 14px;

    color: #93c5fd;

    background:
        rgba(255,255,255,.04);

    border:
        1px solid rgba(255,255,255,.08);

    border-radius: 13px;

    font-size: 9px;
}


/* =========================================================
   MESSAGE
========================================================= */

.message {

    margin-bottom: 20px;

    padding: 14px 16px;

    border-radius: 12px;

    font-size: 9px;
}


.message.success {

    color: #bbf7d0;

    background:
        rgba(34,197,94,.09);

    border:
        1px solid rgba(34,197,94,.18);
}


.message.error {

    color: #fecaca;

    background:
        rgba(239,68,68,.09);

    border:
        1px solid rgba(239,68,68,.18);
}


.message.info {

    color: #bfdbfe;

    background:
        rgba(37,99,235,.09);

    border:
        1px solid rgba(37,99,235,.18);
}


/* =========================================================
   GRID
========================================================= */

.settings-grid {

    display: grid;

    grid-template-columns:
        1fr 1fr;

    gap: 20px;
}


/* =========================================================
   CARD
========================================================= */

.card {

    padding: 24px;

    background:
        rgba(255,255,255,.045);

    border:
        1px solid rgba(255,255,255,.08);

    border-radius: 22px;

    backdrop-filter: blur(20px);

    box-shadow:
        0 20px 60px rgba(0,0,0,.15);
}


.card.full {

    grid-column:
        1 / -1;
}


.card-header {

    display: flex;

    align-items: center;

    gap: 13px;

    margin-bottom: 20px;
}


.card-icon {

    width: 46px;
    height: 46px;

    display: flex;

    align-items: center;
    justify-content: center;

    border-radius: 13px;

    background:
        rgba(37,99,235,.15);

    font-size: 21px;
}


.card-header h2 {

    font-size: 14px;
}


.card-header p {

    margin-top: 4px;

    color: #64748b;

    font-size: 7px;
}


/* =========================================================
   ACCOUNT
========================================================= */

.account-avatar {

    width: 90px;
    height: 90px;

    margin:
        0 auto 18px;

    display: flex;

    align-items: center;
    justify-content: center;

    border-radius: 50%;

    background:
        linear-gradient(
            145deg,
            #2563eb,
            #4f46e5
        );

    font-size: 36px;

    box-shadow:
        0 15px 40px
        rgba(37,99,235,.25);
}


.account-name {

    text-align: center;

    margin-bottom: 20px;
}


.account-name h3 {

    font-size: 18px;
}


.account-name p {

    margin-top: 5px;

    color: #64748b;

    font-size: 8px;
}


.info-list {

    display: grid;

    gap: 9px;
}


.info-row {

    display: flex;

    justify-content: space-between;

    align-items: center;

    gap: 15px;

    padding: 12px;

    background:
        rgba(255,255,255,.035);

    border-radius: 10px;
}


.info-label {

    color: #64748b;

    font-size: 8px;
}


.info-value {

    color: #e2e8f0;

    font-size: 8px;

    font-weight: bold;

    text-align: right;
}


.status {

    padding:
        5px 9px;

    color: #bbf7d0;

    background:
        rgba(34,197,94,.10);

    border-radius: 8px;

    font-size: 7px;
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
}


.form-group input {

    width: 100%;

    padding: 12px 13px;

    color: #fff;

    background:
        rgba(255,255,255,.045);

    border:
        1px solid rgba(255,255,255,.09);

    border-radius: 11px;

    outline: none;

    font-family: inherit;

    font-size: 9px;
}


.form-group input:focus {

    border-color:
        rgba(59,130,246,.65);

    box-shadow:
        0 0 0 3px
        rgba(59,130,246,.08);
}


.form-group input::placeholder {

    color: #475569;
}


/* =========================================================
   BUTTON
========================================================= */

.btn {

    width: 100%;

    padding: 12px 15px;

    border: 0;

    border-radius: 11px;

    cursor: pointer;

    color: #fff;

    background:
        linear-gradient(
            145deg,
            #2563eb,
            #4f46e5
        );

    font-size: 9px;

    font-weight: bold;

    transition: .2s;
}


.btn:hover {

    transform:
        translateY(-1px);

    box-shadow:
        0 10px 25px
        rgba(37,99,235,.18);
}


/* =========================================================
   PASSWORD GRID
========================================================= */

.password-grid {

    display: grid;

    grid-template-columns:
        repeat(3,1fr);

    gap: 15px;
}


/* =========================================================
   NOTE
========================================================= */

.security-note {

    margin-top: 15px;

    padding: 13px;

    color: #94a3b8;

    background:
        rgba(255,255,255,.025);

    border-radius: 11px;

    font-size: 7px;

    line-height: 1.6;
}


/* =========================================================
   RESPONSIVE
========================================================= */

@media(max-width:1000px) {

    .settings-grid {

        grid-template-columns: 1fr;
    }

    .card.full {

        grid-column: auto;
    }

    .password-grid {

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

    .header {

        flex-direction: column;

        align-items: flex-start;

        gap: 12px;
    }

    .info-row {

        flex-direction: column;

        align-items: flex-start;
    }

    .info-value {

        text-align: left;
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


        <a href="blotter.php">

            <span class="nav-icon">
                📝
            </span>

            Blotter

        </a>


        <div class="nav-title">
            Account
        </div>


        <a
            href="profile.php"
        >

            <span class="nav-icon">
                👤
            </span>

            My Profile

        </a>


        <a
            href="settings.php"
            class="active"
        >

            <span class="nav-icon">
                ⚙️
            </span>

            Settings

        </a>

    </nav>


    <a
        href="logout.php"
        class="logout"
        onclick="
            return confirm(
                'Are you sure you want to logout?'
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
                Settings
            </h1>

            <p>
                Manage your MAPALADNEXUS administrator account.
            </p>

        </div>


        <div class="admin-user">

            👑

            <?= e(
                $admin['username']
            ) ?>

        </div>

    </header>


    <!-- =====================================================
         MESSAGE
    ====================================================== -->

    <?php if ($message !== ''): ?>

        <div
            class="
                message
                <?= e($messageType) ?>
            "
        >

            <?= e($message) ?>

        </div>

    <?php endif; ?>


    <!-- =====================================================
         SETTINGS GRID
    ====================================================== -->

    <section class="settings-grid">


        <!-- =================================================
             ACCOUNT INFORMATION
        ================================================== -->

        <div class="card">

            <div class="card-header">

                <div class="card-icon">
                    👤
                </div>

                <div>

                    <h2>
                        Account Information
                    </h2>

                    <p>
                        Current administrator account.
                    </p>

                </div>

            </div>


            <div class="account-avatar">
                👑
            </div>


            <div class="account-name">

                <h3>
                    <?= e(
                        $admin['username']
                    ) ?>
                </h3>

                <p>
                    MAPALADNEXUS Administrator
                </p>

            </div>


            <div class="info-list">


                <div class="info-row">

                    <span class="info-label">
                        Account ID
                    </span>

                    <span class="info-value">

                        #<?= e(
                            $admin['id']
                        ) ?>

                    </span>

                </div>


                <div class="info-row">

                    <span class="info-label">
                        Username
                    </span>

                    <span class="info-value">

                        <?= e(
                            $admin['username']
                        ) ?>

                    </span>

                </div>


                <div class="info-row">

                    <span class="info-label">
                        Role
                    </span>

                    <span class="info-value">

                        <?= e(
                            $admin['role']
                        ) ?>

                    </span>

                </div>


                <div class="info-row">

                    <span class="info-label">
                        Status
                    </span>

                    <span class="status">

                        <?= e(
                            $admin['status']
                        ) ?>

                    </span>

                </div>


                <div class="info-row">

                    <span class="info-label">
                        Created
                    </span>

                    <span class="info-value">

                        <?php

                        if (
                            !empty(
                                $admin['created_at']
                            )
                        ) {

                            echo e(
                                date(
                                    'M d, Y',
                                    strtotime(
                                        $admin['created_at']
                                    )
                                )
                            );

                        } else {

                            echo '—';
                        }

                        ?>

                    </span>

                </div>


            </div>

        </div>


        <!-- =================================================
             USERNAME
        ================================================== -->

        <div class="card">

            <div class="card-header">

                <div class="card-icon">
                    ✏️
                </div>

                <div>

                    <h2>
                        Change Username
                    </h2>

                    <p>
                        Update your administrator username.
                    </p>

                </div>

            </div>


            <form
                method="POST"
                autocomplete="off"
            >

                <input
                    type="hidden"
                    name="action"
                    value="update_username"
                >


                <div class="form-group">

                    <label>
                        Current Username
                    </label>

                    <input
                        type="text"
                        value="<?= e(
                            $admin['username']
                        ) ?>"
                        readonly
                    >

                </div>


                <div class="form-group">

                    <label>
                        New Username
                    </label>

                    <input
                        type="text"
                        name="username"
                        minlength="3"
                        maxlength="100"
                        placeholder="Enter new username"
                        required
                    >

                </div>


                <button
                    type="submit"
                    class="btn"
                >

                    💾 Save Username

                </button>

            </form>

        </div>


        <!-- =================================================
             PASSWORD
        ================================================== -->

        <div class="card full">

            <div class="card-header">

                <div class="card-icon">
                    🔐
                </div>

                <div>

                    <h2>
                        Change Password
                    </h2>

                    <p>
                        Secure your administrator account.
                    </p>

                </div>

            </div>


            <form
                method="POST"
                autocomplete="off"
            >

                <input
                    type="hidden"
                    name="action"
                    value="change_password"
                >


                <div class="password-grid">


                    <div class="form-group">

                        <label>
                            Current Password
                        </label>

                        <input
                            type="password"
                            name="current_password"
                            placeholder="Enter current password"
                            autocomplete="current-password"
                            required
                        >

                    </div>


                    <div class="form-group">

                        <label>
                            New Password
                        </label>

                        <input
                            type="password"
                            name="new_password"
                            placeholder="Enter new password"
                            minlength="6"
                            autocomplete="new-password"
                            required
                        >

                    </div>


                    <div class="form-group">

                        <label>
                            Confirm New Password
                        </label>

                        <input
                            type="password"
                            name="confirm_password"
                            placeholder="Confirm new password"
                            minlength="6"
                            autocomplete="new-password"
                            required
                        >

                    </div>


                </div>


                <button
                    type="submit"
                    class="btn"
                >

                    🔐 Update Password

                </button>


                <div class="security-note">

                    🔒 Your password must contain at least
                    6 characters. The system stores newly
                    changed passwords using secure password
                    hashing.

                </div>

            </form>

        </div>


    </section>


</main>

</body>

</html>