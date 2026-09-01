<?php

session_start();

require_once __DIR__ . '/../config/database.php';

/*
|--------------------------------------------------------------------------
| MAPALADNEXUS
| RESIDENT - REQUEST DETAILS
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

/*
|--------------------------------------------------------------------------
| REQUEST ID
|--------------------------------------------------------------------------
*/

$request_id = isset($_GET['id'])
    ? (int) $_GET['id']
    : 0;

if ($request_id <= 0) {
    header("Location: requests.php");
    exit;
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
| GET RESIDENT
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT
        id,
        first_name,
        last_name,
        purok
    FROM residents
    WHERE user_id = ?
    LIMIT 1
");

$stmt->bind_param(
    "i",
    $user_id
);

$stmt->execute();

$resident_result =
    $stmt->get_result();

if ($resident_result->num_rows !== 1) {

    session_unset();
    session_destroy();

    header("Location: login.php");
    exit;
}

$resident =
    $resident_result->fetch_assoc();

$resident_id =
    (int)$resident['id'];

/*
|--------------------------------------------------------------------------
| GET REQUEST
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT
        sr.id,
        sr.reference_number,
        sr.purpose,
        sr.remarks,
        sr.status,
        sr.requested_at,
        sr.updated_at,

        s.service_name,
        s.description,
        s.requirements,
        s.processing_time,
        s.fee

    FROM service_requests sr

    INNER JOIN services s
        ON s.id = sr.service_id

    WHERE
        sr.id = ?
        AND sr.resident_id = ?

    LIMIT 1
");

$stmt->bind_param(
    "ii",
    $request_id,
    $resident_id
);

$stmt->execute();

$request_result =
    $stmt->get_result();

if ($request_result->num_rows !== 1) {

    header("Location: requests.php");
    exit;
}

$request =
    $request_result->fetch_assoc();

/*
|--------------------------------------------------------------------------
| STATUS
|--------------------------------------------------------------------------
*/

$status =
    $request['status'];

$status_class = 'pending';

if ($status === 'Under Review') {
    $status_class = 'review';
}

if ($status === 'Approved') {
    $status_class = 'approved';
}

if ($status === 'Rejected') {
    $status_class = 'rejected';
}

/*
|--------------------------------------------------------------------------
| TIMELINE STATES
|--------------------------------------------------------------------------
*/

$is_pending =
    true;

$is_review =
    in_array(
        $status,
        [
            'Under Review',
            'Approved'
        ],
        true
    );

$is_approved =
    $status === 'Approved';

$is_rejected =
    $status === 'Rejected';

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
    Request Details | MAPALADNEXUS
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
            circle at 15% 10%,
            rgba(37,99,235,.25),
            transparent 30%
        ),
        radial-gradient(
            circle at 85% 80%,
            rgba(124,58,237,.22),
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

    filter: blur(10px);

    opacity: .20;

    animation:
        float 9s
        ease-in-out
        infinite;
}

.orb.one {

    width: 350px;
    height: 350px;

    background: #2563eb;

    left: -170px;
    top: 8%;
}

.orb.two {

    width: 400px;
    height: 400px;

    background: #7c3aed;

    right: -190px;
    bottom: 0;

    animation-delay: 2s;
}

@keyframes float {

    0%,100% {
        transform: translate(0,0);
    }

    50% {
        transform: translate(25px,-25px);
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
        rgba(5,8,22,.88);

    border-right:
        1px solid
        rgba(255,255,255,.08);

    backdrop-filter:
        blur(25px);

    z-index: 100;
}

.brand {

    display: flex;

    align-items: center;

    gap: 12px;

    padding:
        5px 8px 24px;

    border-bottom:
        1px solid
        rgba(255,255,255,.08);
}

.logo {

    width: 45px;
    height: 45px;

    display: flex;

    align-items: center;

    justify-content: center;

    border-radius: 14px;

    font-size: 21px;

    background:
        linear-gradient(
            145deg,
            rgba(37,99,235,.35),
            rgba(79,70,229,.12)
        );

    box-shadow:
        10px 10px 25px
        rgba(0,0,0,.3);
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
            rgba(37,99,235,.82),
            rgba(79,70,229,.72)
        );

    box-shadow:
        0 12px 25px
        rgba(37,99,235,.2);
}

