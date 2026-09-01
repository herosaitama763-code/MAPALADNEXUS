<?php

session_start();

require_once __DIR__ . '/../config/database.php';

/*
|--------------------------------------------------------------------------
| MAPALADNEXUS
| Resident Dashboard
|--------------------------------------------------------------------------
*/

/*
|--------------------------------------------------------------------------
| SECURITY CHECK
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
| GET RESIDENT INFORMATION
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT
        r.id,
        r.user_id,
        r.first_name,
        r.middle_name,
        r.last_name,
        r.suffix,
        r.birth_date,
        r.gender,
        r.civil_status,
        r.purok,
        r.address,
        r.contact_number,
        r.email,
        r.profile_picture
    FROM residents r
    WHERE r.user_id = ?
    LIMIT 1
");

$stmt->bind_param("i", $user_id);
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

$first_name = $resident['first_name'];
$middle_name = $resident['middle_name'];
$last_name = $resident['last_name'];
$suffix = $resident['suffix'];
$purok = $resident['purok'];
$email = $resident['email'];
$profile_picture = $resident['profile_picture'];

/*
|--------------------------------------------------------------------------
| FULL NAME
|--------------------------------------------------------------------------
*/

$full_name = trim(
    $first_name . ' ' .
    ($middle_name ? $middle_name . ' ' : '') .
    $last_name . ' ' .
    ($suffix ? $suffix : '')
);

/*
|--------------------------------------------------------------------------
| TOTAL REQUESTS
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM service_requests
    WHERE resident_id = ?
");

$stmt->bind_param("i", $resident_id);
$stmt->execute();

$request_count = (int) $stmt->get_result()->fetch_assoc()['total'];

/*
|--------------------------------------------------------------------------
| PENDING REQUESTS
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM service_requests
    WHERE resident_id = ?
    AND status IN ('Pending', 'Under Review')
");

$stmt->bind_param("i", $resident_id);
$stmt->execute();

$pending_count = (int) $stmt->get_result()->fetch_assoc()['total'];

/*
|--------------------------------------------------------------------------
| COMPLETED REQUESTS
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM service_requests
    WHERE resident_id = ?
    AND status = 'Completed'
");

$stmt->bind_param("i", $resident_id);
$stmt->execute();

$completed_count = (int) $stmt->get_result()->fetch_assoc()['total'];

/*
|--------------------------------------------------------------------------
| COMPLAINTS
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM complaints
    WHERE resident_id = ?
");

$stmt->bind_param("i", $resident_id);
$stmt->execute();

$complaint_count = (int) $stmt->get_result()->fetch_assoc()['total'];

/*
|--------------------------------------------------------------------------
| UNREAD NOTIFICATIONS
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM notifications
    WHERE user_id = ?
    AND is_read = 0
");

$stmt->bind_param("i", $user_id);
$stmt->execute();

$notification_count = (int) $stmt->get_result()->fetch_assoc()['total'];

/*
|--------------------------------------------------------------------------
| RECENT REQUESTS
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT
        sr.id,
        sr.reference_number,
        sr.status,
        sr.requested_at,
        s.service_name
    FROM service_requests sr
    INNER JOIN services s
        ON sr.service_id = s.id
    WHERE sr.resident_id = ?
    ORDER BY sr.requested_at DESC
    LIMIT 5
");

$stmt->bind_param("i", $resident_id);
$stmt->execute();

$recent_requests = $stmt->get_result();

/*
|--------------------------------------------------------------------------
| ANNOUNCEMENTS
|--------------------------------------------------------------------------
|
| Show announcements for:
| - Everyone / NULL / empty purok
| - Resident's specific purok
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT
        id,
        title,
        content,
        target_purok,
        created_at
    FROM announcements
    WHERE status = 'Published'
    AND (
        target_purok IS NULL
        OR target_purok = ''
        OR target_purok = ?
    )
    ORDER BY created_at DESC
    LIMIT 4
");

$stmt->bind_param("s", $purok);
$stmt->execute();

$announcements = $stmt->get_result();

/*
|--------------------------------------------------------------------------
| RECENT NOTIFICATIONS
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT
        id,
        title,
        message,
        type,
        is_read,
        created_at
    FROM notifications
    WHERE user_id = ?
    ORDER BY created_at DESC
    LIMIT 5
");

$stmt->bind_param("i", $user_id);
$stmt->execute();

$notifications = $stmt->get_result();

/*
|--------------------------------------------------------------------------
| HELPER FUNCTIONS
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

function formatDateTime($date)
{
    if (!$date) {
        return '';
    }

    return date(
        'M d, Y • h:i A',
        strtotime($date)
    );
}

function statusClass($status)
{
    switch ($status) {

        case 'Completed':
            return 'status-completed';

        case 'Approved':
            return 'status-approved';

        case 'Ready':
            return 'status-ready';

        case 'Under Review':
            return 'status-review';

        case 'Rejected':
            return 'status-rejected';

        default:
            return 'status-pending';
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
    Resident Dashboard | MAPALADNEXUS
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

    z-index: -10;

    pointer-events: none;

    overflow: hidden;
}

.grid {

    position: absolute;

    inset: 0;

    opacity: .45;

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
        floating 10s ease-in-out infinite;
}

.orb.one {

    width: 350px;
    height: 350px;

    background: #2563eb;

    left: -150px;
    top: 10%;
}

.orb.two {

    width: 400px;
    height: 400px;

    background: #7c3aed;

    right: -180px;
    bottom: 10%;

    animation-delay: 2s;
}

.orb.three {

    width: 220px;
    height: 220px;

    background: #10b981;

    left: 45%;
    top: 45%;

    animation-delay: 4s;
}

@keyframes floating {

    0%,
    100% {
        transform:
            translate3d(0,0,0);
    }

    50% {
        transform:
            translate3d(30px,-35px,0);
    }
}

/* =========================================================
   LAYOUT
========================================================= */

