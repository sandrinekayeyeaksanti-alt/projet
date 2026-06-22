<?php
session_start();
require_once 'config.php';

// Vérification du rôle Enseignant
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'enseignant') {
    header("Location: login_enseignant.php");
    exit();
}

$prof_id = $_SESSION['user_id'];
$message = "";

// Récupération de la classe dont il est titulaire
$classe_titulaire = null;
if (isset($pdo)) {
    $stmt = $pdo->prepare("SELECT c.* FROM classes c JOIN admins a ON a.classe_id = c.id WHERE a.id = :id");
    $stmt->execute(['id' => $prof_id]);
    $classe_titulaire = $stmt->fetch();
}

if (!$classe_titulaire) {
    die("Accès refusé : Vous n'êtes pas titulaire d'une classe.");
}

$classe_id = $classe_titulaire['id'];

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

// Récupération des cours de la classe
$stmt_cours = $pdo->prepare("SELECT * FROM cours WHERE classe_id = ?");
$stmt_cours->execute([$classe_id]);
$cours_classe = $stmt_cours->fetchAll();

$stmt_moyennes = $pdo->prepare("SELECT m.*, co.nom AS cours_nom, COALESCE(a.nom, 'Prof') AS prof_nom, COALESCE(a.post_nom_prenom, '') AS prof_prenom FROM cours_moyennes m JOIN cours co ON m.cours_id = co.id LEFT JOIN admins a ON m.enseignant_id = a.id WHERE m.classe_id = ? ORDER BY FIELD(m.periode, '1ère Période', '2ème Période', 'Examen 1er Semestre', '3ème Période', '4ème Période', 'Examen 2ème Semestre'), co.nom");
$stmt_moyennes->execute([$classe_id]);
$cours_moyennes = $stmt_moyennes->fetchAll();

// Récupération des élèves
$stmt_eleves = $pdo->prepare("SELECT * FROM eleves WHERE classe_id = ? AND statut_inscription = 'Validé' ORDER BY nom, post_nom_prenom");
$stmt_eleves->execute([$classe_id]);
$eleves = $stmt_eleves->fetchAll();
$nb_eleves_total = count($eleves);

// Traitement de l'édition directe des notes (Fiche de cotes)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_grade') {
    $eleve_id = (int)$_POST['eleve_id'];
    $cours_id = (int)$_POST['cours_id'];
    $periode_saisie = htmlspecialchars($_POST['periode']);
    $note_val = $_POST['note'] === "" ? null : (float)$_POST['note'];
    $note_max = (int)$_POST['note_max'];

    if ($note_val !== null) {
        // Enregistrer ou modifier
        $stmt_check = $pdo->prepare("SELECT id FROM notes WHERE eleve_id = ? AND cours_id = ? AND periode = ?");
        $stmt_check->execute([$eleve_id, $cours_id, $periode_saisie]);
        if ($stmt_check->fetch()) {
            $stmt_upd = $pdo->prepare("UPDATE notes SET note = ?, note_max = ? WHERE eleve_id = ? AND cours_id = ? AND periode = ?");
            $stmt_upd->execute([$note_val, $note_max, $eleve_id, $cours_id, $periode_saisie]);
        } else {
            $stmt_ins = $pdo->prepare("INSERT INTO notes (eleve_id, cours_id, note, note_max, periode) VALUES (?, ?, ?, ?, ?)");
            $stmt_ins->execute([$eleve_id, $cours_id, $note_val, $note_max, $periode_saisie]);
        }
        $message = "<div style='color: green; background: #dcfce7; padding: 12px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #bbf7d0;'>Cote enregistrée avec succès.</div>";
    } else {
        // Supprimer la note
        $stmt_del = $pdo->prepare("DELETE FROM notes WHERE eleve_id = ? AND cours_id = ? AND periode = ?");
        $stmt_del->execute([$eleve_id, $cours_id, $periode_saisie]);
        $message = "<div style='color: orange; background: #fff7ed; padding: 12px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #fed7aa;'>Cote supprimée.</div>";
    }
}

