<?php

require_once __DIR__ . "/includes/auth.php";
require_once __DIR__ . "/../config/database.php";

function getProductStockValue($product)
{
    if (isset($product["stock_ban"]) && isset($product["stock_oli"])) {
        $sku = strtolower((string) ($product["sku"] ?? ""));
        return $sku === "ban"
            ? (int) ($product["stock_ban"] ?? 0)
            : (int) ($product["stock_oli"] ?? 0);
    }

    return (int) ($product["stock"] ?? 0);
}

function decrementProductStock($pdo, $product, $qty)
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

function ensureUniqueProductSku($pdo, $sku)
{
    $base = trim((string) $sku);
    if ($base === "") {
        return "SKU-" . date("YmdHis") . "-" . random_int(1000, 9999);
    }

    $candidate = strtoupper($base);
    $counter = 1;

    while (true) {
        $existsStmt = $pdo->prepare("SELECT id FROM products WHERE LOWER(sku) = LOWER(?) LIMIT 1");
        $existsStmt->execute([$candidate]);

        if (!$existsStmt->fetch()) {
            return $candidate;
        }

        $candidate = strtoupper($base) . "-" . date("YmdHis") . "-" . $counter;
        $counter++;
    }
}

$message = "";

$hasStockBanColumn = (bool) $pdo->query("SHOW COLUMNS FROM products LIKE 'stock_ban'")->fetch();
$hasStockOliColumn = (bool) $pdo->query("SHOW COLUMNS FROM products LIKE 'stock_oli'")->fetch();
$hasSeparateStock = $hasStockBanColumn && $hasStockOliColumn;

