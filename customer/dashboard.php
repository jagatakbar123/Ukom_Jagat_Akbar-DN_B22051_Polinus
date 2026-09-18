<?php
session_start();
require_once __DIR__ . "/../config/database.php";
$stmt = $pdo->query("SELECT * FROM products WHERE status = 'active' ORDER BY id DESC");
$products = $stmt->fetchAll();
$customerName = $_SESSION["user_name"] ?? "Customer";
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard Customer | Bakoel Ban Motor Kongsi</title>
<link rel="stylesheet" href="../assets/dashboard_customer.css">
</head>
<body>
<div class="layout">
<aside class="sidebar">
    <div class="brand"><div class="logo">BB</div><div><b>Bakoel Ban</b><small>Motor Kongsi</small></div></div>
    <div class="side-title">DAFTAR PRODUK</div>
    <div class="side-products">
    <?php foreach ($products as $product): ?>
        <a class="side-product" href="#product-<?= (int)$product['id'] ?>">
            <div class="side-img">
                <?php if (!empty($product['image'])): ?><img src="../uploads/<?= htmlspecialchars($product['image']) ?>" alt="<?= htmlspecialchars($product['name']) ?>"><?php else: ?>BAN<?php endif; ?>
            </div>
            <div><strong><?= htmlspecialchars($product['name']) ?></strong><span>Rp <?= number_format($product['price'],0,',','.') ?></span></div>
        </a>
    <?php endforeach; ?>
    </div>
    <div class="side-bottom">
        <div class="customer"><div class="avatar"><?= strtoupper(substr($customerName,0,1)) ?></div><div><b><?= htmlspecialchars($customerName) ?></b><small>Customer</small></div></div>
        <?php if (isset($_SESSION['user_id'])): ?><a class="logout" href="../user/logout.php">↪ Logout</a><?php else: ?><a class="login" href="../user/login.php">Login Customer</a><?php endif; ?>
    </div>
</aside>
<main class="main">
<header class="header"><div><small>Selamat datang di</small><h1>Bakoel Ban Motor Kongsi</h1></div><?php if(isset($_SESSION['user_id'])):?><div class="user-pill">Halo, <?= htmlspecialchars($customerName) ?></div><?php endif;?></header>
<section class="hero">
    <div class="hero-text"><span>PROMO & INFORMASI</span><h2>Ban Berkualitas untuk<br>Perjalanan Lebih Aman</h2><p>Temukan berbagai pilihan ban motor dengan harga terbaik sesuai kebutuhan Anda.</p><a href="#produk">Lihat Produk →</a></div>
    <div class="tire"><div class="hole"></div><div class="price">Harga mulai<br><b>Rp 165K*</b></div></div>
</section>
<section id="produk" class="products"><div class="title"><span>DAFTAR HARGA</span><h2>Produk Ban Motor</h2><p>Harga di bawah mengikuti data produk yang tersimpan di database toko.</p></div>
<div class="grid">
<?php foreach ($products as $product): $stock=(int)($product['stock'] ?? 0); ?>
<article class="card" id="product-<?= (int)$product['id'] ?>">
<div class="product-image"><?php if(!empty($product['image'])):?><img src="../uploads/<?= htmlspecialchars($product['image']) ?>" alt="<?= htmlspecialchars($product['name']) ?>"><?php else:?><span>FOTO BAN</span><?php endif;?></div>
<div class="card-body"><div class="top"><span><?= htmlspecialchars($product['sku'] ?? 'BAN') ?></span><em class="<?= $stock>0?'ready':'empty' ?>"><?= $stock>0?'READY':'HABIS' ?></em></div><h3><?= htmlspecialchars($product['name']) ?></h3><?php if(!empty($product['description'])):?><p><?= htmlspecialchars($product['description']) ?></p><?php endif;?><strong class="price-text">Rp <?= number_format($product['price'],0,',','.') ?></strong><div class="bottom"><small>Stok: <?= $stock ?></small><a href="#product-<?= (int)$product['id'] ?>">Detail →</a></div></div>
</article>
<?php endforeach; ?>
</div></section>
<section class="bottom-promo"><div><span>BAKOEL BAN MOTOR KONGSI</span><h2>Butuh ganti ban?</h2><p>Pilih ukuran dan tipe ban yang sesuai dengan kendaraan Anda.</p></div><a href="#produk">Lihat Daftar Harga →</a></section>
<footer>© <?= date('Y') ?> Bakoel Ban Motor Kongsi</footer>
</main></div>
</body></html>