.app {

    min-height: 100vh;

    display: flex;
}

/* =========================================================
   SIDEBAR
========================================================= */

.sidebar {

    width: 265px;

    min-height: 100vh;

    position: fixed;

    left: 0;
    top: 0;

    padding: 22px 16px;

    background:
        rgba(5,8,22,.82);

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

    padding: 8px 8px 25px;

    border-bottom:
        1px solid
        rgba(255,255,255,.08);
}

.brand-logo {

    width: 45px;
    height: 45px;

    flex-shrink: 0;

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

    border:
        1px solid
        rgba(255,255,255,.13);

    box-shadow:
        10px 10px 25px rgba(0,0,0,.3),
        inset 2px 2px 6px rgba(255,255,255,.12);

    font-size: 22px;
}

.brand-text strong {

    display: block;

    font-size: 16px;

    letter-spacing: 1px;
}

.brand-text span {

    display: block;

    margin-top: 4px;

    color: #64748b;

    font-size: 10px;
}

.menu-title {

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

    gap: 13px;

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
        rgba(37,99,235,.20),

        inset 0 1px
        rgba(255,255,255,.15);
}

.menu-icon {

    width: 23px;

    text-align: center;

    font-size: 17px;
}

.sidebar-bottom {

    position: absolute;

    left: 16px;
    right: 16px;
    bottom: 20px;
}

.logout {

    width: 100%;

    display: flex;

    align-items: center;

    justify-content: center;

    gap: 9px;

    padding: 13px;

    border-radius: 14px;

    text-decoration: none;

    color: #fca5a5;

    background:
        rgba(239,68,68,.08);

    border:
        1px solid
        rgba(239,68,68,.15);

    font-size: 13px;

    transition: .25s;
}

.logout:hover {

    background:
        rgba(239,68,68,.15);

    transform:
        translateY(-2px);
}

/* =========================================================
   MAIN
========================================================= */

.main {

    width: calc(100% - 265px);

    margin-left: 265px;

    padding: 25px 30px 50px;
}

/* =========================================================
   TOPBAR
========================================================= */

