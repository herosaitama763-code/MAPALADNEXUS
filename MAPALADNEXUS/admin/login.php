<?php

session_start();

require_once __DIR__ . '/../config/database.php';

/*
|--------------------------------------------------------------------------
| MAPALADNEXUS
| ADMIN LOGIN
| Barangay Mapalad
|--------------------------------------------------------------------------
| Default Admin:
| Username: admin
| Password: admin123
|--------------------------------------------------------------------------
*/

/*
|--------------------------------------------------------------------------
| IF ALREADY LOGGED IN
|--------------------------------------------------------------------------
*/

if (
    isset($_SESSION['user_id']) &&
    isset($_SESSION['role']) &&
    $_SESSION['role'] === 'Admin'
) {
    header("Location: dashboard.php");
    exit;
}

$error = '';

/*
|--------------------------------------------------------------------------
| LOGIN PROCESS
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {

        $error = "Please enter your username and password.";

    } else {

        /*
        |--------------------------------------------------------------------------
        | GET ADMIN ACCOUNT
        |--------------------------------------------------------------------------
        */

        $stmt = $conn->prepare("
            SELECT
                id,
                username,
                password,
                role,
                status
            FROM users
            WHERE username = ?
            AND role = 'Admin'
            LIMIT 1
        ");

        if (!$stmt) {

            $error = "Database error: " . $conn->error;

        } else {

            $stmt->bind_param("s", $username);

            $stmt->execute();

            $result = $stmt->get_result();

            if ($result->num_rows === 1) {

                $user = $result->fetch_assoc();

                /*
                |--------------------------------------------------------------------------
                | CHECK STATUS
                |--------------------------------------------------------------------------
                */

                if ($user['status'] !== 'Active') {

                    $error = "This Admin account is inactive.";

                } else {

                    $password_valid = false;

                    /*
                    |--------------------------------------------------------------------------
                    | NORMAL HASHED PASSWORD
                    |--------------------------------------------------------------------------
                    */

                    if (
                        password_verify(
                            $password,
                            $user['password']
                        )
                    ) {

                        $password_valid = true;
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | DEFAULT ADMIN LOGIN
                    |--------------------------------------------------------------------------
                    |
                    | Allows:
                    |
                    | Username: admin
                    | Password: admin123
                    |
                    | Then automatically saves a secure password hash.
                    |--------------------------------------------------------------------------
                    */

                    if (
                        strtolower($username) === 'admin' &&
                        $password === 'admin123'
                    ) {

                        $password_valid = true;

                        /*
                        |--------------------------------------------------------------------------
                        | AUTOMATICALLY SECURE PASSWORD
                        |--------------------------------------------------------------------------
                        */

                        $new_hash = password_hash(
                            'admin123',
                            PASSWORD_DEFAULT
                        );

                        $update = $conn->prepare("
                            UPDATE users
                            SET password = ?
                            WHERE id = ?
                            AND role = 'Admin'
                        ");

                        if ($update) {

                            $admin_id = (int)$user['id'];

                            $update->bind_param(
                                "si",
                                $new_hash,
                                $admin_id
                            );

                            $update->execute();

                            $update->close();
                        }
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | SUCCESS
                    |--------------------------------------------------------------------------
                    */

                    if ($password_valid) {

                        /*
                        | Prevent session fixation
                        */

                        session_regenerate_id(true);

                        /*
                        | Admin session
                        */

                        $_SESSION['user_id'] =
                            (int)$user['id'];

                        $_SESSION['username'] =
                            $user['username'];

                        $_SESSION['role'] =
                            'Admin';

                        /*
                        |--------------------------------------------------------------------------
                        | ADMIN DASHBOARD
                        |--------------------------------------------------------------------------
                        */

                        header("Location: dashboard.php");
                        exit;

                    } else {

                        $error =
                            "Incorrect username or password.";
                    }
                }

            } else {

                $error =
                    "Admin account not found.";
            }

            $stmt->close();
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
    Admin Login | MAPALADNEXUS
</title>

<style>

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {

    min-height: 100vh;

    display: flex;

    justify-content: center;

    align-items: center;

    padding: 20px;

    font-family:
        Arial,
        Helvetica,
        sans-serif;

    color: white;

    background:
        radial-gradient(
            circle at 15% 15%,
            rgba(37,99,235,.28),
            transparent 32%
        ),
        radial-gradient(
            circle at 85% 85%,
            rgba(124,58,237,.25),
            transparent 32%
        ),
        #050816;

    overflow: hidden;
}

/* =========================================================
   BACKGROUND
========================================================= */

.background {

    position: fixed;

    inset: 0;

    z-index: -1;

    overflow: hidden;
}

.grid {

    position: absolute;

    inset: 0;

    background-image:
        linear-gradient(
            rgba(255,255,255,.025) 1px,
            transparent 1px
        ),
        linear-gradient(
            90deg,
            rgba(255,255,255,.025) 1px,
            transparent 1px
        );

    background-size: 55px 55px;
}

.orb {

    position: absolute;

    border-radius: 50%;

    filter: blur(35px);

    opacity: .22;

    animation: float 9s ease-in-out infinite;
}

.orb.one {

    width: 380px;
    height: 380px;

    left: -180px;
    top: 5%;

    background: #2563eb;
}

.orb.two {

    width: 420px;
    height: 420px;

    right: -180px;
    bottom: -130px;

    background: #7c3aed;

    animation-delay: 2s;
}

@keyframes float {

    0%, 100% {
        transform: translate(0,0);
    }

    50% {
        transform: translate(25px,-25px);
    }
}

/* =========================================================
   LOGIN WRAPPER
========================================================= */

.login-wrapper {

    width: 100%;

    max-width: 450px;

    perspective: 1200px;
}

/* =========================================================
   LOGIN CARD
========================================================= */

.login-card {

    padding: 40px;

    border:
        1px solid
        rgba(255,255,255,.10);

    border-radius: 30px;

    background:
        linear-gradient(
            145deg,
            rgba(255,255,255,.09),
            rgba(255,255,255,.025)
        );

    backdrop-filter: blur(25px);

    box-shadow:
        20px 30px 75px
        rgba(0,0,0,.50);

    transform:
        perspective(1000px)
        rotateX(2deg)
        rotateY(-2deg);

    transition: .35s;
}

.login-card:hover {

    transform:
        perspective(1000px)
        rotateX(0deg)
        rotateY(0deg)
        translateY(-5px);
}

/* =========================================================
   LOGO
========================================================= */

.logo {

    width: 82px;
    height: 82px;

    margin: 0 auto 22px;

    display: flex;

    align-items: center;
    justify-content: center;

    border-radius: 25px;

    background:
        linear-gradient(
            145deg,
            #2563eb,
            #4f46e5
        );

    box-shadow:
        0 18px 38px
        rgba(37,99,235,.30);

    font-size: 38px;

    transform:
        perspective(600px)
        rotateX(7deg);
}

/* =========================================================
   BRAND
========================================================= */

.brand {

    text-align: center;

    margin-bottom: 30px;
}

.brand h1 {

    font-size: 25px;

    letter-spacing: .7px;
}

.brand p {

    margin-top: 8px;

    color: #64748b;

    font-size: 10px;

    letter-spacing: 1.4px;

    text-transform: uppercase;
}

.admin-badge {

    display: inline-block;

    margin-top: 14px;

    padding: 8px 15px;

    color: #bfdbfe;

    background:
        rgba(37,99,235,.10);

    border:
        1px solid
        rgba(96,165,250,.17);

    border-radius: 20px;

    font-size: 8px;

    font-weight: bold;

    letter-spacing: 1.3px;

    text-transform: uppercase;
}

/* =========================================================
   ERROR
========================================================= */

.error {

    margin-bottom: 20px;

    padding: 14px 16px;

    color: #fecaca;

    background:
        rgba(239,68,68,.10);

    border:
        1px solid
        rgba(239,68,68,.20);

    border-radius: 14px;

    font-size: 9px;

    line-height: 1.6;
}

/* =========================================================
   FORM
========================================================= */

.form-group {

    margin-bottom: 20px;
}

.form-group label {

    display: block;

    margin-bottom: 9px;

    color: #94a3b8;

    font-size: 9px;

    font-weight: bold;

    letter-spacing: .5px;
}

.input-wrapper {

    position: relative;
}

.input-icon {

    position: absolute;

    left: 15px;

    top: 50%;

    transform: translateY(-50%);

    color: #64748b;

    font-size: 15px;

    pointer-events: none;
}

.form-control {

    width: 100%;

    padding:
        15px
        15px
        15px
        44px;

    color: white;

    outline: none;

    background:
        rgba(2,6,23,.65);

    border:
        1px solid
        rgba(255,255,255,.09);

    border-radius: 14px;

    font-family: inherit;

    font-size: 11px;

    transition: .25s;
}

.form-control::placeholder {

    color: #475569;
}

.form-control:focus {

    border-color:
        rgba(96,165,250,.60);

    box-shadow:
        0 0 0 3px
        rgba(37,99,235,.12);
}

/* =========================================================
   LOGIN BUTTON
========================================================= */

.login-btn {

    width: 100%;

    margin-top: 3px;

    padding: 16px;

    color: white;

    border: none;

    border-radius: 14px;

    background:
        linear-gradient(
            145deg,
            #2563eb,
            #4f46e5
        );

    box-shadow:
        0 14px 30px
        rgba(37,99,235,.28);

    cursor: pointer;

    font-size: 10px;

    font-weight: bold;

    letter-spacing: .6px;

    transition: .25s;
}

.login-btn:hover {

    transform:
        translateY(-2px);

    box-shadow:
        0 20px 38px
        rgba(37,99,235,.38);
}

.login-btn:active {

    transform:
        translateY(0);
}

/* =========================================================
   SECURITY
========================================================= */

.security {

    margin-top: 18px;

    text-align: center;

    color: #475569;

    font-size: 8px;
}

/* =========================================================
   FOOTER
========================================================= */

.footer {

    margin-top: 27px;

    padding-top: 20px;

    text-align: center;

    border-top:
        1px solid
        rgba(255,255,255,.06);

    color: #475569;

    font-size: 8px;

    line-height: 1.8;
}

.footer strong {

    color: #64748b;
}

/* =========================================================
   MOBILE
========================================================= */

@media (max-width: 500px) {

    .login-card {

        padding: 30px 23px;

        border-radius: 24px;

        transform: none;
    }

    .logo {

        width: 70px;
        height: 70px;

        border-radius: 21px;

        font-size: 32px;
    }

    .brand h1 {

        font-size: 21px;
    }
}

</style>

</head>

<body>

<div class="background">

    <div class="grid"></div>

    <div class="orb one"></div>

    <div class="orb two"></div>

</div>

<div class="login-wrapper">

    <div class="login-card">

        <div class="logo">
            🏛️
        </div>

        <div class="brand">

            <h1>
                MAPALADNEXUS
            </h1>

            <p>
                Barangay Mapalad
            </p>

            <span class="admin-badge">
                Administrator Portal
            </span>

        </div>

        <?php if ($error !== ''): ?>

            <div class="error">

                ⚠️

                <?= htmlspecialchars(
                    $error,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>

            </div>

        <?php endif; ?>

        <form
            method="POST"
            action=""
            autocomplete="off"
        >

            <div class="form-group">

                <label>
                    ADMIN USERNAME
                </label>

                <div class="input-wrapper">

                    <span class="input-icon">
                        👤
                    </span>

                    <input
                        type="text"
                        name="username"
                        class="form-control"
                        placeholder="Enter admin username"
                        required
                        autocomplete="username"
                        value="<?= htmlspecialchars(
                            $_POST['username'] ?? '',
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"
                    >

                </div>

            </div>

            <div class="form-group">

                <label>
                    PASSWORD
                </label>

                <div class="input-wrapper">

                    <span class="input-icon">
                        🔒
                    </span>

                    <input
                        type="password"
                        name="password"
                        class="form-control"
                        placeholder="Enter admin password"
                        required
                        autocomplete="current-password"
                    >

                </div>

            </div>

            <button
                type="submit"
                class="login-btn"
            >
                🔐
                SIGN IN TO ADMIN PANEL
            </button>

        </form>

        <div class="security">
            🔒 Secure Administrator Access
        </div>

        <div class="footer">

            <strong>
                MAPALADNEXUS
            </strong>

            <br>

            Smart Barangay Management System

            <br>

            Barangay Mapalad

        </div>

    </div>

</div>

</body>

</html>