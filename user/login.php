<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . "/../config/database.php";

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";

    if ($email === "" || $password === "") {
        $error = "Email dan password wajib diisi.";
    } else {
        $stmt = $pdo->prepare("SELECT id, name, email, password FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user["password"])) {
            session_regenerate_id(true);

            $_SESSION["auth"] = [
                "role" => "customer",
                "user_id" => (int) $user["id"],
                "user_name" => $user["name"],
                "user_email" => $user["email"],
            ];

            $_SESSION["user_id"] = (int) $user["id"];
            $_SESSION["user_name"] = $user["name"];
            $_SESSION["user_email"] = $user["email"];

            unset($_SESSION["admin_id"], $_SESSION["admin_name"], $_SESSION["admin_email"]);

            header("Location: ../customer/index.php");
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LOGIN WEBSITE BAKOEL BAN KONGSI</title>
    <link rel="stylesheet" href="../assets/style.css">
</head>
<body class="auth-page">
    <div class="auth-shell">
        <div class="auth-visual">
            <div class="brand-mark">BBK</div>
            <p class="mini-label">Bakoel Ban Kongsi</p>
            <h2>Ban berkualitas, harga bersaing, pelayanan cepat.</h2>
            <p>Masuk untuk mengecek stok, keranjang, dan produk terbaru kami.</p>
        </div>

        <div class="box auth-panel">
            <div class="auth-badge">LOGIN</div>
            <h1>LOGIN WEBSITE BAKOEL BAN KONGSI</h1>
            <p>Silakan masuk untuk melanjutkan belanja Anda.</p>

            <?php if ($error): ?>
                <div class="error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" placeholder="you@example.com" required>
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" placeholder="Masukkan password" required>
                </div>
                <button type="submit" class="auth-button">Login</button>
            </form>

            <div class="link">
                Belum punya akun? <a href="register.php">Daftar sekarang</a>
            </div>
        </div>
    </div>
</body>
</html>