// Périodes définies pour la compilation
$periodes_disponibles = [
    '1ère Période' => ['1ère Période'],
    '2ème Période' => ['2ème Période'],
    '1er Semestre' => ['1ère Période', '2ème Période', 'Examen 1er Semestre'],
    '3ème Période' => ['3ème Période'],
    '4ème Période' => ['4ème Période'],
    '2ème Semestre' => ['3ème Période', '4ème Période', 'Examen 2ème Semestre'],
    'Annuel' => ['1ère Période', '2ème Période', 'Examen 1er Semestre', '3ème Période', '4ème Période', 'Examen 2ème Semestre']
];

$compil_periode = $_GET['compil_periode'] ?? '1er Semestre';
$sub_periodes = $periodes_disponibles[$compil_periode] ?? $periodes_disponibles['1er Semestre'];

// Traitement de la génération des bulletins
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'generer_bulletins') {
    $bulletin_data = $_POST['bulletin'] ?? []; // eleve_id => [conduite, application, decision]
    
    // Étape 1 : Calculer les points et classer les élèves pour la période sélectionnée
    $eleves_scores = [];
    foreach ($eleves as $e) {
        $tot_obt = 0;
        $tot_max = 0;
        
        foreach ($cours_classe as $c) {
            foreach ($sub_periodes as $sp) {
                $stmt_n = $pdo->prepare("SELECT note, note_max FROM notes WHERE eleve_id = ? AND cours_id = ? AND periode = ?");
                $stmt_n->execute([$e['id'], $c['id'], $sp]);
                $ndata = $stmt_n->fetch();
                if ($ndata) {
                    $tot_obt += (float)$ndata['note'];
                    $tot_max += (int)$ndata['note_max'];
                } else {
                    // Si pas encodée, on compte le max standard pour ne pas fausser le dénominateur
                    // ou on estime le max
                    if ($sp === 'Examen 1er Semestre' || $sp === 'Examen 2ème Semestre') {
                        $tot_max += 40;
                    } else {
                        $tot_max += 20;
                    }
                }
            }
        }
        
        $pourcentage = $tot_max > 0 ? round(($tot_obt / $tot_max) * 100, 2) : 0;
        
        $eleves_scores[$e['id']] = [
            'eleve' => $e,
            'points_obtenus' => $tot_obt,
            'points_max' => $tot_max,
            'pourcentage' => $pourcentage
        ];
    }
    
    // Classer par pourcentage décroissant pour attribuer les rangs
    uasort($eleves_scores, function($a, $b) {
        return $b['pourcentage'] <=> $a['pourcentage'];
    });
    
    // Attribution des rangs (gérant les ex-æquos)
    $rang = 1;
    $prev_pct = -1;
    $compteur = 0;
    foreach ($eleves_scores as $eid => &$es) {
        $compteur++;
        if ($es['pourcentage'] != $prev_pct) {
            $rang = $compteur;
        }
        $es['place'] = $rang;
        $prev_pct = $es['pourcentage'];
    }
    unset($es);
    
    // Étape 2 : Enregistrer les bulletins en base de données
    try {
        $pdo->beginTransaction();
        
        foreach ($eleves_scores as $eid => $es) {
            $conduite = htmlspecialchars($bulletin_data[$eid]['conduite'] ?? 'A');
            $application = htmlspecialchars($bulletin_data[$eid]['application'] ?? 'Bonne');
            $decision = htmlspecialchars($bulletin_data[$eid]['decision'] ?? '-');
            
            // Supprimer le bulletin existant pour cet élève et cette période
            $stmt_del = $pdo->prepare("DELETE FROM bulletins WHERE eleve_id = ? AND periode = ?");
            $stmt_del->execute([$eid, $compil_periode]);
            
            // Insérer le nouveau bulletin
            $stmt_ins = $pdo->prepare("INSERT INTO bulletins (eleve_id, periode, points_obtenus, points_max, pourcentage, statut, conduite, application, place, nb_eleves, decision, is_published) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)");
            $stmt_ins->execute([
                $eid,
                $compil_periode,
                $es['points_obtenus'],
                $es['points_max'],
                $es['pourcentage'],
                $es['pourcentage'] >= 50 ? 'Admis' : 'Échoué',
                $conduite,
                $application,
                $es['place'],
                $nb_eleves_total,
                $decision
            ]);
        }
        
        $pdo->commit();
        $message = "<div style='color: green; background: #dcfce7; padding: 15px; border-radius: 10px; margin-bottom: 20px; border: 1px solid #bbf7d0; font-weight: bold;'>🎉 Tous les bulletins pour la période [$compil_periode] ont été compilés et publiés avec succès !</div>";
        
        // Rafraichir les élèves
        $stmt_eleves->execute([$classe_id]);
        $eleves = $stmt_eleves->fetchAll();
        
    } catch (Exception $ex) {
        $pdo->rollBack();
        $message = "<div style='color: red; background: #fee2e2; padding: 15px; border-radius: 10px; margin-bottom: 20px; border: 1px solid #fecaca;'>Erreur de génération : " . $ex->getMessage() . "</div>";
    }
}

