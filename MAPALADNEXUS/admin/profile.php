<?php
session_start();

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../config/database.php';

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

$username = $_SESSION['username'];
$message = '';
$messageType = '';

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
| GET CURRENT ADMIN
|--------------------------------------------------------------------------
*/

$admin = null;

$stmt = $conn->prepare("
    SELECT id, username, role, status
    FROM users
    WHERE username = ?
    LIMIT 1
");

if ($stmt) {

    $stmt->bind_param("s", $username);
    $stmt->execute();

    $result = $stmt->get_result();
    $admin = $result->fetch_assoc();

    $stmt->close();
}

if (!$admin) {
    $admin = [
        'id' => 0,
        'username' => $username,
        'role' => $_SESSION['role'] ?? 'Admin',
        'status' => 'Active'
    ];
}

/*
|--------------------------------------------------------------------------
| UPDATE USERNAME
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['action']) &&
    $_POST['action'] === 'update_profile'
) {

    $newUsername =
        trim($_POST['username'] ?? '');

    if ($newUsername === '') {

        $message =
            "Username cannot be empty.";

        $messageType = 'error';

    } elseif (strlen($newUsername) < 3) {

        $message =
            "Username must be at least 3 characters.";

        $messageType = 'error';

    } else {

        /*
        | Check duplicate username
        */

        $check = $conn->prepare("
            SELECT id
            FROM users
            WHERE username = ?
            AND id != ?
            LIMIT 1
        ");

        if ($check) {

            $adminId = (int)$admin['id'];

            $check->bind_param(
                "si",
                $newUsername,
                $adminId
            );

            $check->execute();

            $duplicate =
                $check->get_result()->fetch_assoc();

            $check->close();

            if ($duplicate) {

                $message =
                    "Username is already taken.";

                $messageType = 'error';

            } else {

                $update = $conn->prepare("
                    UPDATE users
                    SET username = ?
                    WHERE id = ?
                ");

                if ($update) {

                    $update->bind_param(
                        "si",
                        $newUsername,
                        $adminId
                    );

                    if ($update->execute()) {

                        $_SESSION['username'] =
                            $newUsername;

                        $username =
                            $newUsername;

                        $admin['username'] =
                            $newUsername;

                        $message =
                            "Profile successfully updated.";

                        $messageType = 'success';

                    } else {

                        $message =
                            "Unable to update profile.";

                        $messageType = 'error';
                    }

                    $update->close();

                } else {

                    $message =
                        "Database error.";

                    $messageType = 'error';
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

    if (
        $currentPassword === '' ||
        $newPassword === '' ||
        $confirmPassword === ''
    ) {

        $message =
            "Please complete all password fields.";

        $messageType = 'error';

    } elseif (
        $newPassword !== $confirmPassword
    ) {

        $message =
            "New passwords do not match.";

        $messageType = 'error';

    } elseif (
        strlen($newPassword) < 6
    ) {

        $message =
            "New password must be at least 6 characters.";

        $messageType = 'error';

    } else {

        /*
        |--------------------------------------------------------------------------
        | GET PASSWORD
        |--------------------------------------------------------------------------
        */

        $stmt = $conn->prepare("
            SELECT password
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

            $row =
                $stmt->get_result()
                     ->fetch_assoc();

            $stmt->close();

            if (!$row) {

                $message =
                    "Admin account was not found.";

                $messageType = 'error';

            } else {

                /*
                |--------------------------------------------------------------------------
                | SUPPORT BOTH HASHED AND PLAIN PASSWORD
                |--------------------------------------------------------------------------
                */

                $storedPassword =
                    $row['password'];

                $validPassword = false;

                if (
                    password_verify(
                        $currentPassword,
                        $storedPassword
                    )
                ) {

                    $validPassword = true;

                } elseif (
                    $currentPassword ===
                    $storedPassword
                ) {

                    $validPassword = true;
                }


                if (!$validPassword) {

                    $message =
                        "Current password is incorrect.";

                    $messageType = 'error';

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

                    $update =
                        $conn->prepare("
                            UPDATE users
                            SET password = ?
                            WHERE id = ?
                        ");

                    if ($update) {

                        $update->bind_param(
                            "si",
                            $hashedPassword,
                            $adminId
                        );

                        if ($update->execute()) {

                            $message =
                                "Password successfully changed.";

                            $messageType =
                                'success';

                        } else {

                            $message =
                                "Unable to change password.";

                            $messageType =
                                'error';
                        }

                        $update->close();

                    } else {

                        $message =
                            "Database error.";

                        $messageType =
                            'error';
                    }
                }
            }
        }
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
MAPALADNEXUS | Admin Profile
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

/* SIDEBAR */

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

    display:flex;
    flex-direction:column;

    z-index:10;
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

/* MESSAGE */

.message{

    margin-bottom:20px;

    padding:14px 16px;

    border-radius:12px;

    font-size:9px;
}

.message.success{

    color:#bbf7d0;

    background:
        rgba(34,197,94,.09);

    border:
        1px solid rgba(34,197,94,.18);
}

.message.error{

    color:#fecaca;

    background:
        rgba(239,68,68,.09);

    border:
        1px solid rgba(239,68,68,.18);
}

/* GRID */

.profile-grid{

    display:grid;

    grid-template-columns:
        1fr 1fr;

    gap:20px;
}

/* CARD */

.card{

    padding:25px;

    background:
        rgba(255,255,255,.045);

    border:
        1px solid rgba(255,255,255,.08);

    border-radius:22px;

    backdrop-filter:blur(20px);

    box-shadow:
        0 20px 60px rgba(0,0,0,.15);
}

.card-header{

    display:flex;

    align-items:center;

    gap:14px;

    margin-bottom:22px;
}

.card-icon{

    width:48px;
    height:48px;

    display:flex;

    align-items:center;
    justify-content:center;

    border-radius:14px;

    background:
        rgba(37,99,235,.15);

    font-size:22px;
}

.card h2{

    font-size:15px;
}

.card p{

    margin-top:5px;

    color:#64748b;

    font-size:8px;
}

/* PROFILE */

.profile-avatar{

    width:100px;
    height:100px;

    margin:0 auto 18px;

    display:flex;

    align-items:center;
    justify-content:center;

    border-radius:50%;

    background:
        linear-gradient(
            145deg,
            #2563eb,
            #4f46e5
        );

    font-size:40px;

    box-shadow:
        0 15px 40px rgba(37,99,235,.25);
}

.info{

    display:grid;

    gap:10px;

    margin-bottom:22px;
}

.info-row{

    display:flex;

    justify-content:space-between;

    gap:20px;

    padding:13px;

    background:
        rgba(255,255,255,.035);

    border-radius:11px;
}

.info-label{

    color:#64748b;

    font-size:8px;
}

.info-value{

    color:#e2e8f0;

    font-size:9px;

    font-weight:bold;
}

.active-badge{

    padding:
        5px 9px;

    color:#bbf7d0;

    background:
        rgba(34,197,94,.10);

    border-radius:8px;

    font-size:7px;
}

/* FORM */

.form-group{

    margin-bottom:15px;
}

.form-group label{

    display:block;

    margin-bottom:7px;

    color:#94a3b8;

    font-size:8px;

    font-weight:bold;
}

.form-group input{

    width:100%;

    padding:12px 13px;

    color:#fff;

    background:
        rgba(255,255,255,.045);

    border:
        1px solid rgba(255,255,255,.09);

    border-radius:11px;

    outline:none;

    font-size:9px;
}

.form-group input:focus{

    border-color:
        rgba(59,130,246,.60);
}

.btn{

    width:100%;

    padding:12px;

    border:0;

    border-radius:11px;

    cursor:pointer;

    color:#fff;

    background:
        linear-gradient(
            145deg,
            #2563eb,
            #4f46e5
        );

    font-size:9px;

    font-weight:bold;
}

.security-note{

    margin-top:15px;

    padding:12px;

    color:#94a3b8;

    background:
        rgba(255,255,255,.025);

    border-radius:10px;

    font-size:7px;

    line-height:1.6;
}

/* RESPONSIVE */

@media(max-width:900px){

    .sidebar{
        display:none;
    }

    .main{

        margin-left:0;

        padding:20px;
    }

    .profile-grid{

        grid-template-columns:1fr;
    }
}

@media(max-width:600px){

    .header{

        flex-direction:column;

        align-items:flex-start;

        gap:12px;
    }

    .info-row{

        flex-direction:column;

        gap:5px;
    }
}

</style>

</head>

<body>

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
            <span class="nav-icon">🏠</span>
            Dashboard
        </a>

        <a href="residents.php">
            <span class="nav-icon">👥</span>
            Residents
        </a>

        <a href="services.php">
            <span class="nav-icon">🛠️</span>
            Services
        </a>

        <a href="requests.php">
            <span class="nav-icon">📋</span>
            Service Requests
        </a>

        <a href="announcements.php">
            <span class="nav-icon">📢</span>
            Announcements
        </a>

        <a href="complaints.php">
            <span class="nav-icon">💬</span>
            Complaints
        </a>

        <a href="reports.php">
            <span class="nav-icon">📊</span>
            Reports
        </a>

        <a href="blotter.php">
            <span class="nav-icon">📝</span>
            Blotter
        </a>

        <div class="nav-title">
            Account
        </div>

        <a
            href="profile.php"
            class="active"
        >
            <span class="nav-icon">👤</span>
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


<!-- MAIN -->

<main class="main">

    <header class="header">

        <div>

            <h1>
                My Profile
            </h1>

            <p>
                Manage your administrator account.
            </p>

        </div>

        <div class="admin">

            👑

            <?= e(
                $admin['username']
            ) ?>

        </div>

    </header>


    <?php if ($message !== ''): ?>

        <div
            class="message <?= e($messageType) ?>"
        >

            <?= e($message) ?>

        </div>

    <?php endif; ?>


    <section class="profile-grid">


        <!-- ACCOUNT INFORMATION -->

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
                        Your administrator account details.
                    </p>

                </div>

            </div>


            <div class="profile-avatar">
                👑
            </div>


            <div class="info">

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

                    <span class="active-badge">

                        <?= e(
                            $admin['status']
                        ) ?>

                    </span>

                </div>


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

            </div>

        </div>


        <!-- UPDATE USERNAME -->

        <div class="card">

            <div class="card-header">

                <div class="card-icon">
                    ✏️
                </div>

                <div>

                    <h2>
                        Update Username
                    </h2>

                    <p>
                        Change your administrator username.
                    </p>

                </div>

            </div>


            <form
                method="POST"
            >

                <input
                    type="hidden"
                    name="action"
                    value="update_profile"
                >


                <div class="form-group">

                    <label>
                        Username
                    </label>

                    <input
                        type="text"
                        name="username"
                        value="<?= e(
                            $admin['username']
                        ) ?>"
                        minlength="3"
                        maxlength="100"
                        required
                    >

                </div>


                <button
                    type="submit"
                    class="btn"
                >

                    💾 Update Username

                </button>

            </form>

        </div>


        <!-- CHANGE PASSWORD -->

        <div
            class="card"
            style="grid-column:1 / -1;"
        >

            <div class="card-header">

                <div class="card-icon">
                    🔐
                </div>

                <div>

                    <h2>
                        Change Password
                    </h2>

                    <p>
                        Update your administrator password securely.
                    </p>

                </div>

            </div>


            <form
                method="POST"
            >

                <input
                    type="hidden"
                    name="action"
                    value="change_password"
                >


                <div
                    style="
                        display:grid;
                        grid-template-columns:
                            repeat(3,1fr);
                        gap:15px;
                    "
                >

                    <div class="form-group">

                        <label>
                            Current Password
                        </label>

                        <input
                            type="password"
                            name="current_password"
                            required
                            autocomplete="current-password"
                        >

                    </div>


                    <div class="form-group">

                        <label>
                            New Password
                        </label>

                        <input
                            type="password"
                            name="new_password"
                            required
                            minlength="6"
                            autocomplete="new-password"
                        >

                    </div>


                    <div class="form-group">

                        <label>
                            Confirm New Password
                        </label>

                        <input
                            type="password"
                            name="confirm_password"
                            required
                            minlength="6"
                            autocomplete="new-password"
                        >

                    </div>

                </div>


                <button
                    type="submit"
                    class="btn"
                >

                    🔐 Change Password

                </button>


                <div class="security-note">

                    🔒 For security, your new password must
                    contain at least 6 characters.

                </div>

            </form>

        </div>


    </section>

</main>

</body>
</html>