.icon {

    width: 22px;

    text-align: center;

    font-size: 17px;
}

.logout-box {

    position: absolute;

    left: 16px;
    right: 16px;

    bottom: 20px;
}

.logout {

    display: flex;

    align-items: center;

    justify-content: center;

    gap: 8px;

    padding: 13px;

    color: #fca5a5;

    text-decoration: none;

    border-radius: 14px;

    background:
        rgba(239,68,68,.08);

    border:
        1px solid
        rgba(239,68,68,.15);

    font-size: 13px;
}

/* =========================================================
   MAIN
========================================================= */

.main {

    width:
        calc(100% - 265px);

    margin-left: 265px;

    padding:
        25px 30px 60px;
}

/* =========================================================
   TOP
========================================================= */

.top {

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 15px;

    margin-bottom: 25px;
}

.back {

    display: inline-flex;

    align-items: center;

    gap: 7px;

    padding: 11px 15px;

    color: #bfdbfe;

    text-decoration: none;

    border-radius: 12px;

    background:
        rgba(255,255,255,.055);

    border:
        1px solid
        rgba(255,255,255,.09);

    font-size: 11px;
}

.back:hover {

    color: white;

    background:
        rgba(255,255,255,.09);
}

/* =========================================================
   HEADER
========================================================= */

.header {

    margin-bottom: 22px;
}

.header small {

    color: #60a5fa;

    font-size: 10px;

    text-transform: uppercase;

    letter-spacing: 1.5px;
}

.header h1 {

    margin-top: 8px;

    font-size:
        clamp(27px,4vw,40px);
}

.header p {

    margin-top: 8px;

    color: #94a3b8;

    font-size: 12px;

    line-height: 1.7;
}

/* =========================================================
   HERO REQUEST
========================================================= */

.hero {

    position: relative;

    overflow: hidden;

    padding: 25px;

    border-radius: 25px;

    background:
        linear-gradient(
            145deg,
            rgba(37,99,235,.14),
            rgba(255,255,255,.045)
        );

    border:
        1px solid
        rgba(96,165,250,.14);

    box-shadow:
        20px 25px 50px
        rgba(0,0,0,.25);

    margin-bottom: 20px;
}

.hero::before {

    content: "";

    position: absolute;

    width: 230px;
    height: 230px;

    right: -80px;
    top: -100px;

    border-radius: 50%;

    background:
        rgba(37,99,235,.14);

    filter: blur(8px);
}

.hero-content {

    position: relative;

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 20px;
}

.service-box {

    display: flex;

    align-items: center;

    gap: 16px;
}

.service-icon {

    width: 65px;
    height: 65px;

    display: flex;

    align-items: center;

    justify-content: center;

    flex-shrink: 0;

    border-radius: 20px;

    background:
        linear-gradient(
            145deg,
            rgba(37,99,235,.25),
            rgba(124,58,237,.16)
        );

    border:
        1px solid
        rgba(96,165,250,.18);

    font-size: 28px;

    box-shadow:
        10px 12px 30px
        rgba(0,0,0,.25);
}

.service-box h2 {

    font-size: 19px;
}

.reference {

    margin-top: 7px;

    color: #64748b;

    font-size: 10px;
}

.reference strong {

    color: #93c5fd;

    letter-spacing: .5px;
}

/* =========================================================
   STATUS
========================================================= */

.status {

    display: inline-flex;

    align-items: center;

    gap: 7px;

    padding: 10px 14px;

    border-radius: 12px;

    font-size: 10px;

    font-weight: bold;
}

.status.pending {

    color: #fcd34d;

    background:
        rgba(245,158,11,.10);

    border:
        1px solid
        rgba(245,158,11,.15);
}

.status.review {

    color: #93c5fd;

    background:
        rgba(37,99,235,.10);

    border:
        1px solid
        rgba(96,165,250,.15);
}

.status.approved {

    color: #86efac;

    background:
        rgba(34,197,94,.10);

    border:
        1px solid
        rgba(34,197,94,.15);
}

.status.rejected {

    color: #fca5a5;

    background:
        rgba(239,68,68,.10);

    border:
        1px solid
        rgba(239,68,68,.15);
}

/* =========================================================
   GRID
========================================================= */

