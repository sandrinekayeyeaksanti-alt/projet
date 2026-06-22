<?php
session_start();
require_once 'config.php';
$error = "";
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $login = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    if (isset($pdo)) {
        $stmt = $pdo->prepare("SELECT * FROM admins WHERE username = :login AND role = 'secretariat'");
        $stmt->execute([':login' => $login]);
        $admin = $stmt->fetch();
        if ($admin && password_verify($password, $admin['password_hash'])) {
            if ($admin['statut'] !== 'Actif') {
                $error = "Accès refusé. Votre compte est en attente de validation par le Préfet.";
            } else {
                $_SESSION['user_id'] = $admin['id'];
                $_SESSION['username'] = $admin['username'];
                $_SESSION['nom_complet'] = $admin['nom_complet'] ?? $admin['username'];
                $_SESSION['role'] = 'secretariat';
                header("Location: dashboard_secretariat.php");
                exit();
            }
        } else { $error = "Identifiants Secrétariat incorrects."; }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Connexion Secrétariat | École Belle Vue</title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?= time() ?>">
    <script src="https://unpkg.com/feather-icons"></script>
    <style>
        body { background: #f1f5f9; height: 100vh; display: flex; align-items: center; justify-content: center; font-family: 'Outfit'; }
        .login-card { background: white; padding: 40px; border-radius: 20px; width: 400px; box-shadow: 0 10px 25px rgba(0,0,0,0.05); border-top: 5px solid #3b82f6; }
    </style>
</head>
<body>
    <div class="login-card">
        <div style="text-align: center; margin-bottom: 30px;">
            <i data-feather="briefcase" style="width: 48px; height: 48px; color: #3b82f6; margin-bottom: 10px;"></i>
            <h2>Espace <span style="color: #3b82f6;">Secrétariat</span></h2>
        </div>
        <?php if($error): ?><div style="color:red; margin-bottom:15px; font-size:14px;"><?= $error ?></div><?php endif; ?>
        <form method="POST">
            <div class="form-group"><label>Utilisateur</label><input type="text" name="username" class="form-control" required></div>
            <div class="form-group" style="margin-top:15px;"><label>Mot de passe</label><input type="password" name="password" class="form-control" required></div>
            <button type="submit" class="btn btn-primary" style="width:100%; margin-top:25px;">Accéder à la gestion</button>
        </form>
        <div style="text-align: center; margin-top: 20px;"><a href="index.php" style="font-size:12px; color:#64748b;">Retour à l'accueil</a></div>
    </div>
    <script>feather.replace();</script>
</body>
</html>

