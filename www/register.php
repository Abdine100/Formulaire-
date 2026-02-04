<?php
session_start();
require_once 'config.php';

$error = '';
$success = '';

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom = trim($_POST['nom'] ?? '');
    $prenom = trim($_POST['prenom'] ?? '');
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $pass = $_POST['password'] ?? '';
    $pass_confirm = $_POST['password_confirm'] ?? '';
    $date_naissance = $_POST['date_naissance'] ?? '';
    $telephone = trim($_POST['telephone'] ?? '');
    $adresse = trim($_POST['adresse'] ?? '');
    $code_postal = trim($_POST['code_postal'] ?? '');
    $ville = trim($_POST['ville'] ?? '');
    $pays = trim($_POST['pays'] ?? '');
    $genre = $_POST['genre'] ?? '';
    
    // Validation des champs
    if (empty($nom) || empty($prenom) || empty($email) || empty($pass) || empty($pass_confirm) 
        || empty($date_naissance) || empty($telephone) || empty($adresse) 
        || empty($code_postal) || empty($ville) || empty($pays)) {
        $error = 'Veuillez remplir tous les champs obligatoires.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Adresse email invalide.';
    } elseif (strlen($pass) < 8) {
        $error = 'Le mot de passe doit contenir au moins 8 caractères.';
    } elseif ($pass !== $pass_confirm) {
        $error = 'Les mots de passe ne correspondent pas.';
    } elseif (!preg_match('/^[0-9]{10}$/', str_replace([' ', '.', '-'], '', $telephone))) {
        $error = 'Numéro de téléphone invalide (10 chiffres requis).';
    } else {
        try {
            $pdo = getDB();
            
            // Vérifier si l'email existe déjà
            $stmt = $pdo->prepare('SELECT id FROM utilisateurs WHERE email = ?');
            $stmt->execute([$email]);
            
            if ($stmt->fetch()) {
                $error = 'Cette adresse email est déjà utilisée.';
            } else {
                // Hasher le mot de passe avec bcrypt
                $hashed_password = hashPasswordBcrypt($pass);
                
                // Insérer le nouvel utilisateur
                $stmt = $pdo->prepare('
                    INSERT INTO utilisateurs 
                    (nom, prenom, email, password, date_naissance, telephone, adresse, code_postal, ville, pays, genre, date_inscription) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ');
                
                $result = $stmt->execute([
                    $nom, 
                    $prenom, 
                    $email, 
                    $hashed_password, 
                    $date_naissance, 
                    $telephone, 
                    $adresse, 
                    $code_postal, 
                    $ville, 
                    $pays, 
                    $genre
                ]);
                
                if ($result) {
                    $success = 'Inscription réussie ! Vous pouvez maintenant vous connecter.';
                    // Redirection vers la page de connexion après 2 secondes
                    header("Refresh: 2; url=connexion.php");
                }
            }
        } catch (PDOException $e) {
            $error = 'Erreur lors de l\'inscription : ' . $e->getMessage();
        }
    }
}

