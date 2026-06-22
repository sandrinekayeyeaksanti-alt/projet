<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'secretariat') {
    header("Location: login_secretariat.php");
    exit();
}

$msg = '';

// Ajout d'un horaire
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_horaire') {
    $classe_id = (int)$_POST['classe_id'];
    $cours_id = (int)$_POST['cours_id'];
    $jour = $_POST['jour'];
    $heure_debut = $_POST['heure_debut'];
    $heure_fin = $_POST['heure_fin'];

    if ($classe_id && $cours_id && $jour && $heure_debut && $heure_fin) {
        $stmt = $pdo->prepare("INSERT INTO horaires (classe_id, cours_id, jour, heure_debut, heure_fin) VALUES (:cl, :co, :j, :hd, :hf)");
        $stmt->execute([
            ':cl' => $classe_id,
            ':co' => $cours_id,
            ':j' => $jour,
            ':hd' => $heure_debut,
            ':hf' => $heure_fin
        ]);
        $msg = "Horaire ajouté avec succès !";
    }
}

// Suppression d'un horaire
if (isset($_GET['delete_id'])) {
    $id = (int)$_GET['delete_id'];
    $pdo->prepare("DELETE FROM horaires WHERE id = ?")->execute([$id]);
    header("Location: gestion_horaires.php");
    exit();
}

// Données pour les formulaires
$classes = [];
$cours = [];
$horaires = [];

$filtre_classe_id = isset($_GET['filtre_classe']) ? (int)$_GET['filtre_classe'] : 0;

