<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Vérifier que c'est bien un user (pas admin)
if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin') {
    header('Location: admin.php');
    exit;
}

try {
    $pdo = getDB();
    $stmt = $pdo->prepare('SELECT * FROM utilisateurs WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    if (!$user) { session_destroy(); header('Location: login.php'); exit; }

    // Récupérer articles actifs
    $cat = $_GET['cat'] ?? '';
    $search = trim($_GET['q'] ?? '');

    $where = "WHERE actif = 1";
    $params = [];
    if ($cat) { $where .= " AND categorie = ?"; $params[] = $cat; }
    if ($search) { $where .= " AND (nom LIKE ? OR description LIKE ? OR marque LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%"; }

    $articles = $pdo->prepare("SELECT * FROM articles $where ORDER BY nouveaute DESC, en_vedette DESC, date_ajout DESC");
    $articles->execute($params);
    $articles = $articles->fetchAll();

    // Catégories disponibles
    $categories = $pdo->query("SELECT DISTINCT categorie FROM articles WHERE actif = 1 ORDER BY categorie")->fetchAll(PDO::FETCH_COLUMN);

    // Articles vedette pour carousel
    $vedette = $pdo->query("SELECT * FROM articles WHERE actif = 1 AND en_vedette = 1 LIMIT 4")->fetchAll();
    if (empty($vedette)) {
        $vedette = $pdo->query("SELECT * FROM articles WHERE actif = 1 LIMIT 4")->fetchAll();
    }

} catch (PDOException $e) {
    die('Erreur base de données.');
}

// Construire l'URL publique d'une image depuis son nom
function imgUrl($filename) {
    if (empty($filename)) return '';
    $filename = basename($filename);
    $path = __DIR__ . '/uploads/articles/' . $filename;
    if (file_exists($path)) {
        return 'uploads/articles/' . htmlspecialchars($filename);
    }
    return '';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MAISON MODE — Boutique</title>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,600;1,300;1,400&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --cream: #f9f6f1;
            --dark: #1a1814;
            --warm: #8b7355;
            --accent: #c9a96e;
            --soft: #e8e0d5;
            --text: #2c2820;
            --muted: #9a9088;
            --white: #ffffff;
            --danger: #c0392b;
        }
        * { margin:0; padding:0; box-sizing:border-box; }

        body {
            font-family: 'DM Sans', sans-serif;
            background: var(--cream);
            color: var(--text);
            min-height: 100vh;
        }

        /* ── NAVBAR ── */
        .navbar {
            position: sticky; top: 0; z-index: 200;
            background: var(--dark);
            padding: 0 48px;
            display: flex; align-items: center; justify-content: space-between;
            height: 70px;
            border-bottom: 1px solid rgba(255,255,255,0.08);
        }
        .nav-logo {
            font-family: 'Cormorant Garamond', serif;
            font-size: 26px; font-weight: 300; letter-spacing: 6px;
            color: var(--accent); text-decoration: none;
            text-transform: uppercase;
        }
        .nav-links {
            display: flex; gap: 36px; list-style: none;
        }
        .nav-links a {
            color: rgba(255,255,255,0.7); text-decoration: none;
            font-size: 13px; letter-spacing: 2px; text-transform: uppercase;
            font-weight: 500; transition: color 0.2s;
        }
        .nav-links a:hover, .nav-links a.active { color: var(--accent); }
        .nav-right {
            display: flex; align-items: center; gap: 20px;
        }
        .nav-user {
            color: rgba(255,255,255,0.6); font-size: 13px;
        }
        .nav-user strong { color: var(--accent); }
        .cart-btn {
            position: relative; background: none; border: none;
            cursor: pointer; color: white; font-size: 22px;
            transition: transform 0.2s;
        }
        .cart-btn:hover { transform: scale(1.1); }
        .cart-count {
            position: absolute; top: -6px; right: -8px;
            background: var(--accent); color: var(--dark);
            font-size: 10px; font-weight: 700;
            width: 18px; height: 18px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
        }
        .logout-link {
            color: rgba(255,255,255,0.5); font-size: 12px;
            text-decoration: none; letter-spacing: 1px;
            text-transform: uppercase; transition: color 0.2s;
            padding: 8px 16px; border: 1px solid rgba(255,255,255,0.2);
            border-radius: 3px;
        }
        .logout-link:hover { color: white; border-color: rgba(255,255,255,0.5); }

        /* ── CAROUSEL ── */
        .carousel {
            position: relative; overflow: hidden;
            height: 520px; background: var(--dark);
        }
        .carousel-track {
            display: flex; height: 100%;
            transition: transform 0.8s cubic-bezier(0.77,0,0.175,1);
        }
        .carousel-slide {
            min-width: 100%; height: 100%;
            position: relative; display: flex;
            align-items: center; justify-content: center;
            overflow: hidden;
        }
        .carousel-bg {
            position: absolute; inset: 0;
            background: linear-gradient(135deg, #1a1814 0%, #2c2418 40%, #3d3020 100%);
        }
        .carousel-bg.has-img {
            background-size: cover; background-position: center;
            filter: brightness(0.45);
        }
        .carousel-content {
            position: relative; z-index: 2;
            text-align: center; padding: 40px;
            max-width: 600px;
        }
        .carousel-tag {
            font-size: 11px; letter-spacing: 4px; text-transform: uppercase;
            color: var(--accent); margin-bottom: 18px; display: block;
        }
        .carousel-title {
            font-family: 'Cormorant Garamond', serif;
            font-size: clamp(36px, 5vw, 64px);
            font-weight: 300; color: white; line-height: 1.1;
            margin-bottom: 16px;
        }
        .carousel-price {
            font-size: 22px; color: var(--accent);
            font-family: 'Cormorant Garamond', serif;
            margin-bottom: 28px;
        }
        .carousel-cta {
            display: inline-block;
            padding: 14px 40px;
            background: var(--accent); color: var(--dark);
            font-size: 12px; letter-spacing: 3px; text-transform: uppercase;
            font-weight: 600; cursor: pointer; border: none;
            transition: all 0.3s; text-decoration: none;
        }
        .carousel-cta:hover { background: white; }
        .carousel-nav {
            position: absolute; bottom: 28px; left: 50%; transform: translateX(-50%);
            display: flex; gap: 10px; z-index: 3;
        }
        .carousel-dot {
            width: 6px; height: 6px; border-radius: 50%;
            background: rgba(255,255,255,0.3); cursor: pointer;
            transition: all 0.3s; border: none;
        }
        .carousel-dot.active { background: var(--accent); width: 24px; border-radius: 3px; }
        .carousel-arrow {
            position: absolute; top: 50%; transform: translateY(-50%);
            background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2);
            color: white; width: 48px; height: 48px;
            display: flex; align-items: center; justify-content: center;
            cursor: pointer; z-index: 3; transition: all 0.2s;
            font-size: 18px; border-radius: 50%;
        }
        .carousel-arrow:hover { background: var(--accent); border-color: var(--accent); color: var(--dark); }
        .carousel-arrow.prev { left: 28px; }
        .carousel-arrow.next { right: 28px; }

        /* ── SEARCH BAR ── */
        .search-section {
            background: var(--white);
            padding: 24px 48px;
            border-bottom: 1px solid var(--soft);
            display: flex; align-items: center; gap: 20px;
            flex-wrap: wrap;
        }
        .search-wrap {
            flex: 1; min-width: 260px;
            position: relative;
        }
        .search-icon {
            position: absolute; left: 16px; top: 50%; transform: translateY(-50%);
            color: var(--muted); font-size: 16px; pointer-events: none;
        }
        .search-input {
            width: 100%; padding: 13px 16px 13px 44px;
            border: 1.5px solid var(--soft); border-radius: 4px;
            font-size: 14px; font-family: 'DM Sans', sans-serif;
            background: var(--cream); color: var(--text);
            transition: border-color 0.2s;
            outline: none;
        }
        .search-input:focus { border-color: var(--warm); background: white; }
        .search-input::placeholder { color: var(--muted); }
        .search-btn {
            padding: 13px 32px;
            background: var(--dark); color: var(--accent);
            border: none; font-size: 12px; letter-spacing: 2px;
            text-transform: uppercase; font-weight: 600;
            cursor: pointer; transition: all 0.2s; border-radius: 4px;
        }
        .search-btn:hover { background: var(--warm); color: white; }
        .result-count {
            font-size: 13px; color: var(--muted); white-space: nowrap;
        }

        /* ── MAIN LAYOUT ── */
        .shop-layout {
            display: flex; gap: 0;
            max-width: 1400px; margin: 0 auto;
            padding: 0 48px 60px;
        }

        /* ── FILTERS SIDEBAR ── */
        .filters-sidebar {
            width: 240px; flex-shrink: 0;
            padding: 36px 0 0;
        }
        .filter-title {
            font-size: 11px; letter-spacing: 3px;
            text-transform: uppercase; color: var(--muted);
            margin-bottom: 20px; font-weight: 600;
        }
        .filter-item {
            display: block; padding: 10px 16px; margin-bottom: 2px;
            font-size: 14px; color: var(--text); text-decoration: none;
            border-left: 2px solid transparent;
            transition: all 0.2s; border-radius: 0 4px 4px 0;
        }
        .filter-item:hover { color: var(--warm); border-left-color: var(--accent); background: rgba(201,169,110,0.06); }
        .filter-item.active { color: var(--dark); border-left-color: var(--dark); font-weight: 600; background: rgba(0,0,0,0.04); }
        .filter-count {
            float: right; font-size: 11px;
            color: var(--muted); background: var(--soft);
            padding: 2px 8px; border-radius: 10px;
        }
        .filter-divider { border: none; border-top: 1px solid var(--soft); margin: 24px 0; }

        /* ── PRODUCTS GRID ── */
        .products-area { flex: 1; padding: 36px 0 0 48px; }
        .products-header {
            display: flex; align-items: center; justify-content: space-between;
            margin-bottom: 32px; padding-bottom: 16px;
            border-bottom: 1px solid var(--soft);
        }
        .products-header h2 {
            font-family: 'Cormorant Garamond', serif;
            font-size: 28px; font-weight: 400; color: var(--dark);
        }
        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
            gap: 32px;
        }

        /* ── PRODUCT CARD ── */
        .product-card {
            background: white;
            border: 1px solid var(--soft);
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
            cursor: pointer;
        }
        .product-card:hover {
            box-shadow: 0 20px 50px rgba(0,0,0,0.12);
            transform: translateY(-4px);
        }
        .product-card:hover .card-actions { opacity: 1; transform: translateY(0); }

        .card-img-wrap {
            position: relative; overflow: hidden;
            aspect-ratio: 3/4; background: var(--soft);
            display: flex; align-items: center; justify-content: center;
        }
        .card-img {
            width: 100%; height: 100%; object-fit: cover;
            transition: transform 0.6s ease;
        }
        .product-card:hover .card-img { transform: scale(1.06); }
        .card-img-placeholder {
            font-size: 64px; color: var(--muted);
            display: flex; align-items: center; justify-content: center;
            width: 100%; height: 100%;
        }
        .card-badge {
            position: absolute; top: 14px; left: 14px;
            font-size: 10px; letter-spacing: 2px; text-transform: uppercase;
            font-weight: 700; padding: 5px 10px;
        }
        .badge-new  { background: var(--dark); color: var(--accent); }
        .badge-sale { background: var(--danger); color: white; }
        .badge-feat { background: var(--accent); color: var(--dark); }

        .card-actions {
            position: absolute; bottom: 0; left: 0; right: 0;
            display: flex; gap: 1px;
            opacity: 0; transform: translateY(10px);
            transition: all 0.3s;
        }
        .card-action-btn {
            flex: 1; padding: 12px 8px;
            background: var(--dark); color: var(--accent);
            border: none; font-size: 11px; letter-spacing: 1.5px;
            text-transform: uppercase; font-weight: 600;
            cursor: pointer; transition: background 0.2s;
        }
        .card-action-btn:hover { background: var(--warm); color: white; }
        .card-action-btn.cart { background: var(--accent); color: var(--dark); }
        .card-action-btn.cart:hover { background: var(--dark); color: var(--accent); }

        .card-body { padding: 18px 20px 20px; }
        .card-brand {
            font-size: 10px; letter-spacing: 2px;
            text-transform: uppercase; color: var(--muted);
            margin-bottom: 6px;
        }
        .card-name {
            font-family: 'Cormorant Garamond', serif;
            font-size: 18px; font-weight: 400;
            color: var(--dark); margin-bottom: 8px;
            line-height: 1.3;
        }
        .card-cat {
            font-size: 12px; color: var(--muted); margin-bottom: 12px;
        }
        .card-price {
            display: flex; align-items: baseline; gap: 10px;
        }
        .price-current {
            font-size: 18px; font-weight: 600; color: var(--dark);
            font-family: 'Cormorant Garamond', serif;
        }
        .price-original {
            font-size: 14px; color: var(--muted);
            text-decoration: line-through;
            font-family: 'Cormorant Garamond', serif;
        }
        .price-sale { color: var(--danger) !important; }
        .card-sizes {
            display: flex; gap: 5px; margin-top: 10px; flex-wrap: wrap;
        }
        .size-tag {
            font-size: 10px; padding: 3px 8px;
            border: 1px solid var(--soft); color: var(--muted);
            border-radius: 2px; letter-spacing: 1px;
        }
        .stock-low { font-size: 11px; color: var(--danger); margin-top: 8px; font-weight: 500; }

        /* ── EMPTY STATE ── */
        .empty-state {
            text-align: center; padding: 80px 40px;
            grid-column: 1 / -1;
        }
        .empty-state .empty-icon { font-size: 60px; margin-bottom: 20px; }
        .empty-state h3 {
            font-family: 'Cormorant Garamond', serif;
            font-size: 28px; font-weight: 300; color: var(--dark);
            margin-bottom: 10px;
        }
        .empty-state p { color: var(--muted); font-size: 15px; }

        /* ── CART DRAWER ── */
        .cart-overlay {
            display: none; position: fixed; inset: 0;
            background: rgba(0,0,0,0.5); z-index: 500;
        }
        .cart-overlay.open { display: block; }
        .cart-drawer {
            position: fixed; top: 0; right: -420px; width: 420px;
            height: 100vh; background: white; z-index: 501;
            display: flex; flex-direction: column;
            transition: right 0.4s cubic-bezier(0.77,0,0.175,1);
            box-shadow: -10px 0 40px rgba(0,0,0,0.15);
        }
        .cart-drawer.open { right: 0; }
        .cart-header {
            padding: 28px; border-bottom: 1px solid var(--soft);
            display: flex; align-items: center; justify-content: space-between;
        }
        .cart-header h3 {
            font-family: 'Cormorant Garamond', serif;
            font-size: 22px; font-weight: 400;
        }
        .cart-close {
            background: none; border: none; font-size: 22px;
            cursor: pointer; color: var(--muted); transition: color 0.2s;
        }
        .cart-close:hover { color: var(--dark); }
        .cart-items { flex: 1; overflow-y: auto; padding: 20px; }
        .cart-item {
            display: flex; gap: 16px; padding: 16px 0;
            border-bottom: 1px solid var(--soft);
        }
        .cart-item-img {
            width: 72px; height: 90px; object-fit: cover;
            background: var(--soft); flex-shrink: 0;
            display: flex; align-items: center; justify-content: center;
            font-size: 28px; color: var(--muted);
        }
        .cart-item-img img { width: 100%; height: 100%; object-fit: cover; }
        .cart-item-info { flex: 1; }
        .cart-item-name {
            font-family: 'Cormorant Garamond', serif;
            font-size: 16px; margin-bottom: 4px;
        }
        .cart-item-price { font-size: 15px; color: var(--warm); font-weight: 600; }
        .cart-item-remove {
            background: none; border: none; color: var(--muted);
            cursor: pointer; font-size: 16px; padding: 4px;
            transition: color 0.2s;
        }
        .cart-item-remove:hover { color: var(--danger); }
        .cart-footer {
            padding: 24px; border-top: 1px solid var(--soft);
        }
        .cart-total {
            display: flex; justify-content: space-between;
            align-items: center; margin-bottom: 20px;
        }
        .cart-total-label { font-size: 13px; letter-spacing: 2px; text-transform: uppercase; color: var(--muted); }
        .cart-total-amount {
            font-family: 'Cormorant Garamond', serif;
            font-size: 26px; color: var(--dark);
        }
        .cart-checkout {
            width: 100%; padding: 16px;
            background: var(--dark); color: var(--accent);
            border: none; font-size: 12px; letter-spacing: 3px;
            text-transform: uppercase; font-weight: 700;
            cursor: pointer; transition: all 0.2s;
        }
        .cart-checkout:hover { background: var(--warm); color: white; }
        .cart-empty-msg {
            text-align: center; padding: 60px 20px;
            color: var(--muted);
        }
        .cart-empty-msg .icon { font-size: 48px; margin-bottom: 16px; }

        /* ── TOAST ── */
        .toast {
            position: fixed; bottom: 32px; right: 32px;
            background: var(--dark); color: var(--accent);
            padding: 14px 24px; border-radius: 4px;
            font-size: 13px; letter-spacing: 1px;
            z-index: 999; transform: translateY(20px);
            opacity: 0; transition: all 0.3s; pointer-events: none;
        }
        .toast.show { opacity: 1; transform: translateY(0); }

        /* ── RESPONSIVE ── */
        @media (max-width: 900px) {
            .navbar { padding: 0 20px; }
            .nav-links { display: none; }
            .search-section { padding: 16px 20px; }
            .shop-layout { padding: 0 20px 40px; flex-direction: column; }
            .filters-sidebar { width: 100%; padding: 24px 0 0; }
            .filter-items { display: flex; flex-wrap: wrap; gap: 8px; }
            .filter-item { border-left: none; border: 1px solid var(--soft); border-radius: 20px; padding: 6px 14px; }
            .filter-item.active { background: var(--dark); color: var(--accent); border-color: var(--dark); }
            .products-area { padding: 24px 0 0; }
            .products-grid { grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 16px; }
            .cart-drawer { width: 100%; right: -100%; }
            .carousel { height: 360px; }
        }
    </style>
