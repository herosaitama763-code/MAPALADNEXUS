<?php

session_start();

require_once __DIR__ . '/../config/database.php';

/*
|--------------------------------------------------------------------------
| MAPALADNEXUS
| Resident Login
|--------------------------------------------------------------------------
*/

/*
|--------------------------------------------------------------------------
| If already logged in as Resident
|--------------------------------------------------------------------------
*/

if (
    isset($_SESSION['user_id']) &&
    isset($_SESSION['role']) &&
    $_SESSION['role'] === 'Resident'
) {
    header("Location: user_dashboard.php");
    exit;
}

$error = '';

/*
|--------------------------------------------------------------------------
| Login Process
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    /*
    |--------------------------------------------------------------------------
    | Validation
    |--------------------------------------------------------------------------
    */

    if ($username === '' || $password === '') {

        $error = "Please enter your username and password.";

    } else {

        /*
        |--------------------------------------------------------------------------
        | Find User
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
            LIMIT 1
        ");

        $stmt->bind_param("s", $username);

        $stmt->execute();

        $result = $stmt->get_result();

        /*
        |--------------------------------------------------------------------------
        | User Found
        |--------------------------------------------------------------------------
        */

        if ($result->num_rows === 1) {

            $user = $result->fetch_assoc();

            /*
            |--------------------------------------------------------------------------
            | Check Account Status
            |--------------------------------------------------------------------------
            */

            if ($user['status'] !== 'Active') {

                $error = "Your account is currently inactive. Please contact the barangay administrator.";

            }

            /*
            |--------------------------------------------------------------------------
            | Check Role
            |--------------------------------------------------------------------------
            */

            elseif ($user['role'] !== 'Resident') {

                $error = "This login page is for residents only.";

            }

            /*
            |--------------------------------------------------------------------------
            | Check Password
            |--------------------------------------------------------------------------
            */

            elseif (password_verify($password, $user['password'])) {

                /*
                |--------------------------------------------------------------------------
                | Regenerate Session
                |--------------------------------------------------------------------------
                */

                session_regenerate_id(true);

                $_SESSION['user_id'] = (int) $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];

                /*
                |--------------------------------------------------------------------------
                | Find Resident Profile
                |--------------------------------------------------------------------------
                */

                $resident_stmt = $conn->prepare("
                    SELECT
                        id,
                        user_id,
                        first_name,
                        middle_name,
                        last_name,
                        suffix,
                        purok,
                        email
                    FROM residents
                    WHERE user_id = ?
                    LIMIT 1
                ");

                $resident_stmt->bind_param(
                    "i",
                    $user['id']
                );

                $resident_stmt->execute();

                $resident_result =
                    $resident_stmt->get_result();

                /*
                |--------------------------------------------------------------------------
                | Resident Profile Found
                |--------------------------------------------------------------------------
                */

                if ($resident_result->num_rows === 1) {

                    $resident =
                        $resident_result->fetch_assoc();

                    $_SESSION['resident_id'] =
                        (int) $resident['id'];

                    $_SESSION['resident_name'] =
                        trim(
                            $resident['first_name'] .
                            ' ' .
                            $resident['last_name']
                        );

                    $_SESSION['purok'] =
                        $resident['purok'];

                    /*
                    |--------------------------------------------------------------------------
                    | Successful Login
                    |--------------------------------------------------------------------------
                    |
                    | Dashboard will be created in the next step.
                    |
                    */

                    header(
                        "Location: user_dashboard.php"
                    );

                    exit;

                } else {

                    /*
                    |--------------------------------------------------------------------------
                    | No Resident Profile
                    |--------------------------------------------------------------------------
                    */

                    session_unset();
                    session_destroy();

                    $error =
                        "Your account exists, but your resident profile was not found.";
                }

                $resident_stmt->close();

            } else {

                $error =
                    "Incorrect username or password.";
            }

        } else {

            $error =
                "Incorrect username or password.";
        }

        $stmt->close();
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
    Resident Login | MAPALADNEXUS
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

    display: flex;

    align-items: center;

    justify-content: center;

    padding: 25px;

    font-family:
        Arial,
        Helvetica,
        sans-serif;

    color: #ffffff;

    overflow-x: hidden;

    background:
        radial-gradient(
            circle at 15% 15%,
            rgba(37, 99, 235, 0.40),
            transparent 32%
        ),

        radial-gradient(
            circle at 85% 25%,
            rgba(124, 58, 237, 0.35),
            transparent 30%
        ),

        radial-gradient(
            circle at 50% 100%,
            rgba(16, 185, 129, 0.18),
            transparent 35%
        ),

        #050816;
}

/* =========================================================
   BACKGROUND
========================================================= */

.background {

    position: fixed;

    inset: 0;

    pointer-events: none;

    overflow: hidden;

    z-index: -1;
}

