<?php

session_start();

require_once __DIR__ . '/../config/database.php';

/*
|--------------------------------------------------------------------------
| MAPALADNEXUS
| RESIDENT ANNOUNCEMENTS
| Barangay Mapalad
|--------------------------------------------------------------------------
*/

/*
|--------------------------------------------------------------------------
| RESIDENT LOGIN CHECK
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
| ESCAPE FUNCTION
|--------------------------------------------------------------------------
*/

function e($value)
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
}

/*
|--------------------------------------------------------------------------
| GET RESIDENT INFORMATION
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

$result = $stmt->get_result();

if ($result->num_rows !== 1) {

    session_unset();
    session_destroy();

    header("Location: login.php");
    exit;
}

$resident = $result->fetch_assoc();

$resident_id = (int) $resident['id'];

$resident_name =
    trim(
        $resident['first_name'] .
        ' ' .
        $resident['last_name']
    );

$resident_purok =
    trim(
        (string) $resident['purok']
    );

/*
|--------------------------------------------------------------------------
| SEARCH
|--------------------------------------------------------------------------
*/

$search = trim(
    $_GET['search'] ?? ''
);

/*
|--------------------------------------------------------------------------
| GET ANNOUNCEMENTS
|--------------------------------------------------------------------------
|
| Shows:
|
| 1. Announcements for the resident's purok
| 2. General announcements
|    where target_purok is NULL or empty
|
| Only Published announcements are shown.
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        a.id,
        a.title,
        a.content,
        a.target_purok,
        a.created_by,
        a.status,
        a.created_at,
        u.username
    FROM announcements a
    LEFT JOIN users u
        ON u.id = a.created_by
    WHERE a.status = 'Published'
    AND (
        a.target_purok IS NULL
        OR a.target_purok = ''
        OR a.target_purok = ?
    )
";

if ($search !== '') {

    $sql .= "
        AND (
            a.title LIKE ?
            OR a.content LIKE ?
        )
    ";
}

$sql .= "
    ORDER BY a.created_at DESC
";

$stmt = $conn->prepare($sql);

if ($search !== '') {

    $search_like =
        '%' . $search . '%';

    $stmt->bind_param(
        "sss",
        $resident_purok,
        $search_like,
        $search_like
    );

} else {

    $stmt->bind_param(
        "s",
        $resident_purok
    );
}

$stmt->execute();

$announcements_result =
    $stmt->get_result();

/*
|--------------------------------------------------------------------------
| COUNT
|--------------------------------------------------------------------------
*/

$announcement_count =
    $announcements_result->num_rows;

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
    Announcements | MAPALADNEXUS
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

html {
    scroll-behavior: smooth;
}