</head>
<body>

<!-- ══ NAVBAR ══ -->
<nav class="navbar">
    <a href="dashboard.php" class="nav-logo">Maison Mode</a>
    <ul class="nav-links">
        <li><a href="?cat=" class="<?= !$cat ? 'active' : '' ?>">Tout</a></li>
        <?php foreach ($categories as $c): ?>
        <li><a href="?cat=<?= urlencode($c) ?>" class="<?= $cat === $c ? 'active' : '' ?>"><?= htmlspecialchars($c) ?></a></li>
        <?php endforeach; ?>
    </ul>
    <div class="nav-right">
        <span class="nav-user">Bonjour, <strong><?= htmlspecialchars($user['prenom']) ?></strong></span>
        <button class="cart-btn" onclick="toggleCart()">
            🛍️
            <span class="cart-count" id="cartCount">0</span>
        </button>
        <a href="logout.php" class="logout-link">Quitter</a>
    </div>
</nav>
<?php /* ?>
<!-- ══ CAROUSEL ══ -->
<?php if (!empty($vedette)): ?>
<div class="carousel">
    <div class="carousel-track" id="carouselTrack">
        <?php foreach ($vedette as $slide): ?>
        <div class="carousel-slide">
            <?php if ($slide['image_principale'] && file_exists($slide['image_principale'])): ?>
               <div class="carousel-bg has-img" style="background-image:url('<?= htmlspecialchars($slide['image_principale']) ?>')"></div>
            <?php else: ?>
                <div class="carousel-bg"></div>
            <?php endif; ?>
            <div class="carousel-content">
                <span class="carousel-tag"><?= htmlspecialchars($slide['categorie']) ?><?= $slide['nouveaute'] ? ' · Nouveauté' : '' ?></span>
                <h2 class="carousel-title"><?= htmlspecialchars($slide['nom']) ?></h2>
                <div class="carousel-price">
                    <?php if ($slide['prix_solde']): ?>
                        <span style="text-decoration:line-through;opacity:0.5;font-size:18px"><?= number_format($slide['prix'],2) ?>€</span>
                        &nbsp;<?= number_format($slide['prix_solde'],2) ?>€
                    <?php else: ?>
                        <?= number_format($slide['prix'],2) ?>€
                    <?php endif; ?>
                </div>
                <button class="carousel-cta" onclick="addToCart(<?= htmlspecialchars(json_encode($slide)) ?>)">Ajouter au panier</button>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <button class="carousel-arrow prev" onclick="moveCarousel(-1)">‹</button>
    <button class="carousel-arrow next" onclick="moveCarousel(1)">›</button>
    <div class="carousel-nav" id="carouselDots">
        <?php foreach ($vedette as $i => $s): ?>
        <button class="carousel-dot <?= $i===0?'active':'' ?>" onclick="goToSlide(<?= $i ?>)"></button>
        <?php endforeach; ?>
    </div>
</div>
<?php else: ?>
<div style="background:var(--dark);padding:60px;text-align:center">
    <p style="font-family:'Cormorant Garamond',serif;font-size:36px;color:var(--accent);letter-spacing:4px">MAISON MODE</p>
    <p style="color:rgba(255,255,255,0.4);margin-top:10px;letter-spacing:2px;font-size:13px">VOTRE BOUTIQUE DE VÊTEMENTS</p>
</div>
<?php endif; ?>
<?php */ ?>

