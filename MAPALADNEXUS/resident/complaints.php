<?php

session_start();

require_once __DIR__ . '/../config/database.php';

/*
|--------------------------------------------------------------------------
| MAPALADNEXUS
| RESIDENT COMPLAINTS & CONCERNS
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

$resident_id = (int)$resident['id'];

/*
|--------------------------------------------------------------------------
| FORM SUBMISSION
|--------------------------------------------------------------------------
*/

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $subject = trim(
        $_POST['subject'] ?? ''
    );

    $description = trim(
        $_POST['description'] ?? ''
    );

    $location = trim(
        $_POST['location'] ?? ''
    );

    /*
    |--------------------------------------------------------------------------
    | VALIDATION
    |--------------------------------------------------------------------------
    */

    if ($subject === '') {

        $message =
            "Please enter the subject of your complaint.";

        $message_type = "error";

    }
    elseif ($description === '') {

        $message =
            "Please describe your complaint or concern.";

        $message_type = "error";

    }
    else {

        /*
        |--------------------------------------------------------------------------
        | INSERT COMPLAINT
        |--------------------------------------------------------------------------
        */

        $stmt = $conn->prepare("
            INSERT INTO complaints
            (
                resident_id,
                subject,
                description,
                location,
                status
            )
            VALUES
            (
                ?,
                ?,
                ?,
                ?,
                'Pending'
            )
        ");

        $stmt->bind_param(
            "isss",
            $resident_id,
            $subject,
            $description,
            $location
        );

        if ($stmt->execute()) {

            $message =
                "Your complaint has been submitted successfully.";

            $message_type = "success";

        } else {

            $message =
                "Unable to submit your complaint. Please try again.";

            $message_type = "error";
        }
    }
}

/*
|--------------------------------------------------------------------------
| GET COMPLAINTS
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT
        id,
        subject,
        description,
        location,
        status,
        created_at,
        updated_at
    FROM complaints
    WHERE resident_id = ?
    ORDER BY created_at DESC
");

$stmt->bind_param(
    "i",
    $resident_id
);

$stmt->execute();

$complaints_result =
    $stmt->get_result();

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
    Complaints | MAPALADNEXUS
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

    filter: blur(15px);

    opacity: .18;

    animation:
        floating 9s ease-in-out infinite;
}

.orb.one {

    width: 360px;
    height: 360px;

    left: -180px;
    top: 10%;

    background: #2563eb;
}

.orb.two {

    width: 420px;
    height: 420px;

    right: -210px;
    bottom: -120px;

    background: #7c3aed;

    animation-delay: 2s;
}

@keyframes floating {

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
        rgba(5,8,22,.92);

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
            rgba(37,99,235,.35),
            rgba(79,70,229,.12)
        );

    box-shadow:
        10px 10px 25px
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
            rgba(37,99,235,.82),
            rgba(79,70,229,.72)
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

/* =========================================================
   MAIN
========================================================= */

.main {

    width:
        calc(100% - 265px);

    margin-left: 265px;

    padding: 30px;
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

    padding: 9px 12px;

    color: white;

    border: none;

    border-radius: 10px;

    background:
        rgba(255,255,255,.08);

    font-size: 18px;
}

/* =========================================================
   HEADER
========================================================= */

.header {

    display: flex;

    align-items: flex-end;

    justify-content: space-between;

    gap: 20px;

    margin-bottom: 25px;
}

.header small {

    color: #60a5fa;

    font-size: 10px;

    text-transform: uppercase;

    letter-spacing: 1.7px;
}

.header h1 {

    margin-top: 8px;

    font-size:
        clamp(28px,4vw,42px);
}

.header p {

    margin-top: 8px;

    max-width: 650px;

    color: #94a3b8;

    font-size: 12px;

    line-height: 1.7;
}

/* =========================================================
   3D STAT CARD
========================================================= */

.stat-card {

    min-width: 190px;

    padding: 20px;

    border-radius: 20px;

    background:
        linear-gradient(
            145deg,
            rgba(37,99,235,.17),
            rgba(124,58,237,.10)
        );

    border:
        1px solid
        rgba(96,165,250,.14);

    box-shadow:
        15px 20px 40px
        rgba(0,0,0,.22);

    transform:
        perspective(700px)
        rotateY(-3deg);
}

.stat-card span {

    display: block;

    color: #64748b;

    font-size: 9px;

    text-transform: uppercase;

    letter-spacing: 1px;
}

.stat-card strong {

    display: block;

    margin-top: 8px;

    font-size: 28px;
}

/* =========================================================
   ALERT
========================================================= */