.topbar {

    min-height: 65px;

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 20px;

    margin-bottom: 28px;

    padding: 13px 18px;

    border-radius: 20px;

    background:
        rgba(255,255,255,.055);

    border:
        1px solid
        rgba(255,255,255,.09);

    backdrop-filter:
        blur(20px);

    box-shadow:
        15px 15px 40px
        rgba(0,0,0,.18),

        inset 1px 1px 5px
        rgba(255,255,255,.06);
}

.welcome {

    display: flex;

    align-items: center;

    gap: 13px;
}

.avatar {

    width: 43px;
    height: 43px;

    border-radius: 14px;

    display: flex;

    align-items: center;

    justify-content: center;

    background:
        linear-gradient(
            145deg,
            #2563eb,
            #4f46e5
        );

    box-shadow:
        8px 8px 20px
        rgba(0,0,0,.3);

    font-weight: bold;

    font-size: 16px;
}

.welcome-text strong {

    display: block;

    font-size: 14px;
}

.welcome-text span {

    display: block;

    margin-top: 3px;

    color: #64748b;

    font-size: 11px;
}

.top-actions {

    display: flex;

    align-items: center;

    gap: 10px;
}

.notification {

    width: 42px;
    height: 42px;

    display: flex;

    align-items: center;

    justify-content: center;

    position: relative;

    border-radius: 13px;

    color: white;

    text-decoration: none;

    background:
        rgba(255,255,255,.06);

    border:
        1px solid
        rgba(255,255,255,.09);
}

.notification-count {

    position: absolute;

    top: -4px;
    right: -4px;

    min-width: 18px;
    height: 18px;

    padding: 0 5px;

    display: flex;

    align-items: center;

    justify-content: center;

    border-radius: 50px;

    background: #ef4444;

    color: white;

    font-size: 9px;

    font-weight: bold;

    border:
        2px solid
        #050816;
}

/* =========================================================
   HERO
========================================================= */

.hero {

    position: relative;

    padding: 32px;

    border-radius: 28px;

    overflow: hidden;

    background:
        linear-gradient(
            135deg,
            rgba(37,99,235,.20),
            rgba(79,70,229,.10),
            rgba(255,255,255,.04)
        );

    border:
        1px solid
        rgba(255,255,255,.10);

    box-shadow:
        25px 25px 60px
        rgba(0,0,0,.25),

        inset 1px 1px 8px
        rgba(255,255,255,.07);

    margin-bottom: 25px;
}

.hero::after {

    content: "";

    position: absolute;

    width: 250px;
    height: 250px;

    right: -80px;
    top: -100px;

    border-radius: 50%;

    background:
        rgba(96,165,250,.15);

    filter: blur(5px);
}

.hero-content {

    position: relative;

    z-index: 2;
}

.hero small {

    color: #93c5fd;

    font-size: 11px;

    letter-spacing: 1.5px;

    text-transform: uppercase;
}

.hero h1 {

    margin-top: 10px;

    font-size:
        clamp(28px, 4vw, 42px);

    letter-spacing: -1px;
}

.hero p {

    max-width: 650px;

    margin-top: 10px;

    color: #94a3b8;

    font-size: 14px;

    line-height: 1.7;
}

.hero-buttons {

    display: flex;

    flex-wrap: wrap;

    gap: 10px;

    margin-top: 22px;
}

.hero-button {

    display: inline-flex;

    align-items: center;

    gap: 8px;

    padding: 12px 16px;

    border-radius: 13px;

    text-decoration: none;

    color: white;

    font-size: 12px;

    font-weight: bold;

    background:
        linear-gradient(
            145deg,
            #2563eb,
            #4f46e5
        );

    box-shadow:
        0 12px 25px
        rgba(37,99,235,.25);

    transition: .25s;
}

.hero-button:hover {

    transform:
        translateY(-3px);
}

.hero-button.secondary {

    background:
        rgba(255,255,255,.07);

    border:
        1px solid
        rgba(255,255,255,.10);

    box-shadow: none;
}

