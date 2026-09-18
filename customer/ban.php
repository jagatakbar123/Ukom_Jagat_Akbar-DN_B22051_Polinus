<?php

session_start();
require_once __DIR__ . "/../config/database.php";

function getCustomerProductStock($product)
{
    if (isset($product["stock_ban"]) && isset($product["stock_oli"])) {
        $category = getProductCategory($product);

        if ($category === "ban") {
            return (int) ($product["stock_ban"] ?? 0);
        }

        if ($category === "oli") {
            return (int) ($product["stock_oli"] ?? 0);
        }
    }

    return (int) ($product["stock"] ?? 0);
}

function getCustomerProductStockPercentage($product)
{
    $stock = getCustomerProductStock($product);
    return max(0, min(100, (int) $stock));
}

function getCustomerProductStockState($product)
{
    $percentage = getCustomerProductStockPercentage($product);

    if ($percentage <= 20) {
        return "low";
    }

    if ($percentage <= 50) {
        return "medium";
    }

    return "high";
}

function getProductCategory($product)
{
    $sku = strtolower((string) ($product["sku"] ?? ""));
    $name = strtolower((string) ($product["name"] ?? ""));

    if (strpos($sku, "oli") !== false || strpos($name, "oli") !== false) {
        return "oli";
    }

    if (strpos($sku, "ban") !== false || strpos($name, "ban") !== false) {
        return "ban";
    }

    if (isset($product["stock_ban"]) && isset($product["stock_oli"])) {
        if ((int) ($product["stock_oli"] ?? 0) > 0 && (int) ($product["stock_ban"] ?? 0) === 0) {
            return "oli";
        }
        return "ban";
    }

    return "ban";
}

$stmt = $pdo->query("SELECT * FROM products WHERE status = 'active' ORDER BY id DESC");
$products = $stmt->fetchAll();
$products = array_values(array_filter($products, function ($product) {
    return getProductCategory($product) === "ban";
}));
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ban</title>
    <link rel="stylesheet" href="../assets/style.css">
</head>
<body>
<div class="container">
    <header class="header">
        <div>
            <p class="eyebrow">TOKO BAN KONGSI</p>
            <h1>Daftar Harga & Stok Ban</h1>
            <p>Produk kategori Ban dengan stok yang terus diperbarui.</p>
        </div>
        <nav class="customer-menu">
            <a href="index.php" class="menu-item">Semua Produk</a>
            <a href="ban.php" class="menu-item active">Ban</a>
            <a href="oli.php" class="menu-item">Oli</a>
        </nav>
    </header>

    <div class="shop-layout">
        <aside class="shop-sidebar">
            <div class="sidebar-panel">
                <p class="sidebar-label">Kategori</p>
                <div class="category-list">
                    <a href="index.php" class="category-item">
                        <span>Semua Produk</span>
                    </a>
                    <a href="ban.php" class="category-item active">
                        <span>Ban</span>
                    </a>
                    <a href="oli.php" class="category-item">
                        <span>Oli</span>
                    </a>
                </div>
            </div>
        </aside>

        <section class="products-section">
            <div class="category-cta-row">
                <a href="index.php" class="category-cta all-cta">Semua Produk</a>
                <a href="ban.php" class="category-cta ban-cta active">Ban</a>
                <a href="oli.php" class="category-cta oli-cta">Oli</a>
            </div>

            <div class="products">
                <?php if (count($products) === 0): ?>
                    <p class="empty-state">Belum ada produk Ban.</p>
                <?php else: ?>
                    <?php foreach ($products as $product): ?>
                        <?php $productStock = getCustomerProductStock($product); ?>
                        <?php $stockPercent = getCustomerProductStockPercentage($product); ?>
                        <?php $stockState = getCustomerProductStockState($product); ?>
                        <article class="product">
                            <?php if (!empty($product["image"])): ?>
                                <div class="product-image-wrap">
                                    <img src="../uploads/<?= htmlspecialchars($product["image"]) ?>" class="product-image" alt="<?= htmlspecialchars($product["name"]) ?>">
                                </div>
                            <?php else: ?>
                                <div class="product-image-wrap placeholder-image"><span>Foto Produk</span></div>
                            <?php endif; ?>

                            <div class="product-body">
                                <div class="product-top">
                                    <span class="product-badge"><?= htmlspecialchars($product["sku"]) ?></span>
                                    <span class="status-badge <?= $productStock > 0 ? 'in-stock' : 'out-of-stock' ?>"><?= $productStock > 0 ? 'Ready' : 'Kosong' ?></span>
                                </div>
                                <h3><?= htmlspecialchars($product["name"]) ?></h3>
                                <p class="product-desc"><?= htmlspecialchars($product["description"]) ?></p>
                                <div class="price">Rp <?= number_format((float) $product["price"], 0, ",", ".") ?></div>
                                <div class="product-footer">
                                    <?php if ($productStock > 0): ?>
                                        <div class="stock-meter-wrapper">
                                            <div class="stock-meter">
                                                <span class="stock-meter-fill <?= htmlspecialchars($stockState) ?>" style="width: <?= htmlspecialchars((string) $stockPercent) ?>%"></span>
                                            </div>
                                            <div class="stock-meter-meta">
                                                <span><?= htmlspecialchars((string) $stockPercent) ?>%</span>
                                                <?php if ($stockState === "low"): ?>
                                                    <span class="stock-warning-text">Stok Rendah</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <p class="stock">Stock: <?= htmlspecialchars((string) $stockPercent) ?>%</p>
                                    <?php else: ?>
                                        <div class="stock-meter-wrapper">
                                            <div class="stock-meter">
                                                <span class="stock-meter-fill low" style="width: 0%"></span>
                                            </div>
                                            <div class="stock-meter-meta">
                                                <span>0%</span>
                                                <span class="stock-warning-text">Stok Habis</span>
                                            </div>
                                        </div>
                                        <p class="stock stock-empty">Stok Habis</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>
    </div>
</div>
</body>
</html>