// Mode affichage
$view_student_id = isset($_GET['view_student_id']) ? (int)$_GET['view_student_id'] : 0;
$view_student = null;
if ($view_student_id) {
    $stmt = $pdo->prepare("SELECT * FROM eleves WHERE id = ? AND classe_id = ?");
    $stmt->execute([$view_student_id, $classe_id]);
    $view_student = $stmt->fetch();
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Gestion du Cursus - Titulaire | École Belle Vue</title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?= time() ?>">
    <script src="https://unpkg.com/feather-icons"></script>
    <style>
        .dashboard-container { display: flex; min-height: 100vh; }
        .sidebar { width: 250px; background: #831843; color: white; padding: 20px; }
        .main-content { flex: 1; padding: 40px; background: #fff1f2; overflow-y: auto; }
        .card { background: white; padding: 25px; border-radius: 15px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); margin-bottom: 25px; border-left: 4px solid #db2777; }
        .grid-stats { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 25px; }
        .stat-box { background: white; padding: 20px; border-radius: 12px; text-align: center; box-shadow: 0 2px 4px rgba(0,0,0,0.02); }
        .stat-box h4 { margin: 0; color: #831843; font-size: 14px; text-transform: uppercase; }
        .stat-box .val { font-size: 28px; font-weight: bold; color: #db2777; margin-top: 10px; }
        
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 12px; background: #fce7f3; color: #831843; font-weight: 600; font-size: 14px; }
        td { padding: 12px; border-bottom: 1px solid #fce7f3; font-size: 14px; }
        tr:hover td { background-color: #fff1f2; }
        
        .badge { padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: bold; }
        .badge-success { background: #dcfce7; color: #166534; }
        .badge-warning { background: #fef9c3; color: #854d0e; }
        
        .btn-action { background: #db2777; color: white; padding: 6px 12px; border-radius: 6px; text-decoration: none; font-size: 12px; font-weight: bold; display: inline-flex; align-items: center; gap: 5px; border: none; cursor: pointer; transition: all 0.2s; }
        .btn-action:hover { background: #be185d; transform: translateY(-1px); }
        .btn-secondary { background: #64748b; }
        .btn-secondary:hover { background: #475569; }
        
        .note-input { width: 60px; padding: 5px; border: 1px solid #cbd5e1; border-radius: 4px; text-align: center; font-weight: bold; }
        .form-control { padding: 8px 12px; border-radius: 6px; border: 1px solid #cbd5e1; width: 100%; }
        
        /* Modal Style for Fiche de cotes */
        .modal-bg { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; display: flex; align-items: center; justify-content: center; backdrop-filter: blur(4px); }
        .modal-body { background: white; padding: 30px; border-radius: 16px; width: 90%; max-width: 900px; max-height: 85vh; overflow-y: auto; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1); border-top: 6px solid #db2777; }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <h2 style="margin-bottom: 30px;">Belle Vue <span style="font-weight: 300;">Prof</span></h2>
            <nav>
                <ul style="list-style: none; padding: 0;">
                    <li style="margin-bottom: 15px;"><a href="teacher_dashboard.php" style="color: #fbcfe8; text-decoration: none; display: flex; align-items: center; gap: 10px;"><i data-feather="home"></i> Mon Espace</a></li>
                    <li style="margin-bottom: 15px;"><a href="appel_presences.php" style="color: #fbcfe8; text-decoration: none; display: flex; align-items: center; gap: 10px;"><i data-feather="check-square"></i> Appel / Présences</a></li>
                    <li style="margin-bottom: 15px;"><a href="saisie_notes.php" style="color: #fbcfe8; text-decoration: none; display: flex; align-items: center; gap: 10px;"><i data-feather="edit-3"></i> Saisie des Notes</a></li>
                    <li style="margin-bottom: 15px;"><a href="titulaire_cursus.php" style="color: white; text-decoration: none; display: flex; align-items: center; gap: 10px; font-weight: bold;"><i data-feather="users"></i> Gestion du Cursus</a></li>
                    <li style="margin-top: 50px;"><a href="logout.php" style="color: #fecaca; text-decoration: none; display: flex; align-items: center; gap: 10px;"><i data-feather="log-out"></i> Déconnexion</a></li>
                </ul>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <header style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
                <div>
                    <h1 style="color: #831843; font-size: 32px;"><i data-feather="award" style="vertical-align: middle;"></i> Gestion du Cursus Scolaire</h1>
                    <p style="color: #64748b; margin-top: 5px;">Classe de titulaire : <strong><?= htmlspecialchars($classe_titulaire['nom']) ?></strong> (<?= htmlspecialchars($classe_titulaire['option_nom']) ?>)</p>
                </div>
                <a href="teacher_dashboard.php" class="btn-action btn-secondary"><i data-feather="arrow-left"></i> Tableau de bord</a>
            </header>

            <?= $message ?>

            <div class="grid-stats">
                <div class="stat-box">
                    <h4>Effectif</h4>
                    <div class="val"><?= $nb_eleves_total ?> Élèves</div>
                </div>
                <div class="stat-box">
                    <h4>Cours affectés</h4>
                    <div class="val"><?= count($cours_classe) ?> Matières</div>
                </div>
                <div class="stat-box">
                    <h4>Bulletins proclamés</h4>
                    <div class="val">
                        <?php 
                        $stmt_b_count = $pdo->prepare("SELECT COUNT(DISTINCT periode) FROM bulletins b JOIN eleves e ON b.eleve_id = e.id WHERE e.classe_id = ?");
                        $stmt_b_count->execute([$classe_id]);
                        echo $stmt_b_count->fetchColumn() ?: 0;
                        ?>
                    </div>
                </div>
            </div>

            <div class="card" style="border-left-color: #f59e0b;">
                <h3><i data-feather="inbox" style="vertical-align: middle; margin-right: 8px;"></i> Moyennes de cours reçues</h3>
                <p style="color: #64748b; font-size: 13px; margin-bottom: 20px;">Les professeurs de matière envoient ici la moyenne de leur cours pour votre classe. Cela vous permet de vérifier les moyennes avant de finaliser les bulletins.</p>
                <?php if (!empty($cours_moyennes)): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Cours</th>
                                <th>Période</th>
                                <th style="text-align: center;">Moyenne</th>
                                <th>Professeur</th>
                                <th>Date d'envoi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($cours_moyennes as $m): ?>
                                <tr>
                                    <td><?= htmlspecialchars($m['cours_nom']) ?></td>
                                    <td><?= htmlspecialchars($m['periode']) ?></td>
                                    <td style="text-align: center;"><strong><?= htmlspecialchars($m['moyenne']) ?> / <?= htmlspecialchars($m['note_max']) ?></strong></td>
                                    <td><?= htmlspecialchars(trim($m['prof_nom'] . ' ' . $m['prof_prenom'])) ?></td>
                                    <td><?= htmlspecialchars($m['created_at']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p style="color: #475569; font-size: 13px; margin: 0;">Aucune moyenne de cours reçue pour le moment. Demandez aux professeurs de saisir et d'envoyer leur moyenne depuis la saisie de notes.</p>
                <?php endif; ?>
            </div>

            <!-- Liste des élèves -->
            <div class="card">
                <h3><i data-feather="users" style="vertical-align: middle; margin-right: 8px;"></i> Liste des Élèves de la classe</h3>
                <p style="color: #64748b; font-size: 13px; margin-bottom: 20px;">Consultez les notes des élèves et générez leurs bulletins périodiques.</p>
                
                <table>
                    <thead>
                        <tr>
                            <th>Nom complet</th>
                            <th>Sexe</th>
                            <th>Frais Scolaires</th>
                            <th style="text-align: center;">Bulletins</th>
                            <th style="text-align: right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($eleves as $e): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($e['nom'].' '.$e['post_nom_prenom']) ?></strong></td>
                            <td><?= $e['sexe'] ?></td>
                            <td>
                                <span class="badge <?= $e['statut_paiement'] == 'Payé' || $e['statut_paiement'] == 'Soldé' ? 'badge-success' : 'badge-warning' ?>">
                                    <?= htmlspecialchars($e['statut_paiement']) ?>
                                </span>
                            </td>
                            <td style="text-align: center;">
                                <?php 
                                $stmt_b = $pdo->prepare("SELECT id, periode, pourcentage FROM bulletins WHERE eleve_id = ? ORDER BY id");
                                $stmt_b->execute([$e['id']]);
                                $ebulls = $stmt_b->fetchAll();
                                foreach($ebulls as $eb) {
                                    echo "<a href='bulletin_virtuel.php?id=".$eb['id']."' target='_blank' class='badge badge-success' style='margin-right: 5px; text-decoration: none;' title='Moyenne: ".$eb['pourcentage']."%'>".$eb['periode']."</a>";
                                }
                                if(empty($ebulls)) {
                                    echo "<span style='color:#94a3b8; font-style:italic; font-size:12px;'>Aucun</span>";
                                }
                                ?>
                            </td>
                            <td style="text-align: right;">
                                <div style="display: flex; gap: 8px; justify-content: flex-end;">
                                    <a href="titulaire_cursus.php?view_student_id=<?= $e['id'] ?>" class="btn-action" style="background:#0284c7;"><i data-feather="edit-3"></i> Cotes</a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- COMPILATEUR DE RESULTATS DE LA CLASSE -->
            <div class="card" style="border-left-color: #10b981;">
                <h3 style="color: #065f46;"><i data-feather="cpu" style="vertical-align: middle; margin-right: 8px;"></i> Compilateur de Résultats & Publication des Bulletins</h3>
                <p style="color: #64748b; font-size: 13px; margin-bottom: 25px;">Sélectionnez une période à compiler. Le système calculera automatiquement la moyenne générale et les rangs (places) de chaque élève pour cette période.</p>
                
                <form method="GET" style="display: flex; gap: 15px; align-items: flex-end; margin-bottom: 30px;">
                    <div style="flex: 2;">
                        <label style="display: block; font-size: 13px; color: #64748b; margin-bottom: 5px; font-weight: 600;">Sélectionner la période d'évaluation :</label>
                        <select name="compil_periode" class="form-control" onchange="this.form.submit()">
                            <?php foreach(array_keys($periodes_disponibles) as $pk): ?>
                                <option value="<?= $pk ?>" <?= $compil_periode == $pk ? 'selected' : '' ?>><?= $pk ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div style="flex: 1;">
                        <button type="button" onclick="document.getElementById('compil_form').scrollIntoView({behavior:'smooth'})" class="btn-action" style="background:#10b981; width: 100%; padding: 10px; justify-content: center;"><i data-feather="eye"></i> Prévisualiser la compilation</button>
                    </div>
                </form>

                <form method="POST" id="compil_form">
                    <input type="hidden" name="action" value="generer_bulletins">
                    <h4 style="color:#065f46; border-bottom: 2px solid #a7f3d0; padding-bottom: 8px; margin-bottom: 15px;"><i data-feather="list"></i> Tableau récapitulatif pour la période : <span style="text-decoration: underline;"><?= $compil_periode ?></span></h4>
                    
                    <table>
                        <thead>
                            <tr>
                                <th>Élève</th>
                                <th style="text-align: center;">Matières notées</th>
                                <th style="text-align: center;">Points Obtenus</th>
                                <th style="text-align: center;">Total Max</th>
                                <th style="text-align: center;">Moyenne (%)</th>
                                <th style="text-align: center;">Conduite</th>
                                <th style="text-align: center;">Application</th>
                                <th>Décision / Remarques</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            // Calculs et affichage en temps réel
                            $temp_scores = [];
                            foreach ($eleves as $e) {
                                $tot_obt = 0;
                                $tot_max = 0;
                                $matires_notées = 0;
                                
                                foreach ($cours_classe as $c) {
                                    $has_note = false;
                                    foreach ($sub_periodes as $sp) {
                                        $stmt_n = $pdo->prepare("SELECT note, note_max FROM notes WHERE eleve_id = ? AND cours_id = ? AND periode = ?");
                                        $stmt_n->execute([$e['id'], $c['id'], $sp]);
                                        $ndata = $stmt_n->fetch();
                                        if ($ndata) {
                                            $tot_obt += (float)$ndata['note'];
                                            $tot_max += (int)$ndata['note_max'];
                                            $has_note = true;
                                        } else {
                                            if ($sp === 'Examen 1er Semestre' || $sp === 'Examen 2ème Semestre') {
                                                $tot_max += 40;
                                            } else {
                                                $tot_max += 20;
                                            }
                                        }
                                    }
                                    if ($has_note) $matires_notées++;
                                }
                                
                                $pourcentage = $tot_max > 0 ? round(($tot_obt / $tot_max) * 100, 2) : 0;
                                $temp_scores[$e['id']] = [
                                    'nom' => $e['nom'].' '.$e['post_nom_prenom'],
                                    'matires_notées' => $matires_notées,
                                    'tot_obt' => $tot_obt,
                                    'tot_max' => $tot_max,
                                    'pourcentage' => $pourcentage
                                ];
                            }
                            
                            // Classer pour avoir le rang virtuel temporaire
                            uasort($temp_scores, function($a, $b) {
                                return $b['pourcentage'] <=> $a['pourcentage'];
                            });
                            $trang = 1;
                            $tprev = -1;
                            $tcount = 0;
                            foreach ($temp_scores as $tid => &$ts) {
                                $tcount++;
                                if ($ts['pourcentage'] != $tprev) {
                                    $trang = $tcount;
                                }
                                $ts['place'] = $trang;
                                $tprev = $ts['pourcentage'];
                            }
                            unset($ts);

                            // Ré-ordonner selon l'ordre alphabétique pour le formulaire
                            foreach ($eleves as $e):
                                $score = $temp_scores[$e['id']];
                                
                                // Charger la conduite et l'application courante si un bulletin existe déjà
                                $stmt_curr_b = $pdo->prepare("SELECT conduite, application, decision FROM bulletins WHERE eleve_id = ? AND periode = ?");
                                $stmt_curr_b->execute([$e['id'], $compil_periode]);
                                $curr_b = $stmt_curr_b->fetch();
                                $curr_cond = $curr_b ? $curr_b['conduite'] : 'TB';
                                $curr_app = $curr_b ? $curr_b['application'] : 'Bonne';
                                $curr_dec = $curr_b ? $curr_b['decision'] : ($score['pourcentage'] >= 50 ? 'Passe dans la classe supérieure' : 'Double la classe');
                            ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($score['nom']) ?></strong></td>
                                <td style="text-align: center;"><span class="badge badge-success"><?= $score['matires_notées'] ?> / <?= count($cours_classe) ?></span></td>
                                <td style="text-align: center; font-weight: bold; color: #db2777;"><?= $score['tot_obt'] ?></td>
                                <td style="text-align: center; color: #64748b;"><?= $score['tot_max'] ?></td>
                                <td style="text-align: center;">
                                    <div style="font-size: 16px; font-weight: bold; color: <?= $score['pourcentage'] >= 50 ? 'green' : 'red' ?>;"><?= $score['pourcentage'] ?>%</div>
                                    <span style="font-size:11px; color:#64748b;">Place : <?= $score['place'] ?>e / <?= $nb_eleves_total ?></span>
                                </td>
                                <td style="text-align: center;">
                                    <select name="bulletin[<?= $e['id'] ?>][conduite]" class="note-input" style="width: 70px;">
                                        <option value="TB" <?= $curr_cond == 'TB' ? 'selected' : '' ?>>TB</option>
                                        <option value="B" <?= $curr_cond == 'B' ? 'selected' : '' ?>>B</option>
                                        <option value="M" <?= $curr_cond == 'M' ? 'selected' : '' ?>>M</option>
                                        <option value="A" <?= $curr_cond == 'A' ? 'selected' : '' ?>>A</option>
                                    </select>
                                </td>
                                <td style="text-align: center;">
                                    <select name="bulletin[<?= $e['id'] ?>][application]" class="note-input" style="width: 90px;">
                                        <option value="Élite" <?= $curr_app == 'Élite' ? 'selected' : '' ?>>Élite</option>
                                        <option value="T. Bonne" <?= $curr_app == 'T. Bonne' ? 'selected' : '' ?>>T. Bonne</option>
                                        <option value="Bonne" <?= $curr_app == 'Bonne' ? 'selected' : '' ?>>Bonne</option>
                                        <option value="Médiocre" <?= $curr_app == 'Médiocre' ? 'selected' : '' ?>>Médiocre</option>
                                    </select>
                                </td>
                                <td>
                                    <input type="text" name="bulletin[<?= $e['id'] ?>][decision]" value="<?= htmlspecialchars($curr_dec) ?>" class="form-control" style="font-size: 12px;" placeholder="Commentaire / Décision...">
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    
                    <div style="margin-top: 30px; text-align: right; background: #fff5f5; padding: 20px; border-radius: 12px; border: 1px solid #fee2e2;">
                        <span style="font-size: 13px; color: #9d174d; margin-right: 20px; font-weight: bold;"><i data-feather="info" style="vertical-align: middle; width: 16px;"></i> La publication écrasera les bulletins existants pour la période <strong><?= $compil_periode ?></strong>.</span>
                        <button type="submit" class="btn-action" style="background:#10b981; padding: 12px 30px; font-size:14px;"><i data-feather="check-circle"></i> Compiler et Délivrer les Bulletins</button>
                    </div>
                </form>
            </div>
        </main>
    </div>

    <!-- MODAL POUR LA FICHE DE COTES INDIVIDUELLE -->
    <?php if ($view_student): ?>
    <div class="modal-bg" id="gradeModal">
        <div class="modal-body">
            <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #fce7f3; padding-bottom: 15px; margin-bottom: 20px;">
                <div>
                    <h3 style="margin: 0; color:#831843;"><i data-feather="edit-2" style="vertical-align: middle;"></i> Fiche de cotes : <?= htmlspecialchars($view_student['nom'] . ' ' . $view_student['post_nom_prenom']) ?></h3>
                    <p style="color: #64748b; font-size: 12px; margin: 5px 0 0;">Visualisez et éditez directement toutes les notes de cet élève.</p>
                </div>
                <a href="titulaire_cursus.php" class="btn-action btn-secondary" style="padding: 5px 10px;"><i data-feather="x"></i> Fermer</a>
            </div>

            <table style="margin-top: 15px;">
                <thead>
                    <tr>
                        <th>Matière</th>
                        <?php 
                        $periodes_saisie_liste = ['1ère Période', '2ème Période', 'Examen 1er Semestre', '3ème Période', '4ème Période', 'Examen 2ème Semestre'];
                        foreach($periodes_saisie_liste as $psl) {
                            echo "<th style='text-align: center; font-size:11px;'>$psl</th>";
                        }
                        ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($cours_classe as $c): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($c['nom']) ?></strong></td>
                        <?php foreach($periodes_saisie_liste as $psl): 
                            // Chercher la note
                            $stmt_n = $pdo->prepare("SELECT note, note_max FROM notes WHERE eleve_id = ? AND cours_id = ? AND periode = ?");
                            $stmt_n->execute([$view_student_id, $c['id'], $psl]);
                            $ndata = $stmt_n->fetch();
                            $nval = $ndata ? $ndata['note'] : '';
                            $nmax = $ndata ? $ndata['note_max'] : (($psl === 'Examen 1er Semestre' || $psl === 'Examen 2ème Semestre') ? 40 : 20);
                        ?>
                        <td style="text-align: center;">
                            <form method="POST" style="display: flex; flex-direction: column; align-items: center; gap: 4px;">
                                <input type="hidden" name="action" value="save_grade">
                                <input type="hidden" name="eleve_id" value="<?= $view_student_id ?>">
                                <input type="hidden" name="cours_id" value="<?= $c['id'] ?>">
                                <input type="hidden" name="periode" value="<?= $psl ?>">
                                <div style="display: flex; align-items: center; gap: 2px;">
                                    <input type="number" step="0.5" name="note" value="<?= $nval ?>" class="note-input" placeholder="...">
                                    <span style="font-size: 11px; color:#64748b;">/</span>
                                    <input type="number" name="note_max" value="<?= $nmax ?>" class="note-input" style="width:40px; background:#f1f5f9; color:#64748b;" required>
                                </div>
                                <button type="submit" class="btn-action" style="padding: 2px 5px; font-size: 9px; background: #64748b;" title="Sauvegarder la note"><i data-feather="save" style="width: 10px; height: 10px;"></i></button>
                            </form>
                        </td>
                        <?php endforeach; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <script>
        feather.replace();
    </script>
</body>
</html>

