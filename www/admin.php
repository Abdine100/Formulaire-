<?php
session_start();
require_once 'config.php';

// Vérifier si connecté et admin
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

try {
    $pdo = getDB();
    if (!isAdmin($pdo, $_SESSION['user_id'])) {
        header('Location: dashboard.php');
        exit;
    }
} catch (PDOException $e) {
    die('Erreur base de données.');
}

$message = '';
$messageType = '';

// ─── ACTIONS POST ────────────────────────────────────────────────────────────

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Désactiver / réactiver un utilisateur
    if ($action === 'toggle_user') {
        $userId = (int)$_POST['user_id'];
        $stmt = $pdo->prepare('SELECT actif FROM utilisateurs WHERE id = ?');
        $stmt->execute([$userId]);
        $u = $stmt->fetch();
        if ($u['actif'] === null) {
            $pdo->prepare('UPDATE utilisateurs SET actif = NOW() WHERE id = ?')->execute([$userId]);
            $message = "Compte utilisateur désactivé.";
        } else {
            $pdo->prepare('UPDATE utilisateurs SET actif = NULL WHERE id = ?')->execute([$userId]);
            $message = "Compte utilisateur réactivé.";
        }
        $messageType = 'success';
    }

    // Ajouter un article
    if ($action === 'add_article') {
        $nom         = trim($_POST['nom'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $prix        = floatval($_POST['prix'] ?? 0);
        $prix_solde  = !empty($_POST['prix_solde']) ? floatval($_POST['prix_solde']) : null;
        $categorie   = trim($_POST['categorie'] ?? '');
        $sous_cat    = trim($_POST['sous_categorie'] ?? '');
        $marque      = trim($_POST['marque'] ?? '');
        $tailles     = trim($_POST['tailles'] ?? '');
        $couleurs    = trim($_POST['couleurs'] ?? '');
        $matiere     = trim($_POST['matiere'] ?? '');
        $stock       = (int)($_POST['stock'] ?? 0);
        $nouveaute   = isset($_POST['nouveaute']) ? 1 : 0;
        $en_vedette  = isset($_POST['en_vedette']) ? 1 : 0;

        $image_principale = '';
        if (!empty($_FILES['image_principale']['name'])) {
            $result = uploadImage($_FILES['image_principale']);
            if (str_starts_with($result, 'ERREUR')) {
                $message = $result;
                $messageType = 'error';
            } else {
                $image_principale = $result; // nom du fichier uniquement
            }
        }

        if ($nom && $description && $prix > 0 && $categorie) {
            $stmt = $pdo->prepare("
                INSERT INTO articles (nom, description, prix, prix_solde, categorie, sous_categorie, marque, tailles, couleurs, matiere, stock, image_principale, nouveaute, en_vedette)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$nom, $description, $prix, $prix_solde, $categorie, $sous_cat, $marque, $tailles, $couleurs, $matiere, $stock, $image_principale, $nouveaute, $en_vedette]);
            $message = "Article \"$nom\" ajouté avec succès !";
            $messageType = 'success';
        } else {
            $message = "Veuillez remplir tous les champs obligatoires.";
            $messageType = 'error';
        }
    }

    // Modifier un article
    if ($action === 'edit_article') {
        $id          = (int)$_POST['article_id'];
        $nom         = trim($_POST['nom'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $prix        = floatval($_POST['prix'] ?? 0);
        $prix_solde  = !empty($_POST['prix_solde']) ? floatval($_POST['prix_solde']) : null;
        $categorie   = trim($_POST['categorie'] ?? '');
        $sous_cat    = trim($_POST['sous_categorie'] ?? '');
        $marque      = trim($_POST['marque'] ?? '');
        $tailles     = trim($_POST['tailles'] ?? '');
        $couleurs    = trim($_POST['couleurs'] ?? '');
        $matiere     = trim($_POST['matiere'] ?? '');
        $stock       = (int)($_POST['stock'] ?? 0);
        $nouveaute   = isset($_POST['nouveaute']) ? 1 : 0;
        $en_vedette  = isset($_POST['en_vedette']) ? 1 : 0;
        $actif       = isset($_POST['actif_article']) ? 1 : 0;

        // Récupérer l'ancien nom de fichier (pas le chemin)
        $stmtImg = $pdo->prepare('SELECT image_principale FROM articles WHERE id = ?');
        $stmtImg->execute([$id]);
        $image_principale = $stmtImg->fetchColumn() ?: '';

        if (!empty($_FILES['image_principale']['name'])) {
            $result = uploadImage($_FILES['image_principale']);
            if (str_starts_with($result, 'ERREUR')) {
                $message = $result;
                $messageType = 'error';
            } else {
                // Supprimer l'ancienne image du serveur si elle existe
                if ($image_principale) {
                    $oldPath = __DIR__ . '/uploads/articles/' . $image_principale;
                    if (file_exists($oldPath)) unlink($oldPath);
                }
                $image_principale = $result; // nouveau nom uniquement
            }
        }

        $stmt = $pdo->prepare("
            UPDATE articles SET nom=?, description=?, prix=?, prix_solde=?, categorie=?, sous_categorie=?,
            marque=?, tailles=?, couleurs=?, matiere=?, stock=?, image_principale=?, nouveaute=?, en_vedette=?, actif=?
            WHERE id=?
        ");
        $stmt->execute([$nom, $description, $prix, $prix_solde, $categorie, $sous_cat, $marque, $tailles, $couleurs, $matiere, $stock, $image_principale, $nouveaute, $en_vedette, $actif, $id]);
        $message = "Article modifié avec succès !";
        $messageType = 'success';
    }

    // Supprimer un article
    if ($action === 'delete_article') {
        $id = (int)$_POST['article_id'];
        $pdo->prepare('DELETE FROM articles WHERE id = ?')->execute([$id]);
        $message = "Article supprimé.";
        $messageType = 'success';
    }
}

// ─── DONNÉES ─────────────────────────────────────────────────────────────────

$utilisateurs = $pdo->query("SELECT * FROM utilisateurs WHERE role = 'user' ORDER BY date_inscription DESC")->fetchAll();
$articles     = $pdo->query("SELECT * FROM articles ORDER BY date_ajout DESC")->fetchAll();
$stats = [
    'users_total'    => $pdo->query("SELECT COUNT(*) FROM utilisateurs WHERE role='user'")->fetchColumn(),
    'users_actifs'   => $pdo->query("SELECT COUNT(*) FROM utilisateurs WHERE role='user' AND actif IS NULL")->fetchColumn(),
    'users_inactifs' => $pdo->query("SELECT COUNT(*) FROM utilisateurs WHERE role='user' AND actif IS NOT NULL")->fetchColumn(),
    'articles_total' => $pdo->query("SELECT COUNT(*) FROM articles")->fetchColumn(),
    'articles_actifs'=> $pdo->query("SELECT COUNT(*) FROM articles WHERE actif=1")->fetchColumn(),
];
$adminInfo = $pdo->prepare('SELECT * FROM utilisateurs WHERE id = ?');
$adminInfo->execute([$_SESSION['user_id']]);
$admin = $adminInfo->fetch();

$activeTab = $_GET['tab'] ?? 'dashboard';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administration — Boutique Mode</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap');
        * { margin: 0; padding: 0; box-sizing: border-box; }

        :root {
            --primary: #6366f1;
            --primary-dark: #4f46e5;
            --primary-light: #eef2ff;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
            --bg: #f8fafc;
            --sidebar-bg: #1e1b4b;
            --sidebar-text: #c7d2fe;
            --sidebar-active: #6366f1;
            --text: #1e293b;
            --text-muted: #64748b;
            --border: #e2e8f0;
            --white: #ffffff;
            --shadow: 0 1px 3px rgba(0,0,0,0.1), 0 1px 2px rgba(0,0,0,0.06);
            --shadow-lg: 0 10px 25px rgba(0,0,0,0.1);
        }

        body { font-family: 'Inter', sans-serif; background: var(--bg); display: flex; min-height: 100vh; }

        /* ── SIDEBAR ── */
        .sidebar {
            width: 260px; min-height: 100vh;
            background: var(--sidebar-bg);
            display: flex; flex-direction: column;
            position: fixed; top: 0; left: 0; z-index: 100;
        }
        .sidebar-logo {
            padding: 28px 24px;
            border-bottom: 1px solid rgba(255,255,255,0.08);
            display: flex; align-items: center; gap: 12px;
        }
        .sidebar-logo .logo-icon {
            width: 42px; height: 42px;
            background: var(--primary);
            border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 22px;
        }
        .sidebar-logo .logo-text { color: white; font-weight: 800; font-size: 18px; }
        .sidebar-logo .logo-sub  { color: var(--sidebar-text); font-size: 12px; }

        .sidebar-nav { flex: 1; padding: 20px 12px; }
        .nav-section-title {
            color: rgba(199,210,254,0.5);
            font-size: 11px; font-weight: 600;
            text-transform: uppercase; letter-spacing: 1px;
            padding: 0 12px; margin: 20px 0 8px;
        }
        .nav-item {
            display: flex; align-items: center; gap: 12px;
            padding: 11px 14px; border-radius: 10px;
            color: var(--sidebar-text);
            text-decoration: none; font-size: 14px; font-weight: 500;
            transition: all 0.2s; cursor: pointer; border: none; background: none;
            width: 100%; margin-bottom: 2px;
        }
        .nav-item:hover  { background: rgba(255,255,255,0.08); color: white; }
        .nav-item.active { background: var(--primary); color: white; }
        .nav-item .icon  { font-size: 18px; width: 24px; text-align: center; }
        .nav-item .badge {
            margin-left: auto; background: var(--danger);
            color: white; font-size: 11px; font-weight: 700;
            padding: 2px 7px; border-radius: 20px;
        }

        .sidebar-footer {
            padding: 20px;
            border-top: 1px solid rgba(255,255,255,0.08);
        }
        .admin-profile {
            display: flex; align-items: center; gap: 12px;
            padding: 12px; border-radius: 10px;
            background: rgba(255,255,255,0.06);
        }
        .admin-avatar {
            width: 38px; height: 38px;
            background: var(--primary); border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 16px; font-weight: 700; color: white;
        }
        .admin-info { flex: 1; }
        .admin-name  { color: white; font-size: 13px; font-weight: 600; }
        .admin-label { color: var(--sidebar-text); font-size: 11px; }
        .logout-link {
            display: block; text-align: center; margin-top: 12px;
            padding: 9px; border-radius: 8px;
            background: rgba(239,68,68,0.15); color: #fca5a5;
            text-decoration: none; font-size: 13px; font-weight: 600;
            transition: background 0.2s;
        }
        .logout-link:hover { background: rgba(239,68,68,0.3); }

        /* ── MAIN ── */
        .main { margin-left: 260px; flex: 1; min-height: 100vh; }

        .topbar {
            background: var(--white);
            padding: 18px 32px;
            border-bottom: 1px solid var(--border);
            display: flex; align-items: center; justify-content: space-between;
            position: sticky; top: 0; z-index: 50;
        }
        .topbar-title { font-size: 22px; font-weight: 700; color: var(--text); }
        .topbar-subtitle { font-size: 13px; color: var(--text-muted); margin-top: 2px; }
        .topbar-right { display: flex; align-items: center; gap: 12px; }
        .topbar-date {
            font-size: 13px; color: var(--text-muted);
            padding: 8px 14px; background: var(--bg); border-radius: 8px;
        }

        .content { padding: 32px; }

        /* ── MESSAGE ── */
        .alert-msg {
            padding: 14px 18px; border-radius: 12px;
            display: flex; align-items: center; gap: 10px;
            margin-bottom: 24px; font-size: 14px; font-weight: 500;
        }
        .alert-msg.success { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
        .alert-msg.error   { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }

        /* ── STATS CARDS ── */
        .stats-grid {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px; margin-bottom: 32px;
        }
        .stat-card {
            background: var(--white); padding: 24px;
            border-radius: 16px; box-shadow: var(--shadow);
            border: 1px solid var(--border);
            display: flex; align-items: center; gap: 16px;
        }
        .stat-icon {
            width: 52px; height: 52px; border-radius: 14px;
            display: flex; align-items: center; justify-content: center;
            font-size: 24px; flex-shrink: 0;
        }
        .stat-icon.blue   { background: #eff6ff; }
        .stat-icon.green  { background: #ecfdf5; }
        .stat-icon.red    { background: #fef2f2; }
        .stat-icon.purple { background: var(--primary-light); }
        .stat-icon.orange { background: #fff7ed; }
        .stat-value { font-size: 28px; font-weight: 800; color: var(--text); }
        .stat-label { font-size: 13px; color: var(--text-muted); font-weight: 500; }

        /* ── TABS ── */
        .tab-content { display: none; }
        .tab-content.active { display: block; }

        /* ── TABLE ── */
        .table-card {
            background: var(--white); border-radius: 16px;
            box-shadow: var(--shadow); border: 1px solid var(--border);
            overflow: hidden;
        }
        .table-header {
            padding: 20px 24px; display: flex;
            align-items: center; justify-content: space-between;
            border-bottom: 1px solid var(--border);
        }
        .table-header h3 { font-size: 16px; font-weight: 700; color: var(--text); }
        .table-header p  { font-size: 13px; color: var(--text-muted); }
        .table-search {
            display: flex; align-items: center; gap: 10px;
        }
        .search-input {
            padding: 9px 14px; border: 1px solid var(--border);
            border-radius: 8px; font-size: 14px; outline: none;
            transition: border-color 0.2s; width: 220px;
        }
        .search-input:focus { border-color: var(--primary); }

        table { width: 100%; border-collapse: collapse; }
        thead th {
            padding: 12px 16px; text-align: left;
            font-size: 12px; font-weight: 700;
            text-transform: uppercase; letter-spacing: 0.5px;
            color: var(--text-muted); background: #f8fafc;
            border-bottom: 1px solid var(--border);
        }
        tbody tr { transition: background 0.15s; }
        tbody tr:hover { background: #f8fafc; }
        tbody td {
            padding: 14px 16px; font-size: 14px;
            color: var(--text); border-bottom: 1px solid #f1f5f9;
        }
        tbody tr:last-child td { border-bottom: none; }

        .badge {
            display: inline-flex; align-items: center; gap: 4px;
            padding: 4px 10px; border-radius: 20px;
            font-size: 12px; font-weight: 600;
        }
        .badge-active   { background: #ecfdf5; color: #065f46; }
        .badge-inactive { background: #fef2f2; color: #991b1b; }
        .badge-new      { background: #eff6ff; color: #1d4ed8; }
        .badge-sale     { background: #fff7ed; color: #c2410c; }

        /* ── BUTTONS ── */
        .btn {
            padding: 8px 16px; border-radius: 8px; border: none;
            font-size: 13px; font-weight: 600; cursor: pointer;
            transition: all 0.2s; display: inline-flex; align-items: center; gap: 6px;
        }
        .btn-primary { background: var(--primary); color: white; }
        .btn-primary:hover { background: var(--primary-dark); }
        .btn-success { background: var(--success); color: white; }
        .btn-success:hover { background: #059669; }
        .btn-danger  { background: var(--danger); color: white; }
        .btn-danger:hover  { background: #dc2626; }
        .btn-warning { background: var(--warning); color: white; }
        .btn-warning:hover { background: #d97706; }
        .btn-outline {
            background: transparent; color: var(--primary);
            border: 1px solid var(--primary);
        }
        .btn-outline:hover { background: var(--primary-light); }
        .btn-sm { padding: 6px 12px; font-size: 12px; }

        /* ── MODAL ── */
        .modal-overlay {
            display: none; position: fixed;
            inset: 0; background: rgba(0,0,0,0.5);
            z-index: 1000; align-items: center; justify-content: center;
        }
        .modal-overlay.open { display: flex; }
        .modal {
            background: white; border-radius: 20px;
            width: 90%; max-width: 680px; max-height: 90vh;
            overflow-y: auto; box-shadow: var(--shadow-lg);
            animation: modalIn 0.3s ease;
        }
        @keyframes modalIn {
            from { opacity: 0; transform: scale(0.95) translateY(-20px); }
            to   { opacity: 1; transform: scale(1) translateY(0); }
        }
        .modal-header {
            padding: 24px 28px; border-bottom: 1px solid var(--border);
            display: flex; align-items: center; justify-content: space-between;
        }
        .modal-header h3 { font-size: 18px; font-weight: 700; }
        .modal-close {
            width: 36px; height: 36px; border-radius: 8px;
            background: var(--bg); border: none; cursor: pointer;
            font-size: 18px; display: flex; align-items: center; justify-content: center;
            transition: background 0.2s;
        }
        .modal-close:hover { background: var(--border); }
        .modal-body { padding: 28px; }
        .modal-footer {
            padding: 20px 28px; border-top: 1px solid var(--border);
            display: flex; gap: 12px; justify-content: flex-end;
        }

        /* ── FORM ── */
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; }
        .form-grid.full { grid-template-columns: 1fr; }
        .form-group { display: flex; flex-direction: column; gap: 6px; }
        .form-group.span-2 { grid-column: span 2; }
        .form-label {
            font-size: 13px; font-weight: 600; color: var(--text);
        }
        .form-label .req { color: var(--danger); }
        .form-control {
            padding: 10px 14px; border: 1.5px solid var(--border);
            border-radius: 10px; font-size: 14px;
            font-family: 'Inter', sans-serif;
            transition: border-color 0.2s, box-shadow 0.2s;
            outline: none;
        }
        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(99,102,241,0.1);
        }
        textarea.form-control { resize: vertical; min-height: 90px; }
        .form-hint { font-size: 12px; color: var(--text-muted); }
        .checkbox-group { display: flex; align-items: center; gap: 8px; }
        .checkbox-group input { width: 16px; height: 16px; accent-color: var(--primary); }
        .checkbox-group label { font-size: 14px; color: var(--text); cursor: pointer; }
        .price-row { display: flex; gap: 12px; align-items: flex-start; }
        .price-row .form-group { flex: 1; }

        .img-preview {
            width: 100%; max-height: 160px; object-fit: cover;
            border-radius: 10px; border: 1px solid var(--border);
            margin-top: 8px; display: none;
        }

        /* ── USER CARD (table row) ── */
        .user-avatar-sm {
            width: 36px; height: 36px; border-radius: 50%;
            background: var(--primary-light); color: var(--primary);
            font-weight: 700; font-size: 14px;
            display: inline-flex; align-items: center; justify-content: center;
        }
        .user-cell { display: flex; align-items: center; gap: 10px; }

        /* ── ARTICLE PREVIEW ── */
        .article-img {
            width: 50px; height: 50px; object-fit: cover;
            border-radius: 8px; background: var(--bg);
        }
        .article-img-placeholder {
            width: 50px; height: 50px; border-radius: 8px;
            background: var(--bg); display: flex; align-items: center; justify-content: center;
            font-size: 22px; color: var(--text-muted);
        }

        /* ── RESPONSIVE ── */
        @media (max-width: 1024px) {
            .sidebar { width: 72px; }
            .sidebar-logo .logo-text,
            .sidebar-logo .logo-sub,
            .nav-item span:not(.icon),
            .nav-item .badge,
            .nav-section-title,
            .admin-info, .logout-link { display: none; }
            .main { margin-left: 72px; }
            .stats-grid { grid-template-columns: 1fr 1fr; }
        }
        @media (max-width: 768px) {
            .content { padding: 20px; }
            .form-grid { grid-template-columns: 1fr; }
            .form-group.span-2 { grid-column: span 1; }
            .topbar { padding: 14px 20px; }
        }
    </style>
</head>
<body>

<!-- ═══════════════════════ SIDEBAR ═══════════════════════ -->
<aside class="sidebar">
    <div class="sidebar-logo">
        <div class="logo-icon"></div>
        <div>
            <div class="logo-text">BoutiqueMode</div>
            <div class="logo-sub">Administration</div>
        </div>
    </div>

    <nav class="sidebar-nav">
        <div class="nav-section-title">Menu</div>

        <a class="nav-item <?= $activeTab === 'dashboard' ? 'active' : '' ?>" href="?tab=dashboard">
            <span class="icon"></span>
            <span>Tableau de bord</span>
        </a>
        <a class="nav-item <?= $activeTab === 'users' ? 'active' : '' ?>" href="?tab=users">
            <span class="icon"></span>
            <span>Utilisateurs</span>
            <?php if ($stats['users_inactifs'] > 0): ?>
                <span class="badge"><?= $stats['users_inactifs'] ?></span>
            <?php endif; ?>
        </a>

        <div class="nav-section-title">Catalogue</div>
        <a class="nav-item <?= $activeTab === 'articles' ? 'active' : '' ?>" href="?tab=articles">
            <span class="icon"></span>
            <span>Articles</span>
        </a>
        <a class="nav-item <?= $activeTab === 'add_article' ? 'active' : '' ?>" href="?tab=add_article">
            <span class="icon"></span>
            <span>Ajouter un article</span>
        </a>
    </nav>

    <div class="sidebar-footer">
        <div class="admin-profile">
            <div class="admin-avatar"><?= strtoupper(substr($admin['prenom'], 0, 1)) ?></div>
            <div class="admin-info">
                <div class="admin-name"><?= htmlspecialchars($admin['prenom'] . ' ' . $admin['nom']) ?></div>
                <div class="admin-label">Administrateur</div>
            </div>
        </div>
        <a href="logout.php" class="logout-link"> Déconnexion</a>
    </div>
</aside>

<!-- ═══════════════════════ MAIN ═══════════════════════ -->
<main class="main">

    <!-- TOPBAR -->
    <header class="topbar">
        <div>
            <div class="topbar-title">
                <?php
                $titles = [
                    'dashboard'   => ' Tableau de bord',
                    'users'       => 'Gestion des utilisateurs',
                    'articles'    => ' Catalogue articles',
                    'add_article' => ' Ajouter un article',
                ];
                echo $titles[$activeTab] ?? 'Administration';
                ?>
            </div>
            <div class="topbar-subtitle">Panneau d'administration — BoutiqueMode</div>
        </div>
        <div class="topbar-right">
            <div class="topbar-date" id="live-date"></div>
        </div>
    </header>

    <div class="content">

        <?php if ($message): ?>
            <div class="alert-msg <?= $messageType ?>">
                <?= $messageType === 'success' ? '' : '' ?>
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <!-- ══════════ DASHBOARD ══════════ -->
        <div class="tab-content <?= $activeTab === 'dashboard' ? 'active' : '' ?>">
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon blue"></div>
                    <div><div class="stat-value"><?= $stats['users_total'] ?></div><div class="stat-label">Utilisateurs total</div></div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon green"></div>
                    <div><div class="stat-value"><?= $stats['users_actifs'] ?></div><div class="stat-label">Comptes actifs</div></div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon red"></div>
                    <div><div class="stat-value"><?= $stats['users_inactifs'] ?></div><div class="stat-label">Comptes désactivés</div></div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon purple"></div>
                    <div><div class="stat-value"><?= $stats['articles_total'] ?></div><div class="stat-label">Articles total</div></div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon orange"></div>
                    <div><div class="stat-value"><?= $stats['articles_actifs'] ?></div><div class="stat-label">Articles en ligne</div></div>
                </div>
            </div>

            <!-- Derniers utilisateurs inscrits -->
            <div class="table-card">
                <div class="table-header">
                    <div>
                        <h3>Dernières inscriptions</h3>
                        <p>5 utilisateurs les plus récents</p>
                    </div>
                    <a href="?tab=users" class="btn btn-outline btn-sm">Voir tous</a>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>Utilisateur</th>
                            <th>Email</th>
                            <th>Ville</th>
                            <th>Statut</th>
                            <th>Inscrit le</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_slice($utilisateurs, 0, 5) as $u): ?>
                        <tr>
                            <td>
                                <div class="user-cell">
                                    <div class="user-avatar-sm"><?= strtoupper(substr($u['prenom'], 0, 1)) ?></div>
                                    <?= htmlspecialchars($u['prenom'] . ' ' . $u['nom']) ?>
                                </div>
                            </td>
                            <td><?= htmlspecialchars($u['email']) ?></td>
                            <td><?= htmlspecialchars($u['ville']) ?></td>
                            <td>
                                <?php if ($u['actif'] === null): ?>
                                    <span class="badge badge-active">● Actif</span>
                                <?php else: ?>
                                    <span class="badge badge-inactive">● Désactivé</span>
                                <?php endif; ?>
                            </td>
                            <td><?= date('d/m/Y', strtotime($u['date_inscription'])) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ══════════ UTILISATEURS ══════════ -->
        <div class="tab-content <?= $activeTab === 'users' ? 'active' : '' ?>">
            <div class="table-card">
                <div class="table-header">
                    <div>
                        <h3>Tous les utilisateurs</h3>
                        <p><?= $stats['users_total'] ?> membres inscrits</p>
                    </div>
                    <input class="search-input" type="text" placeholder=" Rechercher…" id="searchUser" oninput="filterTable('userTable', this.value)">
                </div>
                <table id="userTable">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Utilisateur</th>
                            <th>Email</th>
                            <th>Téléphone</th>
                            <th>Ville</th>
                            <th>Inscrit le</th>
                            <th>Dernière co.</th>
                            <th>Statut</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($utilisateurs as $u): ?>
                        <tr>
                            <td style="color:var(--text-muted)">#<?= $u['id'] ?></td>
                            <td>
                                <div class="user-cell">
                                    <div class="user-avatar-sm"><?= strtoupper(substr($u['prenom'], 0, 1)) ?></div>
                                    <div>
                                        <div><?= htmlspecialchars($u['prenom'] . ' ' . $u['nom']) ?></div>
                                        <div style="font-size:12px;color:var(--text-muted)"><?= ucfirst($u['genre'] ?? '') ?></div>
                                    </div>
                                </div>
                            </td>
                            <td><?= htmlspecialchars($u['email']) ?></td>
                            <td><?= htmlspecialchars($u['telephone']) ?></td>
                            <td><?= htmlspecialchars($u['ville']) ?></td>
                            <td><?= date('d/m/Y', strtotime($u['date_inscription'])) ?></td>
                            <td>
                                <?= $u['derniere_connexion']
                                    ? date('d/m/Y H:i', strtotime($u['derniere_connexion']))
                                    : '<span style="color:var(--text-muted)">Jamais</span>' ?>
                            </td>
                            <td>
                                <?php if ($u['actif'] === null): ?>
                                    <span class="badge badge-active">● Actif</span>
                                <?php else: ?>
                                    <span class="badge badge-inactive">● Désactivé</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <form method="POST" style="display:inline">
                                    <input type="hidden" name="action"  value="toggle_user">
                                    <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                    <?php if ($u['actif'] === null): ?>
                                        <button type="submit" class="btn btn-danger btn-sm"
                                            onclick="return confirm('Désactiver ce compte ?')"> Désactiver</button>
                                    <?php else: ?>
                                        <button type="submit" class="btn btn-success btn-sm"
                                            onclick="return confirm('Réactiver ce compte ?')">Réactiver</button>
                                    <?php endif; ?>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($utilisateurs)): ?>
                        <tr><td colspan="9" style="text-align:center;padding:40px;color:var(--text-muted)">Aucun utilisateur inscrit.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ══════════ ARTICLES ══════════ -->
        <div class="tab-content <?= $activeTab === 'articles' ? 'active' : '' ?>">
            <div class="table-card">
                <div class="table-header">
                    <div>
                        <h3>Catalogue articles</h3>
                        <p><?= $stats['articles_total'] ?> articles</p>
                    </div>
                    <div style="display:flex;gap:10px;align-items:center">
                        <input class="search-input" type="text" placeholder=" Rechercher…" oninput="filterTable('articleTable', this.value)">
                        <a href="?tab=add_article" class="btn btn-primary btn-sm">Ajouter</a>
                    </div>
                </div>
                <table id="articleTable">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Image</th>
                            <th>Nom</th>
                            <th>Catégorie</th>
                            <th>Prix</th>
                            <th>Stock</th>
                            <th>Tags</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($articles as $a): ?>
                        <tr>
                            <td style="color:var(--text-muted)">#<?= $a['id'] ?></td>
                            <td>
                                <?php
                                $imgPath = $a['image_principale'] ? 'uploads/articles/' . $a['image_principale'] : '';
                                $imgExists = $imgPath && file_exists(__DIR__ . '/' . $imgPath);
                                ?>
                                <?php if ($imgExists): ?>
                                    <img src="<?= htmlspecialchars($imgPath) ?>" class="article-img" alt="">
                                <?php else: ?>
                                    <div class="article-img-placeholder">👕</div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong><?= htmlspecialchars($a['nom']) ?></strong>
                                <?php if ($a['marque']): ?>
                                    <div style="font-size:12px;color:var(--text-muted)"><?= htmlspecialchars($a['marque']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?= htmlspecialchars($a['categorie']) ?>
                                <?php if ($a['sous_categorie']): ?>
                                    <div style="font-size:12px;color:var(--text-muted)"><?= htmlspecialchars($a['sous_categorie']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($a['prix_solde']): ?>
                                    <span style="text-decoration:line-through;color:var(--text-muted);font-size:12px"><?= number_format($a['prix'], 2) ?>€</span><br>
                                    <strong style="color:var(--danger)"><?= number_format($a['prix_solde'], 2) ?>€</strong>
                                <?php else: ?>
                                    <strong><?= number_format($a['prix'], 2) ?>€</strong>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span style="color:<?= $a['stock'] > 0 ? 'var(--success)' : 'var(--danger)' ?>;font-weight:600">
                                    <?= $a['stock'] ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($a['nouveaute']): ?>
                                    <span class="badge badge-new">Nouveau</span>
                                <?php endif; ?>
                                <?php if ($a['en_vedette']): ?>
                                    <span class="badge" style="background:#fef9c3;color:#713f12">Vedette</span>
                                <?php endif; ?>
                                <?php if ($a['prix_solde']): ?>
                                    <span class="badge badge-sale">🏷 Solde</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($a['actif']): ?>
                                    <span class="badge badge-active">● En ligne</span>
                                <?php else: ?>
                                    <span class="badge badge-inactive">● Hors ligne</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button class="btn btn-warning btn-sm"
                                    onclick="openEditModal(<?= htmlspecialchars(json_encode($a)) ?>)"> Modifier</button>
                                <form method="POST" style="display:inline">
                                    <input type="hidden" name="action"     value="delete_article">
                                    <input type="hidden" name="article_id" value="<?= $a['id'] ?>">
                                    <button type="submit" class="btn btn-danger btn-sm"
                                        onclick="return confirm('Supprimer cet article ?')">Supprimer</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($articles)): ?>
                        <tr><td colspan="9" style="text-align:center;padding:40px;color:var(--text-muted)">Aucun article. <a href="?tab=add_article">Ajouter le premier !</a></td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ══════════ AJOUTER ARTICLE ══════════ -->
        <div class="tab-content <?= $activeTab === 'add_article' ? 'active' : '' ?>">
            <div class="table-card">
                <div class="table-header">
                    <div>
                        <h3>Ajouter un nouvel article</h3>
                        <p>Remplissez les informations du vêtement</p>
                    </div>
                </div>
                <div style="padding:28px">
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="add_article">

                        <div class="form-grid">
                            <!-- Nom -->
                            <div class="form-group span-2">
                                <label class="form-label">Nom de l'article <span class="req">*</span></label>
                                <input type="text" name="nom" class="form-control" placeholder="Ex : Veste en cuir noir" required>
                            </div>

                            <!-- Description -->
                            <div class="form-group span-2">
                                <label class="form-label">Description <span class="req">*</span></label>
                                <textarea name="description" class="form-control" rows="4"
                                    placeholder="Décrivez le vêtement : matière, coupe, style, entretien…" required></textarea>
                            </div>

                            <!-- Prix -->
                            <div class="form-group">
                                <label class="form-label">Prix normal (€) <span class="req">*</span></label>
                                <input type="number" name="prix" class="form-control" step="0.01" min="0" placeholder="49.99" required>
                            </div>

                            <!-- Prix soldé -->
                            <div class="form-group">
                                <label class="form-label">Prix soldé (€) <span style="color:var(--text-muted);font-weight:400">optionnel</span></label>
                                <input type="number" name="prix_solde" class="form-control" step="0.01" min="0" placeholder="29.99">
                                <span class="form-hint">Laissez vide si pas de solde</span>
                            </div>

                            <!-- Catégorie -->
                            <div class="form-group">
                                <label class="form-label">Catégorie <span class="req">*</span></label>
                                <select name="categorie" class="form-control" required>
                                    <option value="">-- Choisir --</option>
                                    <option>Femme</option>
                                    <option>Homme</option>
                                    <option>Enfant</option>
                                    <option>Bébé</option>
                                    <option>Sport</option>
                                    <option>Accessoires</option>
                                </select>
                            </div>

                            <!-- Sous-catégorie -->
                            <div class="form-group">
                                <label class="form-label">Sous-catégorie</label>
                                <select name="sous_categorie" class="form-control">
                                    <option value="">-- Choisir --</option>
                                    <option>Hauts / T-shirts</option>
                                    <option>Robes</option>
                                    <option>Pantalons / Jeans</option>
                                    <option>Vestes / Manteaux</option>
                                    <option>Shorts</option>
                                    <option>Pulls / Sweats</option>
                                    <option>Chaussures</option>
                                    <option>Sacs</option>
                                    <option>Bijoux</option>
                                    <option>Autre</option>
                                </select>
                            </div>

                            <!-- Marque -->
                            <div class="form-group">
                                <label class="form-label">Marque</label>
                                <input type="text" name="marque" class="form-control" placeholder="Ex : Zara, H&M, Nike…">
                            </div>

                            <!-- Matière -->
                            <div class="form-group">
                                <label class="form-label">Matière / Composition</label>
                                <input type="text" name="matiere" class="form-control" placeholder="Ex : 100% coton, polyester…">
                            </div>

                            <!-- Tailles -->
                            <div class="form-group">
                                <label class="form-label">Tailles disponibles</label>
                                <input type="text" name="tailles" class="form-control" placeholder="XS, S, M, L, XL, XXL">
                                <span class="form-hint">Séparez par des virgules</span>
                            </div>

                            <!-- Couleurs -->
                            <div class="form-group">
                                <label class="form-label">Couleurs disponibles</label>
                                <input type="text" name="couleurs" class="form-control" placeholder="Noir, Blanc, Rouge…">
                                <span class="form-hint">Séparez par des virgules</span>
                            </div>

                            <!-- Stock -->
                            <div class="form-group">
                                <label class="form-label">Stock <span class="req">*</span></label>
                                <input type="number" name="stock" class="form-control" min="0" value="0" required>
                            </div>

                            <!-- Image principale -->
                            <div class="form-group span-2">
                                <label class="form-label">Image principale</label>
                                <input type="file" name="image_principale" class="form-control" accept="image/*"
                                    onchange="previewImg(this, 'previewAdd')">
                                <img id="previewAdd" class="img-preview" alt="Aperçu">
                            </div>

                            <!-- Checkboxes -->
                            <div class="form-group span-2" style="display:flex;gap:30px;flex-wrap:wrap">
                                <div class="checkbox-group">
                                    <input type="checkbox" name="nouveaute" id="nouveaute_add">
                                    <label for="nouveaute_add"> Nouveauté</label>
                                </div>
                                <div class="checkbox-group">
                                    <input type="checkbox" name="en_vedette" id="vedette_add">
                                    <label for="vedette_add"> Mettre en vedette</label>
                                </div>
                            </div>
                        </div>

                        <div style="margin-top:28px;display:flex;gap:12px">
                            <button type="submit" class="btn btn-primary"> Ajouter l'article</button>
                            <a href="?tab=articles" class="btn btn-outline">Annuler</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

    </div><!-- /content -->
</main>

<!-- ═══════════════════════ MODAL MODIFIER ARTICLE ═══════════════════════ -->
<div class="modal-overlay" id="editModal">
    <div class="modal">
        <div class="modal-header">
            <h3> Modifier l'article</h3>
            <button class="modal-close" onclick="closeEditModal()">✕</button>
        </div>
        <form method="POST" enctype="multipart/form-data">
            <div class="modal-body">
                <input type="hidden" name="action"     value="edit_article">
                <input type="hidden" name="article_id" id="edit_id">
                <div class="form-grid">
                    <div class="form-group span-2">
                        <label class="form-label">Nom <span class="req">*</span></label>
                        <input type="text" name="nom" id="edit_nom" class="form-control" required>
                    </div>
                    <div class="form-group span-2">
                        <label class="form-label">Description <span class="req">*</span></label>
                        <textarea name="description" id="edit_description" class="form-control"></textarea>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Prix (€) <span class="req">*</span></label>
                        <input type="number" name="prix" id="edit_prix" class="form-control" step="0.01" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Prix soldé (€)</label>
                        <input type="number" name="prix_solde" id="edit_prix_solde" class="form-control" step="0.01">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Catégorie</label>
                        <select name="categorie" id="edit_categorie" class="form-control">
                            <option>Femme</option><option>Homme</option><option>Enfant</option>
                            <option>Bébé</option><option>Sport</option><option>Accessoires</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Sous-catégorie</label>
                        <select name="sous_categorie" id="edit_sous_categorie" class="form-control">
                            <option value="">--</option>
                            <option>Hauts / T-shirts</option><option>Robes</option>
                            <option>Pantalons / Jeans</option><option>Vestes / Manteaux</option>
                            <option>Shorts</option><option>Pulls / Sweats</option>
                            <option>Chaussures</option><option>Sacs</option><option>Bijoux</option><option>Autre</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Marque</label>
                        <input type="text" name="marque" id="edit_marque" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Matière</label>
                        <input type="text" name="matiere" id="edit_matiere" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Tailles</label>
                        <input type="text" name="tailles" id="edit_tailles" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Couleurs</label>
                        <input type="text" name="couleurs" id="edit_couleurs" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Stock</label>
                        <input type="number" name="stock" id="edit_stock" class="form-control" min="0">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Nouvelle image</label>
                        <input type="file" name="image_principale" class="form-control" accept="image/*"
                            onchange="previewImg(this, 'previewEdit')">
                        <img id="previewEdit" class="img-preview" alt="">
                    </div>
                    <div class="form-group span-2" style="display:flex;gap:24px;flex-wrap:wrap">
                        <div class="checkbox-group">
                            <input type="checkbox" name="nouveaute"      id="edit_nouveaute">
                            <label for="edit_nouveaute">Nouveauté</label>
                        </div>
                        <div class="checkbox-group">
                            <input type="checkbox" name="en_vedette"     id="edit_vedette">
                            <label for="edit_vedette">Vedette</label>
                        </div>
                        <div class="checkbox-group">
                            <input type="checkbox" name="actif_article"  id="edit_actif">
                            <label for="edit_actif">🟢 En ligne</label>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeEditModal()">Annuler</button>
                <button type="submit" class="btn btn-primary"> Enregistrer</button>
            </div>
        </form>
    </div>
</div>

<script>
// Live date
function updateDate() {
    const el = document.getElementById('live-date');
    if (el) {
        el.textContent = new Date().toLocaleString('fr-FR', {
            weekday:'long', day:'numeric', month:'long',
            year:'numeric', hour:'2-digit', minute:'2-digit'
        });
    }
}
updateDate(); setInterval(updateDate, 1000);

// Filter table
function filterTable(tableId, query) {
    const q = query.toLowerCase();
    const rows = document.getElementById(tableId)?.querySelectorAll('tbody tr') ?? [];
    rows.forEach(row => {
        row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
}

// Image preview
function previewImg(input, previewId) {
    const preview = document.getElementById(previewId);
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = e => { preview.src = e.target.result; preview.style.display = 'block'; };
        reader.readAsDataURL(input.files[0]);
    }
}

// Edit modal
function openEditModal(article) {
    document.getElementById('edit_id').value          = article.id;
    document.getElementById('edit_nom').value         = article.nom;
    document.getElementById('edit_description').value = article.description;
    document.getElementById('edit_prix').value        = article.prix;
    document.getElementById('edit_prix_solde').value  = article.prix_solde ?? '';
    document.getElementById('edit_marque').value      = article.marque ?? '';
    document.getElementById('edit_matiere').value     = article.matiere ?? '';
    document.getElementById('edit_tailles').value     = article.tailles ?? '';
    document.getElementById('edit_couleurs').value    = article.couleurs ?? '';
    document.getElementById('edit_stock').value       = article.stock;
    document.getElementById('edit_nouveaute').checked = article.nouveaute == 1;
    document.getElementById('edit_vedette').checked   = article.en_vedette == 1;
    document.getElementById('edit_actif').checked     = article.actif == 1;

    // Catégorie
    const catSel = document.getElementById('edit_categorie');
    for (let opt of catSel.options) if (opt.value === article.categorie) catSel.value = article.categorie;

    // Sous-catégorie
    const sCatSel = document.getElementById('edit_sous_categorie');
    for (let opt of sCatSel.options) if (opt.value === article.sous_categorie) sCatSel.value = article.sous_categorie;

    // Image preview
    const preview = document.getElementById('previewEdit');
    if (article.image_principale) {
        preview.src = article.image_principale;
        preview.style.display = 'block';
    } else {
        preview.style.display = 'none';
    }

    document.getElementById('editModal').classList.add('open');
}

function closeEditModal() {
    document.getElementById('editModal').classList.remove('open');
}

// Close modal on overlay click
document.getElementById('editModal').addEventListener('click', function(e) {
    if (e.target === this) closeEditModal();
});
</script>
</body>
</html>