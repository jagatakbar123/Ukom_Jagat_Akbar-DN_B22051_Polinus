<?php

session_start();

require_once __DIR__ . "/config/database.php";

if (isset($_SESSION["auth"]["role"])) {
    $role = $_SESSION["auth"]["role"];

    if ($role === "admin") {
        header("Location: admin/index.php");
        exit;
    }

    header("Location: customer/index.php");
    exit;
}

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $role = $_POST["role"] ?? "customer";
    $email = trim((string) ($_POST["email"] ?? ""));
    $password = (string) ($_POST["password"] ?? "");

    if ($email === "" || $password === "") {
        $error = "Email dan password wajib diisi.";
    } else {
        $table = ($role === "admin") ? "admins" : "users";

        $stmt = $pdo->prepare("SELECT * FROM {$table} WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user["password"])) {
            session_regenerate_id(true);

            $_SESSION["auth"] = [
                "role" => $role,
                "email" => $user["email"],
            ];

            if ($role === "admin") {
                $_SESSION["admin_id"] = (int) $user["id"];
                $_SESSION["admin_name"] = $user["name"];
                $_SESSION["admin_email"] = $user["email"];
                unset($_SESSION["user_id"], $_SESSION["user_name"], $_SESSION["user_email"]);
                header("Location: admin/index.php");
                exit;
            }

            $_SESSION["user_id"] = (int) $user["id"];
            $_SESSION["user_name"] = $user["name"];
            $_SESSION["user_email"] = $user["email"];
            unset($_SESSION["admin_id"], $_SESSION["admin_name"], $_SESSION["admin_email"]);

            header("Location: customer/index.php");
            exit;
        }

        $error = "Email atau password salah.";
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Toko Ban</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            font-family: Arial, sans-serif;
            background: linear-gradient(135deg, #eff6ff 0%, #e2e8f0 100%);
        }
        .login-box {
            width: min(440px, calc(100% - 24px));
            background: #fff;
            border-radius: 20px;
            padding: 28px;
            box-shadow: 0 22px 50px rgba(15, 23, 42, 0.12);
        }
        h1 { margin: 0 0 8px; font-size: 28px; }
        .subtitle { margin-bottom: 20px; color: #475569; }
        .role-switch {
            display: flex;
            gap: 10px;
            margin-bottom: 18px;
        }
        .role-option {
            flex: 1;
            text-align: center;
            border: 1px solid #dbeafe;
            border-radius: 12px;
            padding: 10px 12px;
            background: #f8fafc;
            font-weight: 700;
            color: #1e3a8a;
        }
        .role-option input { display: none; }
        .role-option.active {
            background: #2563eb;
            border-color: #2563eb;
            color: #fff;
        }
        .field {
            display: flex;
            flex-direction: column;
            gap: 8px;
            margin-bottom: 16px;
        }
        label { font-weight: 700; color: #1f2937; }
        input {
            width: 100%;
            padding: 12px 14px;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            font-size: 15px;
        }
        .error {
            margin-bottom: 16px;
            padding: 10px 12px;
            border-radius: 10px;
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
            font-weight: 600;
        }
        button {
            width: 100%;
            border: none;
            border-radius: 12px;
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
            color: #fff;
            font-weight: 700;
            padding: 13px 16px;
            cursor: pointer;
        }
        .helper {
            margin-top: 16px;
            text-align: center;
            color: #475569;
        }
        .helper a {
            color: #1d4ed8;
            text-decoration: none;
            font-weight: 700;
        }
    </style>
</head>
<body>
    <div class="login-box">
        <h1>Login Toko Ban</h1>
        <p class="subtitle">Masuk sebagai customer atau admin.</p>

        <?php if ($error !== ""): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="role-switch">
                <label class="role-option active">
                    <input type="radio" name="role" value="customer" checked>
                    Customer
                </label>
                <label class="role-option">
                    <input type="radio" name="role" value="admin">
                    Admin
                </label>
            </div>

            <div class="field">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" placeholder="you@example.com" required>
            </div>

            <div class="field">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" placeholder="Masukkan password" required>
            </div>

            <button type="submit">Login</button>
        </form>

        <p class="helper">
            Belum punya akun customer? <a href="user/register.php">Daftar</a>
        </p>
    </div>

    <script>
        document.querySelectorAll('.role-option').forEach((option) => {
            option.addEventListener('click', () => {
                document.querySelectorAll('.role-option').forEach((item) => item.classList.remove('active'));
                option.classList.add('active');
                option.querySelector('input').checked = true;
            });
        });
    </script>
</body>
</html>
