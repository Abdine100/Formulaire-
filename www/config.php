<?php
// Configuration de la base de données pour Docker
define('DB_HOST', 'mysql-db');  // Nom du service MySQL dans Docker
define('DB_NAME', 'inscription');  // Nom de votre base de données
define('DB_USER', 'root');  // Utilisateur MySQL (par défaut root dans Docker)
define('DB_PASS', 'root');  // Mot de passe MySQL défini dans docker-compose

// Configuration pour bcrypt
define('BCRYPT_COST', 12);  // Coût de hashage (entre 10 et 12 recommandé)

// Fonction pour obtenir la connexion PDO
function getDB() {
    try {
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]
        );
        return $pdo;
    } catch (PDOException $e) {
        die("Erreur de connexion à la base de données : " . $e->getMessage());
    }
}

// Fonction pour hasher un mot de passe avec bcrypt
function hashPasswordBcrypt($password) {
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
}

// Fonction pour vérifier un mot de passe avec bcrypt
function verifyPasswordBcrypt($password, $hash) {
    return password_verify($password, $hash);
}
?>