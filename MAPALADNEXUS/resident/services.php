<?php

session_start();

require_once __DIR__ . '/../config/database.php';

/* =========================================================
   SECURITY
========================================================= */

if (
    !isset($_SESSION['user_id']) ||
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'Resident'
) {
    header("Location: login.php");
    exit;
}

/* =========================================================
   GET RESIDENT
========================================================= */

$user_id = (int) $_SESSION['user_id'];

$stmt = $conn->prepare("
    SELECT id, first_name, last_name, purok
    FROM residents
    WHERE user_id = ?
    LIMIT 1
");

$stmt->bind_param("i", $user_id);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    session_destroy();
    header("Location: login.php");
    exit;
}

$resident = $result->fetch_assoc();

$resident_id = (int) $resident['id'];
$first_name = $resident['first_name'];
$last_name = $resident['last_name'];
$purok = $resident['purok'];

function e($value)
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}

/* =========================================================
   LOAD SERVICES
========================================================= */

$services = [];

$service_query = $conn->query("
    SELECT
        id,
        service_name,
        description,
        fee
    FROM services
    WHERE status = 'Active'
    ORDER BY service_name ASC
");

if ($service_query) {

    while ($row = $service_query->fetch_assoc()) {
        $services[] = $row;
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
    Services | MAPALADNEXUS
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
            circle at 90% 20%,
            rgba(124,58,237,.22),
            transparent 30%
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

    filter: blur(8px);

    opacity: .20;

    animation: float 9s ease-in-out infinite;
}

.orb.one {

    width: 330px;
    height: 330px;

    background: #2563eb;

    left: -130px;
    top: 10%;
}

.orb.two {

    width: 360px;
    height: 360px;

    background: #7c3aed;

    right: -150px;
    bottom: 5%;

    animation-delay: 2s;
}

@keyframes float {

    0%,100% {
        transform: translate(0,0);
    }

    50% {
        transform: translate(25px,-30px);
    }
}

/* =========================================================
   LAYOUT
========================================================= */

.layout {

    min-height: 100vh;

    display: flex;
}

/* =========================================================
   SIDEBAR
========================================================= */

.sidebar {

    width: 265px;

    position: fixed;

    inset: 0 auto 0 0;

    padding: 22px 16px;

    background:
        rgba(5,8,22,.85);

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

    background:
        linear-gradient(
            145deg,
            rgba(96,165,250,.35),
            rgba(99,102,241,.08)
        );

    box-shadow:
        10px 10px 25px
        rgba(0,0,0,.3);

    font-size: 22px;
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

    letter-spacing: 1.5px;

    font-weight: bold;
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

    border-radius: 14px;

    color: #94a3b8;

    text-decoration: none;

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
            rgba(37,99,235,.8),
            rgba(79,70,229,.7)
        );

    box-shadow:
        0 12px 25px
        rgba(37,99,235,.20);
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

    border-radius: 14px;

    color: #fca5a5;

    text-decoration: none;

    font-size: 13px;

    background:
        rgba(239,68,68,.08);

    border:
        1px solid
        rgba(239,68,68,.15);
}

/* =========================================================
   MAIN
========================================================= */

.main {

    width: calc(100% - 265px);

    margin-left: 265px;

    padding: 25px 30px 60px;
}

/* =========================================================
   TOPBAR
========================================================= */

.topbar {

    display: flex;

    align-items: center;

    justify-content: space-between;

    padding: 14px 18px;

    margin-bottom: 25px;

    border-radius: 20px;

    background:
        rgba(255,255,255,.055);

    border:
        1px solid
        rgba(255,255,255,.09);

    backdrop-filter:
        blur(20px);
}

.welcome {

    display: flex;

    align-items: center;

    gap: 12px;
}

.avatar {

    width: 43px;
    height: 43px;

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

    font-weight: bold;
}

.welcome strong {

    display: block;

    font-size: 13px;
}

.welcome span {

    display: block;

    margin-top: 4px;

    color: #64748b;

    font-size: 10px;
}

.back {

    padding: 10px 14px;

    border-radius: 12px;

    color: #93c5fd;

    text-decoration: none;

    background:
        rgba(255,255,255,.05);

    border:
        1px solid
        rgba(255,255,255,.08);

    font-size: 11px;
}

/* =========================================================
   PAGE HEADER
========================================================= */

.page-header {

    margin-bottom: 25px;
}

.page-header small {

    color: #93c5fd;

    font-size: 10px;

    letter-spacing: 1.5px;

    text-transform: uppercase;
}

.page-header h1 {

    margin-top: 8px;

    font-size:
        clamp(28px,4vw,40px);
}

.page-header p {

    max-width: 700px;

    margin-top: 9px;

    color: #94a3b8;

    font-size: 13px;

    line-height: 1.7;
}

/* =========================================================
   SERVICES GRID
========================================================= */

.services-grid {

    display: grid;

    grid-template-columns:
        repeat(3, 1fr);

    gap: 20px;
}

/* =========================================================
   SERVICE CARD
========================================================= */

.service-card {

    position: relative;

    min-height: 245px;

    padding: 24px;

    overflow: hidden;

    border-radius: 24px;

    background:
        linear-gradient(
            145deg,
            rgba(255,255,255,.075),
            rgba(255,255,255,.035)
        );

    border:
        1px solid
        rgba(255,255,255,.09);

    box-shadow:
        18px 20px 45px
        rgba(0,0,0,.23),

        inset 1px 1px 6px
        rgba(255,255,255,.06);

    transition:
        .35s ease;
}

.service-card:hover {

    transform:
        translateY(-9px)
        rotateX(2deg)
        rotateY(-1deg);

    border-color:
        rgba(96,165,250,.28);

    box-shadow:
        22px 28px 55px
        rgba(0,0,0,.35),

        0 0 30px
        rgba(37,99,235,.08);
}

.service-card::after {

    content: "";

    position: absolute;

    width: 150px;
    height: 150px;

    right: -65px;
    bottom: -70px;

    border-radius: 50%;

    background:
        rgba(37,99,235,.10);
}

.service-icon {

    width: 58px;
    height: 58px;

    display: flex;

    align-items: center;

    justify-content: center;

    border-radius: 18px;

    font-size: 25px;

    background:
        linear-gradient(
            145deg,
            rgba(37,99,235,.20),
            rgba(124,58,237,.10)
        );

    border:
        1px solid
        rgba(96,165,250,.15);

    box-shadow:
        10px 10px 25px
        rgba(0,0,0,.25),

        inset 2px 2px 5px
        rgba(255,255,255,.08);
}

.service-card h2 {

    margin-top: 18px;

    font-size: 16px;

    line-height: 1.4;
}

.service-card p {

    margin-top: 8px;

    min-height: 40px;

    color: #64748b;

    font-size: 11px;

    line-height: 1.6;
}

.service-bottom {

    position: absolute;

    left: 24px;

    right: 24px;

    bottom: 22px;

    display: flex;

    align-items: center;

    justify-content: space-between;
}

.fee {

    color: #94a3b8;

    font-size: 10px;
}

.fee strong {

    color: #cbd5e1;

    font-size: 13px;
}

.request-btn {

    display: inline-flex;

    align-items: center;

    gap: 6px;

    padding: 10px 13px;

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
        rgba(37,99,235,.25);

    transition: .25s;
}

.request-btn:hover {

    transform:
        translateY(-3px);
}

/* =========================================================
   EMPTY
========================================================= */

.empty {

    grid-column: 1 / -1;

    padding: 70px 25px;

    text-align: center;

    border-radius: 25px;

    color: #64748b;

    background:
        rgba(255,255,255,.04);

    border:
        1px solid
        rgba(255,255,255,.08);
}

.empty-icon {

    font-size: 45px;

    margin-bottom: 15px;
}

.empty strong {

    display: block;

    color: #cbd5e1;

    font-size: 15px;
}

/* =========================================================
   MOBILE
========================================================= */

.mobile-menu {

    display: none;
}

@media(max-width:1050px) {

    .services-grid {

        grid-template-columns:
            repeat(2,1fr);
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

    .mobile-menu {

        display: block;

        border: none;

        color: white;

        background:
            rgba(255,255,255,.08);

        border-radius: 10px;

        padding: 9px 12px;

        font-size: 18px;

        cursor: pointer;
    }

    .mobile-top {

        display: flex;

        align-items: center;

        justify-content: space-between;

        margin-bottom: 15px;
    }
}

@media(max-width:600px) {

    .services-grid {

        grid-template-columns: 1fr;
    }

    .topbar {

        padding: 12px;
    }

    .back {

        display: none;
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

<div class="layout">

<!-- =====================================================
     SIDEBAR
====================================================== -->

<aside class="sidebar" id="sidebar">

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

            <span class="icon">⌂</span>

            Dashboard

        </a>

        <a
            href="services.php"
            class="active"
        >

            <span class="icon">📄</span>

            Services

        </a>

        <a href="requests.php">

            <span class="icon">📋</span>

            My Requests

        </a>

        <a href="complaints.php">

            <span class="icon">💬</span>

            Complaints

        </a>

        <a href="announcements.php">

            <span class="icon">📢</span>

            Announcements

        </a>

        <a href="profile.php">

            <span class="icon">👤</span>

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

    <div class="mobile-top">

        <strong>
            MAPALADNEXUS
        </strong>

        <button
            class="mobile-menu"
            onclick="toggleMenu()"
        >
            ☰
        </button>

    </div>

    <!-- TOPBAR -->

    <div class="topbar">

        <div class="welcome">

            <div class="avatar">

                <?= e(
                    strtoupper(
                        substr($first_name,0,1)
                    )
                ) ?>

            </div>

            <div>

                <strong>
                    <?= e(
                        $first_name . ' ' . $last_name
                    ) ?>
                </strong>

                <span>
                    <?= e($purok) ?> · Resident
                </span>

            </div>

        </div>

        <a
            href="user_dashboard.php"
            class="back"
        >
            ← Dashboard
        </a>

    </div>

    <!-- PAGE HEADER -->

    <section class="page-header">

        <small>
            Barangay Digital Services
        </small>

        <h1>
            Request a Service
        </h1>

        <p>
            Choose a barangay service below.
            Submit your request online and track
            its progress from your resident portal.
        </p>

    </section>

    <!-- SERVICES -->

    <section class="services-grid">

        <?php if (count($services) > 0): ?>

            <?php foreach ($services as $service): ?>

                <?php

                $name =
                    strtolower(
                        $service['service_name']
                    );

                $icon = '📄';

                if (
                    strpos($name,'clearance')
                    !== false
                ) {
                    $icon = '📜';
                }

                if (
                    strpos($name,'indigency')
                    !== false
                ) {
                    $icon = '🤝';
                }

                if (
                    strpos($name,'residency')
                    !== false
                    ||
                    strpos($name,'residence')
                    !== false
                ) {
                    $icon = '🏠';
                }

                if (
                    strpos($name,'business')
                    !== false
                ) {
                    $icon = '🏪';
                }

                if (
                    strpos($name,'certificate')
                    !== false
                ) {
                    $icon = '📑';
                }

                ?>

                <article class="service-card">

                    <div class="service-icon">
                        <?= $icon ?>
                    </div>

                    <h2>
                        <?= e(
                            $service['service_name']
                        ) ?>
                    </h2>

                    <p>

                        <?= e(
                            $service['description']
                            ?: 'Request this barangay service online.'
                        ) ?>

                    </p>

                    <div class="service-bottom">

                        <div class="fee">

                            Service Fee

                            <br>

                            <strong>

                                <?php

                                if (
                                    $service['fee'] !== null &&
                                    $service['fee'] !== ''
                                ) {

                                    echo '₱' .
                                        number_format(
                                            (float)$service['fee'],
                                            2
                                        );

                                } else {

                                    echo 'Free';
                                }

                                ?>

                            </strong>

                        </div>

                        <a
                            href="request_service.php?service_id=<?= (int)$service['id'] ?>"
                            class="request-btn"
                        >

                            Request

                            <span>→</span>

                        </a>

                    </div>

                </article>

            <?php endforeach; ?>

        <?php else: ?>

            <div class="empty">

                <div class="empty-icon">
                    📭
                </div>

                <strong>
                    No services available yet
                </strong>

                <br>

                Please contact the barangay administrator
                to add available services.

            </div>

        <?php endif; ?>

    </section>

</main>

</div>

<script>

function toggleMenu()
{
    const sidebar =
        document.getElementById('sidebar');

    sidebar.classList.toggle('open');
}

</script>

</body>

</html>