$stmt = $pdo->query("
    SELECT *
    FROM products
    ORDER BY id DESC
");

$products = $stmt->fetchAll();

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    if (isset($_POST["invoice_customer_name"]) && trim((string) $_POST["invoice_customer_name"]) !== "") {
        $invoiceCustomerName = trim((string) ($_POST["invoice_customer_name"] ?? ""));
        $invoiceProductId = (int) ($_POST["invoice_product_id"] ?? 0);
        $invoiceQty = (int) ($_POST["invoice_qty"] ?? 0);
        $invoicePaymentStatus = trim((string) ($_POST["invoice_payment_status"] ?? "Lunas"));

        $invoiceProduct = null;
        foreach ($products as $product) {
            if ((int) $product["id"] === $invoiceProductId) {
                $invoiceProduct = $product;
                break;
            }
        }

        if ($invoiceCustomerName === "" || !$invoiceProduct || $invoiceQty <= 0) {
            $message = "Pilih produk dan isi qty dengan benar.";
        } else {
            $invoicePrice = (float) ($invoiceProduct["price"] ?? 0);

            $availableStock = getProductStockValue($invoiceProduct);

            if ($invoiceQty > $availableStock) {
                $message = "Stok produk tidak cukup. Stok tersedia: " . $availableStock;
            } elseif ($invoicePrice <= 0) {
                $message = "Harga produk tidak valid.";
            } else {
                $pdo->beginTransaction();

                try {
                    $invoiceStmt = $pdo->prepare(
                        "INSERT INTO orders (user_id, customer_name, total_amount, payment_method, atm_number, payment_status)
                         VALUES (?, ?, ?, ?, ?, ?)"
                    );

                    $invoiceStmt->execute([
                        0,
                        $invoiceCustomerName,
                        $invoicePrice * $invoiceQty,
                        "Manual Invoice",
                        "MANUAL-INV",
                        $invoicePaymentStatus === "" ? "Lunas" : $invoicePaymentStatus
                    ]);

                    $invoiceOrderId = (int) $pdo->lastInsertId();

                    $invoiceItemStmt = $pdo->prepare(
                        "INSERT INTO order_items (order_id, product_id, product_name, price, qty)
                         VALUES (?, ?, ?, ?, ?)"
                    );

                    $invoiceItemStmt->execute([
                        $invoiceOrderId,
                        (int) $invoiceProduct["id"],
                        $invoiceProduct["name"],
                        $invoicePrice,
                        $invoiceQty
                    ]);

                    decrementProductStock($pdo, $invoiceProduct, $invoiceQty);

                    $pdo->commit();
                    header("Location: index.php?success=invoice_added");
                    exit;
                } catch (PDOException $e) {
                    $pdo->rollBack();
                    $message = "Gagal menambahkan invoice. Silakan coba lagi.";
                }
            }
        }
    }

    if (isset($_POST["id"]) && $_POST["id"] !== "") {

        $id = $_POST["id"];
        $price = $_POST["price"] ?? 0;

        if ($hasSeparateStock) {
            $stockBan = $_POST["stock_ban"] ?? 0;
            $stockOli = $_POST["stock_oli"] ?? 0;

            $stmt = $pdo->prepare("
                UPDATE products
                SET price = ?, stock_ban = ?, stock_oli = ?
                WHERE id = ?
            ");

            $stmt->execute([
                $price,
                $stockBan,
                $stockOli,
                $id
            ]);
        } else {
            $stock = $_POST["stock"] ?? 0;

            $stmt = $pdo->prepare("
                UPDATE products
                SET price = ?, stock = ?
                WHERE id = ?
            ");

            $stmt->execute([
                $price,
                $stock,
                $id
            ]);
        }

        header("Location: index.php?success=updated");
        exit;
    }

    $sku = trim($_POST["sku"] ?? "");
    $name = trim($_POST["name"] ?? "");
    $description = trim($_POST["description"] ?? "");
    $price = $_POST["price"] ?? 0;
    $uploadedImage = "";

    if (isset($_FILES["image"]) && !empty($_FILES["image"]["name"])) {
        $allowed = ["jpg", "jpeg", "png", "webp"];
        $fileName = basename($_FILES["image"]["name"]);
        $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        if (!in_array($fileExt, $allowed, true)) {
            $message = "Format gambar tidak valid. Gunakan JPG, JPEG, PNG, atau WEBP.";
        } elseif (!is_uploaded_file($_FILES["image"]["tmp_name"])) {
            $message = "Upload gambar gagal. Silakan coba lagi.";
        } else {
            $uniqueName = uniqid("prod_", true) . "." . $fileExt;
            $targetPath = __DIR__ . "/../uploads/" . $uniqueName;

            if (move_uploaded_file($_FILES["image"]["tmp_name"], $targetPath)) {
                $uploadedImage = $uniqueName;
            } else {
                $message = "Gagal menyimpan gambar produk.";
            }
        }
    }

    if ($message === "") {
        if ($hasSeparateStock) {
            $stockBan = $_POST["stock_ban"] ?? "";
            $stockOli = $_POST["stock_oli"] ?? "";

            if ($sku === "" || $name === "" || $description === "" || $price === "" || $stockBan === "" || $stockOli === "") {
                $message = "Semua field produk wajib diisi.";
            } else {
                $finalSku = ensureUniqueProductSku($pdo, $sku);

                $stmt = $pdo->prepare("
                    INSERT INTO products (sku, name, description, price, stock_ban, stock_oli, status, image)
                    VALUES (?, ?, ?, ?, ?, ?, 'active', ?)
                ");

                $stmt->execute([
                    $finalSku,
                    $name,
                    $description,
                    $price,
                    $stockBan,
                    $stockOli,
                    $uploadedImage
                ]);

                header("Location: index.php?success=added");
                exit;
            }
        } else {
            $stock = $_POST["stock"] ?? 0;

            if ($sku === "" || $name === "" || $description === "" || $price === "" || $stock === "") {
                $message = "Semua field produk wajib diisi.";
            } else {
                $finalSku = ensureUniqueProductSku($pdo, $sku);

                $stmt = $pdo->prepare("
                    INSERT INTO products (sku, name, description, price, stock, status, image)
                    VALUES (?, ?, ?, ?, ?, 'active', ?)
                ");

                $stmt->execute([
                    $finalSku,
                    $name,
                    $description,
                    $price,
                    $stock,
                    $uploadedImage
                ]);

                header("Location: index.php?success=added");
                exit;
            }
        }
    }
}

$invoiceStmt = $pdo->query("
    SELECT o.id, o.customer_name, o.total_amount, o.payment_status, o.created_at,
           oi.product_name, oi.price, oi.qty
    FROM orders o
    LEFT JOIN order_items oi ON oi.order_id = o.id
    WHERE o.payment_status = 'Lunas'
    ORDER BY o.created_at DESC, oi.id ASC
");

$invoiceRows = $invoiceStmt->fetchAll();

$invoiceOrders = [];
$totalSales = 0;
$totalTransactions = 0;
$paidTransactions = 0;

foreach ($invoiceRows as $row) {
    $orderId = (int) $row["id"];

    if (!isset($invoiceOrders[$orderId])) {
        $invoiceOrders[$orderId] = [
            "id" => $orderId,
            "customer_name" => $row["customer_name"],
            "total_amount" => (float) $row["total_amount"],
            "payment_status" => $row["payment_status"],
            "created_at" => $row["created_at"],
            "items" => []
        ];
    }

    $invoiceOrders[$orderId]["items"][] = [
        "product_name" => $row["product_name"],
        "price" => (float) $row["price"],
        "qty" => (int) $row["qty"]
    ];
}

$invoiceOrders = array_values($invoiceOrders);

foreach ($invoiceOrders as $order) {
    $totalTransactions++;
    $totalSales += (float) $order["total_amount"];

    if (strtolower((string) $order["payment_status"]) === "lunas") {
        $paidTransactions++;
    }
}

$dailyReport = [];
foreach ($invoiceRows as $row) {
    $dateKey = date("Y-m-d", strtotime($row["created_at"]));

    if (!isset($dailyReport[$dateKey])) {
        $dailyReport[$dateKey] = [
            "date" => $dateKey,
            "total_orders" => 0,
            "total_sales" => 0,
            "product_count" => 0,
            "product_names" => []
        ];
    }

    $dailyReport[$dateKey]["total_orders"] += 1;
    $dailyReport[$dateKey]["total_sales"] += (float) $row["price"] * (int) $row["qty"];
    $dailyReport[$dateKey]["product_count"] += (int) $row["qty"];

    if (!empty($row["product_name"]) && !in_array($row["product_name"], $dailyReport[$dateKey]["product_names"], true)) {
        $dailyReport[$dateKey]["product_names"][] = $row["product_name"];
    }
}

$dailyReport = array_values($dailyReport);

$adminName = $_SESSION["admin_name"] ?? "Admin";
$adminInitial = strtoupper(substr(trim($adminName), 0, 1));

?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Dashboard Toko Ban</title>
    <link rel="stylesheet" href="../assets/admin.css">
</head>

<body class="admin-page">

<div class="container">

    <aside class="sidebar">

        <div class="sidebar-brand">
            <div class="sidebar-brand-avatar"><?= htmlspecialchars($adminInitial) ?></div>
            <div>
                <p class="sidebar-role">Admin</p>
                <h3 class="sidebar-title"><?= htmlspecialchars($adminName) ?></h3>
            </div>
        </div>

        <nav class="sidebar-nav">
            <button type="button" class="nav-link active" data-section="stock">Stock</button>
            <button type="button" class="nav-link" data-section="add_stock">Tambah Stock</button>
            <button type="button" class="nav-link" data-section="invoice">Invoice</button>
            <button type="button" class="nav-link" data-section="laporan_harian">Laporan Harian</button>
        </nav>

        <div class="sidebar-logout">
            <a href="logout.php" class="logout-button" onclick="return confirm('Yakin ingin logout?');">
                <span class="logout-icon">↪</span>
                <span>Logout</span>
            </a>
        </div>

    </aside>

    <main class="main-content">

        <div class="header">

            <div>

                <h1>BAKOEL BAN KONGSI</h1>

                <p>
                    Kelola produk, harga, dan stok ban
                </p>

            </div>

        </div>

    <?php if ($message): ?>

        <div class="error">
            <?= htmlspecialchars($message) ?>
        </div>

    <?php endif; ?>

    <?php if (isset($_GET["success"])): ?>

        <div class="success">
            <?php if ($_GET["success"] === "added"): ?>
                Produk berhasil ditambahkan.
            <?php elseif ($_GET["success"] === "invoice_added"): ?>
                Invoice berhasil ditambahkan.
            <?php else: ?>
                Data berhasil diperbarui.
            <?php endif; ?>
        </div>

    <?php endif; ?>

    <div id="section-stock" class="admin-section active">

        <div class="section-card">
            <h2>Daftar Stok Ban</h2>
            <p>Kelola stok dan harga ban yang sudah terdaftar.</p>
        </div>

        <?php
            $stockSummaryTotal = 0;
            $stockSummaryLow = 0;

            foreach ($products as $product) {
                $stockSummaryTotal += getProductStockValue($product);
                if (getProductStockValue($product) <= 5) {
                    $stockSummaryLow++;
                }
            }
        ?>

        <div class="stock-summary-grid">
            <div class="stock-summary-item">
                <span class="summary-label">Total Produk</span>
                <strong><?= count($products) ?></strong>
            </div>
            <div class="stock-summary-item">
                <span class="summary-label">Total Stok</span>
                <strong><?= (int) $stockSummaryTotal ?></strong>
            </div>
            <div class="stock-summary-item warning">
                <span class="summary-label">Stok Rendah</span>
                <strong><?= (int) $stockSummaryLow ?></strong>
            </div>
        </div>

        <div class="table-wrapper">

        <table>

            <thead>

                <tr>
                    <th>Jenis Stock</th>
                    <th>Nama Produk</th>
                    <th>Harga</th>
                    <?php if ($hasSeparateStock): ?>
                        <th>Stock Ban</th>
                        <th>Stock Oli</th>
                    <?php else: ?>
                        <th>Stock</th>
                    <?php endif; ?>
                    <th>Status</th>
                    <th>Aksi</th>
                </tr>

            </thead>

            <tbody>

            <?php if (count($products) > 0): ?>

                <?php foreach ($products as $product): ?>
                    <?php
                        $productStockValue = getProductStockValue($product);
                        $productStatusText = $productStockValue <= 5 ? "Stok Rendah" : "Tersedia";
                        $productStatusClass = $productStockValue <= 5 ? "status-low" : "status-good";
                    ?>

                    <tr>

                        <td>
                            <?= htmlspecialchars($product["sku"]) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($product["name"]) ?>
                        </td>

                        <td>
                            <?= number_format($product["price"], 0, ",", ".") ?>
                        </td>

                        <?php if ($hasSeparateStock): ?>

                            <td>
                                <?= htmlspecialchars($product["stock_ban"] ?? 0) ?>
                            </td>

                            <td>
                                <?= htmlspecialchars($product["stock_oli"] ?? 0) ?>
                            </td>

                        <?php else: ?>

                            <td>
                                <?= htmlspecialchars($product["stock"]) ?>
                            </td>

                        <?php endif; ?>

                        <td>
                            <span class="stock-status <?= $productStatusClass ?>"><?= $productStatusText ?></span>
                        </td>

                        <td>
                            <form method="POST" class="inline-form">
                                <input type="hidden" name="id" value="<?= $product["id"] ?>">
                                <?php if ($hasSeparateStock): ?>
                                    <input type="number" name="price" value="<?= $product["price"] ?>" min="0" required>
                                    <input type="number" name="stock_ban" value="<?= $product["stock_ban"] ?? 0 ?>" min="0" class="stock-input" required>
                                    <input type="number" name="stock_oli" value="<?= $product["stock_oli"] ?? 0 ?>" min="0" class="stock-input" required>
                                <?php else: ?>
                                    <input type="number" name="price" value="<?= $product["price"] ?>" min="0" required>
                                    <input type="number" name="stock" value="<?= $product["stock"] ?>" min="0" class="stock-input" required>
                                <?php endif; ?>
                                <div class="stock-action-group">
                                    <button type="button" class="edit-stock-btn">Edit</button>
                                    <button type="submit" class="save-stock-btn">Simpan</button>
                                </div>
                            </form>
                        </td>

                    </tr>

                <?php endforeach; ?>

            <?php else: ?>

                <tr>

                    <td
                        colspan="<?= $hasSeparateStock ? 7 : 6 ?>"
                        style="text-align:center;"
                    >
                        Belum ada produk.
                    </td>

                </tr>

            <?php endif; ?>

            </tbody>

        </table>

    </div>

    </div>

    <div id="section-add_stock" class="admin-section">

        <div class="form-section">
            <h2>Tambah Produk Baru</h2>
            <p>Gunakan form ini untuk menambahkan stock baru ke inventaris.</p>

            <form method="POST" class="add-product-form" enctype="multipart/form-data">

                <div class="form-row">
                    <label>Jenis Ban</label>
                    <select name="sku" required>
                        <option value="" disabled selected>Pilih jenis ban</option>
                        <option value="ban">Ban</option>
                        <option value="oli">Pelengkap</option>
                    </select>
                </div>

                <div class="form-row">
                    <label>Nama Produk</label>
                    <input type="text" name="name" required>
                </div>

                <div class="form-row full-width">
                    <label>Deskripsi</label>
                    <input type="text" name="description" required>
                </div>

                <div class="form-row">
                    <label>Harga</label>
                    <input type="number" name="price" min="0" required>
                </div>

                <?php if ($hasSeparateStock): ?>

                    <div class="form-row">
                        <label>Stock Ban</label>
                        <input type="number" name="stock_ban" min="0" required>
                    </div>

                    <div class="form-row">
                        <label>Stock Oli</label>
                        <input type="number" name="stock_oli" min="0" required>
                    </div>

                <?php else: ?>

                    <div class="form-row">
                        <label>Stock</label>
                        <input type="number" name="stock" min="0" required>
                    </div>

                <?php endif; ?>

                <div class="form-row full-width">
                    <label>Foto Produk</label>
                    <input type="file" name="image" accept="image/jpg,image/jpeg,image/png,image/webp">
                </div>

                <button type="submit">Tambah Produk</button>

            </form>
        </div>

    </div>

    <div id="section-invoice" class="admin-section">
        <div class="form-section">
            <h2>Tambah Invoice</h2>
            <p>Gunakan form ini untuk membuat invoice baru secara manual. Form ini terpisah dari tambah stock produk.</p>

            <form method="POST" class="add-product-form">
                <div class="form-row">
                    <label>Nama Pelanggan</label>
                    <input type="text" name="invoice_customer_name" required>
                </div>

                <div class="form-row">
                    <label>Nama Produk</label>
                    <select name="invoice_product_id" id="invoice_product_id" required>
                        <option value="" disabled selected>Pilih produk dari daftar customer</option>
                        <?php foreach ($products as $product): ?>
                            <?php
                                $productStock = $hasSeparateStock
                                    ? (strtolower((string) ($product["sku"] ?? "")) === "ban"
                                        ? (int) ($product["stock_ban"] ?? 0)
                                        : (int) ($product["stock_oli"] ?? 0))
                                    : (int) ($product["stock"] ?? 0);
                            ?>
                            <option value="<?= (int) $product["id"] ?>" data-price="<?= (float) $product["price"] ?>" data-stock="<?= (int) $productStock ?>">
                                <?= htmlspecialchars($product["name"]) ?> - Rp <?= number_format((float) $product["price"], 0, ",", ".") ?> - Stock: <?= (int) $productStock ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-row">
                    <label>Harga Satuan</label>
                    <input type="number" name="invoice_price" id="invoice_price" min="0" readonly required>
                </div>

                <div class="form-row">
                    <label>Stok Tersedia</label>
                    <input type="number" id="invoice_stock_available" min="0" readonly>
                </div>

                <div class="form-row">
                    <label>Jumlah</label>
                    <input type="number" name="invoice_qty" id="invoice_qty" min="1" max="1" value="1" required>
                </div>

                <div class="form-row">
                    <label>Total Harga</label>
                    <input type="text" id="invoice_total_amount" value="Rp 0" readonly>
                </div>

                <div class="form-row">
                    <label>Status Pembayaran</label>
                    <select name="invoice_payment_status" required>
                        <option value="Lunas" selected>Lunas</option>
                        <option value="Menunggu Pembayaran">Menunggu Pembayaran</option>
                    </select>
                </div>

                <button type="submit">Tambah Invoice</button>
            </form>
        </div>

        <div class="section-card section-card-spaced">
            <h2>Invoice</h2>
            <p>Daftar transaksi pelanggan yang sudah dibayar dan barang yang dibeli.</p>
        </div>

        <div class="invoice-stats-grid">
            <div class="invoice-stat-card sales">
                <div class="invoice-stat-label">Total Penjualan</div>
                <div class="invoice-stat-value">Rp <?= number_format($totalSales, 0, ",", ".") ?></div>
            </div>
            <div class="invoice-stat-card transactions">
                <div class="invoice-stat-label">Transaksi</div>
                <div class="invoice-stat-value"><?= (int) $totalTransactions ?></div>
            </div>
            <div class="invoice-stat-card paid">
                <div class="invoice-stat-label">Lunas</div>
                <div class="invoice-stat-value"><?= (int) $paidTransactions ?></div>
            </div>
        </div>

        <div class="table-wrapper table-spaced">
            <table>
                <thead>
                    <tr>
                        <th>Invoice</th>
                        <th>Pelanggan</th>
                        <th>Tanggal</th>
                        <th>Produk</th>
                        <th>Total</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($invoiceOrders) === 0): ?>
                        <tr>
                            <td colspan="6" style="text-align:center;">Belum ada transaksi.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($invoiceOrders as $order): ?>
                            <tr>
                                <td>#<?= (int) $order["id"] ?></td>
                                <td><?= htmlspecialchars($order["customer_name"] ?? "Customer") ?></td>
                                <td><?= htmlspecialchars(date("d-m-Y H:i", strtotime($order["created_at"]))) ?></td>
                                <td>
                                    <?php
                                        $productList = [];
                                        foreach ($order["items"] as $item) {
                                            $productList[] = ($item["product_name"] ?? "Produk") . " x " . (int) $item["qty"];
                                        }
                                        echo htmlspecialchars(implode(", ", $productList));
                                    ?>
                                </td>
                                <td>Rp <?= number_format($order["total_amount"], 0, ",", ".") ?></td>
                                <td>
                                    <span class="payment-status <?= strtolower((string) $order["payment_status"]) === "lunas" ? 'paid' : 'pending'; ?>">
                                        <?= htmlspecialchars($order["payment_status"]) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div id="section-laporan_harian" class="admin-section">
        <div class="section-card">
            <h2>Laporan Harian</h2>
            <p>Rangkuman penjualan harian dari semua order yang masuk.</p>
        </div>

        <div class="table-wrapper table-spaced">
            <table>
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>Jumlah Order</th>
                        <th>Jumlah Unit</th>
                        <th>Produk Terjual</th>
                        <th>Total Penjualan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($dailyReport) === 0): ?>
                        <tr>
                            <td colspan="5" style="text-align:center;">Belum ada laporan harian.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($dailyReport as $report): ?>
                            <tr>
                                <td><?= htmlspecialchars(date("d-m-Y", strtotime($report["date"]))) ?></td>
                                <td><?= (int) $report["total_orders"] ?></td>
                                <td><?= (int) $report["product_count"] ?></td>
                                <td><?= htmlspecialchars(implode(", ", $report["product_names"])) ?></td>
                                <td>Rp <?= number_format($report["total_sales"], 0, ",", ".") ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</main>

</div>

<script>
    const navLinks = document.querySelectorAll('.nav-link');
    const sections = document.querySelectorAll('.admin-section');

    navLinks.forEach(link => {
        link.addEventListener('click', () => {
            navLinks.forEach(item => item.classList.remove('active'));
            link.classList.add('active');

            sections.forEach(section => {
                section.classList.toggle('active', section.id === `section-${link.dataset.section}`);
            });
        });
    });

    const invoiceProductSelect = document.getElementById('invoice_product_id');
    const invoicePriceInput = document.getElementById('invoice_price');
    const invoiceStockAvailable = document.getElementById('invoice_stock_available');
    const invoiceQtyInput = document.getElementById('invoice_qty');
    const invoiceTotalAmount = document.getElementById('invoice_total_amount');

    const formatCurrency = (value) => new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0
    }).format(value);

    if (invoiceProductSelect && invoicePriceInput && invoiceStockAvailable && invoiceQtyInput && invoiceTotalAmount) {
        const syncInvoiceMeta = () => {
            const selected = invoiceProductSelect.options[invoiceProductSelect.selectedIndex];
            const price = selected && selected.dataset.price ? Number(selected.dataset.price) : 0;
            const stock = selected && selected.dataset.stock ? Number(selected.dataset.stock) : 0;
            const qty = Number(invoiceQtyInput.value || 0);
            const maxStock = Math.max(1, stock);

            invoiceQtyInput.max = maxStock;
            if (qty > maxStock) {
                invoiceQtyInput.value = maxStock;
            }

            invoicePriceInput.value = price;
            invoiceStockAvailable.value = stock;
            invoiceTotalAmount.value = formatCurrency(price * Number(invoiceQtyInput.value || 0));
        };

        invoiceProductSelect.addEventListener('change', syncInvoiceMeta);
        invoiceQtyInput.addEventListener('input', syncInvoiceMeta);
        syncInvoiceMeta();
    }


    // Mode edit untuk stok: field dikunci sampai tombol Edit ditekan.
    document.querySelectorAll('.inline-form').forEach(form => {
        const fields = form.querySelectorAll('input[type="number"]');
        const editButton = form.querySelector('.edit-stock-btn');

        fields.forEach(field => {
            field.setAttribute('readonly', 'readonly');
        });

        if (editButton) {
            editButton.addEventListener('click', () => {
                const isEditing = form.classList.toggle('is-editing');

                fields.forEach(field => {
                    if (isEditing) {
                        field.removeAttribute('readonly');
                    } else {
                        field.setAttribute('readonly', 'readonly');
                    }
                });

                editButton.textContent = isEditing ? 'Batal' : 'Edit';
            });
        }
    });

</script>

</body>

</html>