.content-grid {

    display: grid;

    grid-template-columns:
        minmax(0,1.35fr)
        minmax(320px,.65fr);

    gap: 20px;

    align-items: start;
}

/* =========================================================
   CARD
========================================================= */

.card {

    padding: 22px;

    border-radius: 22px;

    background:
        rgba(255,255,255,.05);

    border:
        1px solid
        rgba(255,255,255,.08);

    box-shadow:
        15px 20px 40px
        rgba(0,0,0,.18);
}

.card-title {

    display: flex;

    align-items: center;

    gap: 9px;

    margin-bottom: 18px;

    font-size: 14px;

    font-weight: bold;
}

/* =========================================================
   INFORMATION
========================================================= */

.info-grid {

    display: grid;

    grid-template-columns:
        repeat(2,1fr);

    gap: 12px;
}

.info {

    padding: 15px;

    border-radius: 14px;

    background:
        rgba(0,0,0,.15);

    border:
        1px solid
        rgba(255,255,255,.05);
}

.info.full {

    grid-column:
        1 / -1;
}

.info small {

    display: block;

    color: #64748b;

    font-size: 8px;

    letter-spacing: 1px;

    text-transform: uppercase;
}

.info strong {

    display: block;

    margin-top: 6px;

    color: #dbeafe;

    font-size: 12px;

    line-height: 1.6;

    word-break: break-word;
}

/* =========================================================
   TIMELINE
========================================================= */

.timeline {

    position: relative;

    margin-top: 8px;

    padding-left: 18px;
}

.timeline::before {

    content: "";

    position: absolute;

    left: 15px;

    top: 8px;

    bottom: 10px;

    width: 2px;

    background:
        rgba(255,255,255,.08);
}

.step {

    position: relative;

    display: flex;

    gap: 14px;

    min-height: 85px;
}

.dot {

    position: relative;

    z-index: 2;

    width: 28px;
    height: 28px;

    display: flex;

    align-items: center;

    justify-content: center;

    flex-shrink: 0;

    border-radius: 50%;

    color: #64748b;

    background:
        #111827;

    border:
        2px solid
        #334155;

    font-size: 10px;

    font-weight: bold;
}

.step.done .dot {

    color: white;

    background:
        linear-gradient(
            145deg,
            #2563eb,
            #4f46e5
        );

    border-color:
        #60a5fa;

    box-shadow:
        0 0 20px
        rgba(37,99,235,.35);
}

.step.active .dot {

    color: #fcd34d;

    border-color:
        #f59e0b;

    background:
        rgba(245,158,11,.12);

    box-shadow:
        0 0 20px
        rgba(245,158,11,.18);
}

.step-content {

    padding-bottom: 22px;
}

.step-content h3 {

    font-size: 12px;
}

.step-content p {

    margin-top: 5px;

    color: #64748b;

    font-size: 9px;

    line-height: 1.6;
}

.step-content time {

    display: block;

    margin-top: 6px;

    color: #475569;

    font-size: 8px;
}

/* =========================================================
   SERVICE DESCRIPTION
========================================================= */

.description {

    color: #94a3b8;

    font-size: 11px;

    line-height: 1.8;
}

/* =========================================================
   REQUIREMENTS
========================================================= */

.requirements {

    margin-top: 16px;

    padding-top: 16px;

    border-top:
        1px solid
        rgba(255,255,255,.06);
}

.requirements h3 {

    margin-bottom: 10px;

    font-size: 11px;
}

.requirements-text {

    color: #94a3b8;

    font-size: 10px;

    line-height: 1.7;

    white-space: pre-line;
}

/* =========================================================
   REMARKS
========================================================= */

.remarks {

    margin-top: 16px;

    padding: 14px;

    border-radius: 13px;

    background:
        rgba(37,99,235,.06);

    border:
        1px solid
        rgba(96,165,250,.10);
}

.remarks small {

    display: block;

    color: #60a5fa;

    font-size: 8px;

    letter-spacing: 1px;

    text-transform: uppercase;
}

.remarks p {

    margin-top: 6px;

    color: #cbd5e1;

    font-size: 10px;

    line-height: 1.6;
}

/* =========================================================
   ACTION
========================================================= */

.action {

    margin-top: 20px;

    display: flex;

    gap: 10px;

    flex-wrap: wrap;
}

