<?php
session_start();
require_once 'config.php';

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $login = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (isset($pdo)) {
        $stmt = $pdo->prepare("SELECT * FROM admins WHERE username = :login");
        $stmt->execute([':login' => $login]);
        $admin = $stmt->fetch();

        if ($admin && password_verify($password, $admin['password_hash'])) {
            $_SESSION['user_id'] = $admin['id'];
            $_SESSION['username'] = $admin['username'];
            $_SESSION['nom_complet'] = $admin['nom_complet'] ?? $admin['username'];
            $_SESSION['role'] = $admin['role'];
            
            switch ($admin['role']) {
                case 'prefet': header("Location: dashboard_prefet.php"); break;
                case 'secretariat': header("Location: dashboard_secretariat.php"); break;
                case 'enseignant': header("Location: teacher_dashboard.php"); break;
                default: header("Location: admin.php");
            }
            exit();
        } else {
            $error = "Identifiants administrateur incorrects.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion Administration | École Belle Vue</title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?= time() ?>">
    <script src="https://unpkg.com/feather-icons"></script>
    <style>
        .admin-login-body {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
        }
        .login-card {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            padding: 40px;
            border-radius: 20px;
            width: 100%;
            max-width: 400px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        }
        .form-control {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: white;
        }
        .form-control:focus {
            background: rgba(255, 255, 255, 0.15);
            border-color: var(--secondary-blue);
            color: white;
        }
        .btn-admin {
            background: var(--secondary-blue);
            color: white;
            width: 100%;
            padding: 12px;
            border-radius: 8px;
            font-weight: bold;
            margin-top: 20px;
            transition: all 0.3s;
        }
        .btn-admin:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(30, 96, 208, 0.3);
        }
    </style>
</head>
<body class="admin-login-body">

    <div class="login-card">
        <div style="text-align: center; margin-bottom: 30px;">
            <img src="assets/img/logo.webp" alt="Logo" style="height: 60px; margin-bottom: 15px;">
            <h2 style="font-size: 24px;">Espace <span style="color: var(--secondary-blue);">Administration</span></h2>
            <p style="color: #94a3b8; font-size: 14px;">Accès réservé au Préfet et au Staff</p>
        </div>

        <?php if($error): ?>
            <div style="background: rgba(239, 68, 68, 0.2); color: #fca5a5; padding: 12px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; text-align: center; border: 1px solid rgba(239, 68, 68, 0.3);">
                <?= $error ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label style="display: block; margin-bottom: 8px; font-size: 13px; color: #cbd5e1;">Nom d'utilisateur</label>
                <div style="position: relative;">
                    <i data-feather="user" style="position: absolute; left: 12px; top: 12px; width: 16px; color: #94a3b8;"></i>
                    <input type="text" name="username" class="form-control" style="padding-left: 40px;" required placeholder="Ex: prefet">
                </div>
            </div>
            <div class="form-group" style="margin-top: 20px;">
                <label style="display: block; margin-bottom: 8px; font-size: 13px; color: #cbd5e1;">Mot de passe</label>
                <div style="position: relative;">
                    <i data-feather="lock" style="position: absolute; left: 12px; top: 12px; width: 16px; color: #94a3b8;"></i>
                    <input type="password" name="password" class="form-control" style="padding-left: 40px;" required placeholder="••••••••">
                </div>
            </div>
            
            <button type="submit" class="btn-admin">Se connecter</button>
        </form>

        <div style="text-align: center; margin-top: 25px;">
            <a href="index.php" style="color: #94a3b8; text-decoration: none; font-size: 13px;">← Retour au site</a>
        </div>
    </div>

    <script>feather.replace();</script>
</body>
</html>

