<?php
define('DB_HOST', 'mysql-db');
define('DB_NAME', 'inscription');
define('DB_USER', 'root');
define('DB_PASS', 'root');
define('BCRYPT_COST', 12);

// Dossier de stockage des images (chemin absolu)
define('UPLOAD_DIR', __DIR__ . '/uploads/articles/');
// URL publique des images
define('UPLOAD_URL', 'uploads/articles/');

function getDB() {
    try {
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";charset=utf8mb4",
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false
            ]
        );

        $pdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE `" . DB_NAME . "`");
        initDatabase($pdo);

        return $pdo;

    } catch (PDOException $e) {
        die("Erreur de connexion à la base de données : " . $e->getMessage());
    }
}

function initDatabase($pdo) {
    // Table utilisateurs
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS utilisateurs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nom VARCHAR(100) NOT NULL,
            prenom VARCHAR(100) NOT NULL,
            email VARCHAR(150) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            date_naissance DATE NOT NULL,
            telephone VARCHAR(20) NOT NULL,
            adresse TEXT NOT NULL,
            code_postal VARCHAR(10) NOT NULL,
            ville VARCHAR(100) NOT NULL,
            pays VARCHAR(100) NOT NULL,
            genre ENUM('homme', 'femme', 'autre') DEFAULT NULL,
            role ENUM('user', 'admin') NOT NULL DEFAULT 'user',
            actif DATETIME NULL DEFAULT NULL,
            derniere_connexion DATETIME NULL DEFAULT NULL,
            date_inscription DATETIME NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    try { $pdo->exec("ALTER TABLE utilisateurs ADD COLUMN role ENUM('user','admin') NOT NULL DEFAULT 'user'"); } catch(Exception $e) {}
    try { $pdo->exec("ALTER TABLE utilisateurs ADD COLUMN derniere_connexion DATETIME NULL DEFAULT NULL"); } catch(Exception $e) {}

    // Table articles — image_principale stocke UNIQUEMENT le nom du fichier (ex: abc123.jpg)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS articles (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nom VARCHAR(200) NOT NULL,
            description TEXT NOT NULL,
            prix DECIMAL(10,2) NOT NULL,
            prix_solde DECIMAL(10,2) NULL DEFAULT NULL,
            categorie VARCHAR(100) NOT NULL,
            sous_categorie VARCHAR(100) DEFAULT NULL,
            marque VARCHAR(100) DEFAULT NULL,
            tailles TEXT DEFAULT NULL,
            couleurs TEXT DEFAULT NULL,
            matiere VARCHAR(200) DEFAULT NULL,
            stock INT NOT NULL DEFAULT 0,
            image_principale VARCHAR(255) DEFAULT NULL,
            images_supplementaires TEXT DEFAULT NULL,
            nouveaute TINYINT(1) NOT NULL DEFAULT 0,
            en_vedette TINYINT(1) NOT NULL DEFAULT 0,
            actif TINYINT(1) NOT NULL DEFAULT 1,
            date_ajout DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            date_modification DATETIME NULL ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // Migration : nettoyer les anciens chemins complets → garder uniquement le nom du fichier
    try {
        $rows = $pdo->query("SELECT id, image_principale FROM articles WHERE image_principale LIKE '%/%'")->fetchAll();
        foreach ($rows as $row) {
            $nomSeul = basename($row['image_principale']);
            $pdo->prepare("UPDATE articles SET image_principale = ? WHERE id = ?")->execute([$nomSeul, $row['id']]);
        }
    } catch(Exception $e) {}
}

// ── UPLOAD SÉCURISÉ ──────────────────────────────────────────────────────────
// Retourne le nom du fichier (ex: "a1b2c3.jpg") ou un message d'erreur
function uploadImage($file) {
    if (empty($file['name'])) return '';

    // Extensions et types MIME autorisés
    $allowedExt  = ['jpg', 'jpeg', 'png', 'webp'];
    $allowedMime = ['image/jpeg', 'image/png', 'image/webp'];

    $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $mime = mime_content_type($file['tmp_name']); // lecture réelle du contenu

    if (!in_array($ext, $allowedExt) || !in_array($mime, $allowedMime)) {
        return 'ERREUR: Type de fichier non autorisé (jpg, png, webp uniquement).';
    }

    // Taille max : 3 Mo
    if ($file['size'] > 3 * 1024 * 1024) {
        return 'ERREUR: Image trop lourde (max 3 Mo).';
    }

    // Nom aléatoire sécurisé — jamais le nom original de l'utilisateur
    $filename = bin2hex(random_bytes(16)) . '.' . $ext;

    // Créer le dossier si nécessaire
    if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0755, true);

    if (!move_uploaded_file($file['tmp_name'], UPLOAD_DIR . $filename)) {
        return 'ERREUR: Impossible de sauvegarder l\'image.';
    }

    // On retourne UNIQUEMENT le nom du fichier, pas le chemin
    return $filename;
}

// ── MOT DE PASSE ─────────────────────────────────────────────────────────────
function hashPasswordBcrypt($password) {
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
}

function verifyPasswordBcrypt($password, $hash) {
    return password_verify($password, $hash);
}

function isAdmin($pdo, $userId) {
    $stmt = $pdo->prepare('SELECT role FROM utilisateurs WHERE id = ?');
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    return $user && $user['role'] === 'admin';
}
?>