.action a {

    display: inline-flex;

    align-items: center;

    justify-content: center;

    padding: 11px 15px;

    border-radius: 12px;

    color: white;

    text-decoration: none;

    font-size: 10px;

    font-weight: bold;

    background:
        linear-gradient(
            145deg,
            #2563eb,
            #4f46e5
        );

    box-shadow:
        0 10px 20px
        rgba(37,99,235,.18);
}

.action a.secondary {

    color: #93c5fd;

    background:
        rgba(255,255,255,.05);

    border:
        1px solid
        rgba(255,255,255,.08);

    box-shadow: none;
}

/* =========================================================
   MOBILE
========================================================= */

@media(max-width:1000px) {

    .content-grid {

        grid-template-columns: 1fr;
    }
}

@media(max-width:800px) {

    .sidebar {

        transform:
            translateX(-100%);

        transition: .3s;
    }

    .sidebar.open {

        transform:
            translateX(0);
    }

    .main {

        width: 100%;

        margin-left: 0;

        padding: 15px;
    }

    .mobile {

        display: flex !important;
    }
}

.mobile {

    display: none;

    align-items: center;

    justify-content: space-between;

    margin-bottom: 15px;
}

.mobile button {

    padding: 9px 12px;

    color: white;

    border: none;

    border-radius: 10px;

    background:
        rgba(255,255,255,.08);

    font-size: 18px;
}