<!-- ══ SEARCH ══ -->
<div class="search-section">
    <form method="GET" action="" style="display:contents">
        <?php if ($cat): ?><input type="hidden" name="cat" value="<?= htmlspecialchars($cat) ?>"><?php endif; ?>
        <div class="search-wrap">
            <span class="search-icon">🔍</span>
            <input type="text" name="q" class="search-input"
                placeholder="Rechercher un vêtement, une marque…"
                value="<?= htmlspecialchars($search) ?>">
        </div>
        <button type="submit" class="search-btn">Rechercher</button>
        <?php if ($search || $cat): ?>
            <a href="dashboard.php" style="font-size:13px;color:var(--muted);text-decoration:none;white-space:nowrap">✕ Effacer</a>
        <?php endif; ?>
    </form>
    <span class="result-count"><?= count($articles) ?> article<?= count($articles)>1?'s':'' ?></span>
</div>

<!-- ══ SHOP LAYOUT ══ -->
<div class="shop-layout">

    <!-- FILTRES -->
    <aside class="filters-sidebar">
        <p class="filter-title">Catégories</p>
        <div class="filter-items">
            <a href="?<?= $search ? 'q='.urlencode($search) : '' ?>" class="filter-item <?= !$cat ? 'active' : '' ?>">
                Tout voir
                <span class="filter-count"><?= array_sum(array_map(fn($c) => 1, $categories)) ?></span>
            </a>
            <?php foreach ($categories as $c):
                $count = $pdo->prepare("SELECT COUNT(*) FROM articles WHERE actif=1 AND categorie=?");
                $count->execute([$c]); $cnt = $count->fetchColumn();
            ?>
            <a href="?cat=<?= urlencode($c) ?><?= $search ? '&q='.urlencode($search) : '' ?>"
               class="filter-item <?= $cat===$c ? 'active' : '' ?>">
                <?= htmlspecialchars($c) ?>
                <span class="filter-count"><?= $cnt ?></span>
            </a>
            <?php endforeach; ?>
        </div>

        <?php if (!empty($categories)): ?>
        <hr class="filter-divider">
        <p class="filter-title">Filtres rapides</p>
        <a href="?nouveaute=1<?= $cat ? '&cat='.urlencode($cat) : '' ?>" class="filter-item">🆕 Nouveautés</a>
        <a href="?solde=1<?= $cat ? '&cat='.urlencode($cat) : '' ?>" class="filter-item">🏷️ En solde</a>
        <a href="?vedette=1<?= $cat ? '&cat='.urlencode($cat) : '' ?>" class="filter-item">⭐ En vedette</a>
        <?php endif; ?>
    </aside>

    <!-- PRODUITS -->
    <section class="products-area">
        <div class="products-header">
            <h2>
                <?php if ($cat) echo htmlspecialchars($cat);
                elseif ($search) echo "Résultats pour \"".htmlspecialchars($search)."\"";
                else echo "Notre Collection"; ?>
            </h2>
        </div>

        <div class="products-grid">
            <?php if (empty($articles)): ?>
            <div class="empty-state">
                <div class="empty-icon">👗</div>
                <h3>Aucun article trouvé</h3>
                <p>Essayez une autre recherche ou revenez bientôt.</p>
            </div>
            <?php else: ?>
            <?php foreach ($articles as $a):
                $prix = $a['prix_solde'] ?: $a['prix'];
                $tailles = $a['tailles'] ? explode(',', $a['tailles']) : [];
            ?>
            <div class="product-card">
                <div class="card-img-wrap">
                    <?php $imgUrl = imgUrl($a['image_principale']); ?>
                    <?php if ($imgUrl): ?>
                        <img src="<?= $imgUrl ?>" class="card-img" alt="<?= htmlspecialchars($a['nom']) ?>">
                    <?php else: ?>
                        <div class="card-img-placeholder">👕</div>
                    <?php endif; ?>

                    <!-- Badge -->
                    <?php if ($a['nouveaute']): ?>
                        <span class="card-badge badge-new">Nouveau</span>
                    <?php elseif ($a['prix_solde']): ?>
                        <span class="card-badge badge-sale">Solde</span>
                    <?php elseif ($a['en_vedette']): ?>
                        <span class="card-badge badge-feat">⭐</span>
                    <?php endif; ?>

                    <!-- Actions hover -->
                    <div class="card-actions">
                        <button class="card-action-btn cart" onclick="addToCart(<?= htmlspecialchars(json_encode($a)) ?>)">🛍 Panier</button>
                        <button class="card-action-btn" onclick="showDetail(<?= htmlspecialchars(json_encode($a)) ?>)">👁 Voir</button>
                    </div>
                </div>

                <div class="card-body">
                    <?php if ($a['marque']): ?>
                    <div class="card-brand"><?= htmlspecialchars($a['marque']) ?></div>
                    <?php endif; ?>
                    <div class="card-name"><?= htmlspecialchars($a['nom']) ?></div>
                    <div class="card-cat"><?= htmlspecialchars($a['categorie']) ?><?= $a['sous_categorie'] ? ' · '.$a['sous_categorie'] : '' ?></div>
                    <div class="card-price">
                        <span class="price-current <?= $a['prix_solde'] ? 'price-sale' : '' ?>"><?= number_format($prix, 2) ?>€</span>
                        <?php if ($a['prix_solde']): ?>
                        <span class="price-original"><?= number_format($a['prix'], 2) ?>€</span>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($tailles)): ?>
                    <div class="card-sizes">
                        <?php foreach (array_slice($tailles, 0, 5) as $t): ?>
                        <span class="size-tag"><?= htmlspecialchars(trim($t)) ?></span>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                    <?php if ($a['stock'] > 0 && $a['stock'] <= 3): ?>
                    <div class="stock-low">⚡ Plus que <?= $a['stock'] ?> en stock !</div>
                    <?php elseif ($a['stock'] == 0): ?>
                    <div class="stock-low">Rupture de stock</div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>