.alert {

    margin-bottom: 20px;

    padding: 15px 18px;

    border-radius: 15px;

    font-size: 10px;

    line-height: 1.6;
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
   LAYOUT
========================================================= */

.content-grid {

    display: grid;

    grid-template-columns:
        390px
        minmax(0,1fr);

    gap: 22px;

    align-items: start;
}

/* =========================================================
   CARD
========================================================= */

.card {

    padding: 24px;

    border-radius: 22px;

    background:
        linear-gradient(
            145deg,
            rgba(255,255,255,.065),
            rgba(255,255,255,.035)
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

.card-title {

    display: flex;

    align-items: center;

    gap: 9px;

    margin-bottom: 20px;

    font-size: 14px;

    font-weight: bold;
}

/* =========================================================
   FORM
========================================================= */

.form-group {

    margin-bottom: 17px;
}

.form-group label {

    display: block;

    margin-bottom: 8px;

    color: #94a3b8;

    font-size: 10px;

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

    font-size: 11px;

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

textarea.form-control {

    min-height: 135px;

    resize: vertical;

    line-height: 1.7;
}

/* =========================================================
   SUBMIT
========================================================= */

.submit-btn {

    width: 100%;

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

    font-size: 11px;

    font-weight: bold;

    transition: .25s;
}

.submit-btn:hover {

    transform:
        translateY(-2px);

    box-shadow:
        0 17px 30px
        rgba(37,99,235,.30);
}

/* =========================================================
   TIPS
========================================================= */

.tips {

    margin-top: 18px;

    padding: 14px;

    color: #94a3b8;

    background:
        rgba(37,99,235,.06);

    border:
        1px solid
        rgba(96,165,250,.10);

    border-radius: 14px;

    font-size: 9px;

    line-height: 1.8;
}

/* =========================================================
   COMPLAINT LIST
========================================================= */

.complaint-list {

    display: flex;

    flex-direction: column;

    gap: 14px;
}

.complaint {

    position: relative;

    padding: 19px;

    border-radius: 18px;

    background:
        rgba(255,255,255,.035);

    border:
        1px solid
        rgba(255,255,255,.07);

    transition: .25s;
}

.complaint:hover {

    transform:
        translateY(-3px);

    background:
        rgba(255,255,255,.055);

    box-shadow:
        0 15px 35px
        rgba(0,0,0,.20);
}

.complaint-top {

    display: flex;

    align-items: flex-start;

    justify-content: space-between;

    gap: 15px;

    margin-bottom: 10px;
}

.complaint-title {

    color: #e2e8f0;

    font-size: 13px;

    font-weight: bold;
}

.complaint-ref {

    margin-top: 5px;

    color: #475569;

    font-family: monospace;

    font-size: 8px;
}

.badge {

    display: inline-flex;

    align-items: center;

    gap: 5px;

    padding: 7px 10px;

    border-radius: 20px;

    font-size: 8px;

    font-weight: bold;

    white-space: nowrap;
}

.badge.pending {

    color: #fde68a;

    background:
        rgba(234,179,8,.10);

    border:
        1px solid
        rgba(234,179,8,.15);
}

.badge.review {

    color: #93c5fd;

    background:
        rgba(37,99,235,.10);

    border:
        1px solid
        rgba(96,165,250,.15);
}

.badge.resolved {

    color: #86efac;

    background:
        rgba(34,197,94,.09);

    border:
        1px solid
        rgba(34,197,94,.15);
}

.badge.rejected {

    color: #fca5a5;

    background:
        rgba(239,68,68,.09);

    border:
        1px solid
        rgba(239,68,68,.15);
}

.complaint-description {

    color: #94a3b8;

    font-size: 10px;

    line-height: 1.7;

    margin-bottom: 12px;
}

.complaint-meta {

    display: flex;

    flex-wrap: wrap;

    gap: 8px 18px;

    padding-top: 12px;

    border-top:
        1px solid
        rgba(255,255,255,.06);

    color: #64748b;

    font-size: 8px;
}

/* =========================================================
   EMPTY
========================================================= */

.empty {

    padding: 60px 20px;

    text-align: center;

    border-radius: 18px;

    background:
        rgba(255,255,255,.025);

    border:
        1px dashed
        rgba(255,255,255,.10);
}

.empty-icon {

    font-size: 42px;

    opacity: .65;

    margin-bottom: 15px;
}

.empty strong {

    display: block;

    margin-bottom: 7px;

    color: #cbd5e1;

    font-size: 14px;
}

.empty p {

    color: #64748b;

    font-size: 10px;
}

/* =========================================================
   RESPONSIVE
========================================================= */

@media(max-width:1100px) {

    .content-grid {

        grid-template-columns: 1fr;
    }

    .stat-card {

        min-width: 160px;
    }

}

@media(max-width:900px) {

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

        padding: 18px;
    }

    .mobile {

        display: flex;
    }

    .header {

        align-items: flex-start;

        flex-direction: column;
    }

    .stat-card {

        width: 100%;
    }

}

@media(max-width:600px) {

    .complaint-top {

        flex-direction: column;
    }

    .badge {

        align-self: flex-start;
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

        <a
            href="complaints.php"
            class="active"
        >

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

        <button
            onclick="toggleMenu()"
        >
            ☰
        </button>

    </div>

    <!-- HEADER -->

    <section class="header">

        <div>

            <small>
                Resident Portal
            </small>

            <h1>
                Complaints & Concerns
            </h1>

            <p>
                Submit your complaint or concern
                directly to Barangay Mapalad and
                monitor its progress.
            </p>

        </div>

        <div class="stat-card">

            <span>
                My Complaints
            </span>

            <strong>
                <?= $complaints_result->num_rows ?>
            </strong>

        </div>

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

    <!-- CONTENT -->

    <div class="content-grid">

        <!-- =================================================
             FORM
        ================================================== -->

        <section class="card">

            <div class="card-title">

                💬

                Submit Complaint

            </div>

            <form
                method="POST"
                action=""
            >

                <div class="form-group">

                    <label>
                        Subject *
                    </label>

                    <input
                        type="text"
                        name="subject"
                        class="form-control"
                        placeholder="Example: Street light not working"
                        maxlength="200"
                        required
                    >

                </div>

                <div class="form-group">

                    <label>
                        Location
                    </label>

                    <input
                        type="text"
                        name="location"
                        class="form-control"
                        placeholder="Example: Purok 1, Barangay Mapalad"
                        maxlength="255"
                    >

                </div>

                <div class="form-group">

                    <label>
                        Description *
                    </label>

                    <textarea
                        name="description"
                        class="form-control"
                        placeholder="Please explain your complaint or concern clearly..."
                        required
                    ></textarea>

                </div>

                <button
                    type="submit"
                    class="submit-btn"
                >

                    🚀

                    Submit Complaint

                </button>

            </form>

            <div class="tips">

                💡 <strong>Tip:</strong>

                Please provide accurate information,
                especially the location and details
                of the concern, so the barangay can
                respond appropriately.

            </div>

        </section>

        <!-- =================================================
             LIST
        ================================================== -->

        <section class="card">

            <div class="card-title">

                📋

                My Complaints

            </div>

            <?php if (
                $complaints_result->num_rows > 0
            ): ?>

                <div class="complaint-list">

                    <?php while (
                        $complaint =
                        $complaints_result->fetch_assoc()
                    ): ?>

                        <?php

                        $status =
                            $complaint['status']
                            ?: 'Pending';

                        $status_class =
                            strtolower(
                                $status
                            );

                        if (
                            $status ===
                            'Under Review'
                        ) {
                            $status_class =
                                'review';
                        }

                        ?>

                        <article class="complaint">

                            <div class="complaint-top">

                                <div>

                                    <div class="complaint-title">

                                        <?= e(
                                            $complaint['subject']
                                        ) ?>

                                    </div>

                                    <div class="complaint-ref">

                                        Complaint #

                                        <?= str_pad(
                                            (string)$complaint['id'],
                                            6,
                                            '0',
                                            STR_PAD_LEFT
                                        ) ?>

                                    </div>

                                </div>

                                <span
                                    class="badge
                                    <?= e(
                                        $status_class
                                    ) ?>"
                                >

                                    ●

                                    <?= e(
                                        $status
                                    ) ?>

                                </span>

                            </div>

                            <div class="complaint-description">

                                <?= nl2br(
                                    e(
                                        $complaint['description']
                                    )
                                ) ?>

                            </div>

                            <div class="complaint-meta">

                                <span>

                                    📍

                                    <?= !empty(
                                        $complaint['location']
                                    )
                                        ? e(
                                            $complaint['location']
                                        )
                                        : 'Location not specified'
                                    ?>

                                </span>

                                <span>

                                    📅

                                    <?= e(
                                        date(
                                            'M d, Y • h:i A',
                                            strtotime(
                                                $complaint['created_at']
                                            )
                                        )
                                    ) ?>

                                </span>

                                <span>

                                    🔄 Updated:

                                    <?= e(
                                        date(
                                            'M d, Y • h:i A',
                                            strtotime(
                                                $complaint['updated_at']
                                            )
                                        )
                                    ) ?>

                                </span>

                            </div>

                        </article>

                    <?php endwhile; ?>

                </div>

            <?php else: ?>

                <div class="empty">

                    <div class="empty-icon">
                        💬
                    </div>

                    <strong>
                        No complaints yet
                    </strong>

                    <p>
                        You haven't submitted
                        any complaints or concerns.
                    </p>

                </div>

            <?php endif; ?>

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