<?php
session_start();
require_once 'config.php';

// Vérification du rôle Élève ou Parent
if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'eleve' && $_SESSION['role'] !== 'parent')) {
    header("Location: login.php");
    exit();
}

$eleve_id = ($_SESSION['role'] === 'parent' && isset($_GET['view_as_parent'])) ? (int)$_GET['view_as_parent'] : ($_SESSION['eleve_id'] ?? 0);

if (!$eleve_id) {
    die("Accès refusé ou élève non spécifié.");
}
$eleve = [];
$bulletins = [];
$horaires = [];
$presences = [];

if (isset($pdo)) {
    // 1. Infos Élève
    $stmt = $pdo->prepare("SELECT e.*, c.nom as classe_nom, c.niveau, c.option_nom FROM eleves e LEFT JOIN classes c ON e.classe_id = c.id WHERE e.id = :id");
    $stmt->execute(['id' => $eleve_id]);
    $eleve = $stmt->fetch();

    // 2. Horaire de la classe
    if ($eleve['classe_id']) {
        $stmt_h = $pdo->prepare("SELECT h.*, co.nom as cours_nom FROM horaires h JOIN cours co ON h.cours_id = co.id WHERE h.classe_id = :cid ORDER BY FIELD(jour, 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'), heure_debut");
        $stmt_h->execute(['cid' => $eleve['classe_id']]);
        $horaires = $stmt_h->fetchAll();
    }

    // 3. Bulletins/Notes
    $stmt_b = $pdo->prepare("SELECT * FROM bulletins WHERE eleve_id = :id ORDER BY id DESC");
    $stmt_b->execute(['id' => $eleve_id]);
    $bulletins = $stmt_b->fetchAll();

    // 4. Statistiques de présences (Logique robuste)
    $stmt_p = $pdo->prepare("SELECT statut, COUNT(*) as nb FROM presences WHERE eleve_id = :id GROUP BY statut");
    $stmt_p->execute(['id' => $eleve_id]);
    $raw_presences = $stmt_p->fetchAll(PDO::FETCH_KEY_PAIR);
    
    // On extrait les absents et on considère tout le reste comme présent
    $nb_absents = $raw_presences['Absent'] ?? ($raw_presences['absent'] ?? 0);
    $total_lignes = array_sum($raw_presences);
    $nb_presents = $total_lignes - $nb_absents;
    
    $presences = ['Present' => $nb_presents, 'Absent' => $nb_absents];

    // 5. Calcul de la moyenne générale
    $moyenne_generale = 0;
    if (count($bulletins) > 0) {
        $total_pourcentage = array_sum(array_column($bulletins, 'pourcentage'));
        $moyenne_generale = round($total_pourcentage / count($bulletins), 2);
    }

    // 6. Heure de sortie
    $heure_sortie = "13:00"; // Par défaut
    if (!empty($horaires)) {
        $max_h = max(array_column($horaires, 'heure_fin'));
        $heure_sortie = substr($max_h, 0, 5);
    }

    // VERIFICATION DE SECURITE : Si l'élève n'est pas encore validé
    if ($eleve['statut_inscription'] === 'En attente') {
        include 'waiting_approval.php';
        exit();
    }
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Mon Profil | École Belle Vue</title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?= time() ?>">
    <script src="https://unpkg.com/feather-icons"></script>
    <style>
        .dashboard-grid { display: grid; grid-template-columns: 350px 1fr; gap: 30px; max-width: 1200px; margin: 40px auto; padding: 0 20px; }
        .id-card { background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%); color: white; padding: 30px; border-radius: 20px; box-shadow: 0 10px 20px rgba(30, 58, 138, 0.2); }
        .main-panel { display: flex; flex-direction: column; gap: 25px; }
        .card { background: white; border-radius: 15px; padding: 25px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        .horaire-item { display: flex; justify-content: space-between; padding: 12px; border-bottom: 1px solid #f1f5f9; }
        .horaire-item:last-child { border-bottom: none; }
        .badge-presence { padding: 4px 10px; border-radius: 10px; font-size: 12px; font-weight: 600; }
        .day-group { margin-bottom: 15px; border-left: 3px solid #3b82f6; padding-left: 15px; }
    </style>
</head>
<body style="background: #f8fafc;">
    
    <nav style="background: white; padding: 15px 0; border-bottom: 1px solid #e2e8f0; margin-bottom: 40px;">
        <div style="max-width: 1200px; margin: 0 auto; padding: 0 20px; display: flex; justify-content: space-between; align-items: center;">
            <img src="assets/img/logo.webp" alt="Logo" style="height: 40px;">
            <a href="logout.php" style="color: #64748b; text-decoration: none; font-weight: 500;"><i data-feather="log-out" style="width: 18px; vertical-align: middle;"></i> Quitter</a>
        </div>
    </nav>

    <div class="dashboard-grid">
        <!-- Sidebar: Identité -->
        <aside class="sidebar">
            <div class="id-card">
                <div style="text-align: center; margin-bottom: 20px;">
                    <div style="width: 80px; height: 80px; background: rgba(255,255,255,0.2); border-radius: 50%; margin: 0 auto; display: flex; align-items: center; justify-content: center; font-size: 32px; font-weight: bold;">
                        <?= substr($eleve['nom'], 0, 1) ?>
                    </div>
                    <h2 style="margin: 15px 0 5px;"><?= htmlspecialchars($eleve['nom']) ?></h2>
                    <p style="opacity: 0.8; font-size: 14px;"><?= htmlspecialchars($eleve['post_nom_prenom']) ?></p>
                </div>
                <div style="font-size: 14px; border-top: 1px solid rgba(255,255,255,0.1); padding-top: 20px;">
                    <p><strong>Classe :</strong> <?= htmlspecialchars($eleve['classe_nom']) ?></p>
                    <p><strong>Option :</strong> <?= htmlspecialchars($eleve['option_nom']) ?></p>
                    <p><strong>Sexe :</strong> <?= $eleve['sexe'] ?></p>
                    <p><strong>Heure de Sortie :</strong> <span style="background: rgba(0,0,0,0.3); padding: 4px 10px; border-radius: 5px; font-weight: bold;"><?= $heure_sortie ?></span></p>
                </div>
            </div>

            <!-- Moyenne Générale -->
            <div class="card" style="margin-top: 20px; text-align: center; background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); color: white;">
                <h3 style="font-size: 16px; margin-bottom: 5px;">Moyenne Générale</h3>
                <div style="font-size: 36px; font-weight: bold;"><?= $moyenne_generale ?>%</div>
            </div>

            <div class="card" style="margin-top: 20px;">
                <h3><i data-feather="activity" style="width: 18px;"></i> Présences</h3>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 15px;">
                    <div style="background: #f0fdf4; padding: 10px; border-radius: 10px; text-align: center;">
                        <div style="color: #166534; font-size: 20px; font-weight: bold;"><?= $presences['Present'] ?? 0 ?></div>
                        <div style="color: #166534; font-size: 11px;">Présents</div>
                    </div>
                    <div style="background: #fef2f2; padding: 10px; border-radius: 10px; text-align: center;">
                        <div style="color: #991b1b; font-size: 20px; font-weight: bold;"><?= $presences['Absent'] ?? 0 ?></div>
                        <div style="color: #991b1b; font-size: 11px;">Absents</div>
                    </div>
                </div>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="main-panel">
            <!-- Horaire (Uniquement pour le Secondaire/Humanités) -->
            <?php if ($eleve['niveau'] === 'Secondaire'): ?>
            <div class="card">
                <h3><i data-feather="calendar" style="width: 20px; vertical-align: middle; color: #3b82f6;"></i> Emploi du Temps (Humanités)</h3>
                <div style="margin-top: 15px;">
                    <?php 
                    $current_day = "";
                    foreach ($horaires as $h): 
                        if ($current_day != $h['jour']):
                            $current_day = $h['jour'];
                            echo "<div class='day-group'><h4 style='color: #1e3a8a; margin-bottom: 10px;'>$current_day</h4>";
                        endif;
                    ?>
                    <div class="horaire-item">
                        <span style="color: #64748b; font-size: 14px;"><?= substr($h['heure_debut'], 0, 5) ?> - <?= substr($h['heure_fin'], 0, 5) ?></span>
                        <span style="font-weight: 600;"><?= htmlspecialchars($h['cours_nom']) ?></span>
                    </div>
                    <?php 
                        if (!next($horaires) || $horaires[key($horaires)]['jour'] != $current_day) echo "</div>";
                    endforeach; 
                    ?>
                    <?php if (empty($horaires)): ?>
                    <p style="color: #94a3b8; font-style: italic;">L'horaire des Humanités n'a pas encore été encodé par le secrétariat.</p>
                    <?php endif; ?>
                </div>
            </div>
            <?php else: ?>
            <div class="card" style="background: #f0fdfa; border-left: 5px solid #14b8a6;">
                <h3 style="color: #0d9488;"><i data-feather="clock" style="width: 20px; vertical-align: middle;"></i> Rythme Scolaire</h3>
                <p style="color: #0f766e; margin-top: 10px; font-size: 14px; line-height: 1.5;">
                    En <strong><?= htmlspecialchars($eleve['niveau']) ?></strong>, les activités suivent un programme standardisé de 07h30 à 12h30. 
                    L'emploi du temps détaillé par cours est réservé aux Humanités.
                </p>
            </div>
            <?php endif; ?>

             <!-- Notes Récentes -->
             <div class="card">
                 <h3><i data-feather="award" style="width: 20px; vertical-align: middle; color: #f59e0b;"></i> Résultats & Bulletins Proclamés</h3>
                 <p style="font-size: 13px; color: #64748b; margin-bottom: 15px;">Consultez vos bulletins virtuels officiels signés et scellés.</p>
                 <div style="margin-top: 15px;">
                     <?php foreach ($bulletins as $b): ?>
                     <div style="display: flex; justify-content: space-between; align-items: center; padding: 18px; background: #fffbeb; border-radius: 12px; margin-bottom: 15px; border: 1px solid #fef3c7; border-left: 5px solid #d97706;">
                         <div>
                             <div style="font-weight: bold; color: #92400e; font-size: 16px;"><?= htmlspecialchars($b['periode']) ?></div>
                             <div style="font-size: 13px; color: #b45309; margin-top: 3px;">Mention : <strong><?= htmlspecialchars($b['statut']) ?></strong></div>
                             <div style="font-size: 12px; color: #b45309; margin-top: 5px;">Moyenne : <strong><?= $b['pourcentage'] ?>%</strong> (<?= $b['points_obtenus'] ?> / <?= $b['points_max'] ?> pts)</div>
                         </div>
                         <div style="text-align: right; display: flex; flex-direction: column; gap: 8px;">
                             <a href="bulletin_virtuel.php?id=<?= $b['id'] ?>" target="_blank" style="display: inline-flex; align-items: center; gap: 5px; padding: 8px 15px; background: #d97706; color: white; border-radius: 8px; font-weight: bold; font-size: 12px; text-decoration: none; box-shadow: 0 4px 6px rgba(217,119,6,0.2); transition: all 0.2s;">
                                 <i data-feather="eye" style="width: 14px; height: 14px;"></i> Voir le Bulletin
                             </a>
                         </div>
                     </div>
                     <?php endforeach; ?>
                     <?php if (empty($bulletins)): ?>
                     <p style="color: #94a3b8; font-style: italic; font-size: 14px; text-align: center; padding: 20px;">Aucun bulletin n'a encore été proclamé pour vous cette année.</p>
                     <?php endif; ?>
                 </div>
             </div>

            <!-- Suivi des Paiements -->
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h3><i data-feather="dollar-sign" style="width: 20px; vertical-align: middle; color: #10b981;"></i> Suivi des Paiements</h3>
                    <a href="payer_minerval.php" class="btn btn-primary" style="background: #0a1931; font-size: 13px; padding: 10px 20px; display: flex; align-items: center; gap: 8px; text-decoration: none;">
                        <i data-feather="credit-card" style="width: 16px;"></i> Payer mon Minerval en ligne
                    </a>
                </div>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 12px;">
                    <?php 
                    // Liste complète des 9 tranches + Frais initiaux
                    $frais_scolaires = ["Frais de Test", "Test d'admission", "1ère Tranche", "2ème Tranche", "3ème Tranche", "4ème Tranche", "5ème Tranche", "6ème Tranche", "7ème Tranche", "8ème Tranche", "9ème Tranche", "Frais Divers"];
                    
                    // Récupération des paiements réels
                    $payes = $pdo->prepare("SELECT type_frais FROM paiements WHERE eleve_id = ?");
                    $payes->execute([$eleve_id]);
                    $liste_payes = $payes->fetchAll(PDO::FETCH_COLUMN);

                    // Détection archive : Si l'élève est soldé
                    $is_archive = ($eleve['statut_paiement'] === 'Soldé');

                    foreach ($frais_scolaires as $frais): 
                        // On considère payé si c'est en DB OU si c'est un élève archive
                        $is_paye = ($is_archive || in_array($frais, $liste_payes));
                    ?>
                    <div style="padding: 12px; border-radius: 10px; border: 2px solid <?= $is_paye ? '#10b981' : '#fee2e2' ?>; background: <?= $is_paye ? '#f0fdf4' : '#fff1f1' ?>; display: flex; align-items: center; gap: 10px; transition: all 0.3s;">
                        <div style="width: 20px; height: 20px; border-radius: 50%; background: <?= $is_paye ? '#10b981' : '#ef4444' ?>; display: flex; align-items: center; justify-content: center; color: white; flex-shrink: 0;">
                            <i data-feather="<?= $is_paye ? 'check' : 'x' ?>" style="width: 12px; height: 12px;"></i>
                        </div>
                        <div style="font-size: 12px; font-weight: 700; color: <?= $is_paye ? '#166534' : '#991b1b' ?>; line-height: 1.2;">
                            <?= $frais ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </main>
    </div>

    <script>feather.replace();</script>
</body>
</html>