</div>

<!-- ══ CART DRAWER ══ -->
<div class="cart-overlay" id="cartOverlay" onclick="toggleCart()"></div>
<div class="cart-drawer" id="cartDrawer">
    <div class="cart-header">
        <h3>Mon Panier</h3>
        <button class="cart-close" onclick="toggleCart()">✕</button>
    </div>
    <div class="cart-items" id="cartItems">
        <div class="cart-empty-msg">
            <div class="icon">🛍️</div>
            <p>Votre panier est vide</p>
        </div>
    </div>
    <div class="cart-footer">
        <div class="cart-total">
            <span class="cart-total-label">Total</span>
            <span class="cart-total-amount" id="cartTotal">0,00€</span>
        </div>
        <button class="cart-checkout" onclick="alert('Fonctionnalité de paiement à intégrer !')">Commander</button>
    </div>
</div>

<!-- ══ TOAST ══ -->
<div class="toast" id="toast"></div>

<script>
// ── CAROUSEL ──────────────────────────────────────────────────────────────────
let currentSlide = 0;
const totalSlides = document.querySelectorAll('.carousel-slide').length;

function moveCarousel(dir) {
    currentSlide = (currentSlide + dir + totalSlides) % totalSlides;
    updateCarousel();
}
function goToSlide(n) {
    currentSlide = n;
    updateCarousel();
}
function updateCarousel() {
    document.getElementById('carouselTrack').style.transform = `translateX(-${currentSlide * 100}%)`;
    document.querySelectorAll('.carousel-dot').forEach((d, i) => {
        d.classList.toggle('active', i === currentSlide);
    });
}
if (totalSlides > 1) setInterval(() => moveCarousel(1), 5000);

