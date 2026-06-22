<?php
session_start();
require_once 'config.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $login = htmlspecialchars(trim($_POST['login'] ?? ''));
    $password = $_POST['password'] ?? '';
    $user_type_selected = $_POST['user_type'] ?? 'auto';

    if (isset($pdo)) {
        if ($user_type_selected === 'staff') {
            // 1. Check staff (admins table)
            $stmt_admin = $pdo->prepare("SELECT * FROM admins WHERE username = :login");
            $stmt_admin->execute([':login' => $login]);
            $admin = $stmt_admin->fetch();

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
            }
        } else {
            // 2. Check Parent/Student (eleves table)
            // Recherche par téléphone, nom de famille, nom complet de l'élève ou nom du tuteur (insensible à la casse et aux espaces)
            $stmt_eleve = $pdo->prepare("SELECT * FROM eleves WHERE 
                TRIM(tuteur_tel) = :login 
                OR LOWER(TRIM(nom)) = LOWER(:login) 
                OR LOWER(CONCAT(TRIM(nom), ' ', TRIM(post_nom_prenom))) = LOWER(:login)
                OR LOWER(TRIM(tuteur_nom)) = LOWER(:login)");
            $stmt_eleve->execute([':login' => trim($login)]);
            $eleve = $stmt_eleve->fetch();

            if ($eleve && password_verify($password, $eleve['code_pin'])) {
                $_SESSION['eleve_id'] = $eleve['id'];
                $_SESSION['parent_id'] = $eleve['id'];
                $_SESSION['user_id'] = $eleve['id'];
                $_SESSION['username'] = $eleve['tuteur_tel'];
                $_SESSION['eleve_nom'] = $eleve['nom'] . ' ' . $eleve['post_nom_prenom'];
                $_SESSION['parent_nom'] = $eleve['tuteur_nom'];
                
                if ($user_type_selected === 'parent') {
                    $_SESSION['role'] = 'parent';
                    header("Location: dashboard_parent.php");
                } else {
                    $_SESSION['role'] = 'eleve';
                    header("Location: dashboard_eleve.php");
                }
                exit();
            }
        }

        $error = "Identifiant ou mot de passe incorrect pour ce type de profil.";
    } else {
        $error = "Erreur de connexion à la base de données.";
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion | École Belle Vue</title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?= time() ?>">
    <script src="https://unpkg.com/feather-icons"></script>
    <style>
        .login-container {
            min-height: 80vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: transparent;
            padding: 20px;
        }
        .login-card {
            background: var(--white);
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
            width: 100%;
            max-width: 400px;
        }
    </style>
</head>
<body>
    <!-- Background Video -->
    <div class="bg-video-container">
        <video autoplay muted loop playsinline>
            <source src="assets/media/video_belle_vue.mp4" type="video/mp4">
        </video>
    </div>
    <!-- Navbar -->
    <nav class="navbar">
        <div class="container">
            <a href="index.php" class="logo"><img src="assets/img/logo.webp" alt="Logo Belle Vue"></a>
            <ul class="nav-links">
                <li><a href="index.php">Accueil</a></li>
                <li><a href="inscription.php">S'inscrire</a></li>
                <li><a href="login.php" class="active">Espace Portail</a></li>
            </ul>
        </div>
    </nav>

    <div class="login-container">
        <div class="login-card">
            <div style="text-align: center; margin-bottom: 30px;">
                <i data-feather="lock" style="color: var(--primary-blue); width: 48px; height: 48px; margin-bottom: 15px;"></i>
                <h2 style="font-size: 24px;">Connexion au Portail</h2>
                <p style="color: var(--text-medium); font-size: 14px; margin-top: 5px;">Parents & Administration</p>
            </div>

            <?php if (!empty($error)): ?>
                <div style="background: #fee2e2; color: #b91c1c; padding: 12px; border-radius: 6px; margin-bottom: 20px; font-size: 14px; text-align: center;">
                    <?= $error ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="" autocomplete="off">
                <div class="form-group">
                    <label class="form-label">Type de profil</label>
                    <select name="user_type" class="form-control" style="margin-bottom: 15px;">
                        <option value="eleve">Élève / Étudiant</option>
                        <option value="parent">Parent d'élève</option>
                        <option value="staff">Personnel (Préfet/Secrétariat/Enseignant)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Identifiant (Tél ou Nom de l'élève)</label>
                    <input type="text" id="login_input" name="login" class="form-control" required placeholder="Saisissez le nom ou le téléphone...">
                </div>
                <div class="form-group" style="margin-top: 15px;">
                    <label class="form-label">Code PIN / Mot de passe</label>
                    <input type="password" name="password" class="form-control" required placeholder="****" autocomplete="new-password">
                </div>
                <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 10px;">Accéder à mon espace</button>
            </form>
            
            <div style="text-align: center; margin-top: 25px; font-size: 14px; color: var(--text-medium);">
                Pas encore de dossier ? <a href="inscription.php" style="color: var(--primary-blue); font-weight: 500;">Inscrire mon enfant</a>
            </div>
        </div>
    </div>

    <script src="assets/js/main.js"></script>
    <script>
        feather.replace();

        // Auto-initialize feather icons

    </script>
</body>
</html>


