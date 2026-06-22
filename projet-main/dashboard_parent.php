<?php
session_start();
require_once 'config.php';

// Vérification du rôle Parent
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'parent') {
    header("Location: login_parent.php");
    exit();
}

$parent_id = $_SESSION['user_id'];
$enfants = [];

if (isset($pdo)) {
    // On cherche tous les enfants liés par le même numéro de téléphone du tuteur
    $stmt = $pdo->prepare("SELECT e.*, c.nom as classe_nom FROM eleves e LEFT JOIN classes c ON e.classe_id = c.id WHERE e.tuteur_tel = :tel");
    $stmt->execute(['tel' => $_SESSION['username']]);
    $enfants_bruts = $stmt->fetchAll();

    foreach ($enfants_bruts as $e) {
        $eleve_id = $e['id'];
        
        // Calcul Moyenne
        $stmt_moy = $pdo->prepare("SELECT AVG(pourcentage) FROM bulletins WHERE eleve_id = ?");
        $stmt_moy->execute([$eleve_id]);
        $moyenne = $stmt_moy->fetchColumn();
        
        // Comptage Absences
        $stmt_abs = $pdo->prepare("SELECT COUNT(*) FROM presences WHERE eleve_id = ? AND statut = 'Absent'");
        $stmt_abs->execute([$eleve_id]);
        $absences = $stmt_abs->fetchColumn();

        $e['moyenne'] = $moyenne ? round($moyenne, 1) . '%' : '-- %';
        $e['absences'] = $absences;
        $enfants[] = $e;
    }
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Espace Parent | École Belle Vue</title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?= time() ?>">
    <script src="https://unpkg.com/feather-icons"></script>
    <style>
        .parent-container { max-width: 1000px; margin: 40px auto; padding: 0 20px; }
        .enfant-card { background: white; border-radius: 15px; padding: 25px; margin-bottom: 30px; border-top: 5px solid #10b981; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        .grid-info { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin: 20px 0; }
        .btn-view { display: inline-block; padding: 10px 20px; background: #10b981; color: white; text-decoration: none; border-radius: 8px; font-weight: 600; }
    </style>
</head>
<body style="background: #f0fdf4;">
    <div class="parent-container">
        <header style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 40px;">
            <div>
                <h1 style="color: #064e3b;">Espace Parent</h1>
                <p style="color: #059669;">Suivi de la scolarité de vos enfants.</p>
            </div>
            <a href="logout.php" style="color: #059669; text-decoration: none;"><i data-feather="log-out"></i> Déconnexion</a>
        </header>

        <?php foreach ($enfants as $enfant): ?>
        <div class="enfant-card">
            <div style="display: flex; justify-content: space-between; align-items: start;">
                <div>
                    <h2 style="margin: 0; color: #065f46;"><?= htmlspecialchars($enfant['nom'] . ' ' . $enfant['post_nom_prenom']) ?></h2>
                    <span class="badge" style="background: #d1fae5; color: #065f46;"><?= htmlspecialchars($enfant['classe_nom']) ?></span>
                </div>
                <div style="text-align: right;">
                    <span style="font-size: 13px; color: #64748b;">Statut Inscription</span>
                    <div style="font-weight: bold; color: <?= $enfant['statut_inscription'] == 'Validé' ? '#10b981' : '#f59e0b' ?>;">
                        <?= $enfant['statut_inscription'] ?>
                    </div>
                </div>
            </div>

            <div class="grid-info">
                <div style="background: #f8fafc; padding: 15px; border-radius: 10px;">
                    <div style="font-size: 12px; color: #64748b;">Moyenne Générale</div>
                    <div style="font-size: 20px; font-weight: bold; color: #065f46;"><?= $enfant['moyenne'] ?></div>
                </div>
                <div style="background: #f8fafc; padding: 15px; border-radius: 10px;">
                    <div style="font-size: 12px; color: #64748b;">Absences</div>
                    <div style="font-size: 20px; font-weight: bold; color: #b91c1c;"><?= $enfant['absences'] ?></div>
                </div>
                <div style="background: #f8fafc; padding: 15px; border-radius: 10px;">
                    <div style="font-size: 12px; color: #64748b;">Frais Scolaires</div>
                    <div style="font-size: 18px; font-weight: bold; color: #10b981;">
                        <?= str_replace(['PayÚ', '1pre'], ['Payé', '1ère'], $enfant['statut_paiement']) ?>
                    </div>
                </div>
            </div>

            <div style="margin-top: 25px; border-top: 1px solid #f1f5f9; padding-top: 20px;">
                <h4 style="font-size: 14px; color: #64748b; margin-bottom: 15px;"><i data-feather="dollar-sign" style="width: 14px;"></i> État des paiements :</h4>
                <div style="display: flex; flex-wrap: wrap; gap: 10px;">
                    <?php 
                    $frais_scolaires = ["Frais de Test", "Test d'admission", "1ère Tranche", "2ème Tranche", "3ème Tranche", "4ème Tranche", "5ème Tranche", "6ème Tranche", "7ème Tranche", "8ème Tranche", "9ème Tranche", "Frais Divers"];
                    $payes_stmt = $pdo->prepare("SELECT type_frais FROM paiements WHERE eleve_id = ?");
                    $payes_stmt->execute([$enfant['id']]);
                    $liste_payes = $payes_stmt->fetchAll(PDO::FETCH_COLUMN);

                    foreach ($frais_scolaires as $frais): 
                        $is_paye = (in_array($frais, $liste_payes) || $enfant['statut_paiement'] === 'Soldé');
                    ?>
                    <div style="padding: 6px 12px; border-radius: 8px; font-size: 11px; font-weight: 600; display: flex; align-items: center; gap: 5px; border: 1px solid <?= $is_paye ? '#10b981' : '#fee2e2' ?>; background: <?= $is_paye ? '#d1fae5' : '#fff1f1' ?>; color: <?= $is_paye ? '#065f46' : '#991b1b' ?>;">
                        <i data-feather="<?= $is_paye ? 'check' : 'x' ?>" style="width: 12px; height: 12px;"></i>
                        <?= $frais ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div style="display: flex; gap: 15px; margin-top: 20px;">
                <a href="dashboard_eleve.php?view_as_parent=<?= $enfant['id'] ?>" class="btn-view">Consulter l'horaire et les notes</a>
                <a href="payer_minerval.php?eleve_id=<?= $enfant['id'] ?>" class="btn-view" style="background: #0a1931; display: flex; align-items: center; gap: 8px;">
                    <i data-feather="credit-card" style="width: 16px;"></i> Payer les frais en ligne
                </a>
                <a href="#" style="padding: 10px 20px; color: #059669; text-decoration: none; font-weight: 500;">Contacter l'école</a>
            </div>
        </div>
        <?php endforeach; ?>

        <?php if (empty($enfants)): ?>
        <div class="card" style="text-align: center; padding: 50px;">
            <i data-feather="user-x" style="width: 48px; height: 48px; color: #94a3b8; margin-bottom: 20px;"></i>
            <h3>Aucun enfant trouvé</h3>
            <p style="color: #64748b;">Aucun élève n'est lié à votre numéro de téléphone dans notre base de données.</p>
        </div>
        <?php endif; ?>
    </div>
    <script>feather.replace();</script>
</body>
</html>

