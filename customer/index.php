<?php

session_start();

require_once __DIR__ . "/../config/database.php";

function ensureUsersTable($pdo)
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            email VARCHAR(150) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB"
    );
}

ensureUsersTable($pdo);

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

$searchTerm = trim((string) ($_GET["search"] ?? ""));
$statusFilter = $_GET["status"] ?? "all";
$statusFilter = in_array($statusFilter, ["all", "ready", "empty"], true) ? $statusFilter : "all";
$selectedCategory = $_GET["category"] ?? "all";
$selectedCategory = in_array($selectedCategory, ["all", "ban", "oli"], true) ? $selectedCategory : "all";

$stmt = $pdo->query("
    SELECT *
    FROM products
    WHERE status = 'active'
    ORDER BY id DESC
");

$products = $stmt->fetchAll();

if ($searchTerm !== "") {
    $filteredProducts = [];
    foreach ($products as $product) {
        $haystack = strtolower((string) ($product["name"] ?? "") . " " . ($product["description"] ?? "") . " " . ($product["sku"] ?? ""));
        if (strpos($haystack, strtolower($searchTerm)) !== false) {
            $filteredProducts[] = $product;
        }
    }
    $products = $filteredProducts;
}

if ($selectedCategory !== "all") {
    $filteredProducts = [];
    foreach ($products as $product) {
        if (getProductCategory($product) === $selectedCategory) {
            $filteredProducts[] = $product;
        }
    }
    $products = $filteredProducts;
}

if ($statusFilter !== "all") {
    $filteredProducts = [];
    foreach ($products as $product) {
        $stock = getCustomerProductStock($product);
        if ($statusFilter === "ready" && $stock > 0) {
            $filteredProducts[] = $product;
        }
        if ($statusFilter === "empty" && $stock <= 0) {
            $filteredProducts[] = $product;
        }
    }
    $products = $filteredProducts;
}

?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Harga Ban</title>
    <link rel="stylesheet" href="../assets/style.css">
</head>

<body>

<div class="container">

    <header class="header">
        <div>
            <p class="eyebrow">TOKO BAN KONGSI</p>
            <h1>Daftar Harga & Stok Ban Tersedia</h1>
            <p>Temukan ban berkualitas yang sesuai dengan kebutuhan kendaraan Anda dengan harga kompetitif.</p>
        </div>

        <nav class="customer-menu">
            <a href="#daftar-harga" class="menu-item active">Daftar Harga</a>
        </nav>
    </header>

    <div class="shop-layout">
        <aside class="shop-sidebar">
            <div class="sidebar-panel">
                <p class="sidebar-label">Kategori</p>
                <div class="category-list">
                    <a href="index.php" class="category-item <?= $selectedCategory === "all" ? "active" : "" ?>">
                        <span>Semua Produk</span>
                    </a>
                    <a href="ban.php" class="category-item <?= $selectedCategory === "ban" ? "active" : "" ?>">
                        <span>Ban</span>
                    </a>
                    <a href="oli.php" class="category-item <?= $selectedCategory === "oli" ? "active" : "" ?>">
                        <span>Oli</span>
                    </a>
                </div>
            </div>
        </aside>

        <section id="daftar-harga" class="products-section">
            <div class="category-cta-row">
                <a href="index.php" class="category-cta all-cta <?= $selectedCategory === "all" ? "active" : "" ?>">Semua Produk</a>
                <a href="ban.php" class="category-cta ban-cta <?= $selectedCategory === "ban" ? "active" : "" ?>">Ban</a>
                <a href="oli.php" class="category-cta oli-cta <?= $selectedCategory === "oli" ? "active" : "" ?>">Oli</a>
            </div>
            <div class="section-title">
                <div>
                    <h2><?= $selectedCategory === "ban" ? "Ban Pilihan" : ($selectedCategory === "oli" ? "Oli Pilihan" : "Produk Pilihan") ?></h2>
                    <p>Berbagai pilihan <?= $selectedCategory === "oli" ? "oli" : "ban" ?> berkualitas yang siap digunakan dengan stok yang selalu diperbarui.</p>
                </div>
            </div>

            <div class="category-cta-row">
                <a href="ban.php" class="category-cta ban-cta">Ban</a>
                <a href="oli.php" class="category-cta oli-cta">Oli</a>
            </div>

            <form method="GET" class="shop-search-bar">
                <input type="hidden" name="category" value="<?= htmlspecialchars($selectedCategory) ?>">
                <input
                    type="text"
                    name="search"
                    value="<?= htmlspecialchars($searchTerm) ?>"
                    placeholder="Cari ban, oli, tipe, atau nama produk..."
                >
                <button type="submit">Cari</button>
                <?php if ($searchTerm !== "" || $statusFilter !== "all" || $selectedCategory !== "all"): ?>
                    <a href="index.php?category=all" class="clear-search">Reset</a>
                <?php endif; ?>
            </form>

            <div class="shop-filter-bar">
                <a href="index.php?search=<?= urlencode($searchTerm) ?>&status=all&category=<?= urlencode($selectedCategory) ?>" class="filter-chip <?= $statusFilter === "all" ? "active" : "" ?>">Semua</a>
                <a href="index.php?search=<?= urlencode($searchTerm) ?>&status=ready&category=<?= urlencode($selectedCategory) ?>" class="filter-chip <?= $statusFilter === "ready" ? "active" : "" ?>">Ready</a>
                <a href="index.php?search=<?= urlencode($searchTerm) ?>&status=empty&category=<?= urlencode($selectedCategory) ?>" class="filter-chip <?= $statusFilter === "empty" ? "active" : "" ?>">Stok Habis</a>
            </div>

            <div class="products">
                <?php if (count($products) === 0): ?>
                    <p class="empty-state">Belum ada produk untuk kategori ini.</p>
                <?php else: ?>
                    <?php foreach ($products as $product): ?>
                        <?php $productStock = getCustomerProductStock($product); ?>
                        <?php $stockPercent = getCustomerProductStockPercentage($product); ?>
                        <?php $stockState = getCustomerProductStockState($product); ?>
                        <article class="product">
                            <?php if (!empty($product["image"])): ?>
                                <div class="product-image-wrap">
                                    <img
                                        src="../uploads/<?= htmlspecialchars($product["image"]) ?>"
                                        class="product-image"
                                        alt="<?= htmlspecialchars($product["name"]) ?>"
                                    >
                                </div>
                            <?php else: ?>
                                <div class="product-image-wrap placeholder-image">
                                    <span>Foto Produk</span>
                                </div>
                            <?php endif; ?>

                            <div class="product-body">
                                <div class="product-top">
                                    <span class="product-badge"><?= htmlspecialchars($product["sku"]) ?></span>
                                    <?php if ($productStock > 0): ?>
                                        <span class="status-badge in-stock">Ready</span>
                                    <?php else: ?>
                                        <span class="status-badge out-of-stock">Kosong</span>
                                    <?php endif; ?>
                                </div>

                                <h3><?= htmlspecialchars($product["name"]) ?></h3>
                                <p class="product-desc"><?= htmlspecialchars($product["description"]) ?></p>

                                <div class="price">
                                    Rp <?= number_format($product["price"], 0, ",", ".") ?>
                                </div>

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