/* =========================================================
   STATISTICS
========================================================= */

.stats {

    display: grid;

    grid-template-columns:
        repeat(4, 1fr);

    gap: 17px;

    margin-bottom: 25px;
}

.stat-card {

    position: relative;

    padding: 21px;

    min-height: 140px;

    border-radius: 22px;

    background:
        rgba(255,255,255,.055);

    border:
        1px solid
        rgba(255,255,255,.09);

    box-shadow:
        15px 15px 35px
        rgba(0,0,0,.20),

        inset 1px 1px 5px
        rgba(255,255,255,.06);

    overflow: hidden;

    transition: .3s;
}

.stat-card:hover {

    transform:
        translateY(-7px)
        rotateX(2deg);

    background:
        rgba(255,255,255,.075);
}

.stat-icon {

    width: 43px;
    height: 43px;

    display: flex;

    align-items: center;

    justify-content: center;

    border-radius: 14px;

    background:
        rgba(96,165,250,.12);

    border:
        1px solid
        rgba(96,165,250,.13);

    font-size: 19px;
}

.stat-label {

    margin-top: 15px;

    color: #64748b;

    font-size: 11px;
}

.stat-value {

    margin-top: 4px;

    font-size: 28px;

    font-weight: bold;
}

.stat-decoration {

    position: absolute;

    right: -25px;
    bottom: -35px;

    width: 110px;
    height: 110px;

    border-radius: 50%;

    background:
        rgba(96,165,250,.06);
}

/* =========================================================
   CONTENT GRID
========================================================= */

.content-grid {

    display: grid;

    grid-template-columns:
        minmax(0, 1.45fr)
        minmax(300px, .8fr);

    gap: 20px;
}

.panel {

    padding: 22px;

    border-radius: 23px;

    background:
        rgba(255,255,255,.05);

    border:
        1px solid
        rgba(255,255,255,.09);

    box-shadow:
        15px 15px 40px
        rgba(0,0,0,.20),

        inset 1px 1px 5px
        rgba(255,255,255,.05);
}

.panel-header {

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 10px;

    margin-bottom: 18px;
}

.panel-header h2 {

    font-size: 16px;
}

.panel-header a {

    color: #93c5fd;

    text-decoration: none;

    font-size: 11px;
}

/* =========================================================
   REQUESTS
========================================================= */

.request {

    display: flex;

    align-items: center;

    gap: 13px;

    padding: 14px 0;

    border-bottom:
        1px solid
        rgba(255,255,255,.06);
}

.request:last-child {

    border-bottom: none;
}

.request-icon {

    width: 42px;
    height: 42px;

    flex-shrink: 0;

    display: flex;

    align-items: center;

    justify-content: center;

    border-radius: 13px;

    background:
        rgba(96,165,250,.09);

    font-size: 17px;
}

.request-info {

    min-width: 0;

    flex: 1;
}

.request-info strong {

    display: block;

    overflow: hidden;

    text-overflow: ellipsis;

    white-space: nowrap;

    font-size: 13px;
}

.request-info span {

    display: block;

    margin-top: 5px;

    color: #64748b;

    font-size: 10px;
}

.status {

    display: inline-flex;

    align-items: center;

    padding: 6px 9px;

    border-radius: 50px;

    font-size: 9px;

    font-weight: bold;

    white-space: nowrap;
}

.status-pending {

    color: #fcd34d;

    background:
        rgba(245,158,11,.10);
}

.status-review {

    color: #93c5fd;

    background:
        rgba(59,130,246,.10);
}

.status-approved {

    color: #86efac;

    background:
        rgba(34,197,94,.10);
}

.status-ready {

    color: #c4b5fd;

    background:
        rgba(139,92,246,.10);
}

.status-completed {

    color: #6ee7b7;

    background:
        rgba(16,185,129,.10);
}

.status-rejected {

    color: #fca5a5;

    background:
        rgba(239,68,68,.10);
}

.empty {

    padding: 35px 15px;

    text-align: center;

    color: #64748b;

    font-size: 12px;
}