// Liste des pays
$pays_liste = [
    'France', 'Belgique', 'Suisse', 'Canada', 'Luxembourg', 'Monaco',
    'Algérie', 'Maroc', 'Tunisie', 'Sénégal', 'Côte d\'Ivoire', 'Cameroun',
    'Madagascar', 'Mali', 'Burkina Faso', 'Niger', 'Tchad', 'Congo',
    'Allemagne', 'Espagne', 'Italie', 'Portugal', 'Royaume-Uni', 'États-Unis'
];
sort($pays_liste);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscription</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: flex-start;
            padding: 20px;
            position: relative;
            overflow-x: hidden;
        }
        
        body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: 
                radial-gradient(circle at 20% 50%, rgba(255, 255, 255, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 80% 80%, rgba(255, 255, 255, 0.1) 0%, transparent 50%);
            pointer-events: none;
        }
        
        .register-container {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(10px);
            padding: 30px 35px;
            border-radius: 20px;
            box-shadow: 
                0 20px 60px rgba(0, 0, 0, 0.3),
                0 0 0 1px rgba(255, 255, 255, 0.1);
            width: 100%;
            max-width: 900px;
            margin: 10px auto;
            position: relative;
            animation: fadeInUp 0.6s ease-out;
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .header-section {
            text-align: center;
            margin-bottom: 25px;
        }
        
        .logo {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 14px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 12px;
            box-shadow: 0 8px 16px rgba(102, 126, 234, 0.4);
        }
        
        .logo svg {
            width: 28px;
            height: 28px;
            fill: white;
        }
        
        h2 {
            color: #1a1a2e;
            margin-bottom: 6px;
            font-size: 26px;
            font-weight: 700;
            letter-spacing: -0.5px;
        }
        
        .subtitle {
            color: #6b7280;
            font-size: 14px;
            font-weight: 400;
        }
        
        .required {
            color: #ef4444;
            font-weight: 600;
        }
        
        .section-title {
            color: #1f2937;
            font-size: 16px;
            font-weight: 600;
            margin: 25px 0 15px 0;
            padding-left: 14px;
            position: relative;
            letter-spacing: -0.3px;
        }
        
        .section-title::before {
            content: '';
            position: absolute;
            left: 0;
            top: 50%;
            transform: translateY(-50%);
            width: 3px;
            height: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 2px;
        }
        
        .section-title:first-of-type {
            margin-top: 0;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
            margin-bottom: 0;
        }
        
        .form-group {
            margin-bottom: 18px;
            flex: 1;
        }
        
        label {
            display: block;
            margin-bottom: 7px;
            color: #374151;
            font-weight: 500;
            font-size: 13px;
            letter-spacing: 0.2px;
        }
        
        input[type="text"],
        input[type="email"],
        input[type="password"],
        input[type="date"],
        input[type="tel"],
        select,
        textarea {
            width: 100%;
            padding: 11px 14px;
            border: 2px solid #e5e7eb;
            border-radius: 10px;
            font-size: 14px;
            transition: all 0.3s ease;
            font-family: 'Inter', sans-serif;
            background: #ffffff;
            color: #1f2937;
        }
        
        input[type="text"]:focus,
        input[type="email"]:focus,
        input[type="password"]:focus,
        input[type="date"]:focus,
        input[type="tel"]:focus,
        select:focus,
        textarea:focus {
            outline: none;
            border-color: #667eea;
            background: #f9fafb;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
        }
        
        input::placeholder,
        textarea::placeholder {
            color: #9ca3af;
        }
        
        select {
            cursor: pointer;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='24' height='24' viewBox='0 0 24 24' fill='none' stroke='%23667eea' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 12px center;
            background-size: 20px;
            padding-right: 45px;
        }
        
        .radio-group {
            display: flex;
            gap: 16px;
            margin-top: 10px;
        }
        
        .radio-option {
            flex: 1;
            position: relative;
        }
        
        .radio-option input[type="radio"] {
            position: absolute;
            opacity: 0;
            cursor: pointer;
        }
        
        .radio-option label {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 10px 16px;
            border: 2px solid #e5e7eb;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s ease;
            background: white;
            font-weight: 500;
            color: #6b7280;
            font-size: 13px;
        }
        
        .radio-option input[type="radio"]:checked + label {
            border-color: #667eea;
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
            color: #667eea;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
        }
        
        .radio-option label:hover {
            border-color: #c7d2fe;
            background: #f9fafb;
        }
        
        .password-requirements {
            font-size: 12px;
            color: #6b7280;
            margin-top: 6px;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .password-requirements::before {
            content: 'ℹ';
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 16px;
            height: 16px;
            background: #e0e7ff;
            color: #667eea;
            border-radius: 50%;
            font-size: 11px;
            font-weight: 600;
        }
        
        button {
            width: 100%;
            padding: 13px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 20px;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
            letter-spacing: 0.3px;
        }
        
        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.5);
        }
        
        button:active {
            transform: translateY(0);
        }
        
        .alert {
            padding: 14px 18px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 13px;
            font-weight: 500;
            animation: slideDown 0.4s ease-out;
        }
        
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .alert-error {
            background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%);
            color: #991b1b;
            border: 2px solid #fecaca;
        }
        
        .alert-error::before {
            content: '⚠';
            font-size: 20px;
        }
        
        .alert-success {
            background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
            color: #166534;
            border: 2px solid #bbf7d0;
        }
        
        .alert-success::before {
            content: '✓';
            font-size: 20px;
        }
        
        .links {
            margin-top: 20px;
            text-align: center;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
            color: #6b7280;
            font-size: 14px;
        }
        
        .links a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.2s ease;
        }
        
        .links a:hover {
            color: #764ba2;
            text-decoration: underline;
        }
        
        textarea {
            resize: vertical;
            min-height: 70px;
            line-height: 1.5;
        }
        
        @media (max-width: 640px) {
            .register-container {
                padding: 25px 20px;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
            
            h2 {
                font-size: 22px;
            }
            
            .radio-group {
                flex-direction: column;
            }
        }
        
        @media (min-width: 641px) and (max-width: 900px) {
            .form-row {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (min-width: 901px) {
            .form-row {
                grid-template-columns: repeat(3, 1fr);
            }
            
            .form-row.two-cols {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        input:valid:not(:placeholder-shown),
        select:valid,
        textarea:valid:not(:placeholder-shown) {
            border-color: #10b981;
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="header-section">
            <div class="logo">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 3c1.66 0 3 1.34 3 3s-1.34 3-3 3-3-1.34-3-3 1.34-3 3-3zm0 14.2c-2.5 0-4.71-1.28-6-3.22.03-1.99 4-3.08 6-3.08 1.99 0 5.97 1.09 6 3.08-1.29 1.94-3.5 3.22-6 3.22z"/>
                </svg>
            </div>
            <h2>Créer un compte</h2>
            <p class="subtitle">Les champs marqués d'un <span class="required">*</span> sont obligatoires</p>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="section-title">Informations personnelles</div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="nom">Nom <span class="required">*</span></label>
                    <input type="text" id="nom" name="nom" value="<?php echo htmlspecialchars($_POST['nom'] ?? ''); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="prenom">Prénom <span class="required">*</span></label>
                    <input type="text" id="prenom" name="prenom" value="<?php echo htmlspecialchars($_POST['prenom'] ?? ''); ?>" required>
                </div>
            
                <div class="form-group">
                    <label for="date_naissance">Date de naissance <span class="required">*</span></label>
                    <input type="date" id="date_naissance" name="date_naissance" value="<?php echo htmlspecialchars($_POST['date_naissance'] ?? ''); ?>" max="<?php echo date('Y-m-d'); ?>" required>
                </div>
            </div>
            
            <div class="form-group">
                <label>Genre</label>
                <div class="radio-group">
                    <div class="radio-option">
                        <input type="radio" id="homme" name="genre" value="homme" <?php echo (($_POST['genre'] ?? '') === 'homme') ? 'checked' : ''; ?>>
                        <label for="homme">Homme</label>
                    </div>
                    <div class="radio-option">
                        <input type="radio" id="femme" name="genre" value="femme" <?php echo (($_POST['genre'] ?? '') === 'femme') ? 'checked' : ''; ?>>
                        <label for="femme">Femme</label>
                    </div>
                    <div class="radio-option">
                        <input type="radio" id="autre" name="genre" value="autre" <?php echo (($_POST['genre'] ?? '') === 'autre') ? 'checked' : ''; ?>>
                        <label for="autre">Autre</label>
                    </div>
                </div>
            </div>
            
            <div class="section-title">Coordonnées</div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="email">Email <span class="required">*</span></label>
                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="telephone">Téléphone <span class="required">*</span></label>
                    <input type="tel" id="telephone" name="telephone" value="<?php echo htmlspecialchars($_POST['telephone'] ?? ''); ?>" placeholder="0612345678" required>
                </div>
            
                <div class="form-group">
                    <label for="pays">Pays <span class="required">*</span></label>
                    <select id="pays" name="pays" required>
                        <option value="">Sélectionnez un pays</option>
                        <?php foreach ($pays_liste as $p): ?>
                            <option value="<?php echo htmlspecialchars($p); ?>" <?php echo (($_POST['pays'] ?? '') === $p) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($p); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="section-title">Adresse</div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="adresse">Adresse complète <span class="required">*</span></label>
                    <textarea id="adresse" name="adresse" placeholder="Numéro et nom de rue" required><?php echo htmlspecialchars($_POST['adresse'] ?? ''); ?></textarea>
                </div>
            
                <div class="form-group">
                    <label for="code_postal">Code postal <span class="required">*</span></label>
                    <input type="text" id="code_postal" name="code_postal" value="<?php echo htmlspecialchars($_POST['code_postal'] ?? ''); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="ville">Ville <span class="required">*</span></label>
                    <input type="text" id="ville" name="ville" value="<?php echo htmlspecialchars($_POST['ville'] ?? ''); ?>" required>
                </div>
            </div>
            
            <div class="section-title">Sécurité</div>
            
            <div class="form-row two-cols">
                <div class="form-group">
                    <label for="password">Mot de passe <span class="required">*</span></label>
                    <input type="password" id="password" name="password" required>
                    <div class="password-requirements">Minimum 8 caractères recommandés</div>
                </div>
                
                <div class="form-group">
                    <label for="password_confirm">Confirmer le mot de passe <span class="required">*</span></label>
                    <input type="password" id="password_confirm" name="password_confirm" required>
                </div>
            </div>
            
            <button type="submit">S'inscrire</button>
        </form>
        
        <div class="links">
            Vous avez déjà un compte ? <a href="login.php">Se connecter</a>
        </div>
    </div>
</body>
</html>