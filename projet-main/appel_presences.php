<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'enseignant') {
    header("Location: login_enseignant.php");
    exit();
}

$prof_id = $_SESSION['user_id'];
$cours_id = isset($_GET['cours_id']) ? (int)$_GET['cours_id'] : 0;
$msg = "";

// Récupérer les cours du prof
$stmt_cours = $pdo->prepare("SELECT co.*, cl.nom as classe_nom FROM cours co JOIN classes cl ON co.classe_id = cl.id WHERE co.enseignant_id = :id");
$stmt_cours->execute(['id' => $prof_id]);
$mes_cours = $stmt_cours->fetchAll();

$current_cours = null;
if ($cours_id) {
    $stmt = $pdo->prepare("SELECT co.*, cl.nom as classe_nom FROM cours co JOIN classes cl ON co.classe_id = cl.id WHERE co.id = ? AND co.enseignant_id = ?");
    $stmt->execute([$cours_id, $prof_id]);
    $current_cours = $stmt->fetch();
}

// Traitement de l'appel
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['faire_appel'])) {
    $date = date('Y-m-d');
    $presences = $_POST['presences'] ?? []; // eleve_id => statut
    
    foreach ($presences as $eleve_id => $statut) {
        // Vérifier si une présence existe déjà pour ce cours/élève/date
        $stmt_check = $pdo->prepare("SELECT id FROM presences WHERE eleve_id = ? AND cours_id = ? AND date_presence = ?");
        $stmt_check->execute([$eleve_id, $cours_id, $date]);
        
        if ($stmt_check->fetch()) {
            $stmt_upd = $pdo->prepare("UPDATE presences SET statut = ? WHERE eleve_id = ? AND cours_id = ? AND date_presence = ?");
            $stmt_upd->execute([$statut, $eleve_id, $cours_id, $date]);
        } else {
            $stmt_ins = $pdo->prepare("INSERT INTO presences (eleve_id, cours_id, date_presence, statut) VALUES (?, ?, ?, ?)");
            $stmt_ins->execute([$eleve_id, $cours_id, $date, $statut]);
        }
    }
    $msg = "Appel enregistré avec succès pour le " . date('d/m/Y');
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Faire l'appel | École Belle Vue</title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?= time() ?>">
    <script src="https://unpkg.com/feather-icons"></script>
    <style>
        .dashboard-container { display: flex; min-height: 100vh; }
        .sidebar { width: 250px; background: #831843; color: white; padding: 20px; }
        .main-content { flex: 1; padding: 40px; background: #fff1f2; }
        .card { background: white; padding: 25px; border-radius: 15px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); margin-bottom: 25px; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 12px; background: #fce7f3; color: #831843; }
        td { padding: 12px; border-bottom: 1px solid #fce7f3; }
        .radio-group { display: flex; gap: 15px; }
        .btn-save { background: #db2777; color: white; padding: 12px 30px; border-radius: 10px; border: none; cursor: pointer; font-weight: bold; }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <aside class="sidebar">
            <h2 style="margin-bottom: 30px;">Belle Vue <span style="font-weight: 300;">Prof</span></h2>
            <nav>
                <ul style="list-style: none; padding: 0;">
                    <li style="margin-bottom: 15px;"><a href="teacher_dashboard.php" style="color: #fbcfe8; text-decoration: none; display: flex; align-items: center; gap: 10px;"><i data-feather="home"></i> Mon Espace</a></li>
                    <li style="margin-bottom: 15px;"><a href="appel_presences.php" style="color: white; text-decoration: none; display: flex; align-items: center; gap: 10px;"><i data-feather="check-square"></i> Appel / Présences</a></li>
                    <li style="margin-bottom: 15px;"><a href="saisie_notes.php" style="color: #fbcfe8; text-decoration: none; display: flex; align-items: center; gap: 10px;"><i data-feather="edit-3"></i> Saisie des Notes</a></li>
                    <li style="margin-top: 50px;"><a href="logout.php" style="color: #fecaca; text-decoration: none; display: flex; align-items: center; gap: 10px;"><i data-feather="log-out"></i> Déconnexion</a></li>
                </ul>
            </nav>
        </aside>

        <main class="main-content">
            <header style="margin-bottom: 30px;">
                <h1><i data-feather="check-square" style="vertical-align: middle;"></i> Faire l'appel</h1>
                <p style="color: #64748b;">Enregistrez la présence des élèves pour vos cours.</p>
            </header>

            <?php if ($msg): ?>
                <div style="background: #dcfce7; color: #166534; padding: 15px; border-radius: 10px; margin-bottom: 20px; border: 1px solid #bbf7d0;">
                    <?= $msg ?>
                </div>
            <?php endif; ?>

            <div class="card">
                <form method="GET" style="display: flex; gap: 15px; align-items: flex-end;">
                    <div style="flex: 1;">
                        <label style="display: block; font-size: 13px; color: #64748b; margin-bottom: 5px;">Sélectionnez un cours :</label>
                        <select name="cours_id" class="form-control" onchange="this.form.submit()">
                            <option value="">-- Choisir un cours --</option>
                            <?php foreach ($mes_cours as $c): ?>
                                <option value="<?= $c['id'] ?>" <?= $cours_id == $c['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($c['nom']) ?> - <?= htmlspecialchars($c['classe_nom']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </form>
            </div>

            <?php if ($current_cours): ?>
            <div class="card">
                <h3>Liste des élèves (<?= htmlspecialchars($current_cours['classe_nom']) ?>)</h3>
                <p style="font-size: 14px; color: #64748b; margin-bottom: 20px;">Cours : <?= htmlspecialchars($current_cours['nom']) ?> | Date : <?= date('d/m/Y') ?></p>
                
                <form method="POST">
                    <input type="hidden" name="faire_appel" value="1">
                    <table>
                        <thead>
                            <tr>
                                <th>Élève</th>
                                <th>Statut de présence</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $stmt_eleves = $pdo->prepare("SELECT * FROM eleves WHERE classe_id = ? AND statut_inscription = 'Validé' ORDER BY nom");
                            $stmt_eleves->execute([$current_cours['classe_id']]);
                            $eleves = $stmt_eleves->fetchAll();
                            
                            foreach ($eleves as $e): 
                                // Récupérer le statut actuel si déjà fait aujourd'hui
                                $stmt_status = $pdo->prepare("SELECT statut FROM presences WHERE eleve_id = ? AND cours_id = ? AND date_presence = ?");
                                $stmt_status->execute([$e['id'], $cours_id, date('Y-m-d')]);
                                $current_status = $stmt_status->fetchColumn() ?: 'Présent';
                            ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($e['nom'] . ' ' . $e['post_nom_prenom']) ?></strong></td>
                                <td>
                                    <div class="radio-group">
                                        <label><input type="radio" name="presences[<?= $e['id'] ?>]" value="Présent" <?= $current_status == 'Présent' ? 'checked' : '' ?>> Présent</label>
                                        <label><input type="radio" name="presences[<?= $e['id'] ?>]" value="Absent" <?= $current_status == 'Absent' ? 'checked' : '' ?>> Absent</label>
                                        <label><input type="radio" name="presences[<?= $e['id'] ?>]" value="Justifié" <?= $current_status == 'Justifié' ? 'checked' : '' ?>> Justifié</label>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <div style="margin-top: 30px; text-align: right;">
                        <button type="submit" class="btn-save">Enregistrer l'appel</button>
                    </div>
                </form>
            </div>
            <?php elseif ($cours_id): ?>
                <p style="color: #ef4444;">Cours non trouvé ou accès non autorisé.</p>
            <?php endif; ?>
        </main>
    </div>
    <script>feather.replace();</script>
</body>
</html>

