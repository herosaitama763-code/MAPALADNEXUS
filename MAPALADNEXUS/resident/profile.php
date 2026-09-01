<?php

session_start();

require_once __DIR__ . '/../config/database.php';

/*
|--------------------------------------------------------------------------
| MAPALADNEXUS
| RESIDENT PROFILE
| Barangay Mapalad
|--------------------------------------------------------------------------
*/

if (
    !isset($_SESSION['user_id']) ||
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'Resident'
) {
    header("Location: login.php");
    exit;
}

$user_id = (int) $_SESSION['user_id'];

function e($value)
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}

$message = '';
$message_type = '';

/*
|--------------------------------------------------------------------------
| GET USER + RESIDENT
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT
        u.id AS user_id,
        u.username,
        u.status,
        u.created_at AS account_created,
        r.id AS resident_id,
        r.first_name,
        r.middle_name,
        r.last_name,
        r.suffix,
        r.birth_date,
        r.gender,
        r.civil_status,
        r.purok,
        r.address
    FROM users u
    LEFT JOIN residents r
        ON r.user_id = u.id
    WHERE u.id = ?
    LIMIT 1
");

$stmt->bind_param(
    "i",
    $user_id
);

$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows !== 1) {

    session_unset();
    session_destroy();

    header("Location: login.php");
    exit;
}

$profile = $result->fetch_assoc();

if (empty($profile['resident_id'])) {

    $message =
        "Resident profile information was not found.";

    $message_type = "error";
}

/*
|--------------------------------------------------------------------------
| UPDATE PROFILE
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['update_profile']) &&
    !empty($profile['resident_id'])
) {

    $first_name =
        trim($_POST['first_name'] ?? '');

    $middle_name =
        trim($_POST['middle_name'] ?? '');

    $last_name =
        trim($_POST['last_name'] ?? '');

    $suffix =
        trim($_POST['suffix'] ?? '');

    $birth_date =
        trim($_POST['birth_date'] ?? '');

    $gender =
        trim($_POST['gender'] ?? '');

    $civil_status =
        trim($_POST['civil_status'] ?? '');

    $purok =
        trim($_POST['purok'] ?? '');

    $address =
        trim($_POST['address'] ?? '');

    /*
    |--------------------------------------------------------------------------
    | VALIDATION
    |--------------------------------------------------------------------------
    */

    if (
        $first_name === '' ||
        $last_name === ''
    ) {

        $message =
            "First name and last name are required.";

        $message_type = "error";

    } else {

        /*
        |--------------------------------------------------------------------------
        | UPDATE RESIDENT
        |--------------------------------------------------------------------------
        */

        $stmt = $conn->prepare("
            UPDATE residents
            SET
                first_name = ?,
                middle_name = ?,
                last_name = ?,
                suffix = ?,
                birth_date = NULLIF(?, ''),
                gender = ?,
                civil_status = ?,
                purok = ?,
                address = ?
            WHERE id = ?
            AND user_id = ?
        ");

        $stmt->bind_param(
            "sssssssssii",
            $first_name,
            $middle_name,
            $last_name,
            $suffix,
            $birth_date,
            $gender,
            $civil_status,
            $purok,
            $address,
            $profile['resident_id'],
            $user_id
        );

        if ($stmt->execute()) {

            $message =
                "Your profile has been updated successfully.";

            $message_type = "success";

            /*
            |--------------------------------------------------------------------------
            | REFRESH PROFILE DATA
            |--------------------------------------------------------------------------
            */

            $stmt = $conn->prepare("
                SELECT
                    u.id AS user_id,
                    u.username,
                    u.status,
                    u.created_at AS account_created,
                    r.id AS resident_id,
                    r.first_name,
                    r.middle_name,
                    r.last_name,
                    r.suffix,
                    r.birth_date,
                    r.gender,
                    r.civil_status,
                    r.purok,
                    r.address
                FROM users u
                LEFT JOIN residents r
                    ON r.user_id = u.id
                WHERE u.id = ?
                LIMIT 1
            ");

            $stmt->bind_param(
                "i",
                $user_id
            );

            $stmt->execute();

            $profile =
                $stmt->get_result()->fetch_assoc();
        } else {

            $message =
                "Unable to update your profile. Please try again.";

            $message_type = "error";
        }
    }
}

/*
|--------------------------------------------------------------------------
| FULL NAME
|--------------------------------------------------------------------------
*/

$full_name =
    trim(
        $profile['first_name'] . ' ' .
        (
            $profile['middle_name']
            ? $profile['middle_name'] . ' '
            : ''
        ) .
        $profile['last_name'] .
        (
            $profile['suffix']
            ? ' ' . $profile['suffix']
            : ''
        )
    );

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
    My Profile | MAPALADNEXUS
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

body {

    min-height: 100vh;

    font-family:
        Arial,
        Helvetica,
        sans-serif;

    color: white;

    background:
        radial-gradient(
            circle at 10% 10%,
            rgba(37,99,235,.25),
            transparent 30%
        ),
        radial-gradient(
            circle at 90% 85%,
            rgba(124,58,237,.23),
            transparent 32%
        ),
        #050816;

    overflow-x: hidden;
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

    filter: blur(20px);

    opacity: .18;

    animation:
        floating 10s ease-in-out infinite;
}

.orb.one {

    width: 400px;
    height: 400px;

    left: -200px;
    top: 10%;

    background: #2563eb;
}

.orb.two {

    width: 450px;
    height: 450px;

    right: -220px;
    bottom: -150px;

    background: #7c3aed;

    animation-delay: 2s;
}

@keyframes floating {

    0%,100% {
        transform: translate(0,0);
    }

    50% {
        transform: translate(25px,-30px);
    }
}

/* =========================================================
   SIDEBAR
========================================================= */

.sidebar {

    position: fixed;

    top: 0;
    bottom: 0;
    left: 0;

    width: 265px;

    padding: 22px 16px;

    background:
        rgba(5,8,22,.94);

    border-right:
        1px solid
        rgba(255,255,255,.08);

    backdrop-filter:
        blur(25px);

    z-index: 100;

    transition: .3s;
}

.brand {

    display: flex;

    align-items: center;

    gap: 12px;

    padding:
        4px 8px 24px;

    border-bottom:
        1px solid
        rgba(255,255,255,.08);
}

.logo {

    width: 46px;
    height: 46px;

    display: flex;

    align-items: center;
    justify-content: center;

    border-radius: 14px;

    background:
        linear-gradient(
            145deg,
            rgba(37,99,235,.45),
            rgba(79,70,229,.20)
        );

    box-shadow:
        10px 12px 25px
        rgba(0,0,0,.3);

    font-size: 21px;
}

.brand strong {

    display: block;

    font-size: 15px;

    letter-spacing: 1px;
}

.brand small {

    display: block;

    margin-top: 4px;

    color: #64748b;

    font-size: 10px;
}

.section-title {

    margin:
        25px 10px 10px;

    color: #64748b;

    font-size: 10px;

    font-weight: bold;

    letter-spacing: 1.5px;
}

.menu {

    display: flex;

    flex-direction: column;

    gap: 6px;
}

.menu a {

    display: flex;

    align-items: center;

    gap: 12px;

    padding: 13px 14px;

    color: #94a3b8;

    text-decoration: none;

    border-radius: 14px;

    font-size: 13px;

    transition: .25s;
}

.menu a:hover {

    color: white;

    background:
        rgba(255,255,255,.07);

    transform:
        translateX(4px);
}

.menu a.active {

    color: white;

    background:
        linear-gradient(
            145deg,
            rgba(37,99,235,.85),
            rgba(79,70,229,.75)
        );

    box-shadow:
        0 12px 28px
        rgba(37,99,235,.20);
}

.icon {

    width: 22px;

    text-align: center;

    font-size: 17px;
}

/* =========================================================
   LOGOUT
========================================================= */

.logout-box {

    position: absolute;

    left: 16px;
    right: 16px;

    bottom: 20px;
}

.logout {

    display: flex !important;

    justify-content: center;

    align-items: center;

    gap: 8px;

    padding: 13px !important;

    color: #fca5a5 !important;

    background:
        rgba(239,68,68,.08);

    border:
        1px solid
        rgba(239,68,68,.15);

    font-size: 13px !important;
}

/* =========================================================
   MAIN
========================================================= */

.main {

    width:
        calc(100% - 265px);

    margin-left: 265px;

    padding: 30px;

    min-height: 100vh;
}

/* =========================================================
   MOBILE
========================================================= */

.mobile {

    display: none;

    align-items: center;

    justify-content: space-between;

    margin-bottom: 20px;
}

.mobile button {

    width: 42px;
    height: 42px;

    color: white;

    border:
        1px solid
        rgba(255,255,255,.08);

    border-radius: 12px;

    background:
        rgba(255,255,255,.06);

    cursor: pointer;

    font-size: 18px;
}

/* =========================================================
   HEADER
========================================================= */

.header {

    margin-bottom: 25px;
}

.header small {

    color: #60a5fa;

    font-size: 10px;

    font-weight: bold;

    letter-spacing: 1.7px;

    text-transform: uppercase;
}

.header h1 {

    margin-top: 8px;

    font-size:
        clamp(28px,4vw,42px);
}

.header p {

    margin-top: 9px;

    max-width: 700px;

    color: #94a3b8;

    font-size: 12px;

    line-height: 1.7;
}

/* =========================================================
   ALERT
========================================================= */

.alert {

    margin-bottom: 22px;

    padding: 15px 18px;

    border-radius: 15px;

    font-size: 10px;
}

.alert.success {

    color: #bbf7d0;

    background:
        rgba(34,197,94,.08);

    border:
        1px solid
        rgba(34,197,94,.15);
}

.alert.error {

    color: #fecaca;

    background:
        rgba(239,68,68,.08);

    border:
        1px solid
        rgba(239,68,68,.15);
}

/* =========================================================
   PROFILE LAYOUT
========================================================= */

.profile-layout {

    display: grid;

    grid-template-columns:
        310px
        minmax(0,1fr);

    gap: 22px;

    align-items: start;
}

/* =========================================================
   CARD
========================================================= */

.card {

    padding: 25px;

    border-radius: 23px;

    background:
        linear-gradient(
            145deg,
            rgba(255,255,255,.065),
            rgba(255,255,255,.025)
        );

    border:
        1px solid
        rgba(255,255,255,.08);

    box-shadow:
        15px 22px 45px
        rgba(0,0,0,.20);

    backdrop-filter:
        blur(18px);
}

/* =========================================================
   PROFILE 3D
========================================================= */

.profile-card {

    text-align: center;

    transform:
        perspective(900px)
        rotateY(-3deg);

    transition: .3s;
}

.profile-card:hover {

    transform:
        perspective(900px)
        rotateY(0deg)
        translateY(-4px);
}

.avatar {

    width: 115px;
    height: 115px;

    margin:
        10px auto 20px;

    display: flex;

    align-items: center;
    justify-content: center;

    border-radius: 34px;

    background:
        linear-gradient(
            145deg,
            #2563eb,
            #4f46e5
        );

    box-shadow:
        15px 20px 35px
        rgba(0,0,0,.35);

    font-size: 48px;

    transform:
        perspective(600px)
        rotateX(5deg);
}

.profile-card h2 {

    color: #f8fafc;

    font-size: 20px;

    line-height: 1.4;
}

.role {

    display: inline-block;

    margin-top: 8px;

    padding: 7px 12px;

    color: #93c5fd;

    background:
        rgba(37,99,235,.10);

    border:
        1px solid
        rgba(96,165,250,.14);

    border-radius: 20px;

    font-size: 8px;

    font-weight: bold;

    letter-spacing: 1px;

    text-transform: uppercase;
}

.profile-info {

    margin-top: 25px;

    text-align: left;
}

.info-item {

    padding:
        13px 0;

    border-bottom:
        1px solid
        rgba(255,255,255,.06);
}

.info-item:last-child {

    border-bottom: none;
}

.info-item span {

    display: block;

    color: #64748b;

    font-size: 8px;

    text-transform: uppercase;

    letter-spacing: 1px;
}

.info-item strong {

    display: block;

    margin-top: 5px;

    color: #cbd5e1;

    font-size: 10px;

    word-break: break-word;
}

/* =========================================================
   FORM TITLE
========================================================= */

.card-title {

    margin-bottom: 22px;

    font-size: 15px;

    font-weight: bold;
}

.card-subtitle {

    margin-top: -14px;

    margin-bottom: 22px;

    color: #64748b;

    font-size: 9px;
}

/* =========================================================
   FORM GRID
========================================================= */

.form-grid {

    display: grid;

    grid-template-columns:
        repeat(2,minmax(0,1fr));

    gap: 17px;
}

.form-group {

    margin-bottom: 0;
}

.form-group.full {

    grid-column: 1 / -1;
}

.form-group label {

    display: block;

    margin-bottom: 8px;

    color: #94a3b8;

    font-size: 9px;

    font-weight: bold;
}

.form-control {

    width: 100%;

    padding: 13px 14px;

    color: white;

    outline: none;

    border:
        1px solid
        rgba(255,255,255,.09);

    border-radius: 13px;

    background:
        rgba(2,6,23,.55);

    font-family: inherit;

    font-size: 10px;

    transition: .25s;
}

.form-control::placeholder {

    color: #475569;
}

.form-control:focus {

    border-color:
        rgba(96,165,250,.55);

    box-shadow:
        0 0 0 3px
        rgba(37,99,235,.10);
}

select.form-control {

    cursor: pointer;
}

/* =========================================================
   BUTTON
========================================================= */

.update-btn {

    width: 100%;

    margin-top: 22px;

    padding: 14px;

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
        0 12px 25px
        rgba(37,99,235,.20);

    cursor: pointer;

    font-size: 10px;

    font-weight: bold;

    transition: .25s;
}

.update-btn:hover {

    transform:
        translateY(-2px);

    box-shadow:
        0 17px 30px
        rgba(37,99,235,.30);
}

/* =========================================================
   NOTE
========================================================= */

.note {

    margin-top: 18px;

    padding: 13px;

    color: #64748b;

    border-radius: 13px;

    background:
        rgba(255,255,255,.025);

    font-size: 8px;

    line-height: 1.7;
}

/* =========================================================
   RESPONSIVE
========================================================= */

@media(max-width:1050px) {

    .profile-layout {

        grid-template-columns: 1fr;
    }

    .profile-card {

        transform: none;
    }

    .profile-info {

        display: grid;

        grid-template-columns:
            repeat(2,1fr);

        gap: 10px;
    }

}

@media(max-width:900px) {

    .sidebar {

        transform:
            translateX(-100%);
    }

    .sidebar.open {

        transform:
            translateX(0);
    }

    .main {

        width: 100%;

        margin-left: 0;

        padding: 18px;
    }

    .mobile {

        display: flex;
    }

}

@media(max-width:650px) {

    .form-grid {

        grid-template-columns: 1fr;
    }

    .form-group.full {

        grid-column: auto;
    }

    .profile-info {

        grid-template-columns: 1fr;
    }

}

</style>

</head>

<body>

<!-- =====================================================
     BACKGROUND
====================================================== -->

<div class="background">

    <div class="grid"></div>

    <div class="orb one"></div>

    <div class="orb two"></div>

</div>

<!-- =====================================================
     SIDEBAR
====================================================== -->

<aside
    class="sidebar"
    id="sidebar"
>

    <div class="brand">

        <div class="logo">
            🏛️
        </div>

        <div>

            <strong>
                MAPALADNEXUS
            </strong>

            <small>
                Barangay Mapalad
            </small>

        </div>

    </div>

    <div class="section-title">
        Resident Portal
    </div>

    <nav class="menu">

        <a href="user_dashboard.php">

            <span class="icon">
                ⌂
            </span>

            Dashboard

        </a>

        <a href="services.php">

            <span class="icon">
                📄
            </span>

            Services

        </a>

        <a href="requests.php">

            <span class="icon">
                📋
            </span>

            My Requests

        </a>

        <a href="complaints.php">

            <span class="icon">
                💬
            </span>

            Complaints

        </a>

        <a href="announcements.php">

            <span class="icon">
                📢
            </span>

            Announcements

        </a>

        <a
            href="profile.php"
            class="active"
        >

            <span class="icon">
                👤
            </span>

            My Profile

        </a>

    </nav>

    <div class="logout-box">

        <a
            href="logout.php"
            class="logout"
        >

            🚪 Logout

        </a>

    </div>

</aside>

<!-- =====================================================
     MAIN
====================================================== -->

<main class="main">

    <div class="mobile">

        <strong>
            MAPALADNEXUS
        </strong>

        <button
            type="button"
            onclick="toggleMenu()"
        >
            ☰
        </button>

    </div>

    <!-- HEADER -->

    <section class="header">

        <small>
            Resident Account
        </small>

        <h1>
            My Profile
        </h1>

        <p>
            View and manage your personal
            information registered in
            Barangay Mapalad.
        </p>

    </section>

    <!-- ALERT -->

    <?php if ($message !== ''): ?>

        <div
            class="alert
            <?= e($message_type) ?>"
        >

            <?= $message_type === 'success'
                ? '✓ '
                : '⚠ '
            ?>

            <?= e($message) ?>

        </div>

    <?php endif; ?>

    <!-- PROFILE -->

    <div class="profile-layout">

        <!-- =================================================
             PROFILE SUMMARY
        ================================================== -->

        <section class="card profile-card">

            <div class="avatar">
                👤
            </div>

            <h2>
                <?= e(
                    $full_name
                ) ?>
            </h2>

            <span class="role">
                Resident
            </span>

            <div class="profile-info">

                <div class="info-item">

                    <span>
                        Username
                    </span>

                    <strong>
                        <?= e(
                            $profile['username']
                        ) ?>
                    </strong>

                </div>

                <div class="info-item">

                    <span>
                        Purok
                    </span>

                    <strong>
                        <?= e(
                            $profile['purok']
                            ?: 'Not specified'
                        ) ?>
                    </strong>

                </div>

                <div class="info-item">

                    <span>
                        Account Status
                    </span>

                    <strong>
                        <?= e(
                            $profile['status']
                        ) ?>
                    </strong>

                </div>

                <div class="info-item">

                    <span>
                        Account Created
                    </span>

                    <strong>
                        <?= !empty(
                            $profile['account_created']
                        )
                            ? e(
                                date(
                                    'M d, Y',
                                    strtotime(
                                        $profile['account_created']
                                    )
                                )
                            )
                            : 'N/A'
                        ?>
                    </strong>

                </div>

            </div>

        </section>

        <!-- =================================================
             EDIT PROFILE
        ================================================== -->

        <section class="card">

            <div class="card-title">

                ✏️

                Personal Information

            </div>

            <div class="card-subtitle">

                Keep your information accurate
                so the barangay can properly
                process your requests.

            </div>

            <form
                method="POST"
                action=""
            >

                <div class="form-grid">

                    <div class="form-group">

                        <label>
                            First Name *
                        </label>

                        <input
                            type="text"
                            name="first_name"
                            class="form-control"
                            value="<?= e(
                                $profile['first_name']
                            ) ?>"
                            required
                        >

                    </div>

                    <div class="form-group">

                        <label>
                            Middle Name
                        </label>

                        <input
                            type="text"
                            name="middle_name"
                            class="form-control"
                            value="<?= e(
                                $profile['middle_name']
                            ) ?>"
                        >

                    </div>

                    <div class="form-group">

                        <label>
                            Last Name *
                        </label>

                        <input
                            type="text"
                            name="last_name"
                            class="form-control"
                            value="<?= e(
                                $profile['last_name']
                            ) ?>"
                            required
                        >

                    </div>

                    <div class="form-group">

                        <label>
                            Suffix
                        </label>

                        <input
                            type="text"
                            name="suffix"
                            class="form-control"
                            value="<?= e(
                                $profile['suffix']
                            ) ?>"
                            placeholder="Jr., Sr., III"
                        >

                    </div>

                    <div class="form-group">

                        <label>
                            Birth Date
                        </label>

                        <input
                            type="date"
                            name="birth_date"
                            class="form-control"
                            value="<?= e(
                                $profile['birth_date']
                            ) ?>"
                        >

                    </div>

                    <div class="form-group">

                        <label>
                            Gender
                        </label>

                        <select
                            name="gender"
                            class="form-control"
                        >

                            <option value="">
                                Select Gender
                            </option>

                            <option
                                value="Male"
                                <?= $profile['gender'] === 'Male'
                                    ? 'selected'
                                    : '' ?>
                            >
                                Male
                            </option>

                            <option
                                value="Female"
                                <?= $profile['gender'] === 'Female'
                                    ? 'selected'
                                    : '' ?>
                            >
                                Female
                            </option>

                        </select>

                    </div>

                    <div class="form-group">

                        <label>
                            Civil Status
                        </label>

                        <select
                            name="civil_status"
                            class="form-control"
                        >

                            <option value="">
                                Select Civil Status
                            </option>

                            <option
                                value="Single"
                                <?= $profile['civil_status'] === 'Single'
                                    ? 'selected'
                                    : '' ?>
                            >
                                Single
                            </option>

                            <option
                                value="Married"
                                <?= $profile['civil_status'] === 'Married'
                                    ? 'selected'
                                    : '' ?>
                            >
                                Married
                            </option>

                            <option
                                value="Widowed"
                                <?= $profile['civil_status'] === 'Widowed'
                                    ? 'selected'
                                    : '' ?>
                            >
                                Widowed
                            </option>

                            <option
                                value="Separated"
                                <?= $profile['civil_status'] === 'Separated'
                                    ? 'selected'
                                    : '' ?>
                            >
                                Separated
                            </option>

                        </select>

                    </div>

                    <div class="form-group">

                        <label>
                            Purok
                        </label>

                        <input
                            type="text"
                            name="purok"
                            class="form-control"
                            value="<?= e(
                                $profile['purok']
                            ) ?>"
                            placeholder="Example: Purok 1"
                        >

                    </div>

                    <div class="form-group full">

                        <label>
                            Address
                        </label>

                        <input
                            type="text"
                            name="address"
                            class="form-control"
                            value="<?= e(
                                $profile['address']
                            ) ?>"
                            placeholder="Complete barangay address"
                        >

                    </div>

                </div>

                <button
                    type="submit"
                    name="update_profile"
                    class="update-btn"
                >

                    💾

                    Save Changes

                </button>

            </form>

            <div class="note">

                🔒 Your account information is only
                accessible through your resident
                account. Make sure your details are
                correct before submitting barangay
                requests.

            </div>

        </section>

    </div>

</main>

<script>

function toggleMenu()
{
    document
        .getElementById('sidebar')
        .classList
        .toggle('open');
}

</script>

</body>

</html>