/* =========================================================
   ANNOUNCEMENTS
========================================================= */

.announcement {

    padding: 15px 0;

    border-bottom:
        1px solid
        rgba(255,255,255,.06);
}

.announcement:last-child {

    border-bottom: none;
}

.announcement-title {

    display: flex;

    align-items: flex-start;

    gap: 10px;
}

.announcement-icon {

    width: 35px;
    height: 35px;

    display: flex;

    align-items: center;

    justify-content: center;

    flex-shrink: 0;

    border-radius: 11px;

    background:
        rgba(124,58,237,.12);
}

.announcement h3 {

    font-size: 12px;

    line-height: 1.4;
}

.announcement p {

    margin-top: 7px;

    color: #64748b;

    font-size: 11px;

    line-height: 1.6;

    display: -webkit-box;

    -webkit-line-clamp: 2;

    -webkit-box-orient: vertical;

    overflow: hidden;
}

.announcement-date {

    display: block;

    margin-top: 7px;

    color: #475569;

    font-size: 9px;
}

/* =========================================================
   NOTIFICATIONS
========================================================= */

.notification-item {

    display: flex;

    gap: 11px;

    padding: 13px 0;

    border-bottom:
        1px solid
        rgba(255,255,255,.06);
}

.notification-item:last-child {

    border-bottom: none;
}

.notification-item.unread {

    background:
        rgba(37,99,235,.04);

    border-radius: 10px;

    padding-left: 8px;

    padding-right: 8px;
}

.notification-icon {

    width: 35px;
    height: 35px;

    display: flex;

    align-items: center;

    justify-content: center;

    flex-shrink: 0;

    border-radius: 11px;

    background:
        rgba(37,99,235,.10);
}

.notification-info {

    flex: 1;
}

.notification-info strong {

    display: block;

    font-size: 11px;
}

.notification-info p {

    margin-top: 4px;

    color: #64748b;

    font-size: 10px;

    line-height: 1.5;
}

.notification-info span {

    display: block;

    margin-top: 5px;

    color: #475569;

    font-size: 9px;
}

/* =========================================================
   PROFILE CARD
========================================================= */

.profile-card {

    margin-top: 20px;

    padding: 20px;

    border-radius: 20px;

    background:
        linear-gradient(
            145deg,
            rgba(255,255,255,.07),
            rgba(255,255,255,.025)
        );

    border:
        1px solid
        rgba(255,255,255,.08);
}

.profile-head {

    display: flex;

    align-items: center;

    gap: 13px;
}

.profile-avatar {

    width: 50px;
    height: 50px;

    display: flex;

    align-items: center;

    justify-content: center;

    border-radius: 16px;

    background:
        linear-gradient(
            145deg,
            #2563eb,
            #7c3aed
        );

    font-size: 17px;

    font-weight: bold;

    box-shadow:
        10px 10px 25px
        rgba(0,0,0,.25);
}

.profile-head strong {

    display: block;

    font-size: 13px;
}

.profile-head span {

    display: block;

    margin-top: 4px;

    color: #64748b;

    font-size: 10px;
}

.profile-details {

    margin-top: 17px;

    display: grid;

    gap: 9px;
}

.profile-row {

    display: flex;

    justify-content: space-between;

    gap: 15px;

    font-size: 10px;
}

.profile-row span:first-child {

    color: #64748b;
}

.profile-row span:last-child {

    color: #cbd5e1;

    text-align: right;
}

/* =========================================================
   MOBILE MENU
========================================================= */

.mobile-header {

    display: none;
}

.overlay {

    display: none;
}

/* =========================================================
   RESPONSIVE
========================================================= */

@media(max-width:1100px) {

    .stats {

        grid-template-columns:
            repeat(2, 1fr);
    }

    .content-grid {

        grid-template-columns: 1fr;
    }
}

