<?php
/**
 * Script à exécuter UNE SEULE FOIS pour créer le compte admin.
 * Accédez à : http://votre-site/create_admin.php
 * Puis supprimez ce fichier après utilisation !
 */
require_once 'config.php';

$pdo = getDB();

$nom       = 'Admin';
$prenom    = 'Super';
$email     = 'admin@boutiquemode.fr';  // ← Changez cet email
$password  = 'Admin@1234';            // ← Changez ce mot de passe

// Vérifier si un admin existe déjà
$stmt = $pdo->prepare("SELECT id FROM utilisateurs WHERE email = ?");
$stmt->execute([$email]);
if ($stmt->fetch()) {
    die("Un compte avec cet email existe déjà.");
}

$hash = hashPasswordBcrypt($password);

$stmt = $pdo->prepare("
    INSERT INTO utilisateurs (nom, prenom, email, password, date_naissance, telephone, adresse, code_postal, ville, pays, genre, role, date_inscription)
    VALUES (?, ?, ?, ?, '1990-01-01', '0600000000', '1 rue Admin', '75001', 'Paris', 'France', 'autre', 'admin', NOW())
");
$stmt->execute([$nom, $prenom, $email, $hash]);

echo "Compte admin créé !<br>";
echo "Email : <strong>$email</strong><br>";
echo "Mot de passe : <strong>$password</strong><br><br>";
echo "<strong style='color:red'> SUPPRIMEZ CE FICHIER MAINTENANT !</strong>";
?>