// ── CART ──────────────────────────────────────────────────────────────────────
let cart = JSON.parse(localStorage.getItem('maisonmode_cart') || '[]');

function saveCart() { localStorage.setItem('maisonmode_cart', JSON.stringify(cart)); }

function toggleCart() {
    document.getElementById('cartOverlay').classList.toggle('open');
    document.getElementById('cartDrawer').classList.toggle('open');
    renderCart();
}

function addToCart(article) {
    const existing = cart.find(i => i.id == article.id);
    if (existing) {
        existing.qty++;
    } else {
        cart.push({
            id: article.id,
            nom: article.nom,
            prix: article.prix_solde || article.prix,
            image: article.image_principale ? 'uploads/articles/' + article.image_principale : '',
            qty: 1
        });
    }
    saveCart();
    updateCartCount();
    showToast('✓ ' + article.nom + ' ajouté au panier');
}

function removeFromCart(id) {
    cart = cart.filter(i => i.id != id);
    saveCart();
    updateCartCount();
    renderCart();
}

function updateCartCount() {
    const total = cart.reduce((s, i) => s + i.qty, 0);
    document.getElementById('cartCount').textContent = total;
}

function renderCart() {
    const el = document.getElementById('cartItems');
    if (cart.length === 0) {
        el.innerHTML = `<div class="cart-empty-msg"><div class="icon">🛍️</div><p>Votre panier est vide</p></div>`;
        document.getElementById('cartTotal').textContent = '0,00€';
        return;
    }
    let html = '';
    let total = 0;
    cart.forEach(item => {
        total += item.prix * item.qty;
        html += `
        <div class="cart-item">
            <div class="cart-item-img">
                ${item.image ? `<img src="${item.image}" alt="">` : '👕'}
            </div>
            <div class="cart-item-info">
                <div class="cart-item-name">${item.nom}</div>
                <div style="font-size:12px;color:#9a9088;margin:4px 0">Qté : ${item.qty}</div>
                <div class="cart-item-price">${(item.prix * item.qty).toFixed(2)}€</div>
            </div>
            <button class="cart-item-remove" onclick="removeFromCart(${item.id})">✕</button>
        </div>`;
    });
    el.innerHTML = html;
    document.getElementById('cartTotal').textContent = total.toFixed(2).replace('.', ',') + '€';
}

function showToast(msg) {
    const t = document.getElementById('toast');
    t.textContent = msg;
    t.classList.add('show');
    setTimeout(() => t.classList.remove('show'), 3000);
}

function showDetail(article) {
    const prix = article.prix_solde || article.prix;
    alert(`${article.nom}\n\n${article.description}\n\nPrix : ${parseFloat(prix).toFixed(2)}€\nTailles : ${article.tailles || 'N/A'}\nCouleurs : ${article.couleurs || 'N/A'}`);
}

// Init
updateCartCount();
</script>
</body>
</html>