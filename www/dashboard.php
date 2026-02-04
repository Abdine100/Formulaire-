<?php
session_start();
require_once 'config.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header('Location: connexion.php');
    exit;
}

// Récupérer les informations complètes de l'utilisateur
try {
    $pdo = getDB();
    $stmt = $pdo->prepare('SELECT * FROM utilisateurs WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    
    if (!$user) {
        session_destroy();
        header('Location: connexion.php');
        exit;
    }
} catch (PDOException $e) {
    die('Erreur de connexion à la base de données.');
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de bord</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background: #f3f4f6;
            min-height: 100vh;
        }
        
        .navbar {
            background: white;
            padding: 20px 0;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }
        
        .navbar-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .logo-section {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .logo {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .logo svg {
            width: 22px;
            height: 22px;
            fill: white;
        }
        
        .logo-text {
            font-size: 20px;
            font-weight: 700;
            color: #1a1a2e;
        }
        
        .user-section {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .user-info {
            text-align: right;
        }
        
        .user-name {
            font-weight: 600;
            color: #1f2937;
            font-size: 14px;
        }
        
        .user-email {
            font-size: 12px;
            color: #6b7280;
        }
        
        .logout-btn {
            padding: 10px 20px;
            background: #ef4444;
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            font-size: 14px;
        }
        
        .logout-btn:hover {
            background: #dc2626;
            transform: translateY(-2px);
        }
        
        .container {
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 20px;
        }
        
        .welcome-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 40px;
            border-radius: 20px;
            color: white;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
        }
        
        .welcome-section h1 {
            font-size: 32px;
            margin-bottom: 10px;
        }
        
        .welcome-section p {
            font-size: 16px;
            opacity: 0.9;
        }
        
        .cards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }
        
        .card {
            background: white;
            padding: 30px;
            border-radius: 16px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
        }
        
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.12);
        }
        
        .card-icon {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 20px;
        }
        
        .card-icon svg {
            width: 26px;
            height: 26px;
            fill: white;
        }
        
        .card h3 {
            color: #1f2937;
            font-size: 18px;
            margin-bottom: 10px;
        }
        
        .card p {
            color: #6b7280;
            font-size: 14px;
            line-height: 1.6;
        }
        
        .info-section {
            background: white;
            padding: 35px;
            border-radius: 16px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
        }
        
        .info-section h2 {
            color: #1f2937;
            margin-bottom: 25px;
            font-size: 24px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f3f4f6;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }
        
        .info-item {
            padding: 15px;
            background: #f9fafb;
            border-radius: 10px;
            border-left: 3px solid #667eea;
        }
        
        .info-label {
            font-size: 12px;
            font-weight: 600;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 6px;
        }
        
        .info-value {
            font-size: 15px;
            color: #1f2937;
            font-weight: 500;
        }
        
        @media (max-width: 768px) {
            .navbar-content {
                flex-direction: column;
                gap: 20px;
            }
            
            .user-section {
                flex-direction: column;
                width: 100%;
            }
            
            .logout-btn {
                width: 100%;
            }
            
            .welcome-section h1 {
                font-size: 24px;
            }
            
            .cards-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="navbar-content">
            <div class="logo-section">
                <div class="logo">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 3c1.66 0 3 1.34 3 3s-1.34 3-3 3-3-1.34-3-3 1.34-3 3-3zm0 14.2c-2.5 0-4.71-1.28-6-3.22.03-1.99 4-3.08 6-3.08 1.99 0 5.97 1.09 6 3.08-1.29 1.94-3.5 3.22-6 3.22z"/>
                    </svg>
                </div>
                <span class="logo-text">Mon Application</span>
            </div>
            
            <div class="user-section">
                <div class="user-info">
                    <div class="user-name"><?php echo htmlspecialchars($user['prenom'] . ' ' . $user['nom']); ?></div>
                    <div class="user-email"><?php echo htmlspecialchars($user['email']); ?></div>
                </div>
                <a href="logout.php" class="logout-btn">Déconnexion</a>
            </div>
        </div>
    </nav>
    
    <div class="container">
        <div class="welcome-section">
            <h1>Bienvenue, <?php echo htmlspecialchars($user['prenom']); ?> ! 👋</h1>
            <p>Ravi de vous revoir. Voici votre tableau de bord personnel.</p>
        </div>
        
        <div class="cards-grid">
            <div class="card">
                <div class="card-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 3c1.66 0 3 1.34 3 3s-1.34 3-3 3-3-1.34-3-3 1.34-3 3-3zm0 14.2c-2.5 0-4.71-1.28-6-3.22.03-1.99 4-3.08 6-3.08 1.99 0 5.97 1.09 6 3.08-1.29 1.94-3.5 3.22-6 3.22z"/>
                    </svg>
                </div>
                <h3>Profil complet</h3>
                <p>Votre profil a été créé avec succès. Toutes vos informations sont sécurisées.</p>
            </div>
            
            <div class="card">
                <div class="card-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                    </svg>
                </div>
                <h3>Compte vérifié</h3>
                <p>Votre inscription a été validée. Vous avez accès à toutes les fonctionnalités.</p>
            </div>
            
            <div class="card">
                <div class="card-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/>
                    </svg>
                </div>
                <h3>Sécurité</h3>
                <p>Votre mot de passe est crypté et vos données sont protégées.</p>
            </div>
        </div>
        
        <div class="info-section">
            <h2>📋 Vos informations</h2>
            <div class="info-grid">
                <div class="info-item">
                    <div class="info-label">Nom complet</div>
                    <div class="info-value"><?php echo htmlspecialchars($user['prenom'] . ' ' . $user['nom']); ?></div>
                </div>
                
                <div class="info-item">
                    <div class="info-label">Email</div>
                    <div class="info-value"><?php echo htmlspecialchars($user['email']); ?></div>
                </div>
                
                <div class="info-item">
                    <div class="info-label">Date de naissance</div>
                    <div class="info-value"><?php echo date('d/m/Y', strtotime($user['date_naissance'])); ?></div>
                </div>
                
                <div class="info-item">
                    <div class="info-label">Genre</div>
                    <div class="info-value"><?php echo ucfirst(htmlspecialchars($user['genre'] ?? 'Non renseigné')); ?></div>
                </div>
                
                <div class="info-item">
                    <div class="info-label">Téléphone</div>
                    <div class="info-value"><?php echo htmlspecialchars($user['telephone']); ?></div>
                </div>
                
                <div class="info-item">
                    <div class="info-label">Pays</div>
                    <div class="info-value"><?php echo htmlspecialchars($user['pays']); ?></div>
                </div>
                
                <div class="info-item">
                    <div class="info-label">Ville</div>
                    <div class="info-value"><?php echo htmlspecialchars($user['ville'] . ', ' . $user['code_postal']); ?></div>
                </div>
                
                <div class="info-item">
                    <div class="info-label">Adresse</div>
                    <div class="info-value"><?php echo htmlspecialchars($user['adresse']); ?></div>
                </div>
                
                <div class="info-item">
                    <div class="info-label">Membre depuis</div>
                    <div class="info-value"><?php echo date('d/m/Y à H:i', strtotime($user['date_inscription'])); ?></div>
                </div>
                
                <div class="info-item">
                    <div class="info-label">Dernière connexion</div>
                    <div class="info-value">
                        <?php 
                        echo $user['derniere_connexion'] 
                            ? date('d/m/Y à H:i', strtotime($user['derniere_connexion'])) 
                            : 'Première connexion'; 
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>