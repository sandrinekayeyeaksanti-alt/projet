<?php
session_start();
require_once 'config.php';

$success = "";
$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nom = htmlspecialchars(trim($_POST['nom_complet'] ?? ''));
    $username = htmlspecialchars(trim($_POST['username'] ?? ''));
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? 'enseignant';

    if (isset($pdo)) {
        // Vérifier si le nom d'utilisateur existe déjà
        $check = $pdo->prepare("SELECT id FROM admins WHERE username = :u");
        $check->execute([':u' => $username]);
        if ($check->fetch()) {
            $error = "Ce nom d'utilisateur est déjà utilisé.";
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO admins (username, password_hash, role, nom_complet, statut) VALUES (:u, :p, :r, :n, 'En attente')");
            if ($stmt->execute([':u' => $username, ':p' => $hash, ':r' => $role, ':n' => $nom])) {
                $success = "Votre demande a été envoyée au Préfet. Vous recevrez un accès dès validation.";
            } else {
                $error = "Une erreur est survenue lors de l'envoi de la demande.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Demande d'Accès Staff | École Belle Vue</title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?= time() ?>">
    <script src="https://unpkg.com/feather-icons"></script>
    <style>
        body { background: #f1f5f9; min-height: 100vh; display: flex; align-items: center; justify-content: center; font-family: 'Outfit'; }
        .register-card { background: white; padding: 40px; border-radius: 24px; width: 500px; box-shadow: 0 10px 25px rgba(0,0,0,0.05); }
    </style>
</head>
<body>
    <div class="register-card">
        <div style="text-align: center; margin-bottom: 30px;">
            <h2 style="font-size: 24px; color: var(--primary-blue);">Demande de Compte Staff</h2>
            <p style="color: #64748b; font-size: 14px;">Pour les Enseignants et Secrétaires</p>
        </div>

        <?php if($success): ?>
            <div style="background: #dcfce7; color: #166534; padding: 15px; border-radius: 12px; margin-bottom: 25px; border: 1px solid #bbf7d0;">
                <i data-feather="check-circle" style="vertical-align: middle; margin-right: 10px;"></i> <?= $success ?>
                <br><br><a href="index.php" class="btn btn-primary" style="width: 100%; text-decoration: none; display: inline-block; text-align: center;">Retour à l'accueil</a>
            </div>
        <?php else: ?>

            <?php if($error): ?>
                <div style="background: #fef2f2; color: #991b1b; padding: 12px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; border: 1px solid #fee2e2;">
                    <?= $error ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label class="form-label">Nom Complet</label>
                    <input type="text" name="nom_complet" class="form-control" required placeholder="Ex: Prof. Kanda Jean">
                </div>
                <div class="form-group" style="margin-top: 15px;">
                    <label class="form-label">Nom d'utilisateur (pour la connexion)</label>
                    <input type="text" name="username" class="form-control" required placeholder="Ex: jean_prof">
                </div>
                <div class="form-group" style="margin-top: 15px;">
                    <label class="form-label">Mot de passe souhaité</label>
                    <input type="password" name="password" class="form-control" required placeholder="••••••••">
                </div>
                <div class="form-group" style="margin-top: 15px;">
                    <label class="form-label">Fonction sollicitée</label>
                    <select name="role" class="form-control" required>
                        <option value="enseignant">Enseignant / Professeur</option>
                        <option value="secretariat">Secrétaire / Administratif</option>
                    </select>
                </div>
                
                <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 30px; padding: 12px;">Envoyer ma demande au Préfet</button>
            </form>

            <div style="text-align: center; margin-top: 25px;">
                <a href="choix_portail.php" style="color: #64748b; text-decoration: none; font-size: 13px;">← Retour</a>
            </div>
        <?php endif; ?>
    </div>
    <script>feather.replace();</script>
</body>
</html>

