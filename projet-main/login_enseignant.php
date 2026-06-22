<?php
session_start();
require_once 'config.php';
$error = "";
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $login = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    if (isset($pdo)) {
        $stmt = $pdo->prepare("SELECT * FROM admins WHERE username = :login AND role = 'enseignant'");
        $stmt->execute([':login' => $login]);
        $admin = $stmt->fetch();
        if ($admin && password_verify($password, $admin['password_hash'])) {
            $_SESSION['user_id'] = $admin['id'];
            $_SESSION['username'] = $admin['username'];
            $_SESSION['nom_complet'] = $admin['nom_complet'] ?? $admin['username'];
            $_SESSION['role'] = 'enseignant';
            header("Location: teacher_dashboard.php");
            exit();
        } else { $error = "Identifiants Enseignant incorrects."; }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Connexion Enseignant | École Belle Vue</title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?= time() ?>">
    <script src="https://unpkg.com/feather-icons"></script>
    <style>
        body { background: #fdf2f8; height: 100vh; display: flex; align-items: center; justify-content: center; font-family: 'Outfit'; }
        .login-card { background: white; padding: 40px; border-radius: 20px; width: 400px; box-shadow: 0 10px 25px rgba(0,0,0,0.05); border-top: 5px solid #db2777; }
    </style>
</head>
<body>
    <div class="login-card">
        <div style="text-align: center; margin-bottom: 30px;">
            <i data-feather="edit-3" style="width: 48px; height: 48px; color: #db2777; margin-bottom: 10px;"></i>
            <h2>Espace <span style="color: #db2777;">Enseignant</span></h2>
        </div>
        <?php if($error): ?><div style="color:red; margin-bottom:15px; font-size:14px;"><?= $error ?></div><?php endif; ?>
        <form method="POST">
            <div class="form-group"><label>Utilisateur</label><input type="text" name="username" class="form-control" required></div>
            <div class="form-group" style="margin-top:15px;"><label>Mot de passe</label><input type="password" name="password" class="form-control" required></div>
            <button type="submit" class="btn btn-primary" style="width:100%; margin-top:25px; background:#db2777; border:none;">Accéder aux notes</button>
        </form>
        <div style="text-align: center; margin-top: 20px;"><a href="index.php" style="font-size:12px; color:#64748b;">Retour à l'accueil</a></div>
    </div>
    <script>feather.replace();</script>
</body>
</html>