if (isset($pdo)) {
    try {
        $classes = $pdo->query("SELECT * FROM classes WHERE niveau = 'Secondaire' ORDER BY nom")->fetchAll();
        $cours = $pdo->query("SELECT * FROM cours ORDER BY nom")->fetchAll();

        // Construire la requête
        $sql = "
            SELECT h.*, c.nom as classe_nom, c.option_nom, co.nom as cours_nom 
            FROM horaires h 
            JOIN classes c ON h.classe_id = c.id 
            JOIN cours co ON h.cours_id = co.id 
        ";
        
        $params = [];
        if ($filtre_classe_id > 0) {
            $sql .= " WHERE h.classe_id = :cid ";
            $params[':cid'] = $filtre_classe_id;
        }
        
        // On ordonne d'abord par nom de classe, puis par option, puis par jour et par heure
        $sql .= " ORDER BY c.niveau, c.nom, c.option_nom, FIELD(h.jour, 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'), h.heure_debut";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $horaires = $stmt->fetchAll();
        
    } catch(PDOException $e) {
        $msg = "Erreur de base de données : " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Horaires | Secrétariat</title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?= time() ?>">
    <script src="https://unpkg.com/feather-icons"></script>
    <style>
        .dashboard-container { display: flex; min-height: 100vh; }
        .sidebar { width: 250px; background: #0a1931; color: white; padding: 20px; }
        .main-content { flex: 1; padding: 30px; background: #f0f4f8; }
        .card { background: white; border-radius: 12px; padding: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); border: 1px solid #e2e8f0; margin-bottom: 25px; }
        
        /* Class Selector Cards - Premium Version */
        .class-selection-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 15px; margin-top: 20px; }
        .class-card { 
            background: #0a1931; /* Bleu de Nuit */
            border: 2px solid #f59e0b; /* Jaune Or */
            border-radius: 16px; 
            padding: 25px; 
            text-align: center; 
            text-decoration: none; 
            color: #f59e0b; /* Jaune Or */
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            cursor: pointer;
            display: flex; 
            flex-direction: column; 
            align-items: center; 
            gap: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }
        .class-card:hover { 
            transform: translateY(-5px) scale(1.02); 
            background: #f59e0b; 
            color: #0a1931; 
            box-shadow: 0 15px 30px rgba(245, 158, 11, 0.3);
        }
        .class-card i { color: #f59e0b; width: 35px; height: 35px; transition: color 0.3s; }
        .class-card:hover i { color: #0a1931; }
        .class-card strong { font-size: 15px; font-weight: 800; letter-spacing: 0.5px; }
        .class-card span { font-size: 11px; font-weight: 600; opacity: 0.8; text-transform: uppercase; }

        /* Compact Timetable Grid */
        .timetable-wrapper { background: white; border-radius: 12px; border: 2px solid #0a1931; overflow: hidden; margin-top: 20px; }
        .timetable-header { display: grid; grid-template-columns: 80px repeat(6, 1fr); background: #0a1931; color: #f59e0b; }
        .day-label { padding: 12px; text-align: center; font-size: 11px; font-weight: 800; text-transform: uppercase; border-right: 1px solid rgba(255,255,255,0.1); }
        
        .timetable-body { display: grid; grid-template-columns: 80px repeat(6, 1fr); border-top: 1px solid #e2e8f0; }
        .time-col { background: #0a1931; border-right: 1px solid rgba(255,255,255,0.1); display: flex; align-items: center; justify-content: center; font-size: 11px; font-weight: 800; color: #fff; padding: 10px 5px; }
        .day-cell { border-right: 1px solid #f1f5f9; padding: 5px; background: #fff; min-height: 70px; }
        
        .entry-card { 
            background: #fffbeb; border-radius: 6px; padding: 8px; margin-bottom: 5px; font-size: 10px; 
            border-left: 4px solid #f59e0b; position: relative; color: #92400e; font-weight: 700;
        }
        .entry-card strong { display: block; color: #78350f; font-size: 11px; }

        .btn-primary { background: #f59e0b; color: white; border: none; padding: 12px 20px; border-radius: 8px; font-weight: 700; cursor: pointer; transition: all 0.2s; text-transform: uppercase; letter-spacing: 1px; }
        .btn-primary:hover { background: #d97706; box-shadow: 0 4px 12px rgba(245, 158, 11, 0.3); }
        .form-control { width: 100%; padding: 12px; border: 2px solid #f1f5f9; border-radius: 10px; font-size: 14px; color: #0a1931; }
        .form-control:focus { border-color: #f59e0b; outline: none; }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <aside class="sidebar">
            <h2 style="margin-bottom: 30px;">Belle Vue <span style="font-weight: 300;">Secrétariat</span></h2>
            <nav>
                <ul style="list-style: none; padding: 0;">
                    <li style="margin-bottom: 15px;"><a href="dashboard_secretariat.php" style="color: #bfdbfe; text-decoration: none; display: flex; align-items: center; gap: 10px;"><i data-feather="home"></i> Accueil</a></li>
                    <li style="margin-bottom: 15px;"><a href="inscriptions_secretariat.php" style="color: #bfdbfe; text-decoration: none; display: flex; align-items: center; gap: 10px;"><i data-feather="user-check"></i> Inscriptions</a></li>
                    <li style="margin-bottom: 15px;"><a href="gestion_horaires.php" style="color: white; text-decoration: none; display: flex; align-items: center; gap: 10px;"><i data-feather="calendar"></i> Horaires</a></li>
                    <li style="margin-top: 50px;"><a href="logout.php" style="color: #fca5a5; text-decoration: none; display: flex; align-items: center; gap: 10px;"><i data-feather="log-out"></i> Déconnexion</a></li>
                </ul>
            </nav>
        </aside>

        <main class="main-content">
            <header style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
                <h1>Gestion des Horaires</h1>
                <div style="display: flex; align-items: center; gap: 10px;">
                    <i data-feather="user"></i> <span><?= htmlspecialchars($_SESSION['nom_complet']) ?></span>
                </div>
            </header>

            <?php if ($msg): ?>
                <div style="background: #dcfce7; color: #166534; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                    <?= htmlspecialchars($msg) ?>
                </div>
            <?php endif; ?>

            <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 30px;">
                <!-- Formulaire Ajout -->
                <div class="card" style="align-self: start;">
                    <h3 style="margin-bottom: 20px; display: flex; align-items: center; gap: 10px;"><i data-feather="plus-circle" style="color: #3b82f6;"></i> Planifier un cours</h3>
                    <form method="POST">
                        <input type="hidden" name="action" value="add_horaire">
                        
                        <div class="form-group" style="margin-bottom: 15px;">
                            <label class="form-label">Classe</label>
                            <select name="classe_id" class="form-control" required>
                                <option value="">Choisir une classe...</option>
                                <?php foreach($classes as $c): ?>
                                    <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['nom']) ?> <?= $c['option_nom'] ? '('.htmlspecialchars($c['option_nom']).')' : '' ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group" style="margin-bottom: 15px;">
                            <label class="form-label">Matière / Cours</label>
                            <select name="cours_id" class="form-control" required>
                                <option value="">Choisir un cours...</option>
                                <?php foreach($cours as $co): ?>
                                    <option value="<?= $co['id'] ?>"><?= htmlspecialchars($co['nom']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group" style="margin-bottom: 15px;">
                            <label class="form-label">Jour de la semaine</label>
                            <select name="jour" class="form-control" required>
                                <option value="Lundi">Lundi</option>
                                <option value="Mardi">Mardi</option>
                                <option value="Mercredi">Mercredi</option>
                                <option value="Jeudi">Jeudi</option>
                                <option value="Vendredi">Vendredi</option>
                                <option value="Samedi">Samedi</option>
                            </select>
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 20px;">
                            <div class="form-group">
                                <label class="form-label">Début</label>
                                <input type="time" name="heure_debut" class="form-control" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Fin</label>
                                <input type="time" name="heure_fin" class="form-control" required>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary" style="width: 100%;">Enregistrer</button>
                    </form>
                </div>

                <!-- Liste des Horaires -->
                <div style="flex: 1;">
                    <header style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                        <h3 style="margin: 0; color: #1e3a8a;">Grille des Cours</h3>
                        <?php if ($filtre_classe_id > 0): ?>
                            <a href="gestion_horaires.php" style="font-size: 13px; color: #3b82f6; text-decoration: none; font-weight: 600;">← Changer de classe</a>
                        <?php endif; ?>
                    </header>

                    <?php if ($filtre_classe_id == 0): ?>
                        <!-- Mode Sélection : On montre les classes -->
                        <p style="color: #64748b;">Choisissez une classe pour gérer son emploi du temps :</p>
                        <div class="class-selection-grid">
                            <?php foreach($classes as $c): ?>
                                <a href="?filtre_classe=<?= $c['id'] ?>" class="class-card">
                                    <i data-feather="users"></i>
                                    <strong><?= htmlspecialchars($c['nom']) ?></strong>
                                    <span style="font-size: 11px; opacity: 0.6;"><?= htmlspecialchars($c['option_nom'] ?? 'Générale') ?></span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <!-- Mode Grille : Une seule classe, vue compacte -->
                        <?php 
                        $selected_class_name = "";
                        foreach($classes as $c) if($c['id'] == $filtre_classe_id) $selected_class_name = $c['nom'] . ' (' . ($c['option_nom'] ?? 'Générale') . ')';
                        ?>
                        <div style="background: #1e3a8a; color: white; padding: 12px 20px; border-radius: 10px; margin-bottom: 15px; font-weight: 700;">
                            Classe : <?= $selected_class_name ?>
                        </div>

                        <div class="timetable-wrapper">
                            <div class="timetable-header">
                                <div class="day-label" style="background: rgba(0,0,0,0.1);">Heures</div>
                                <div class="day-label">Lun</div>
                                <div class="day-label">Mar</div>
                                <div class="day-label">Mer</div>
                                <div class="day-label">Jeu</div>
                                <div class="day-label">Ven</div>
                                <div class="day-label">Sam</div>
                            </div>
                            
                            <?php 
                            // On définit des tranches horaires fixes pour une vue propre
                            $tranches = [
                                '07:30 - 08:20', '08:20 - 09:10', '09:10 - 10:00', 
                                '10:00 - 10:30' => 'RÉCRÉATION', 
                                '10:30 - 11:20', '11:20 - 12:10', '12:10 - 13:00'
                            ];
                            $jours_semaine = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'];

                            foreach($tranches as $t => $label): 
                                $is_break = !is_numeric($t);
                                $time_range = $is_break ? $t : $label;
                            ?>
                            <div class="timetable-body" style="<?= $is_break ? 'background: #fef3c7; border-bottom: 2px solid #f59e0b;' : '' ?>">
                                <div class="time-col" style="<?= $is_break ? 'background: #f59e0b; color: #fff;' : '' ?>">
                                    <?= $is_break ? substr($t, 0, 5) : substr($time_range, 0, 5) ?>
                                </div>
                                
                                <?php if($is_break): ?>
                                    <!-- Ligne de récréation fusionnée visuellement -->
                                    <div style="grid-column: span 6; display: flex; align-items: center; justify-content: center; font-weight: 900; color: #92400e; letter-spacing: 5px; font-size: 11px; text-transform: uppercase;">
                                        ⚡ <?= $label ?> ⚡
                                    </div>
                                <?php else: ?>
                                    <?php foreach($jours_semaine as $j): ?>
                                        <div class="day-cell">
                                            <?php 
                                            foreach($horaires as $h): 
                                                $h_range = substr($h['heure_debut'], 0, 5) . ' - ' . substr($h['heure_fin'], 0, 5);
                                                if ($h['jour'] === $j && $h_range === $time_range): 
                                            ?>
                                                <div class="entry-card">
                                                    <a href="?delete_id=<?= $h['id'] ?>&filtre_classe=<?= $filtre_classe_id ?>" class="del-mini" onclick="return confirm('Supprimer ?')">
                                                        <i data-feather="x" style="width: 10px;"></i>
                                                    </a>
                                                    <strong><?= htmlspecialchars($h['cours_nom']) ?></strong>
                                                </div>
                                            <?php 
                                                endif;
                                            endforeach; 
                                            ?>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <p style="font-size: 12px; color: #94a3b8; margin-top: 15px; text-align: center;">Les cours s'alignent automatiquement sur les tranches horaires standards de l'école.</p>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
    <script>feather.replace();</script>
</body>
</html>

