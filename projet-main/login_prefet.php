<?php
session_start();
require_once 'config.php';

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $login = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (isset($pdo)) {
        // On cherche spécifiquement un admin avec le rôle 'prefet'
        $stmt = $pdo->prepare("SELECT * FROM admins WHERE username = :login AND role = 'prefet'");
        $stmt->execute([':login' => $login]);
        $admin = $stmt->fetch();

        if ($admin && password_verify($password, $admin['password_hash'])) {
            if ($admin['statut'] !== 'Actif') {
                $error = "Votre compte est en attente de validation ou a été désactivé.";
            } else {
                $_SESSION['user_id'] = $admin['id'];
                $_SESSION['username'] = $admin['username'];
                $_SESSION['nom_complet'] = $admin['nom_complet'] ?? $admin['username'];
                $_SESSION['role'] = 'prefet';
                
                header("Location: dashboard_prefet.php");
                exit();
            }
        } else {
            $error = "Identifiants Préfet incorrects ou accès refusé.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Espace Préfet | École Belle Vue</title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?= time() ?>">
    <script src="https://unpkg.com/feather-icons"></script>
    <style>
        .prefet-body {
            background: #020617;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-family: 'Outfit', sans-serif;
        }
        .login-container {
            width: 100%;
            max-width: 420px;
            padding: 20px;
        }
        .prefet-card {
            background: rgba(30, 41, 59, 0.7);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            padding: 50px 40px;
            border-radius: 32px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            text-align: center;
        }
        .gold-border {
            width: 80px;
            height: 80px;
            border: 2px solid #fbbf24;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 25px;
            color: #fbbf24;
            box-shadow: 0 0 20px rgba(251, 191, 36, 0.2);
        }
        .form-input {
            width: 100%;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            padding: 15px 20px;
            border-radius: 12px;
            color: white;
            margin-bottom: 20px;
            transition: all 0.3s;
        }
        .form-input:focus {
            outline: none;
            border-color: #fbbf24;
            background: rgba(255, 255, 255, 0.1);
        }
        .btn-prefet {
            width: 100%;
            background: #fbbf24;
            color: #020617;
            padding: 15px;
            border-radius: 12px;
            font-weight: 700;
            border: none;
            cursor: pointer;
            text-transform: uppercase;
            letter-spacing: 1px;
            transition: all 0.3s;
        }
        .btn-prefet:hover {
            background: #f59e0b;
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(251, 191, 36, 0.3);
        }
    </style>
</head>
<body class="prefet-body">

    <div class="login-container">
        <div class="prefet-card">
            <div class="gold-border">
                <i data-feather="shield" style="width: 40px; height: 40px;"></i>
            </div>
            <h1 style="font-size: 24px; margin-bottom: 10px; font-weight: 600;">Direction Générale</h1>
            <p style="color: #94a3b8; font-size: 14px; margin-bottom: 40px;">Authentification sécurisée du Préfet</p>

            <?php if($error): ?>
                <div style="background: rgba(239, 68, 68, 0.1); color: #fca5a5; padding: 12px; border-radius: 12px; margin-bottom: 25px; font-size: 13px; border: 1px solid rgba(239, 68, 68, 0.2);">
                    <?= $error ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <input type="text" name="username" class="form-input" required placeholder="Nom d'utilisateur">
                <input type="password" name="password" class="form-input" required placeholder="Mot de passe confidentiel">
                
                <button type="submit" class="btn-prefet">Accéder au contrôle</button>
            </form>

            <div style="margin-top: 30px;">
                <a href="index.php" style="color: #64748b; text-decoration: none; font-size: 12px;">← Retour au portail public</a>
            </div>
        </div>
    </div>

    <script>feather.replace();</script>
</body>
</html>

