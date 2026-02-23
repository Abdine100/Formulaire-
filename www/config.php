<?php
// Configuration de la base de données pour Docker
define('DB_HOST', 'mysql-db');
define('DB_NAME', 'inscription');
define('DB_USER', 'root');
define('DB_PASS', 'root');
define('BCRYPT_COST', 12);

function getDB() {
    try {
        // 1. Connexion SANS préciser la base de données
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";charset=utf8mb4",
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]
        );

        // 2. Créer la base de données si elle n'existe pas
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

        // 3. Sélectionner la base de données
        $pdo->exec("USE `" . DB_NAME . "`");

        // 4. Créer les tables si elles n'existent pas
        initDatabase($pdo);

        return $pdo;

    } catch (PDOException $e) {
        die("Erreur de connexion à la base de données : " . $e->getMessage());
    }
}

function initDatabase($pdo) {
    // Table utilisateurs (correspond exactement à ton register.php)
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
            date_inscription DATETIME NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // Tu peux ajouter d'autres tables ici si ton projet en a d'autres
    // $pdo->exec("CREATE TABLE IF NOT EXISTS ...")
}

function hashPasswordBcrypt($password) {
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
}

function verifyPasswordBcrypt($password, $hash) {
    return password_verify($password, $hash);
}
?>