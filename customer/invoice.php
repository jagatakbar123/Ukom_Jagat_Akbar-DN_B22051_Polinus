<?php

session_start();

require_once __DIR__ . "/../config/database.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: ../user/login.php");
    exit;
}

$stmt = $pdo->prepare(
    "SELECT o.id, o.total_amount, o.payment_method, o.atm_number, o.payment_status, o.created_at,
            oi.product_name, oi.price, oi.qty
     FROM orders o
     LEFT JOIN order_items oi ON oi.order_id = o.id
     WHERE o.user_id = ?
     ORDER BY o.created_at DESC, oi.id ASC"
);

$stmt->execute([(int) $_SESSION["user_id"]]);
$rows = $stmt->fetchAll();

$orders = [];
foreach ($rows as $row) {
    $orderId = (int) $row["id"];
    if (!isset($orders[$orderId])) {
        $orders[$orderId] = [
            "id" => $orderId,
            "total_amount" => (float) $row["total_amount"],
            "payment_method" => $row["payment_method"],
            "atm_number" => $row["atm_number"],
            "payment_status" => $row["payment_status"],
            "created_at" => $row["created_at"],
            "items" => []
        ];
    }

    $orders[$orderId]["items"][] = [
        "product_name" => $row["product_name"],
        "price" => (float) $row["price"],
        "qty" => (int) $row["qty"]
    ];
}

$orders = array_values($orders);

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice</title>
    <link rel="stylesheet" href="../assets/style.css">
</head>
<body class="invoice-page">

<div class="invoice-shell">
    <div class="invoice-card">
        <div class="invoice-header">
            <div>
                <p class="invoice-kicker">Invoice</p>
                <h1>Daftar Transaksi</h1>
            </div>
            <a href="index.php" class="invoice-link">Kembali ke Produk</a>
        </div>

        <?php if (isset($_SESSION["checkout_success"])): ?>
            <div class="success">
                <?= htmlspecialchars($_SESSION["checkout_success"]) ?>
            </div>
            <?php unset($_SESSION["checkout_success"]); ?>
        <?php endif; ?>

        <div class="notice-box">
            Lihatkan invoice ini & bukti transfer ke kasir untuk mengambil kendaraan
        </div>

        <?php if (count($orders) === 0): ?>
            <div class="empty-state">Belum ada transaksi.</div>
        <?php else: ?>
            <?php foreach ($orders as $order): ?>
                <div class="invoice-item">
                    <div class="invoice-item-header">
                        <div>
                            <h3>Invoice #<?= (int) $order["id"] ?></h3>
                            <p>Tanggal: <?= htmlspecialchars($order["created_at"]) ?></p>
                        </div>
                        <span class="invoice-status"><?= htmlspecialchars($order["payment_status"]) ?></span>
                    </div>

                    <div class="invoice-meta">
                        <p><strong>Metode:</strong> <?= htmlspecialchars($order["payment_method"]) ?></p>
                        <p><strong>Nomor ATM:</strong> <?= htmlspecialchars($order["atm_number"]) ?></p>
                    </div>

                    <ul class="invoice-list">
                        <?php foreach ($order["items"] as $item): ?>
                            <li>
                                <span><?= htmlspecialchars($item["product_name"]) ?> x <?= (int) $item["qty"] ?></span>
                                <strong>Rp <?= number_format((float) $item["price"] * (int) $item["qty"], 0, ",", ".") ?></strong>
                            </li>
                        <?php endforeach; ?>
                    </ul>

                    <div class="invoice-total-wrap">
                        <h3>Total: Rp <?= number_format($order["total_amount"], 0, ",", ".") ?></h3>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

</body>
</html>
