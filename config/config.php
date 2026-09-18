<?php

session_start();

require_once __DIR__ . "/../config/database.php";

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";

    if ($email === "" || $password === "") {

        $error = "Email dan password wajib diisi.";

    } else {

        $stmt = $pdo->prepare("
            SELECT *
            FROM admins
            WHERE email = ?
            LIMIT 1
        ");

        $stmt->execute([$email]);

        $admin = $stmt->fetch();

        if (
            $admin &&
            password_verify($password, $admin["password"])
        ) {

            session_regenerate_id(true);

            $_SESSION["admin_id"] = $admin["id"];
            $_SESSION["admin_name"] = $admin["name"];
            $_SESSION["admin_email"] = $admin["email"];

            header("Location: ../admin/index.php");
            exit;

        } else {

            $error = "Email atau password salah.";

        }
    }
}

?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Login Admin Toko Ban</title>
    <link rel="stylesheet" href="../assets/style.css">
</head>

<body class="auth-page admin-login">

<div class="login-box">

    <h1>Login Admin</h1>

    <p>Silakan login untuk masuk dashboard toko ban</p>

    <?php if ($error): ?>

        <div class="error">
            <?= htmlspecialchars($error) ?>
        </div>

    <?php endif; ?>

    <form method="POST">

        <div class="form-group">

            <label>Email</label>

            <input
                type="email"
                name="email"
                placeholder="Masukkan email"
                required
            >

        </div>

        <div class="form-group">

            <label>Password</label>

            <input
                type="password"
                name="password"
                placeholder="Masukkan password"
                required
            >

        </div>

        <button
            type="submit"
            class="login-button"
        >
            Login
        </button>

    </form>

</div>

</body>

</html>