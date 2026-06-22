<?php
session_start();
require_once 'config.php';
$error = "";
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $login = trim($_POST['login'] ?? '');
    $password = $_POST['password'] ?? '';
    if (isset($pdo)) {
        // Recherche par téléphone, par nom exact, ou par nom complet (nom + post_nom_prenom)
        $stmt_eleve = $pdo->prepare("
            SELECT * FROM eleves 
            WHERE TRIM(tuteur_tel) = :login 
               OR LOWER(TRIM(nom)) = LOWER(:login) 
               OR LOWER(CONCAT(TRIM(nom), ' ', TRIM(post_nom_prenom))) = LOWER(:login)
        ");
        $stmt_eleve->execute([':login' => $login]);
        $eleve = $stmt_eleve->fetch();
        if ($eleve && password_verify($password, $eleve['code_pin'])) {
            $_SESSION['eleve_id'] = $eleve['id'];
            $_SESSION['eleve_nom'] = $eleve['nom'] . ' ' . $eleve['post_nom_prenom'];
            $_SESSION['role'] = 'eleve';
            header("Location: dashboard_eleve.php");
            exit();
        } else { $error = "Identifiants élève incorrects."; }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Espace Élève | École Belle Vue</title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?= time() ?>">
    <script src="https://unpkg.com/feather-icons"></script>
    <style>
        body { background: #f0f9ff; height: 100vh; display: flex; align-items: center; justify-content: center; font-family: 'Outfit'; }
        .login-card { background: white; padding: 40px; border-radius: 20px; width: 400px; box-shadow: 0 10px 25px rgba(0,0,0,0.05); border-top: 5px solid #0284c7; }
    </style>
</head>
<body>
    <div class="login-card">
        <div style="text-align: center; margin-bottom: 30px;">
            <i data-feather="user" style="width: 48px; height: 48px; color: #0284c7; margin-bottom: 10px;"></i>
            <h2>Mon Espace <span style="color: #0284c7;">Élève</span></h2>
        </div>
        <?php if($error): ?><div style="color:red; margin-bottom:15px; font-size:14px;"><?= $error ?></div><?php endif; ?>
        <form method="POST">
            <div class="form-group"><label>Nom ou Téléphone</label><input type="text" name="login" class="form-control" required placeholder="Ex: Mutombo"></div>
            <div class="form-group" style="margin-top:15px;"><label>Code PIN</label><input type="password" name="password" class="form-control" required placeholder="****"></div>
            <button type="submit" class="btn btn-primary" style="width:100%; margin-top:25px; background:#0284c7; border:none;">Consulter mon cursus</button>
        </form>
        <div style="text-align: center; margin-top: 20px;"><a href="index.php" style="font-size:12px; color:#64748b;">Retour à l'accueil</a></div>
    </div>
    <script>feather.replace();</script>
</body>
</html>

