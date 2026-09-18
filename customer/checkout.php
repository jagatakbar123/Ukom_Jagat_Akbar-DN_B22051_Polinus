<?php

session_start();

require_once __DIR__ . "/../config/database.php";

function ensureOrderTables($pdo)
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS orders (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            customer_name VARCHAR(150) NOT NULL,
            total_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
            payment_method VARCHAR(50) NOT NULL DEFAULT 'ATM Transfer',
            atm_number VARCHAR(50) NOT NULL,
            payment_status VARCHAR(30) NOT NULL DEFAULT 'Menunggu Pembayaran',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS order_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            order_id INT NOT NULL,
            product_id INT NOT NULL,
            product_name VARCHAR(150) NOT NULL,
            price DECIMAL(12,2) NOT NULL,
            qty INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB"
    );
}

ensureOrderTables($pdo);

if (!isset($_SESSION["user_id"])) {
    header("Location: ../user/login.php");
    exit;
}

if (!isset($_SESSION["customer_cart"]) || count($_SESSION["customer_cart"]) === 0) {
    header("Location: index.php");
    exit;
}

function getCheckoutProductStock($product)
{
    if (isset($product["stock_ban"]) && isset($product["stock_oli"])) {
        $sku = strtolower((string) ($product["sku"] ?? ""));
        return $sku === "ban"
            ? (int) ($product["stock_ban"] ?? 0)
            : (int) ($product["stock_oli"] ?? 0);
    }

    return (int) ($product["stock"] ?? 0);
}

function decrementCustomerProductStock($pdo, $product, $qty)
{
    $qty = max(0, (int) $qty);

    if (isset($product["stock_ban"]) && isset($product["stock_oli"])) {
        $sku = strtolower((string) ($product["sku"] ?? ""));
        $stockField = $sku === "ban" ? "stock_ban" : "stock_oli";
        $stmt = $pdo->prepare("UPDATE products SET {$stockField} = GREATEST({$stockField} - ?, 0) WHERE id = ?");
        return $stmt->execute([$qty, (int) ($product["id"] ?? 0)]);
    }

    $stmt = $pdo->prepare("UPDATE products SET stock = GREATEST(stock - ?, 0) WHERE id = ?");
    return $stmt->execute([$qty, (int) ($product["id"] ?? 0)]);
}

$atmNumber = "1234 5678 9012";
$cartItems = array_values($_SESSION["customer_cart"]);
$totalAmount = 0;

foreach ($cartItems as $item) {
    $totalAmount += (float) ($item["price"] ?? 0) * (int) ($item["qty"] ?? 1);
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $stockCheck = true;
    $stockErrors = [];

    foreach ($cartItems as $item) {
        $productStmt = $pdo->prepare("SELECT * FROM products WHERE id = ? LIMIT 1");
        $productStmt->execute([(int) ($item["id"] ?? 0)]);
        $product = $productStmt->fetch();

        if (!$product) {
            $stockCheck = false;
            $stockErrors[] = "Produk tidak ditemukan.";
            continue;
        }

        $availableStock = getCheckoutProductStock($product);
        $requestedQty = (int) ($item["qty"] ?? 1);

        if ($requestedQty > $availableStock) {
            $stockCheck = false;
            $stockErrors[] = ($item["name"] ?? "Produk") . " hanya tersisa " . $availableStock . " unit.";
        }
    }

    if (!$stockCheck) {
        $error = implode(" ", $stockErrors);
    } else {
        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare(
                "INSERT INTO orders (user_id, customer_name, total_amount, payment_method, atm_number, payment_status)
                VALUES (?, ?, ?, ?, ?, 'Lunas')"
            );

            $stmt->execute([
                (int) $_SESSION["user_id"],
                $_SESSION["user_name"] ?? "Customer",
                $totalAmount,
                "ATM Transfer",
                $atmNumber
            ]);

            $orderId = (int) $pdo->lastInsertId();

            $itemStmt = $pdo->prepare(
                "INSERT INTO order_items (order_id, product_id, product_name, price, qty)
                VALUES (?, ?, ?, ?, ?)"
            );

            foreach ($cartItems as $item) {
                $itemStmt->execute([
                    $orderId,
                    (int) ($item["id"] ?? 0),
                    $item["name"] ?? "Produk",
                    (float) ($item["price"] ?? 0),
                    (int) ($item["qty"] ?? 1)
                ]);

                $productStmt = $pdo->prepare("SELECT * FROM products WHERE id = ? LIMIT 1");
                $productStmt->execute([(int) ($item["id"] ?? 0)]);
                $product = $productStmt->fetch();

                if ($product) {
                    decrementCustomerProductStock($pdo, $product, (int) ($item["qty"] ?? 1));
                }
            }

            $pdo->commit();

            $_SESSION["customer_cart"] = [];
            $_SESSION["checkout_success"] = "Pembayaran berhasil. Invoice Anda sudah tersedia.";

            header("Location: invoice.php");
            exit;
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = "Gagal memproses checkout. Silakan coba lagi.";
        }
    }
}

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout</title>
    <link rel="stylesheet" href="../assets/style.css">
</head>
<body class="checkout-page">

<div class="checkout-shell">
    <div class="checkout-card">
        <div class="checkout-header">
            <div>
                <p class="checkout-kicker">Checkout</p>
                <h1>Konfirmasi Pembayaran</h1>
            </div>
            <a href="index.php" class="checkout-link">Kembali ke Produk</a>
        </div>

        <p class="checkout-intro">Silakan lakukan pembayaran melalui ATM. Setelah pembayaran berhasil, transaksi akan otomatis muncul di menu invoice.</p>

        <?php if (!empty($error)): ?>
            <div class="error">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <div class="atm-box">
            <div>
                <span class="atm-label">Nomor ATM</span>
                <p class="atm-number"><?= htmlspecialchars($atmNumber) ?></p>
            </div>
            <p>Transfer sejumlah total pembayaran ke rekening BCA ini.</p>
        </div>

        <div class="checkout-grid">
            <div class="summary-box">
                <h3>Ringkasan Belanja</h3>
                <ul class="summary-list">
                    <?php foreach ($cartItems as $item): ?>
                        <li>
                            <span><?= htmlspecialchars($item["name"] ?? "Produk") ?> x <?= (int) ($item["qty"] ?? 1) ?></span>
                            <strong>Rp <?= number_format((float) ($item["price"] ?? 0) * (int) ($item["qty"] ?? 1), 0, ",", ".") ?></strong>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <div class="total-box">
                <p>Total Pembayaran</p>
                <h2>Rp <?= number_format($totalAmount, 0, ",", ".") ?></h2>

                <form method="POST" class="checkout-form">
                    <button type="submit" class="checkout-button">Konfirmasi Pembayaran</button>
                </form>
            </div>
        </div>
    </div>
</div>

</body>
</html>