.grid {

    position: absolute;

    inset: 0;

    background-image:

        linear-gradient(
            rgba(255,255,255,0.035) 1px,
            transparent 1px
        ),

        linear-gradient(
            90deg,
            rgba(255,255,255,0.035) 1px,
            transparent 1px
        );

    background-size: 55px 55px;

    mask-image:
        linear-gradient(
            to bottom,
            black,
            transparent
        );
}

.orb {

    position: absolute;

    border-radius: 50%;

    filter: blur(4px);

    opacity: 0.30;

    animation:
        floating 8s ease-in-out infinite;
}

.orb.one {

    width: 280px;

    height: 280px;

    background: #2563eb;

    left: -100px;

    top: 10%;
}

.orb.two {

    width: 330px;

    height: 330px;

    background: #7c3aed;

    right: -120px;

    bottom: 5%;

    animation-delay: 2s;
}

@keyframes floating {

    0%,
    100% {
        transform:
            translate3d(0, 0, 0);
    }

    50% {
        transform:
            translate3d(20px, -30px, 0);
    }
}

/* =========================================================
   LOGIN WRAPPER
========================================================= */

.login-wrapper {

    width: 100%;

    max-width: 470px;

    perspective: 1200px;
}

/* =========================================================
   LOGIN CARD
========================================================= */

.login-card {

    position: relative;

    padding: 42px 38px;

    border-radius: 32px;

    background:
        linear-gradient(
            145deg,
            rgba(255,255,255,0.12),
            rgba(255,255,255,0.045)
        );

    border:
        1px solid rgba(255,255,255,0.15);

    backdrop-filter:
        blur(28px);

    box-shadow:

        30px 35px 80px
        rgba(0,0,0,0.45),

        inset 2px 2px 8px
        rgba(255,255,255,0.10),

        inset -2px -2px 8px
        rgba(0,0,0,0.12);

    transform:
        rotateX(2deg);

    animation:
        cardEntrance
        0.8s
        ease-out;
}

@keyframes cardEntrance {

    from {

        opacity: 0;

        transform:
            translateY(35px)
            rotateX(8deg)
            scale(0.97);
    }

    to {

        opacity: 1;

        transform:
            translateY(0)
            rotateX(2deg)
            scale(1);
    }
}

/* =========================================================
   LOGO
========================================================= */

.logo {

    width: 88px;

    height: 88px;

    margin:
        0 auto 22px;

    display: flex;

    align-items: center;

    justify-content: center;

    border-radius: 27px;

    font-size: 42px;

    background:
        linear-gradient(
            145deg,
            rgba(96,165,250,0.35),
            rgba(99,102,241,0.08)
        );

    border:
        1px solid rgba(255,255,255,0.15);

    box-shadow:

        18px 18px 40px
        rgba(0,0,0,0.35),

        inset 3px 3px 8px
        rgba(255,255,255,0.12);

    transform:
        translateZ(30px);
}

/* =========================================================
   HEADER
========================================================= */

.header {

    text-align: center;
}

.header h1 {

    font-size: 29px;

    letter-spacing: 1px;

    background:
        linear-gradient(
            120deg,
            #ffffff,
            #c7d2fe,
            #60a5fa
        );

    -webkit-background-clip: text;

    background-clip: text;

    color: transparent;
}

.header p {

    margin-top: 9px;

    color: #94a3b8;

    font-size: 13px;
}

/* =========================================================
   ERROR
========================================================= */

.error {

    margin-top: 24px;

    padding: 14px 16px;

    border-radius: 15px;

    background:
        rgba(239,68,68,0.12);

    border:
        1px solid rgba(239,68,68,0.30);

    color: #fca5a5;

    font-size: 13px;

    line-height: 1.5;

    text-align: left;
}

/* =========================================================
   FORM
========================================================= */

form {

    margin-top: 27px;
}

.field {

    margin-bottom: 19px;
}

.field label {

    display: block;

    margin-bottom: 8px;

    color: #cbd5e1;

    font-size: 12px;

    font-weight: 600;
}

.input-box {

    position: relative;
}

.input-icon {

    position: absolute;

    left: 15px;

    top: 50%;

    transform:
        translateY(-50%);

    font-size: 17px;

    opacity: 0.75;

    pointer-events: none;
}

input {

    width: 100%;

    padding:
        15px
        15px
        15px
        45px;

    border-radius: 15px;

    outline: none;

    color: white;

    background:
        rgba(0,0,0,0.22);

    border:
        1px solid
        rgba(255,255,255,0.12);

    font-size: 14px;

    transition:
        0.25s ease;

    box-shadow:
        inset 0 2px 7px
        rgba(0,0,0,0.15);
}

input::placeholder {

    color: #64748b;
}