@media(max-width:600px) {

    .hero-content {

        align-items: flex-start;

        flex-direction: column;
    }

    .info-grid {

        grid-template-columns: 1fr;
    }

    .info.full {

        grid-column: auto;
    }

    .service-box {

        align-items: flex-start;
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

        <a
            href="requests.php"
            class="active"
        >

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

        <a href="profile.php">

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

        <button onclick="toggleMenu()">
            ☰
        </button>

    </div>

    <div class="top">

        <a
            href="requests.php"
            class="back"
        >
            ← Back to My Requests
        </a>

        <div class="status <?= $status_class ?>">

            <?php

            if ($status === 'Approved') {
                echo '✓';
            } elseif ($status === 'Rejected') {
                echo '×';
            } elseif ($status === 'Under Review') {
                echo '◉';
            } else {
                echo '◷';
            }

            ?>

            <?= e($status) ?>

        </div>

    </div>

    <div class="header">

        <small>
            Request Tracking
        </small>

        <h1>
            Request Details
        </h1>

        <p>
            View the complete information and
            processing progress of your barangay request.
        </p>

    </div>

    <!-- =================================================
         HERO
    ================================================== -->

    <section class="hero">

        <div class="hero-content">

            <div class="service-box">

                <div class="service-icon">
                    📄
                </div>

                <div>

                    <h2>
                        <?= e(
                            $request['service_name']
                        ) ?>
                    </h2>

                    <div class="reference">

                        Reference Number:

                        <strong>
                            <?= e(
                                $request['reference_number']
                            ) ?>
                        </strong>

                    </div>

                </div>

            </div>

            <div class="status <?= $status_class ?>">

                Current Status:

                <?= e($status) ?>

            </div>

        </div>

    </section>

    <!-- =================================================
         CONTENT
    ================================================== -->

    <div class="content-grid">

        <!-- LEFT -->
        <div>

            <section class="card">

                <div class="card-title">
                    📋 Request Information
                </div>

                <div class="info-grid">

                    <div class="info">

                        <small>
                            Resident
                        </small>

                        <strong>

                            <?= e(
                                $resident['first_name'] .
                                ' ' .
                                $resident['last_name']
                            ) ?>

                        </strong>

                    </div>

                    <div class="info">

                        <small>
                            Purok
                        </small>

                        <strong>
                            <?= e(
                                $resident['purok']
                            ) ?>
                        </strong>

                    </div>

                    <div class="info">

                        <small>
                            Service Fee
                        </small>

                        <strong>

                            <?php

                            if (
                                $request['fee'] !== null &&
                                $request['fee'] !== ''
                            ) {

                                echo '₱' .
                                    number_format(
                                        (float)
                                        $request['fee'],
                                        2
                                    );

                            } else {

                                echo 'Free';

                            }

                            ?>

                        </strong>

                    </div>

                    <div class="info">

                        <small>
                            Processing Time
                        </small>

                        <strong>

                            <?= e(
                                $request['processing_time']
                                ?: 'To be advised'
                            ) ?>

                        </strong>

                    </div>

                    <div class="info full">

                        <small>
                            Purpose
                        </small>

                        <strong>

                            <?= e(
                                $request['purpose']
                            ) ?>

                        </strong>

                    </div>

                    <div class="info">

                        <small>
                            Date Requested
                        </small>

                        <strong>

                            <?= e(
                                date(
                                    'M d, Y',
                                    strtotime(
                                        $request['requested_at']
                                    )
                                )
                            ) ?>

                        </strong>

                    </div>

                    <div class="info">

                        <small>
                            Last Updated
                        </small>

                        <strong>

                            <?= e(
                                date(
                                    'M d, Y',
                                    strtotime(
                                        $request['updated_at']
                                    )
                                )
                            ) ?>

                        </strong>

                    </div>

                </div>

                <?php if (
                    !empty(
                        $request['remarks']
                    )
                ): ?>

                    <div class="remarks">

                        <small>
                            Official Remarks
                        </small>

                        <p>
                            <?= nl2br(
                                e(
                                    $request['remarks']
                                )
                            ) ?>
                        </p>

                    </div>

                <?php endif; ?>

            </section>

            <section
                class="card"
                style="margin-top:20px;"
            >

                <div class="card-title">
                    ℹ️ Service Information
                </div>

                <div class="description">

                    <?= nl2br(
                        e(
                            $request['description']
                        )
                    ) ?>

                </div>

                <?php if (
                    !empty(
                        $request['requirements']
                    )
                ): ?>

                    <div class="requirements">

                        <h3>
                            Requirements
                        </h3>

                        <div class="requirements-text">

                            <?= e(
                                $request['requirements']
                            ) ?>

                        </div>

                    </div>

                <?php endif; ?>

            </section>

        </div>

        <!-- RIGHT -->
        <div>

            <section class="card">

                <div class="card-title">
                    🚀 Request Progress
                </div>

                <div class="timeline">

                    <!-- STEP 1 -->

                    <div class="step done">

                        <div class="dot">
                            ✓
                        </div>

                        <div class="step-content">

                            <h3>
                                Request Submitted
                            </h3>

                            <p>
                                Your service request
                                has been successfully submitted.
                            </p>

                            <time>

                                <?= e(
                                    date(
                                        'M d, Y • h:i A',
                                        strtotime(
                                            $request['requested_at']
                                        )
                                    )
                                ) ?>

                            </time>

                        </div>

                    </div>

                    <!-- STEP 2 -->

                    <div class="step <?= $is_review ? 'done' : ($status === 'Pending' ? 'active' : '') ?>">

                        <div class="dot">

                            <?= $is_review ? '✓' : '2' ?>

                        </div>

                        <div class="step-content">

                            <h3>
                                Under Review
                            </h3>

                            <p>
                                Barangay personnel are
                                reviewing your request.
                            </p>

                            <?php if ($is_review): ?>

                                <time>
                                    Processing started
                                </time>

                            <?php endif; ?>

                        </div>

                    </div>

                    <!-- STEP 3 -->

                    <div class="step <?= $is_approved ? 'done' : ($is_rejected ? 'active' : '') ?>">

                        <div class="dot">

                            <?php

                            if ($is_approved) {
                                echo '✓';
                            } elseif ($is_rejected) {
                                echo '×';
                            } else {
                                echo '3';
                            }

                            ?>

                        </div>

                        <div class="step-content">

                            <h3>

                                <?= $is_rejected
                                    ? 'Request Rejected'
                                    : 'Request Decision'
                                ?>

                            </h3>

                            <p>

                                <?php

                                if ($is_approved) {

                                    echo 'Your request has been approved.';

                                } elseif ($is_rejected) {

                                    echo 'Your request was rejected. Please check the official remarks.';

                                } else {

                                    echo 'Waiting for the final decision from barangay personnel.';

                                }

                                ?>

                            </p>

                        </div>

                    </div>

                </div>

            </section>

            <div class="action">

                <a href="requests.php">
                    ← All Requests
                </a>

                <a
                    href="services.php"
                    class="secondary"
                >
                    + New Request
                </a>

            </div>

        </div>

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