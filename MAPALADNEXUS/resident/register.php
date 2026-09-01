<?php

session_start();

require_once __DIR__ . '/../config/database.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $first_name = trim($_POST['first_name'] ?? '');
    $middle_name = trim($_POST['middle_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $birth_date = $_POST['birth_date'] ?? '';
    $gender = trim($_POST['gender'] ?? '');
    $civil_status = trim($_POST['civil_status'] ?? '');
    $purok = trim($_POST['purok'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $contact_number = trim($_POST['contact_number'] ?? '');
    $email = trim($_POST['email'] ?? '');

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (
        $first_name === '' ||
        $last_name === '' ||
        $birth_date === '' ||
        $gender === '' ||
        $purok === '' ||
        $address === '' ||
        $username === '' ||
        $password === ''
    ) {

        $error = 'Please complete all required fields.';

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $error = 'Please enter a valid email address.';

    } elseif (strlen($password) < 6) {

        $error = 'Password must be at least 6 characters.';

    } elseif ($password !== $confirm_password) {

        $error = 'Passwords do not match.';

    } else {

        $check = $conn->prepare(
            "SELECT id FROM users WHERE username = ?"
        );

        $check->bind_param("s", $username);
        $check->execute();

        $result = $check->get_result();

        if ($result->num_rows > 0) {

            $error = 'Username already exists. Please choose another.';

        } else {

            $hashed_password = password_hash(
                $password,
                PASSWORD_DEFAULT
            );

            $conn->begin_transaction();

            try {

                $user = $conn->prepare(
                    "INSERT INTO users
                    (username, password, role, status)
                    VALUES (?, ?, 'Resident', 'Active')"
                );

                $user->bind_param(
                    "ss",
                    $username,
                    $hashed_password
                );

                $user->execute();

                $user_id = $conn->insert_id;

                $resident = $conn->prepare(
                    "INSERT INTO residents
                    (
                        user_id,
                        first_name,
                        middle_name,
                        last_name,
                        birth_date,
                        gender,
                        civil_status,
                        purok,
                        address,
                        contact_number,
                        email,
                        status
                    )
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Active')"
                );

                $resident->bind_param(
                    "issssssssss",
                    $user_id,
                    $first_name,
                    $middle_name,
                    $last_name,
                    $birth_date,
                    $gender,
                    $civil_status,
                    $purok,
                    $address,
                    $contact_number,
                    $email
                );

                $resident->execute();

                $conn->commit();

                $success = 'Account successfully created! You can now login.';

                $_POST = [];

            } catch (Exception $e) {

                $conn->rollback();

                $error = 'Registration failed. Please try again.';
            }
        }

        $check->close();
    }
}

?>
<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Resident Registration | MAPALADNEXUS</title>

<style>

* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}

body {
    min-height: 100vh;
    font-family: Arial, Helvetica, sans-serif;

    background:
        radial-gradient(
            circle at 10% 20%,
            rgba(37, 99, 235, .35),
            transparent 30%
        ),
        radial-gradient(
            circle at 90% 80%,
            rgba(124, 58, 237, .35),
            transparent 30%
        ),
        #050816;

    color: white;
    padding: 30px 15px;
}

.container {
    width: min(950px, 100%);
    margin: auto;
}

.card {
    padding: 40px;

    border-radius: 30px;

    background: rgba(255,255,255,.07);

    border: 1px solid rgba(255,255,255,.14);

    backdrop-filter: blur(25px);

    box-shadow:
        25px 25px 70px rgba(0,0,0,.35),
        inset 1px 1px 8px rgba(255,255,255,.08);
}

.header {
    text-align: center;
    margin-bottom: 35px;
}

.logo {
    width: 75px;
    height: 75px;

    margin: auto auto 18px;

    display: flex;
    align-items: center;
    justify-content: center;

    border-radius: 22px;

    background:
        linear-gradient(
            145deg,
            rgba(96,165,250,.3),
            rgba(99,102,241,.08)
        );

    font-size: 38px;

    box-shadow:
        15px 15px 35px rgba(0,0,0,.3),
        inset 2px 2px 7px rgba(255,255,255,.12);
}

h1 {
    font-size: 30px;
    letter-spacing: 1px;
}

.header p {
    color: #94a3b8;
    margin-top: 8px;
}

.section {
    margin-top: 30px;
}

.section-title {
    font-size: 18px;
    margin-bottom: 18px;
    color: #c7d2fe;
}

.grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 17px;
}

.field {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.field.full {
    grid-column: 1 / -1;
}

label {
    font-size: 12px;
    color: #cbd5e1;
}

input,
select,
textarea {
    width: 100%;

    padding: 14px 15px;

    border-radius: 14px;

    border: 1px solid rgba(255,255,255,.12);

    background: rgba(0,0,0,.22);

    color: white;

    outline: none;

    transition: .2s;
}

select option {
    color: black;
}

textarea {
    min-height: 100px;
    resize: vertical;
}

input:focus,
select:focus,
textarea:focus {
    border-color: #60a5fa;

    box-shadow:
        0 0 0 3px rgba(96,165,250,.12);
}

.alert {
    padding: 14px 16px;
    border-radius: 14px;
    margin-bottom: 20px;
    font-size: 13px;
}

.error {
    background: rgba(239,68,68,.12);
    border: 1px solid rgba(239,68,68,.3);
    color: #fca5a5;
}

.success {
    background: rgba(34,197,94,.12);
    border: 1px solid rgba(34,197,94,.3);
    color: #86efac;
}

.actions {
    margin-top: 35px;

    display: flex;
    gap: 12px;

    justify-content: center;
    flex-wrap: wrap;
}

button,
.btn {
    border: 0;
    cursor: pointer;

    padding: 15px 25px;

    border-radius: 15px;

    color: white;

    font-weight: bold;

    text-decoration: none;

    transition: .25s;
}

button {
    background:
        linear-gradient(
            145deg,
            #2563eb,
            #4f46e5
        );

    box-shadow:
        0 15px 30px rgba(37,99,235,.3);
}

.btn {
    background: rgba(255,255,255,.07);

    border: 1px solid rgba(255,255,255,.12);
}

button:hover,
.btn:hover {
    transform: translateY(-4px);
}

.login {
    text-align: center;
    margin-top: 25px;
    color: #94a3b8;
    font-size: 13px;
}

.login a {
    color: #93c5fd;
    text-decoration: none;
    font-weight: bold;
}

@media(max-width:700px) {

    .card {
        padding: 25px 18px;
    }

    .grid {
        grid-template-columns: 1fr;
    }

    .field.full {
        grid-column: auto;
    }
}

</style>

</head>

<body>

<div class="container">

<div class="card">

<div class="header">

<div class="logo">
    🏛️
</div>

<h1>Join MAPALADNEXUS</h1>

<p>
Create your Barangay Mapalad resident account
</p>

</div>

<?php if ($error): ?>

<div class="alert error">
    <?= htmlspecialchars($error) ?>
</div>

<?php endif; ?>

<?php if ($success): ?>

<div class="alert success">
    <?= htmlspecialchars($success) ?>
</div>

<?php endif; ?>

<form method="POST">

<div class="section">

<div class="section-title">
    👤 Personal Information
</div>

<div class="grid">

<div class="field">

<label>First Name *</label>

<input
    type="text"
    name="first_name"
    value="<?= htmlspecialchars($_POST['first_name'] ?? '') ?>"
    required
>

</div>

<div class="field">

<label>Middle Name</label>

<input
    type="text"
    name="middle_name"
    value="<?= htmlspecialchars($_POST['middle_name'] ?? '') ?>"
>

</div>

<div class="field">

<label>Last Name *</label>

<input
    type="text"
    name="last_name"
    value="<?= htmlspecialchars($_POST['last_name'] ?? '') ?>"
    required
>

</div>

<div class="field">

<label>Birth Date *</label>

<input
    type="date"
    name="birth_date"
    value="<?= htmlspecialchars($_POST['birth_date'] ?? '') ?>"
    required
>

</div>

<div class="field">

<label>Gender *</label>

<select name="gender" required>

<option value="">Select Gender</option>

<option value="Male">Male</option>
<option value="Female">Female</option>
<option value="Other">Other</option>

</select>

</div>

<div class="field">

<label>Civil Status</label>

<select name="civil_status">

<option value="">Select Status</option>

<option value="Single">Single</option>
<option value="Married">Married</option>
<option value="Widowed">Widowed</option>
<option value="Separated">Separated</option>

</select>

</div>

</div>

</div>

<div class="section">

<div class="section-title">
    🏠 Address Information
</div>

<div class="grid">

<div class="field">

<label>Purok *</label>

<select name="purok" required>

<option value="">Select Purok</option>

<option value="Purok 1">Purok 1</option>
<option value="Purok 2">Purok 2</option>
<option value="Purok 3">Purok 3</option>
<option value="Purok 4">Purok 4</option>
<option value="Purok 5">Purok 5</option>
<option value="Purok 6">Purok 6</option>
<option value="Purok 7">Purok 7</option>

</select>

</div>

<div class="field">

<label>Contact Number</label>

<input
    type="text"
    name="contact_number"
    value="<?= htmlspecialchars($_POST['contact_number'] ?? '') ?>"
>

</div>

<div class="field full">

<label>Complete Address *</label>

<textarea
    name="address"
    required
><?= htmlspecialchars($_POST['address'] ?? '') ?></textarea>

</div>

<div class="field full">

<label>Email Address *</label>

<input
    type="email"
    name="email"
    value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
    required
>

</div>

</div>

</div>

<div class="section">

<div class="section-title">
    🔐 Account Information
</div>

<div class="grid">

<div class="field">

<label>Username *</label>

<input
    type="text"
    name="username"
    value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
    required
>

</div>

<div class="field">

<label>Password *</label>

<input
    type="password"
    name="password"
    required
>

</div>

<div class="field">

<label>Confirm Password *</label>

<input
    type="password"
    name="confirm_password"
    required
>

</div>

</div>

</div>

<div class="actions">

<button type="submit">
    Create Resident Account
</button>

<a class="btn" href="../index.php">
    Back to Home
</a>

</div>

</form>

<div class="login">

Already have an account?

<a href="login.php">
    Login here
</a>

</div>

</div>

</div>

</body>
</html>