<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>MAPALADNEXUS | Barangay Mapalad</title>

    <meta
        name="description"
        content="MAPALADNEXUS - Digital Barangay Management and Community Services System of Barangay Mapalad."
    >

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            min-height: 100vh;
            color: #ffffff;
            background:
                radial-gradient(circle at 15% 20%, rgba(59,130,246,.35), transparent 30%),
                radial-gradient(circle at 85% 25%, rgba(139,92,246,.35), transparent 30%),
                radial-gradient(circle at 50% 90%, rgba(16,185,129,.25), transparent 35%),
                #050816;
            overflow-x: hidden;
        }

        /* =========================
           BACKGROUND
        ========================== */

        .background {
            position: fixed;
            inset: 0;
            z-index: -5;
            overflow: hidden;
        }

        .orb {
            position: absolute;
            border-radius: 50%;
            filter: blur(3px);
            opacity: .35;
            animation: float 10s ease-in-out infinite;
        }

        .orb.one {
            width: 280px;
            height: 280px;
            background: #2563eb;
            top: 10%;
            left: -80px;
        }

        .orb.two {
            width: 350px;
            height: 350px;
            background: #7c3aed;
            right: -100px;
            top: 30%;
            animation-delay: 2s;
        }

        .orb.three {
            width: 220px;
            height: 220px;
            background: #10b981;
            left: 40%;
            bottom: -80px;
            animation-delay: 4s;
        }

        @keyframes float {
            0%, 100% {
                transform: translateY(0) translateX(0);
            }

            50% {
                transform: translateY(-35px) translateX(20px);
            }
        }

        .grid {
            position: absolute;
            inset: 0;
            background-image:
                linear-gradient(rgba(255,255,255,.035) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255,255,255,.035) 1px, transparent 1px);
            background-size: 55px 55px;
            mask-image: linear-gradient(to bottom, black, transparent);
        }

        /* =========================
           NAVBAR
        ========================== */

        .navbar {
            width: min(1200px, calc(100% - 40px));
            margin: 20px auto 0;
            padding: 15px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: rgba(255,255,255,.07);
            border: 1px solid rgba(255,255,255,.12);
            backdrop-filter: blur(20px);
            border-radius: 22px;
            box-shadow:
                0 20px 50px rgba(0,0,0,.25),
                inset 0 1px rgba(255,255,255,.12);
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .brand-icon {
            width: 48px;
            height: 48px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 15px;
            background:
                linear-gradient(
                    145deg,
                    rgba(255,255,255,.2),
                    rgba(255,255,255,.03)
                );
            border: 1px solid rgba(255,255,255,.15);
            box-shadow:
                8px 8px 20px rgba(0,0,0,.3),
                inset 2px 2px 5px rgba(255,255,255,.12);
            font-size: 23px;
        }

        .brand-text strong {
            display: block;
            font-size: 18px;
            letter-spacing: 1px;
        }

        .brand-text span {
            display: block;
            font-size: 11px;
            color: #a5b4fc;
            margin-top: 3px;
        }

        .nav-links {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .nav-links a {
            color: #e2e8f0;
            text-decoration: none;
            padding: 11px 16px;
            border-radius: 12px;
            transition: .25s;
            font-size: 14px;
        }

        .nav-links a:hover {
            background: rgba(255,255,255,.09);
            transform: translateY(-2px);
        }

        .nav-login {
            background:
                linear-gradient(
                    145deg,
                    #2563eb,
                    #4f46e5
                ) !important;

            box-shadow:
                0 10px 25px rgba(37,99,235,.35);
        }

        /* =========================
           HERO
        ========================== */

        .hero {
            width: min(1200px, calc(100% - 40px));
            min-height: calc(100vh - 110px);
            margin: auto;
            display: grid;
            grid-template-columns: 1.05fr .95fr;
            align-items: center;
            gap: 50px;
        }

        .hero-content {
            animation: appear 1s ease forwards;
        }

        @keyframes appear {
            from {
                opacity: 0;
                transform: translateY(30px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 9px 14px;
            border-radius: 100px;
            background: rgba(255,255,255,.07);
            border: 1px solid rgba(255,255,255,.12);
            color: #c7d2fe;
            font-size: 12px;
            box-shadow:
                inset 0 1px rgba(255,255,255,.1),
                0 10px 30px rgba(0,0,0,.2);
        }

        .badge-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #34d399;
            box-shadow: 0 0 15px #34d399;
        }

        .hero h1 {
            margin-top: 22px;
            font-size: clamp(50px, 8vw, 92px);
            line-height: .9;
            letter-spacing: -5px;
            background:
                linear-gradient(
                    120deg,
                    #ffffff,
                    #c7d2fe 45%,
                    #60a5fa
                );
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            text-shadow: 0 25px 50px rgba(0,0,0,.25);
        }

        .hero h2 {
            margin-top: 22px;
            font-size: clamp(21px, 3vw, 30px);
            font-weight: 500;
            color: #e2e8f0;
        }

        .hero-description {
            margin-top: 18px;
            max-width: 650px;
            color: #94a3b8;
            line-height: 1.8;
            font-size: 16px;
        }

        .hero-buttons {
            margin-top: 32px;
            display: flex;
            flex-wrap: wrap;
            gap: 14px;
        }

        .btn {
            text-decoration: none;
            color: white;
            padding: 15px 22px;
            border-radius: 15px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 9px;
            font-weight: bold;
            font-size: 14px;
            transition: .25s;
        }

        .btn:hover {
            transform: translateY(-5px);
        }

        .btn-primary {
            background:
                linear-gradient(
                    145deg,
                    #2563eb,
                    #4f46e5
                );

            box-shadow:
                0 15px 30px rgba(37,99,235,.35),
                inset 0 1px rgba(255,255,255,.25);
        }

        .btn-secondary {
            background: rgba(255,255,255,.07);
            border: 1px solid rgba(255,255,255,.12);
            box-shadow:
                0 15px 30px rgba(0,0,0,.2),
                inset 0 1px rgba(255,255,255,.1);
        }

        /* =========================
           3D CARD
        ========================== */

        .hero-visual {
            perspective: 1200px;
            display: flex;
            justify-content: center;
        }

        .cube-container {
            width: min(430px, 90vw);
            height: 430px;
            position: relative;
            transform-style: preserve-3d;
            animation: cardFloat 6s ease-in-out infinite;
        }

        @keyframes cardFloat {
            0%, 100% {
                transform:
                    rotateX(3deg)
                    rotateY(-7deg)
                    translateY(0);
            }

            50% {
                transform:
                    rotateX(-3deg)
                    rotateY(7deg)
                    translateY(-18px);
            }
        }

        .main-card {
            position: absolute;
            inset: 25px;
            border-radius: 35px;
            background:
                linear-gradient(
                    145deg,
                    rgba(255,255,255,.15),
                    rgba(255,255,255,.035)
                );
            border: 1px solid rgba(255,255,255,.16);
            backdrop-filter: blur(25px);
            box-shadow:
                35px 35px 70px rgba(0,0,0,.35),
                -10px -10px 40px rgba(255,255,255,.03),
                inset 2px 2px 8px rgba(255,255,255,.12);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            transform: translateZ(45px);
        }

        .main-logo {
            width: 130px;
            height: 130px;
            border-radius: 35px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 65px;
            background:
                linear-gradient(
                    145deg,
                    rgba(96,165,250,.35),
                    rgba(99,102,241,.08)
                );
            border: 1px solid rgba(255,255,255,.18);
            box-shadow:
                20px 20px 45px rgba(0,0,0,.35),
                inset 3px 3px 10px rgba(255,255,255,.15);
            transform: translateZ(80px);
            margin-bottom: 25px;
        }

        .main-card h3 {
            font-size: 25px;
            letter-spacing: 2px;
        }

        .main-card p {
            color: #94a3b8;
            margin-top: 8px;
            font-size: 13px;
        }

        .floating-card {
            position: absolute;
            padding: 16px 18px;
            border-radius: 20px;
            background: rgba(255,255,255,.08);
            border: 1px solid rgba(255,255,255,.14);
            backdrop-filter: blur(18px);
            box-shadow:
                20px 20px 45px rgba(0,0,0,.3),
                inset 1px 1px 5px rgba(255,255,255,.1);
            display: flex;
            align-items: center;
            gap: 11px;
            font-size: 13px;
        }

        .floating-card strong {
            display: block;
            font-size: 14px;
        }

        .floating-card span {
            display: block;
            color: #94a3b8;
            font-size: 11px;
            margin-top: 3px;
        }

        .floating-card.one {
            top: 5px;
            left: 0;
            transform: translateZ(100px);
            animation: floatSmall 4s ease-in-out infinite;
        }

        .floating-card.two {
            right: 0;
            top: 120px;
            transform: translateZ(130px);
            animation: floatSmall 5s ease-in-out infinite 1s;
        }

        .floating-card.three {
            bottom: 10px;
            left: 15px;
            transform: translateZ(110px);
            animation: floatSmall 5s ease-in-out infinite 2s;
        }

        @keyframes floatSmall {
            0%, 100% {
                translate: 0 0;
            }

            50% {
                translate: 0 -12px;
            }
        }

        /* =========================
           FEATURES
        ========================== */

        .features {
            width: min(1200px, calc(100% - 40px));
            margin: 30px auto 100px;
        }

        .section-title {
            text-align: center;
            margin-bottom: 35px;
        }

        .section-title h2 {
            font-size: 35px;
        }

        .section-title p {
            margin-top: 10px;
            color: #94a3b8;
        }

        .feature-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 18px;
        }

        .feature {
            padding: 27px;
            border-radius: 25px;
            background: rgba(255,255,255,.055);
            border: 1px solid rgba(255,255,255,.1);
            box-shadow:
                15px 15px 35px rgba(0,0,0,.2),
                inset 1px 1px 5px rgba(255,255,255,.06);
            transition: .3s;
        }

        .feature:hover {
            transform: translateY(-10px) rotateX(2deg);
            background: rgba(255,255,255,.08);
        }

        .feature-icon {
            font-size: 35px;
            margin-bottom: 17px;
        }

        .feature h3 {
            font-size: 17px;
            margin-bottom: 9px;
        }

        .feature p {
            color: #94a3b8;
            font-size: 13px;
            line-height: 1.6;
        }

        /* =========================
           FOOTER
        ========================== */

        footer {
            width: min(1200px, calc(100% - 40px));
            margin: auto;
            padding: 30px 0 40px;
            text-align: center;
            color: #64748b;
            font-size: 12px;
        }

        /* =========================
           RESPONSIVE
        ========================== */

        @media (max-width: 950px) {

            .hero {
                grid-template-columns: 1fr;
                padding-top: 70px;
                padding-bottom: 50px;
                text-align: center;
            }

            .hero-content {
                display: flex;
                flex-direction: column;
                align-items: center;
            }

            .hero-visual {
                margin-top: 20px;
            }

            .feature-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .nav-links a:not(.nav-login) {
                display: none;
            }
        }

        @media (max-width: 600px) {

            .navbar {
                width: calc(100% - 20px);
                margin-top: 10px;
            }

            .hero,
            .features {
                width: calc(100% - 25px);
            }

            .hero h1 {
                font-size: 58px;
                letter-spacing: -3px;
            }

            .cube-container {
                height: 350px;
            }

            .main-card {
                inset: 15px;
            }

            .floating-card {
                transform: scale(.85);
            }

            .feature-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>

<div class="background">
    <div class="grid"></div>
    <div class="orb one"></div>
    <div class="orb two"></div>
    <div class="orb three"></div>
</div>

<!-- =========================
     NAVIGATION
========================== -->

<nav class="navbar">

    <div class="brand">

        <div class="brand-icon">
            🏛️
        </div>

        <div class="brand-text">
            <strong>MAPALADNEXUS</strong>
            <span>Barangay Mapalad</span>
        </div>

    </div>

    <div class="nav-links">

        <a href="#home">
            Home
        </a>

        <a href="#services">
            Services
        </a>

        <a href="#about">
            About
        </a>

        <!-- ADMIN LANG -->
        <a
            class="nav-login"
            href="admin/login.php"
        >
            Admin
        </a>

    </div>

</nav>


<!-- =========================
     HERO
========================== -->

<section class="hero" id="home">

    <div class="hero-content">

        <div class="badge">

            <span class="badge-dot"></span>

            DIGITAL BARANGAY PLATFORM

        </div>

        <h1>
            MAPALAD<br>
            NEXUS
        </h1>

        <h2>
            Barangay Mapalad, Connected.
        </h2>

        <p class="hero-description">

            A modern digital platform designed to connect
            residents, barangay officials, and community
            services in one secure and convenient system.

        </p>

        <div class="hero-buttons">

            <!-- RESIDENT -->
            <a
                href="resident/login.php"
                class="btn btn-primary"
            >
                👤 Resident Portal
            </a>

            <!-- ADMIN -->
            <a
                href="admin/login.php"
                class="btn btn-secondary"
            >
                🛡️ Admin Portal
            </a>

        </div>

    </div>


    <!-- =========================
         3D VISUAL
    ========================== -->

    <div class="hero-visual">

        <div class="cube-container">

            <div class="floating-card one">

                <div>📄</div>

                <div>
                    <strong>Online Requests</strong>
                    <span>Fast & convenient</span>
                </div>

            </div>


            <div class="floating-card two">

                <div>📢</div>

                <div>
                    <strong>Announcements</strong>
                    <span>Stay updated</span>
                </div>

            </div>


            <div class="floating-card three">

                <div>💬</div>

                <div>
                    <strong>Community Concerns</strong>
                    <span>We're listening</span>
                </div>

            </div>


            <div class="main-card">

                <div class="main-logo">
                    🏛️
                </div>

                <h3>
                    MAPALADNEXUS
                </h3>

                <p>
                    Barangay Mapalad Digital Hub
                </p>

            </div>

        </div>

    </div>

</section>


<!-- =========================
     FEATURES
========================== -->

<section
    class="features"
    id="services"
>

    <div class="section-title">

        <h2>
            Everything in One Place
        </h2>

        <p>
            Simple. Connected. Accessible.
        </p>

    </div>


    <div class="feature-grid">

        <div class="feature">

            <div class="feature-icon">
                📄
            </div>

            <h3>
                Online Services
            </h3>

            <p>
                Request barangay documents and services
                without unnecessary steps.
            </p>

        </div>


        <div class="feature">

            <div class="feature-icon">
                📊
            </div>

            <h3>
                Request Tracking
            </h3>

            <p>
                Monitor the progress of your requests
                from submission to completion.
            </p>

        </div>


        <div class="feature">

            <div class="feature-icon">
                📢
            </div>

            <h3>
                Announcements
            </h3>

            <p>
                Receive important community updates
                and barangay announcements.
            </p>

        </div>


        <div class="feature">

            <div class="feature-icon">
                💬
            </div>

            <h3>
                Complaints & Concerns
            </h3>

            <p>
                Send concerns directly to the
                appropriate barangay officials.
            </p>

        </div>

    </div>

</section>


<!-- =========================
     ABOUT
========================== -->

<section
    class="features"
    id="about"
>

    <div class="section-title">

        <h2>
            About MAPALADNEXUS
        </h2>

        <p>
            Digital services for the community of Barangay Mapalad.
        </p>

    </div>

</section>


<!-- =========================
     FOOTER
========================== -->

<footer>

    © <?php echo date("Y"); ?>

    MAPALADNEXUS · Barangay Mapalad

</footer>

</body>
</html>