input:focus {

    border-color:
        rgba(96,165,250,0.75);

    background:
        rgba(0,0,0,0.28);

    box-shadow:

        0 0 0 3px
        rgba(96,165,250,0.10),

        inset 0 2px 7px
        rgba(0,0,0,0.15);
}

/* =========================================================
   PASSWORD TOGGLE
========================================================= */

.password-toggle {

    position: absolute;

    right: 13px;

    top: 50%;

    transform:
        translateY(-50%);

    border: none;

    background: transparent;

    color: #94a3b8;

    cursor: pointer;

    font-size: 16px;

    padding: 5px;
}

.password-toggle:hover {

    color: white;
}

/* =========================================================
   LOGIN BUTTON
========================================================= */

.login-button {

    width: 100%;

    margin-top: 5px;

    padding: 16px;

    border: none;

    border-radius: 16px;

    cursor: pointer;

    color: white;

    font-size: 14px;

    font-weight: bold;

    background:
        linear-gradient(
            145deg,
            #2563eb,
            #4f46e5
        );

    box-shadow:

        0 17px 35px
        rgba(37,99,235,0.32),

        inset 0 1px 1px
        rgba(255,255,255,0.25);

    transition:
        0.25s ease;
}

.login-button:hover {

    transform:
        translateY(-4px);

    box-shadow:

        0 22px 40px
        rgba(37,99,235,0.40);
}

.login-button:active {

    transform:
        translateY(0);
}

/* =========================================================
   LINKS
========================================================= */

.links {

    margin-top: 25px;

    text-align: center;

    display: flex;

    flex-direction: column;

    gap: 13px;
}

.links a {

    color: #93c5fd;

    text-decoration: none;

    font-size: 13px;

    transition: 0.2s;
}

.links a:hover {

    color: white;
}

.back {

    color: #94a3b8 !important;
}

/* =========================================================
   SECURITY NOTE
========================================================= */

.security {

    margin-top: 28px;

    padding-top: 20px;

    border-top:
        1px solid
        rgba(255,255,255,0.08);

    text-align: center;

    color: #64748b;

    font-size: 11px;

    line-height: 1.6;
}

/* =========================================================
   MOBILE
========================================================= */

@media (max-width: 520px) {

    body {
        padding: 15px;
    }

    .login-card {

        padding:
            32px
            22px;

        border-radius: 26px;
    }

    .logo {

        width: 78px;

        height: 78px;

        font-size: 36px;
    }

    .header h1 {

        font-size: 25px;
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

        <!-- LOGO -->

        <div class="logo">
            🏛️
        </div>

        <!-- HEADER -->

        <div class="header">

            <h1>
                Resident Portal
            </h1>

            <p>
                Barangay Mapalad · MAPALADNEXUS
            </p>

        </div>

        <!-- ERROR -->

        <?php if ($error !== ''): ?>

            <div class="error">

                ⚠️
                <?= htmlspecialchars($error) ?>

            </div>

        <?php endif; ?>

        <!-- LOGIN FORM -->

        <form method="POST">

            <!-- USERNAME -->

            <div class="field">

                <label for="username">
                    Username
                </label>

                <div class="input-box">

                    <span class="input-icon">
                        👤
                    </span>

                    <input
                        type="text"
                        id="username"
                        name="username"
                        placeholder="Enter your username"
                        autocomplete="username"
                        value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                        required
                    >

                </div>

            </div>

            <!-- PASSWORD -->

            <div class="field">

                <label for="password">
                    Password
                </label>

                <div class="input-box">

                    <span class="input-icon">
                        🔐
                    </span>

                    <input
                        type="password"
                        id="password"
                        name="password"
                        placeholder="Enter your password"
                        autocomplete="current-password"
                        required
                    >

                    <button
                        type="button"
                        class="password-toggle"
                        onclick="togglePassword()"
                        aria-label="Show password"
                    >
                        👁️
                    </button>

                </div>

            </div>

            <!-- LOGIN -->

            <button
                type="submit"
                class="login-button"
            >
                Login to Resident Portal
            </button>

        </form>

        <!-- LINKS -->

        <div class="links">

            <a href="register.php">
                ✨ Create a Resident Account
            </a>

            <a
                href="../index.php"
                class="back"
            >
                ← Back to MAPALADNEXUS
            </a>

        </div>

        <!-- SECURITY -->

        <div class="security">

            🔒 Your account information is protected
            by the MAPALADNEXUS authentication system.

        </div>

    </div>

</div>

<script>

function togglePassword() {

    const password =
        document.getElementById('password');

    const button =
        document.querySelector('.password-toggle');

    if (password.type === 'password') {

        password.type = 'text';

        button.textContent = '🙈';

    } else {

        password.type = 'password';

        button.textContent = '👁️';
    }
}

</script>

</body>

</html>