@media(max-width:800px) {

    .sidebar {

        transform:
            translateX(-100%);

        transition:
            .3s ease;
    }

    .sidebar.open {

        transform:
            translateX(0);
    }

    .main {

        width: 100%;

        margin-left: 0;

        padding:
            15px
            15px
            40px;
    }

    .mobile-header {

        display: flex;

        align-items: center;

        justify-content: space-between;

        margin-bottom: 15px;

        padding: 12px 15px;

        border-radius: 16px;

        background:
            rgba(255,255,255,.06);

        border:
            1px solid
            rgba(255,255,255,.08);
    }

    .mobile-header strong {

        font-size: 14px;

        letter-spacing: 1px;
    }

    .mobile-menu {

        width: 40px;
        height: 40px;

        border: none;

        border-radius: 12px;

        color: white;

        background:
            rgba(255,255,255,.08);

        cursor: pointer;

        font-size: 18px;
    }

    .overlay {

        position: fixed;

        inset: 0;

        background:
            rgba(0,0,0,.55);

        z-index: 90;
    }

    .overlay.show {

        display: block;
    }
}

@media(max-width:600px) {

    .stats {

        grid-template-columns: 1fr;
    }

    .hero {

        padding: 24px 20px;
    }

    .topbar {

        padding: 12px;
    }

    .welcome-text span {

        display: none;
    }

    .request {

        align-items: flex-start;

        flex-wrap: wrap;
    }

    .request .status {

        margin-left: 55px;
    }
}

</style>

</head>

<body>

<!-- =========================================================
     BACKGROUND
========================================================= -->

<div class="background">

    <div class="grid"></div>

    <div class="orb one"></div>

    <div class="orb two"></div>

    <div class="orb three"></div>

</div>

<div class="app">

<!-- =========================================================
     SIDEBAR
========================================================= -->

<aside class="sidebar" id="sidebar">

    <div class="brand">

        <div class="brand-logo">
            🏛️
        </div>

        <div class="brand-text">

            <strong>
                MAPALADNEXUS
            </strong>

            <span>
                Barangay Mapalad
            </span>

        </div>

    </div>

    <div class="menu-title">
        Resident Portal
    </div>

    <nav class="menu">

        <a
            href="user_dashboard.php"
            class="active"
        >

            <span class="menu-icon">⌂</span>

            Dashboard

        </a>

        <a href="services.php">

            <span class="menu-icon">📄</span>

            Services

        </a>

        <a href="requests.php">

            <span class="menu-icon">📋</span>

            My Requests

        </a>

        <a href="complaints.php">

            <span class="menu-icon">💬</span>

            Complaints

        </a>

        <a href="announcements.php">

            <span class="menu-icon">📢</span>

            Announcements

        </a>

        <a href="profile.php">

            <span class="menu-icon">👤</span>

            My Profile

        </a>

    </nav>

    <div class="sidebar-bottom">

        <a
            href="logout.php"
            class="logout"
        >

            🚪

            Logout

        </a>

    </div>

</aside>

<div
    class="overlay"
    id="overlay"
    onclick="closeSidebar()"
></div>

<!-- =========================================================
     MAIN
========================================================= -->

