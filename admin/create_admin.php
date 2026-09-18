<?php

session_start();

require_once __DIR__ . "/../config/database.php";

$error = "";
$success = "";
$name = "";
$email = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $name = trim($_POST["name"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";
    $confirmPassword = $_POST["confirm_password"] ?? "";

    if ($name === "" || $email === "" || $password === "" || $confirmPassword === "") {

        $error = "Semua kolom wajib diisi.";

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $error = "Format email tidak valid.";

    } elseif ($password !== $confirmPassword) {

        $error = "Konfirmasi password tidak sesuai.";

    } else {

        $stmt = $pdo->prepare("SELECT id FROM admins WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);

        if ($stmt->fetch()) {

            $error = "Email sudah terdaftar.";

        } else {

            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $insertStmt = $pdo->prepare(
                "INSERT INTO admins (name, email, password) VALUES (?, ?, ?)"
            );

            try {

                $insertStmt->execute([$name, $email, $hashedPassword]);
                $success = "Akun admin berhasil dibuat. Silakan login.";
                $name = "";
                $email = "";

            } catch (PDOException $e) {

                $error = "Gagal membuat akun admin.";

            }
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

    <title>Daftar Admin Toko Ban</title>
    <link rel="stylesheet" href="../assets/style.css">
</head>
<body class="auth-page admin-login">

<div class="login-box">

    <div class="admin-icon">
        📝
    </div>

    <h1>Daftar Admin</h1>

    <p class="subtitle">
        Buat akun admin baru untuk toko ban
    </p>

    <?php if ($error !== ""): ?>

        <div class="error">

            <?= htmlspecialchars($error) ?>

        </div>

    <?php endif; ?>

    <?php if ($success !== ""): ?>

        <div style="padding: 0.75rem; margin-bottom: 1rem; border: 1px solid #b7e4c7; border-radius: 8px; background: #e8f5e9; color: #2e7d32;">

            <?= htmlspecialchars($success) ?>

        </div>

    <?php endif; ?>

    <form method="POST">

        <div class="form-group">

            <label for="name">
                Nama Lengkap
            </label>

            <input
                type="text"
                id="name"
                name="name"
                placeholder="Masukkan nama lengkap"
                value="<?= htmlspecialchars($name) ?>"
                required
            >

        </div>

        <div class="form-group">

            <label for="email">
                Email
            </label>

            <input
                type="email"
                id="email"
                name="email"
                placeholder="Masukkan email"
                autocomplete="email"
                value="<?= htmlspecialchars($email) ?>"
                required
            >

        </div>

        <div class="form-group">

            <label for="password">
                Password
            </label>

            <input
                type="password"
                id="password"
                name="password"
                placeholder="Masukkan password"
                required
            >

        </div>

        <div class="form-group">

            <label for="confirm_password">
                Konfirmasi Password
            </label>

            <input
                type="password"
                id="confirm_password"
                name="confirm_password"
                placeholder="Ulangi password"
                required
            >

        </div>

        <button
            type="submit"
            class="login-button"
        >
            Daftar
        </button>

    </form>

    <p class="signup-link" style="margin-top: 1rem; text-align: center;">
        Sudah punya akun?
        <a href="login.php">Masuk di sini</a>
    </p>

</div>

</body>

</html>