<?php
session_start();
require_once 'config.php';

// Vérification de la session admin/staff
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'prefet', 'secretariat'])) {
    header("Location: login.php");
    exit();
}

$message = '';
$eleves = [];

if (isset($pdo)) {
    try {
        // Liste des élèves pour le menu déroulant
        $stmt_eleves = $pdo->query("SELECT eleves.id, eleves.nom, eleves.post_nom_prenom, classes.nom as nom_classe, classes.option_nom 
                                    FROM eleves 
                                    LEFT JOIN classes ON eleves.classe_id = classes.id 
                                    ORDER BY eleves.nom ASC");
        $eleves = $stmt_eleves->fetchAll();
    } catch(PDOException $e) {}
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $eleve_id = $_POST['eleve_id'];
    $periode = htmlspecialchars($_POST['periode']);
    $points_obtenus = $_POST['points_obtenus'];
    $points_max = $_POST['points_max'];
    $pourcentage_manuel = $_POST['pourcentage_manuel']; // Le pourcentage est saisi manuellement
    $statut = htmlspecialchars($_POST['statut']);

    // Récupérer la classe de l'élève
    $classe_display = '';
    foreach($eleves as $e) {
        if ($e['id'] == $eleve_id) {
            $classe_display = $e['nom_classe'] . ($e['option_nom'] !== 'Générale' ? ' - ' . $e['option_nom'] : '');
            break;
        }
    }

    if (isset($pdo) && !empty($eleve_id)) {
        try {
            // Casting explicit pour éviter les erreurs de type en base de données
            $eleve_id_clean = intval($eleve_id);
            $points_obt_clean = intval($points_obtenus);
            $points_max_clean = intval($points_max);
            
            // On gère la virgule et on retire un éventuel symbole '%' et on cast en float
            $pct_clean = str_replace(',', '.', $pourcentage_manuel);
            $pct_clean = str_replace('%', '', $pct_clean);
            $pct_clean = floatval($pct_clean);

            $stmt = $pdo->prepare("INSERT INTO bulletins (eleve_id, periode, points_obtenus, points_max, pourcentage, statut) 
                                   VALUES (:id, :periode, :points_obt, :points_max, :pourcentage, :statut)");
            $stmt->execute([
                ':id' => $eleve_id_clean,
                ':periode' => $periode,
                ':points_obt' => $points_obt_clean,
                ':points_max' => $points_max_clean,
                ':pourcentage' => $pct_clean,
                ':statut' => $statut
            ]);
            $message = "<div style='color: green; padding: 10px; background: #dcfce7; border-radius: 5px; margin-bottom: 20px;'>Bulletin enregistré avec succès. <a href='admin.php'>Voir les élèves</a></div>";

        } catch(PDOException $e) {
            $message = "<div style='color: red;'>Erreur : " . $e->getMessage() . "</div>";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Saisir Bulletins - École Belle Vue</title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?= time() ?>">
    <script src="https://unpkg.com/feather-icons"></script>
</head>
<body style="background: transparent;">

    <!-- Background Video -->
    <div class="bg-video-container">
        <video autoplay muted loop playsinline>
            <source src="assets/media/video_belle_vue.mp4" type="video/mp4">
        </video>
    </div>

    <!-- Navbar Admin -->
    <nav class="navbar" style="position: fixed; width: 100%; top: 0; z-index: 1000; background: var(--primary-blue);">
        <div class="container" style="display: flex; justify-content: space-between; align-items: center;">
            <a href="admin.php" class="logo"><img src="assets/img/logo.webp" alt="Logo Belle Vue Admin"></a>
            <div style="display: flex; align-items: center; gap: 20px; color: white;">
                <span style="font-weight: 500; font-size: 14px;">
                    <i data-feather="shield" style="width: 16px; margin-right: 5px; vertical-align: text-bottom;"></i> 
                    <?= htmlspecialchars($_SESSION['username'] ?? '') ?>
                </span>
                <a href="logout.php" class="btn btn-outline" style="padding: 8px 15px; font-size: 14px; border-color: white; color: white;">Déconnexion</a>
            </div>
        </div>
    </nav>

    <div class="container dashboard-layout" style="margin-top: 80px;">
        <aside class="sidebar">
            <ul class="sidebar-nav">
                <li><a href="admin.php"><i data-feather="users"></i> Liste des élèves</a></li>
                <li><a href="saisir_bulletin.php" class="active"><i data-feather="file-plus"></i> Saisir Bulletins</a></li>
                <li><a href="#"><i data-feather="settings"></i> Paramètres API</a></li>
            </ul>
        </aside>

        <main>
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
                <h2 style="font-size: 28px;">Saisie d'un <span style="color: var(--primary-blue);">Bulletin</span></h2>
            </div>
            
            <?= $message ?>

            <div style="background: white; padding: 30px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.03);">
                <form method="POST">
                    <div class="form-group">
                        <label class="form-label">Sélectionner l'élève</label>
                        <select name="eleve_id" class="form-control" required>
                            <option value="">-- Choisir dans la liste --</option>
                            <?php foreach($eleves as $e): ?>
                                <option value="<?= $e['id'] ?>">
                                    <?= htmlspecialchars($e['nom'].' '.$e['post_nom_prenom'].' ('.$e['nom_classe'].($e['option_nom'] !== 'Générale' ? ' - '.$e['option_nom'] : '').')') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                        <div class="form-group">
                            <label class="form-label">Période</label>
                            <input type="text" name="periode" class="form-control" required placeholder="Ex: 1er Semestre, 1ère Période...">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Décision / Statut</label>
                            <select name="statut" class="form-control" required>
                                <option value="Réussi">Réussi - Admis en classe sup.</option>
                                <option value="Échoué">Échoué</option>
                                <option value="Ajourné">Ajourné</option>
                                <option value="-">Non défini (Période intermédiaire)</option>
                            </select>
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px;">
                        <div class="form-group">
                            <label class="form-label">Points Obtenus</label>
                            <input type="number" name="points_obtenus" class="form-control" required placeholder="Ex: 750">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Total Maximum</label>
                            <input type="number" name="points_max" class="form-control" required placeholder="Ex: 1000">
                        </div>
                        <div class="form-group" style="padding: 10px; background: #fef9c3; border-left: 3px solid #eab308; border-radius: 4px;">
                            <label class="form-label" style="color: #ca8a04;">Pourcentage Saisi Manuellement (%)</label>
                            <input type="text" name="pourcentage_manuel" class="form-control" required placeholder="Ex: 75.5">
                        </div>
                    </div>

                    <div style="margin-top: 20px; text-align: right;">
                        <button type="submit" class="btn btn-primary">Enregistrer le bulletin</button>
                    </div>
                </form>
            </div>
        </main>
    </div>

    <script>
        feather.replace();
    </script>
</body>
</html>


