<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'enseignant') {
    header("Location: login_enseignant.php");
    exit();
}

$prof_id = $_SESSION['user_id'];
$cours_id = isset($_GET['cours_id']) ? (int)$_GET['cours_id'] : 0;
$periode = isset($_GET['periode']) ? $_GET['periode'] : '1ère Période';
$msg = "";

// Récupérer les cours du prof
$stmt_cours = $pdo->prepare("SELECT co.*, cl.nom as classe_nom FROM cours co JOIN classes cl ON co.classe_id = cl.id WHERE co.enseignant_id = :id");
$stmt_cours->execute(['id' => $prof_id]);
$mes_cours = $stmt_cours->fetchAll();

$current_cours = null;
$current_average = null;
if ($cours_id) {
    $stmt = $pdo->prepare("SELECT co.*, cl.nom as classe_nom FROM cours co JOIN classes cl ON co.classe_id = cl.id WHERE co.id = ? AND co.enseignant_id = ?");
    $stmt->execute([$cours_id, $prof_id]);
    $current_cours = $stmt->fetch();
}

if (isset($pdo)) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS cours_moyennes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        cours_id INT NOT NULL,
        periode VARCHAR(50) NOT NULL,
        moyenne DECIMAL(6,2) NOT NULL,
        note_max INT NOT NULL DEFAULT 20,
        enseignant_id INT NOT NULL,
        classe_id INT NOT NULL,
        statut VARCHAR(20) NOT NULL DEFAULT 'Envoyée',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_cours_periode (cours_id, periode),
        FOREIGN KEY (cours_id) REFERENCES cours(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

// Traitement des notes
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['enregistrer_notes'])) {
    $notes = $_POST['notes'] ?? []; // eleve_id => note
    $note_max = (int)$_POST['note_max'];
    
    foreach ($notes as $eleve_id => $valeur) {
        if ($valeur === "") continue; // Ignorer les cases vides
        
        $valeur = (float)$valeur;
        
        // Vérifier si une note existe déjà pour ce cours/élève/période
        $stmt_check = $pdo->prepare("SELECT id FROM notes WHERE eleve_id = ? AND cours_id = ? AND periode = ?");
        $stmt_check->execute([$eleve_id, $cours_id, $periode]);
        
        if ($stmt_check->fetch()) {
            $stmt_upd = $pdo->prepare("UPDATE notes SET note = ?, note_max = ? WHERE eleve_id = ? AND cours_id = ? AND periode = ?");
            $stmt_upd->execute([$valeur, $note_max, $eleve_id, $cours_id, $periode]);
        } else {
            $stmt_ins = $pdo->prepare("INSERT INTO notes (eleve_id, cours_id, note, note_max, periode) VALUES (?, ?, ?, ?, ?)");
            $stmt_ins->execute([$eleve_id, $cours_id, $valeur, $note_max, $periode]);
        }
    }
    $msg = "Notes enregistrées avec succès pour la " . $periode;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['envoyer_moyenne'])) {
    $periode = $_POST['periode'] ?? $periode;
    $custom_moyenne = trim($_POST['moyenne_valeur'] ?? '');
    $note_max_sent = isset($_POST['note_max']) ? (int)$_POST['note_max'] : 20;
    $note_max_sent = $note_max_sent > 0 ? $note_max_sent : 20;
    $average_value = null;

    if ($custom_moyenne !== '') {
        $average_value = (float)$custom_moyenne;
    } else {
        $stmt_avg = $pdo->prepare("SELECT AVG(note) AS moyenne, MAX(note_max) AS note_max FROM notes WHERE cours_id = ? AND periode = ?");
        $stmt_avg->execute([$cours_id, $periode]);
        $avg_data = $stmt_avg->fetch();
        if (!$avg_data || $avg_data['moyenne'] === null) {
            $msg = "Impossible d'envoyer la moyenne : aucune note encodée pour ce cours et cette période.";
        } else {
            $average_value = round((float)$avg_data['moyenne'], 2);
            $note_max_sent = (int)$avg_data['note_max'] ?: $note_max_sent;
        }
    }

    if ($average_value !== null) {
        $stmt_check = $pdo->prepare("SELECT id FROM cours_moyennes WHERE cours_id = ? AND periode = ?");
        $stmt_check->execute([$cours_id, $periode]);
        if ($stmt_check->fetch()) {
            $stmt_upd = $pdo->prepare("UPDATE cours_moyennes SET moyenne = ?, note_max = ?, enseignant_id = ?, classe_id = ?, statut = 'Envoyée', updated_at = NOW() WHERE cours_id = ? AND periode = ?");
            $stmt_upd->execute([$average_value, $note_max_sent, $prof_id, $current_cours['classe_id'], $cours_id, $periode]);
        } else {
            $stmt_ins = $pdo->prepare("INSERT INTO cours_moyennes (cours_id, periode, moyenne, note_max, enseignant_id, classe_id) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt_ins->execute([$cours_id, $periode, $average_value, $note_max_sent, $prof_id, $current_cours['classe_id']]);
        }
        $msg = "Moyenne envoyée au titulaire : " . $average_value . " / " . $note_max_sent;
    }
}

$current_note_max = 20;
if ($current_cours) {
    $stmt_avg = $pdo->prepare("SELECT moyenne, note_max, statut, created_at FROM cours_moyennes WHERE cours_id = ? AND periode = ?");
    $stmt_avg->execute([$cours_id, $periode]);
    $current_average = $stmt_avg->fetch();

    $stmt_note_max = $pdo->prepare("SELECT MAX(note_max) AS max_note FROM notes WHERE cours_id = ? AND periode = ?");
    $stmt_note_max->execute([$cours_id, $periode]);
    $note_max_data = $stmt_note_max->fetch();
    if (!empty($note_max_data['max_note'])) {
        $current_note_max = (int)$note_max_data['max_note'];
    } elseif ($current_average && !empty($current_average['note_max'])) {
        $current_note_max = (int)$current_average['note_max'];
    }
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Saisie des Notes | École Belle Vue</title>
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
        .note-input { width: 60px; padding: 8px; border: 1px solid #ddd; border-radius: 5px; text-align: center; }
        .btn-save { background: #be185d; color: white; padding: 12px 30px; border-radius: 10px; border: none; cursor: pointer; font-weight: bold; }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <aside class="sidebar">
            <h2 style="margin-bottom: 30px;">Belle Vue <span style="font-weight: 300;">Prof</span></h2>
            <nav>
                <ul style="list-style: none; padding: 0;">
                    <li style="margin-bottom: 15px;"><a href="teacher_dashboard.php" style="color: #fbcfe8; text-decoration: none; display: flex; align-items: center; gap: 10px;"><i data-feather="home"></i> Mon Espace</a></li>
                    <li style="margin-bottom: 15px;"><a href="appel_presences.php" style="color: #fbcfe8; text-decoration: none; display: flex; align-items: center; gap: 10px;"><i data-feather="check-square"></i> Appel / Présences</a></li>
                    <li style="margin-bottom: 15px;"><a href="saisie_notes.php" style="color: white; text-decoration: none; display: flex; align-items: center; gap: 10px;"><i data-feather="edit-3"></i> Saisie des Notes</a></li>
                    <li style="margin-top: 50px;"><a href="logout.php" style="color: #fecaca; text-decoration: none; display: flex; align-items: center; gap: 10px;"><i data-feather="log-out"></i> Déconnexion</a></li>
                </ul>
            </nav>
        </aside>

        <main class="main-content">
            <header style="margin-bottom: 30px;">
                <h1><i data-feather="edit-3" style="vertical-align: middle;"></i> Saisie des Notes</h1>
                <p style="color: #64748b;">Encoder les points des élèves par cours et par période.</p>
            </header>

            <?php if ($msg): ?>
                <div style="background: #dcfce7; color: #166534; padding: 15px; border-radius: 10px; margin-bottom: 20px; border: 1px solid #bbf7d0;">
                    <?= $msg ?>
                </div>
            <?php endif; ?>

            <div class="card">
                <form method="GET" style="display: flex; gap: 15px; align-items: flex-end;">
                    <div style="flex: 2;">
                        <label style="display: block; font-size: 13px; color: #64748b; margin-bottom: 5px;">Cours :</label>
                        <select name="cours_id" class="form-control" onchange="this.form.submit()">
                            <option value="">-- Choisir un cours --</option>
                            <?php foreach ($mes_cours as $c): ?>
                                <option value="<?= $c['id'] ?>" <?= $cours_id == $c['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($c['nom']) ?> - <?= htmlspecialchars($c['classe_nom']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div style="flex: 1;">
                        <label style="display: block; font-size: 13px; color: #64748b; margin-bottom: 5px;">Période :</label>
                        <select name="periode" class="form-control" onchange="this.form.submit()">
                            <option value="1ère Période" <?= $periode == '1ère Période' ? 'selected' : '' ?>>1ère Période</option>
                            <option value="2ème Période" <?= $periode == '2ème Période' ? 'selected' : '' ?>>2ème Période</option>
                            <option value="Examen 1er Semestre" <?= $periode == 'Examen 1er Semestre' ? 'selected' : '' ?>>Examen 1er Semestre</option>
                            <option value="3ème Période" <?= $periode == '3ème Période' ? 'selected' : '' ?>>3ème Période</option>
                            <option value="4ème Période" <?= $periode == '4ème Période' ? 'selected' : '' ?>>4ème Période</option>
                            <option value="Examen 2ème Semestre" <?= $periode == 'Examen 2ème Semestre' ? 'selected' : '' ?>>Examen 2ème Semestre</option>
                        </select>
                    </div>
                </form>
            </div>

            <?php if ($current_cours): ?>
            <div class="card">
                <?php if ($current_average): ?>
                    <div style="background: #f8fafc; border: 1px solid #cbd5e1; padding: 15px; border-radius: 12px; margin-bottom: 20px;">
                        <strong>Moyenne déjà envoyée au titulaire :</strong>
                        <?= htmlspecialchars($current_average['moyenne']) ?> / <?= htmlspecialchars($current_average['note_max']) ?>
                        <span style="color: #475569; display: block; margin-top: 4px;">Période : <?= htmlspecialchars($periode) ?> | Statut : <?= htmlspecialchars($current_average['statut']) ?> | Envoyée le <?= htmlspecialchars($current_average['created_at']) ?></span>
                    </div>
                <?php endif; ?>
                <form method="POST">
                    <input type="hidden" name="enregistrer_notes" value="1">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; gap: 20px; flex-wrap: wrap;">
                        <div>
                            <h3 style="margin: 0;">Liste des élèves (<?= htmlspecialchars($current_cours['classe_nom']) ?>)</h3>
                            <p style="font-size: 14px; color: #64748b;">Cours : <?= htmlspecialchars($current_cours['nom']) ?> | <?= $periode ?></p>
                        </div>
                        <div style="text-align: right;">
                            <label style="font-size: 13px; color: #64748b;">Points Max :</label>
                            <input type="number" name="note_max" value="20" class="note-input" style="width: 80px;" required>
                        </div>
                    </div>
                    
                    <table>
                        <thead>
                            <tr>
                                <th>Élève</th>
                                <th style="text-align: center;">Note obtenue</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $stmt_eleves = $pdo->prepare("SELECT * FROM eleves WHERE classe_id = ? AND statut_inscription = 'Validé' ORDER BY nom");
                            $stmt_eleves->execute([$current_cours['classe_id']]);
                            $eleves = $stmt_eleves->fetchAll();
                            
                            foreach ($eleves as $e): 
                                // Récupérer la note actuelle si déjà encodée
                                $stmt_note = $pdo->prepare("SELECT note, note_max FROM notes WHERE eleve_id = ? AND cours_id = ? AND periode = ?");
                                $stmt_note->execute([$e['id'], $cours_id, $periode]);
                                $current_data = $stmt_note->fetch();
                                $current_val = $current_data ? $current_data['note'] : '';
                            ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($e['nom'] . ' ' . $e['post_nom_prenom']) ?></strong></td>
                                <td style="text-align: center;">
                                    <input type="number" step="0.5" name="notes[<?= $e['id'] ?>]" value="<?= $current_val ?>" class="note-input" placeholder="...">
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <div style="margin-top: 30px; text-align: right;">
                        <button type="submit" class="btn-save">Enregistrer les notes</button>
                    </div>
                </form>

                <form method="POST" style="margin-top: 30px; border-top: 1px solid #f3e8ff; padding-top: 24px;">
                    <input type="hidden" name="envoyer_moyenne" value="1">
                    <input type="hidden" name="periode" value="<?= htmlspecialchars($periode) ?>">
                    <input type="hidden" name="note_max" value="<?= htmlspecialchars($current_note_max) ?>">
                    <div style="display: flex; flex-wrap: wrap; gap: 20px; align-items: flex-end;">
                        <div style="flex: 1; min-width: 220px;">
                            <label style="display: block; font-size: 13px; color: #64748b; margin-bottom: 8px;">Moyenne de la classe (laisser vide pour calcul automatique)</label>
                            <input type="number" step="0.01" min="0" name="moyenne_valeur" class="note-input" style="width: 180px;" placeholder="Ex. 14.75">
                        </div>
                        <div style="flex: 2; min-width: 240px; color: #475569; font-size: 13px;">
                            <p style="margin: 0;">Envoyez cette moyenne au titulaire une fois le cours terminé. Le titulaire pourra consulter cette moyenne avant de finaliser les bulletins de la classe.</p>
                        </div>
                        <div style="text-align: right;">
                            <button type="submit" class="btn-save" style="background:#0f172a;">Envoyer la moyenne au titulaire</button>
                        </div>
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