<main class="main">

    <!-- MOBILE HEADER -->

    <div class="mobile-header">

        <strong>
            MAPALADNEXUS
        </strong>

        <button
            class="mobile-menu"
            onclick="openSidebar()"
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
                        substr($first_name, 0, 1)
                    )
                ) ?>

            </div>

            <div class="welcome-text">

                <strong>
                    Welcome back, <?= e($first_name) ?>!
                </strong>

                <span>
                    <?= e($purok) ?> · Resident
                </span>

            </div>

        </div>

        <div class="top-actions">

            <a
                href="#notifications"
                class="notification"
            >

                🔔

                <?php if ($notification_count > 0): ?>

                    <span class="notification-count">
                        <?= $notification_count > 99
                            ? '99+'
                            : $notification_count
                        ?>
                    </span>

                <?php endif; ?>

            </a>

        </div>

    </div>

    <!-- =====================================================
         HERO
    ====================================================== -->

    <section class="hero">

        <div class="hero-content">

            <small>
                Barangay Mapalad Digital Community
            </small>

            <h1>
                Hello, <?= e($first_name) ?> 👋
            </h1>

            <p>
                Welcome to your MAPALADNEXUS resident portal.
                Access barangay services, submit requests,
                track applications, read announcements, and
                send community concerns—all in one place.
            </p>

            <div class="hero-buttons">

                <a
                    href="services.php"
                    class="hero-button"
                >
                    📄 Request a Service
                </a>

                <a
                    href="complaints.php"
                    class="hero-button secondary"
                >
                    💬 Send a Concern
                </a>

            </div>

        </div>

    </section>

    <!-- =====================================================
         STATISTICS
    ====================================================== -->

    <section class="stats">

        <div class="stat-card">

            <div class="stat-icon">
                📋
            </div>

            <div class="stat-label">
                TOTAL REQUESTS
            </div>

            <div class="stat-value">
                <?= $request_count ?>
            </div>

            <div class="stat-decoration"></div>

        </div>

        <div class="stat-card">

            <div class="stat-icon">
                ⏳
            </div>

            <div class="stat-label">
                PENDING
            </div>

            <div class="stat-value">
                <?= $pending_count ?>
            </div>

            <div class="stat-decoration"></div>

        </div>

        <div class="stat-card">

            <div class="stat-icon">
                ✅
            </div>

            <div class="stat-label">
                COMPLETED
            </div>

            <div class="stat-value">
                <?= $completed_count ?>
            </div>

            <div class="stat-decoration"></div>

        </div>

        <div class="stat-card">

            <div class="stat-icon">
                💬
            </div>

            <div class="stat-label">
                MY CONCERNS
            </div>

            <div class="stat-value">
                <?= $complaint_count ?>
            </div>

            <div class="stat-decoration"></div>

        </div>

    </section>

    <!-- =====================================================
         CONTENT
    ====================================================== -->

    <div class="content-grid">

        <!-- =================================================
             LEFT
        ================================================== -->

        <div>

            <!-- RECENT REQUESTS -->

            <section class="panel">

                <div class="panel-header">

                    <h2>
                        📋 Recent Requests
                    </h2>

                    <a href="requests.php">
                        View All →
                    </a>

                </div>

                <?php if ($recent_requests->num_rows > 0): ?>

                    <?php while (
                        $request =
                        $recent_requests->fetch_assoc()
                    ): ?>

                        <div class="request">

                            <div class="request-icon">
                                📄
                            </div>

                            <div class="request-info">

                                <strong>
                                    <?= e(
                                        $request['service_name']
                                    ) ?>
                                </strong>

                                <span>

                                    Reference:
                                    <?= e(
                                        $request['reference_number']
                                    ) ?>

                                    ·

                                    <?= e(
                                        formatDateTime(
                                            $request['requested_at']
                                        )
                                    ) ?>

                                </span>

                            </div>

                            <span
                                class="status
                                <?= e(
                                    statusClass(
                                        $request['status']
                                    )
                                ) ?>"
                            >

                                <?= e(
                                    $request['status']
                                ) ?>

                            </span>

                        </div>

                    <?php endwhile; ?>

                <?php else: ?>

                    <div class="empty">

                        📄

                        <br><br>

                        You don't have any service requests yet.

                    </div>

                <?php endif; ?>

            </section>

            <!-- ANNOUNCEMENTS -->

            <section
                class="panel"
                style="margin-top:20px;"
            >

                <div class="panel-header">

                    <h2>
                        📢 Latest Announcements
                    </h2>

                    <a href="announcements.php">
                        View All →
                    </a>

                </div>

                <?php if ($announcements->num_rows > 0): ?>

                    <?php while (
                        $announcement =
                        $announcements->fetch_assoc()
                    ): ?>

                        <div class="announcement">

                            <div class="announcement-title">

                                <div class="announcement-icon">
                                    📢
                                </div>

                                <div>

                                    <h3>
                                        <?= e(
                                            $announcement['title']
                                        ) ?>
                                    </h3>

                                    <p>
                                        <?= e(
                                            $announcement['content']
                                        ) ?>
                                    </p>

                                    <span
                                        class="announcement-date"
                                    >
                                        <?= e(
                                            formatDateTime(
                                                $announcement['created_at']
                                            )
                                        ) ?>

                                        <?php if (
                                            !empty(
                                                $announcement[
                                                    'target_purok'
                                                ]
                                            )
                                        ): ?>

                                            ·
                                            <?= e(
                                                $announcement[
                                                    'target_purok'
                                                ]
                                            ) ?>

                                        <?php endif; ?>

                                    </span>

                                </div>

                            </div>

                        </div>

                    <?php endwhile; ?>

                <?php else: ?>

                    <div class="empty">

                        📢

                        <br><br>

                        No announcements available for
                        <?= e($purok) ?>.

                    </div>

                <?php endif; ?>

            </section>

        </div>

        <!-- =================================================
             RIGHT
        ================================================== -->

        <div>

            <!-- PROFILE -->

            <section class="profile-card">

                <div class="profile-head">

                    <div class="profile-avatar">

                        <?= e(
                            strtoupper(
                                substr($first_name, 0, 1)
                            )
                        ) ?>

                    </div>

                    <div>

                        <strong>
                            <?= e($full_name) ?>
                        </strong>

                        <span>
                            Resident · <?= e($purok) ?>
                        </span>

                    </div>

                </div>

                <div class="profile-details">

                    <div class="profile-row">

                        <span>
                            Username
                        </span>

                        <span>
                            <?= e(
                                $_SESSION['username']
                            ) ?>
                        </span>

                    </div>

                    <div class="profile-row">

                        <span>
                            Purok
                        </span>

                        <span>
                            <?= e($purok) ?>
                        </span>

                    </div>

                    <div class="profile-row">

                        <span>
                            Email
                        </span>

                        <span>
                            <?= e($email ?: 'Not provided') ?>
                        </span>

                    </div>

                </div>

            </section>

            <!-- NOTIFICATIONS -->

            <section
                class="panel"
                id="notifications"
                style="margin-top:20px;"
            >

                <div class="panel-header">

                    <h2>
                        🔔 Notifications
                    </h2>

                </div>

                <?php if ($notifications->num_rows > 0): ?>

                    <?php while (
                        $notification =
                        $notifications->fetch_assoc()
                    ): ?>

                        <div
                            class="notification-item
                            <?= $notification['is_read']
                                ? ''
                                : 'unread'
                            ?>"
                        >

                            <div class="notification-icon">

                                <?php

                                if (
                                    $notification['type']
                                    === 'success'
                                ) {

                                    echo '✅';

                                } elseif (
                                    $notification['type']
                                    === 'warning'
                                ) {

                                    echo '⚠️';

                                } elseif (
                                    $notification['type']
                                    === 'danger'
                                ) {

                                    echo '🚨';

                                } else {

                                    echo '🔔';
                                }

                                ?>

                            </div>

                            <div class="notification-info">

                                <strong>
                                    <?= e(
                                        $notification['title']
                                    ) ?>
                                </strong>

                                <p>
                                    <?= e(
                                        $notification['message']
                                    ) ?>
                                </p>

                                <span>
                                    <?= e(
                                        formatDateTime(
                                            $notification['created_at']
                                        )
                                    ) ?>
                                </span>

                            </div>

                        </div>

                    <?php endwhile; ?>

                <?php else: ?>

                    <div class="empty">

                        🔔

                        <br><br>

                        You're all caught up.

                    </div>

                <?php endif; ?>

            </section>

        </div>

    </div>

</main>

</div>

<script>

function openSidebar() {

    const sidebar =
        document.getElementById('sidebar');

    const overlay =
        document.getElementById('overlay');

    sidebar.classList.add('open');

    overlay.classList.add('show');
}

function closeSidebar() {

    const sidebar =
        document.getElementById('sidebar');

    const overlay =
        document.getElementById('overlay');

    sidebar.classList.remove('open');

    overlay.classList.remove('show');
}

</script>

</body>

</html>