body {

    min-height: 100vh;

    font-family:
        Arial,
        Helvetica,
        sans-serif;

    color: #ffffff;

    background:
        radial-gradient(
            circle at 10% 10%,
            rgba(37, 99, 235, .25),
            transparent 30%
        ),
        radial-gradient(
            circle at 90% 85%,
            rgba(124, 58, 237, .23),
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

    z-index: -5;

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
        floatOrb 10s ease-in-out infinite;
}

.orb.one {

    width: 400px;
    height: 400px;

    top: 5%;
    left: -180px;

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

.orb.three {

    width: 220px;
    height: 220px;

    top: 45%;
    right: 20%;

    background: #0ea5e9;

    animation-delay: 4s;
}

@keyframes floatOrb {

    0%, 100% {
        transform:
            translate3d(0, 0, 0);
    }

    50% {
        transform:
            translate3d(25px, -30px, 0);
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
        rgba(5, 8, 22, .94);

    border-right:
        1px solid
        rgba(255,255,255,.08);

    backdrop-filter:
        blur(25px);

    z-index: 100;

    transition:
        transform .3s ease;
}

/* =========================================================
   BRAND
========================================================= */

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
        rgba(0,0,0,.30);

    font-size: 21px;

    transform:
        perspective(500px)
        rotateY(-8deg);
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

/* =========================================================
   MENU
========================================================= */

.section-title {

    margin:
        25px 10px 10px;

    color: #64748b;

    font-size: 10px;

    font-weight: bold;

    letter-spacing: 1.5px;

    text-transform: uppercase;
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

    transform:
        translateZ(5px);
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

    align-items: center;

    justify-content: center;

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

.logout:hover {

    background:
        rgba(239,68,68,.14) !important;

    transform:
        translateY(-2px) !important;
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
   MOBILE BAR
========================================================= */

.mobile {

    display: none;

    align-items: center;

    justify-content: space-between;

    margin-bottom: 20px;
}

.mobile strong {

    font-size: 14px;

    letter-spacing: 1px;
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

    display: flex;

    align-items: flex-end;

    justify-content: space-between;

    gap: 25px;

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
        clamp(28px, 4vw, 42px);

    line-height: 1.1;
}

.header p {

    margin-top: 10px;

    max-width: 680px;

    color: #94a3b8;

    font-size: 12px;

    line-height: 1.7;
}

/* =========================================================
   PROFILE MINI CARD
========================================================= */

.profile-mini {

    min-width: 230px;

    padding: 18px 20px;

    border-radius: 20px;

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
        15px 20px 40px
        rgba(0,0,0,.22);

    transform:
        perspective(700px)
        rotateY(-4deg);

    backdrop-filter:
        blur(18px);
}

.profile-mini span {

    display: block;

    color: #64748b;

    font-size: 9px;

    text-transform: uppercase;

    letter-spacing: 1px;
}

.profile-mini strong {

    display: block;

    margin-top: 7px;

    font-size: 14px;
}

.profile-mini small {

    display: block;

    margin-top: 5px;

    color: #60a5fa;

    font-size: 9px;
}

/* =========================================================
   SEARCH CARD
========================================================= */

.search-card {

    display: flex;

    align-items: center;

    gap: 12px;

    margin-bottom: 22px;

    padding: 15px;

    border-radius: 19px;

    background:
        rgba(255,255,255,.045);

    border:
        1px solid
        rgba(255,255,255,.07);

    box-shadow:
        12px 16px 35px
        rgba(0,0,0,.15);

    backdrop-filter:
        blur(18px);
}

.search-wrapper {

    position: relative;

    flex: 1;
}

.search-wrapper span {

    position: absolute;

    top: 50%;
    left: 14px;

    transform:
        translateY(-50%);

    color: #64748b;

    font-size: 14px;
}

.search-input {

    width: 100%;

    padding:
        13px
        15px
        13px
        40px;

    color: white;

    outline: none;

    border:
        1px solid
        rgba(255,255,255,.08);

    border-radius: 13px;

    background:
        rgba(2,6,23,.55);

    font-size: 11px;

    transition: .25s;
}

.search-input::placeholder {

    color: #475569;
}

.search-input:focus {

    border-color:
        rgba(96,165,250,.55);

    box-shadow:
        0 0 0 3px
        rgba(37,99,235,.10);
}

.search-btn {

    padding:
        13px 20px;

    color: white;

    border: none;

    border-radius: 13px;

    background:
        linear-gradient(
            145deg,
            #2563eb,
            #4f46e5
        );

    cursor: pointer;

    font-size: 10px;

    font-weight: bold;

    box-shadow:
        0 10px 22px
        rgba(37,99,235,.18);

    transition: .25s;
}

.search-btn:hover {

    transform:
        translateY(-2px);
}

/* =========================================================
   ANNOUNCEMENT GRID
========================================================= */

.announcement-grid {

    display: grid;

    grid-template-columns:
        repeat(
            auto-fit,
            minmax(290px, 1fr)
        );

    gap: 20px;
}

/* =========================================================
   ANNOUNCEMENT CARD
========================================================= */

.announcement {

    position: relative;

    min-height: 245px;

    padding: 23px;

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
        14px 20px 45px
        rgba(0,0,0,.20);

    backdrop-filter:
        blur(18px);

    overflow: hidden;

    transition:
        transform .3s ease,
        box-shadow .3s ease,
        border-color .3s ease;
}

.announcement::before {

    content: "";

    position: absolute;

    top: -80px;
    right: -80px;

    width: 180px;
    height: 180px;

    border-radius: 50%;

    background:
        radial-gradient(
            circle,
            rgba(37,99,235,.18),
            transparent 70%
        );

    pointer-events: none;
}

.announcement:hover {

    transform:
        translateY(-7px)
        perspective(700px)
        rotateX(1deg);

    border-color:
        rgba(96,165,250,.20);

    box-shadow:
        18px 28px 55px
        rgba(0,0,0,.28);
}

/* =========================================================
   ANNOUNCEMENT TOP
========================================================= */

.announcement-top {

    display: flex;

    align-items: flex-start;

    justify-content: space-between;

    gap: 12px;

    margin-bottom: 18px;
}

.announcement-icon {

    width: 48px;
    height: 48px;

    flex-shrink: 0;

    display: flex;

    align-items: center;
    justify-content: center;

    border-radius: 15px;

    background:
        linear-gradient(
            145deg,
            rgba(37,99,235,.25),
            rgba(79,70,229,.12)
        );

    border:
        1px solid
        rgba(96,165,250,.12);

    box-shadow:
        8px 10px 20px
        rgba(0,0,0,.18);

    font-size: 21px;
}

.scope {

    padding:
        7px 10px;

    border-radius: 20px;

    color: #93c5fd;

    background:
        rgba(37,99,235,.09);

    border:
        1px solid
        rgba(96,165,250,.13);

    font-size: 8px;

    font-weight: bold;

    white-space: nowrap;
}

/* =========================================================
   TITLE
========================================================= */

.announcement h2 {

    color: #f8fafc;

    font-size: 16px;

    line-height: 1.4;

    margin-bottom: 11px;
}

.announcement-content {

    color: #94a3b8;

    font-size: 10px;

    line-height: 1.8;

    white-space: normal;

    word-break: break-word;
}

/* =========================================================
   FOOTER
========================================================= */

.announcement-footer {

    display: flex;

    flex-wrap: wrap;

    gap: 10px 18px;

    margin-top: 20px;

    padding-top: 14px;

    border-top:
        1px solid
        rgba(255,255,255,.06);

    color: #64748b;

    font-size: 8px;
}

.announcement-footer span {

    display: inline-flex;

    align-items: center;

    gap: 5px;
}

/* =========================================================
   EMPTY
========================================================= */

.empty {

    grid-column: 1 / -1;

    padding: 75px 20px;

    text-align: center;

    border-radius: 23px;

    background:
        rgba(255,255,255,.025);

    border:
        1px dashed
        rgba(255,255,255,.10);
}

.empty-icon {

    width: 75px;
    height: 75px;

    margin:
        0 auto 20px;

    display: flex;

    align-items: center;
    justify-content: center;

    border-radius: 24px;

    background:
        rgba(37,99,235,.08);

    font-size: 34px;

    box-shadow:
        10px 15px 30px
        rgba(0,0,0,.20);
}

.empty strong {

    display: block;

    margin-bottom: 8px;

    color: #cbd5e1;

    font-size: 15px;
}

.empty p {

    color: #64748b;

    font-size: 10px;
}

/* =========================================================
   RESULTS
========================================================= */

.results-info {

    margin-bottom: 14px;

    color: #64748b;

    font-size: 9px;
}

.results-info strong {

    color: #94a3b8;
}

/* =========================================================
   RESPONSIVE
========================================================= */

@media(max-width:1000px) {

    .header {

        align-items: flex-start;

        flex-direction: column;
    }

    .profile-mini {

        width: 100%;

        transform: none;
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

    .search-card {

        flex-direction: column;
    }

    .search-wrapper {

        width: 100%;
    }

    .search-btn {

        width: 100%;
    }

    .announcement-grid {

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

    <div class="orb three"></div>

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

        <a
            href="announcements.php"
            class="active"
        >

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

    <!-- MOBILE -->

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

    <!-- =================================================
         HEADER
    ================================================== -->

    <section class="header">

        <div>

            <small>
                Barangay Information Center
            </small>

            <h1>
                Announcements
            </h1>

            <p>
                Stay updated with the latest
                announcements, activities, programs,
                and important information from
                Barangay Mapalad.
            </p>

        </div>

        <div class="profile-mini">

            <span>
                Resident
            </span>

            <strong>
                <?= e($resident_name) ?>
            </strong>

            <small>
                📍 <?= e($resident_purok ?: 'Purok not specified') ?>
            </small>

        </div>

    </section>

    <!-- =================================================
         SEARCH
    ================================================== -->

    <form
        class="search-card"
        method="GET"
        action=""
    >

        <div class="search-wrapper">

            <span>
                🔎
            </span>

            <input
                type="text"
                name="search"
                class="search-input"
                placeholder="Search announcements..."
                value="<?= e($search) ?>"
            >

        </div>

        <button
            type="submit"
            class="search-btn"
        >
            🔎 Search
        </button>

        <?php if ($search !== ''): ?>

            <a
                href="announcements.php"
                style="
                    padding:13px 15px;
                    color:#94a3b8;
                    text-decoration:none;
                    font-size:10px;
                    border-radius:13px;
                    border:1px solid rgba(255,255,255,.08);
                    background:rgba(255,255,255,.04);
                "
            >
                Clear
            </a>

        <?php endif; ?>

    </form>

    <!-- =================================================
         RESULT INFO
    ================================================== -->

    <div class="results-info">

        Showing

        <strong>
            <?= $announcement_count ?>
        </strong>

        published announcement(s)

        <?php if ($search !== ''): ?>

            for

            <strong>
                "<?= e($search) ?>"
            </strong>

        <?php endif; ?>

    </div>

    <!-- =================================================
         ANNOUNCEMENTS
    ================================================== -->

    <section class="announcement-grid">

        <?php if (
            $announcements_result->num_rows > 0
        ): ?>

            <?php while (
                $announcement =
                $announcements_result->fetch_assoc()
            ): ?>

                <?php

                $target =
                    trim(
                        (string)
                        $announcement['target_purok']
                    );

                $scope =
                    $target !== ''
                        ? $target
                        : 'All Residents';

                $posted_by =
                    !empty(
                        $announcement['username']
                    )
                        ? $announcement['username']
                        : 'Barangay Administration';

                ?>

                <article class="announcement">

                    <div class="announcement-top">

                        <div class="announcement-icon">
                            📢
                        </div>

                        <div class="scope">

                            📍

                            <?= e($scope) ?>

                        </div>

                    </div>

                    <h2>

                        <?= e(
                            $announcement['title']
                        ) ?>

                    </h2>

                    <div class="announcement-content">

                        <?= nl2br(
                            e(
                                $announcement['content']
                            )
                        ) ?>

                    </div>

                    <div class="announcement-footer">

                        <span>

                            👤

                            Posted by:

                            <?= e(
                                $posted_by
                            ) ?>

                        </span>

                        <span>

                            📅

                            <?= e(
                                date(
                                    'M d, Y',
                                    strtotime(
                                        $announcement['created_at']
                                    )
                                )
                            ) ?>

                        </span>

                        <span>

                            🕒

                            <?= e(
                                date(
                                    'h:i A',
                                    strtotime(
                                        $announcement['created_at']
                                    )
                                )
                            ) ?>

                        </span>

                    </div>

                </article>

            <?php endwhile; ?>

        <?php else: ?>

            <div class="empty">

                <div class="empty-icon">
                    📢
                </div>

                <strong>
                    No announcements found
                </strong>

                <p>

                    <?php if ($search !== ''): ?>

                        No published announcement
                        matches your search.

                    <?php else: ?>

                        There are currently no
                        published announcements
                        available for you.

                    <?php endif; ?>

                </p>

            </div>

        <?php endif; ?>

    </section>

</main>

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