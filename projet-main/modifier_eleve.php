<?php
session_start();
require_once 'config.php';

// Vérification du rôle Secrétariat
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'secretariat') {
    header("Location: login_secretariat.php");
    exit();
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$eleve = null;
$msg = "";
$msg_class = "";

if ($id && isset($pdo)) {
    // Récupérer les informations de l'élève
    $stmt = $pdo->prepare("SELECT * FROM eleves WHERE id = ?");
    $stmt->execute([$id]);
    $eleve = $stmt->fetch();
}

if (!$eleve) {
    die("Élève introuvable.");
}

// Récupérer les classes disponibles pour la réattribution
$classes = [];
if (isset($pdo)) {
    $classes = $pdo->query("SELECT * FROM classes ORDER BY FIELD(niveau, 'Maternelle', 'Primaire', 'Secondaire'), id ASC")->fetchAll();
}

// Traitement de la mise à jour
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['modifier_eleve'])) {
    $nom = htmlspecialchars(trim($_POST['nom'] ?? ''));
    $post_nom_prenom = htmlspecialchars(trim($_POST['post_nom_prenom'] ?? ''));
    $lieu_date_naissance = htmlspecialchars(trim($_POST['lieu_date_naissance'] ?? ''));
    $sexe = $_POST['sexe'] ?? 'M';
    $classe_id = (int)($_POST['classe_id'] ?? 0);
    $ecole_provenance = htmlspecialchars(trim($_POST['ecole_provenance'] ?? ''));
    $tuteur_nom = htmlspecialchars(trim($_POST['tuteur_nom'] ?? ''));
    $tuteur_tel = htmlspecialchars(trim($_POST['tuteur_tel'] ?? ''));
    $tuteur_email = htmlspecialchars(trim($_POST['tuteur_email'] ?? ''));
    $tuteur_adresse = htmlspecialchars(trim($_POST['tuteur_adresse'] ?? ''));
    
    if ($nom && $post_nom_prenom && $tuteur_nom && $tuteur_tel && $classe_id) {
        try {
            $stmt_upd = $pdo->prepare("UPDATE eleves SET 
                nom = ?, 
                post_nom_prenom = ?, 
                lieu_date_naissance = ?, 
                sexe = ?, 
                classe_id = ?, 
                ecole_provenance = ?, 
                tuteur_nom = ?, 
                tuteur_tel = ?, 
                tuteur_email = ?, 
                tuteur_adresse = ? 
                WHERE id = ?");
            $stmt_upd->execute([
                $nom,
                $post_nom_prenom,
                $lieu_date_naissance,
                $sexe,
                $classe_id,
                $ecole_provenance,
                $tuteur_nom,
                $tuteur_tel,
                $tuteur_email,
                $tuteur_adresse,
                $id
            ]);
            
            // Re-charger les informations mises à jour
            $stmt = $pdo->prepare("SELECT * FROM eleves WHERE id = ?");
            $stmt->execute([$id]);
            $eleve = $stmt->fetch();
            
            $msg = "Les informations de l'élève ont été mises à jour avec succès.";
            $msg_class = "success";
        } catch (Exception $e) {
            $msg = "Erreur lors de la mise à jour : " . $e->getMessage();
            $msg_class = "danger";
        }
    } else {
        $msg = "Veuillez remplir tous les champs obligatoires.";
        $msg_class = "danger";
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Modifier Élève | Secrétariat Belle Vue</title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?= time() ?>">
    <script src="https://unpkg.com/feather-icons"></script>
    <style>
        .dashboard-container { display: flex; min-height: 100vh; }
        .sidebar { width: 250px; background: #1e3a8a; color: white; padding: 20px; }
        .main-content { flex: 1; padding: 40px; background: #f1f5f9; }
        .card { background: white; padding: 35px; border-radius: 16px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); border: 1px solid #e2e8f0; max-width: 800px; margin: 0 auto; }
        .btn-save { background: #3b82f6; color: white; padding: 12px 30px; border-radius: 10px; border: none; cursor: pointer; font-weight: bold; font-size: 14px; transition: all 0.2s; }
        .btn-save:hover { background: #2563eb; }
        .alert { padding: 15px; border-radius: 10px; margin-bottom: 25px; font-weight: 500; font-size: 14px; }
        .alert-success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .alert-danger { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 25px; }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <aside class="sidebar">
            <h2 style="margin-bottom: 30px;">Belle Vue <span style="font-weight: 300;">Secrétariat</span></h2>
            <nav>
                <ul style="list-style: none; padding: 0;">
                    <li style="margin-bottom: 15px;"><a href="dashboard_secretariat.php" style="color: #bfdbfe; text-decoration: none; display: flex; align-items: center; gap: 10px;"><i data-feather="home"></i> Accueil</a></li>
                    <li style="margin-bottom: 15px;"><a href="inscriptions_secretariat.php" style="color: white; text-decoration: none; display: flex; align-items: center; gap: 10px;"><i data-feather="user-check"></i> Inscriptions</a></li>
                    <li style="margin-bottom: 15px;"><a href="gestion_horaires.php" style="color: #bfdbfe; text-decoration: none; display: flex; align-items: center; gap: 10px;"><i data-feather="calendar"></i> Horaires</a></li>
                    <li style="margin-top: 50px;"><a href="logout.php" style="color: #fca5a5; text-decoration: none; display: flex; align-items: center; gap: 10px;"><i data-feather="log-out"></i> Déconnexion</a></li>
                </ul>
            </nav>
        </aside>

        <main class="main-content">
            <div style="max-width: 800px; margin: 0 auto 20px auto;">
                <a href="inscriptions_secretariat.php" style="text-decoration: none; color: #1e3a8a; font-weight: bold; display: flex; align-items: center; gap: 8px;">
                    <i data-feather="arrow-left"></i> Retour aux inscriptions
                </a>
            </div>

            <div class="card">
                <h2 style="margin-bottom: 25px; color: #1e3a8a; display: flex; align-items: center; gap: 10px;">
                    <i data-feather="edit-2"></i> Modifier le dossier de l'élève
                </h2>

                <?php if ($msg): ?>
                    <div class="alert alert-<?= $msg_class ?>">
                        <?= $msg ?>
                    </div>
                <?php endif; ?>

                <form method="POST">
                    <input type="hidden" name="modifier_eleve" value="1">
                    
                    <h3 style="font-size: 16px; color: #475569; margin-bottom: 15px; border-bottom: 1px solid #f1f5f9; padding-bottom: 8px;">1. État Civil de l'élève</h3>
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Nom</label>
                            <input type="text" name="nom" class="form-control" value="<?= htmlspecialchars($eleve['nom']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Post-nom & Prénom</label>
                            <input type="text" name="post_nom_prenom" class="form-control" value="<?= htmlspecialchars($eleve['post_nom_prenom']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Lieu & Date de naissance</label>
                            <input type="text" name="lieu_date_naissance" class="form-control" value="<?= htmlspecialchars($eleve['lieu_date_naissance']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Sexe</label>
                            <select name="sexe" class="form-control" required>
                                <option value="M" <?= $eleve['sexe'] === 'M' ? 'selected' : '' ?>>Masculin (M)</option>
                                <option value="F" <?= $eleve['sexe'] === 'F' ? 'selected' : '' ?>>Féminin (F)</option>
                            </select>
                        </div>
                    </div>

                    <h3 style="font-size: 16px; color: #475569; margin-bottom: 15px; border-bottom: 1px solid #f1f5f9; padding-bottom: 8px;">2. Scolarité</h3>
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Classe affectée</label>
                            <select name="classe_id" class="form-control" required>
                                <option value="">Choisir une classe...</option>
                                <?php foreach ($classes as $classe): ?>
                                    <option value="<?= $classe['id'] ?>" <?= $eleve['classe_id'] == $classe['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($classe['nom']) ?> <?= ($classe['option_nom'] !== 'Générale' && $classe['option_nom'] !== 'Generale') ? ' - ' . htmlspecialchars($classe['option_nom']) : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">École de provenance</label>
                            <input type="text" name="ecole_provenance" class="form-control" value="<?= htmlspecialchars($eleve['ecole_provenance'] ?? '') ?>">
                        </div>
                    </div>

                    <h3 style="font-size: 16px; color: #475569; margin-bottom: 15px; border-bottom: 1px solid #f1f5f9; padding-bottom: 8px;">3. Informations du Tuteur</h3>
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Nom du tuteur</label>
                            <input type="text" name="tuteur_nom" class="form-control" value="<?= htmlspecialchars($eleve['tuteur_nom']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Téléphone du tuteur</label>
                            <input type="tel" name="tuteur_tel" class="form-control" value="<?= htmlspecialchars($eleve['tuteur_tel']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Email du tuteur</label>
                            <input type="email" name="tuteur_email" class="form-control" value="<?= htmlspecialchars($eleve['tuteur_email'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Adresse physique</label>
                            <input type="text" name="tuteur_adresse" class="form-control" value="<?= htmlspecialchars($eleve['tuteur_adresse']) ?>" required>
                        </div>
                    </div>

                    <div style="text-align: right; margin-top: 30px;">
                        <button type="submit" class="btn-save">
                            <i data-feather="save" style="width: 16px; height: 16px; vertical-align: middle; margin-right: 5px;"></i>
                            Sauvegarder les modifications
                        </button>
                    </div>
                </form>
            </div>
        </main>
    </div>
    <script>feather.replace();</script>
</body>
</html>
