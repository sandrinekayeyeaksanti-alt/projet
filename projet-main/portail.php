<?php
session_start();
require_once 'config.php';

if (isset($_SESSION['role'])) {
    switch ($_SESSION['role']) {
        case 'prefet': header("Location: dashboard_prefet.php"); break;
        case 'secretariat': header("Location: dashboard_secretariat.php"); break;
        case 'enseignant': header("Location: teacher_dashboard.php"); break;
        case 'eleve': header("Location: dashboard_eleve.php"); break;
        case 'parent': header("Location: dashboard_parent.php"); break;
        default: header("Location: login.php");
    }
    exit();
}

// Vérification de la session parent
if (!isset($_SESSION['parent_id'])) {
    header("Location: login.php");
    exit();
}

$parent_id = $_SESSION['parent_id']; // C'est l'ID de l'élève rattaché
$parent_nom = $_SESSION['parent_nom'];
$eleve_nom = $_SESSION['eleve_nom'];

$bulletins = [];
$eleve_info = null;

if (isset($pdo)) {
    try {
        // Infos élève
        $stmt_eleve = $pdo->prepare("SELECT e.*, c.nom AS classe FROM eleves e LEFT JOIN classes c ON e.classe_id = c.id WHERE e.id = :id");
        $stmt_eleve->execute([':id' => $parent_id]);
        $eleve_info = $stmt_eleve->fetch();
        $eleve = $eleve_info; // Pour la compatibilité avec waiting_approval.php

        // VERIFICATION DE SECURITE : Si l'élève n'est pas encore validé
        if ($eleve_info && $eleve_info['statut_inscription'] === 'En attente') {
            include 'waiting_approval.php';
            exit();
        }

        // Bulletins
        $stmt_bull = $pdo->prepare("SELECT b.*, c.nom AS classe FROM bulletins b JOIN eleves e ON b.eleve_id = e.id LEFT JOIN classes c ON e.classe_id = c.id WHERE b.eleve_id = :id ORDER BY b.id DESC");
        $stmt_bull->execute([':id' => $parent_id]);
        $bulletins = $stmt_bull->fetchAll();
    } catch(PDOException $e) {
        $error = "Erreur de récupération des données.";
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Espace Parent - École Belle Vue</title>
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

    <!-- Navbar -->
    <nav class="navbar" style="position: fixed; width: 100%; top: 0; z-index: 1000;">
        <div class="container" style="display: flex; justify-content: space-between; align-items: center;">
            <a href="index.php" class="logo"><img src="assets/img/logo.webp" alt="Logo Belle Vue"></a>
            <div style="display: flex; align-items: center; gap: 20px;">
                <span style="font-weight: 500; font-size: 14px;">
                    <i data-feather="user" style="width: 16px; margin-right: 5px; vertical-align: text-bottom;"></i> 
                    Famille <?= htmlspecialchars($parent_nom) ?>
                </span>
                <a href="logout.php" class="btn btn-outline" style="padding: 8px 15px; font-size: 14px;">Déconnexion</a>
            </div>
        </div>
    </nav>

    <div class="container dashboard-layout" style="margin-top: 80px;">
        <!-- Sidebar -->
        <aside class="sidebar">
            <ul class="sidebar-nav">
                <li><a href="#" class="active"><i data-feather="grid"></i> Tableau de bord</a></li>
                <li><a href="suivi_cursus.php"><i data-feather="trending-up"></i> Suivi de Cursus</a></li>
                <li><a href="#"><i data-feather="file-text"></i> Bulletins & Points</a></li>
                <li><a href="mes_paiements.php"><i data-feather="credit-card"></i> Mes Paiements</a></li>
                <li style="margin-top: 10px;"><a href="payer_minerval.php" style="background: #0a1931; color: white; border-radius: 8px; font-weight: bold;"><i data-feather="shield"></i> Payer le Minerval</a></li>
            </ul>
            <div style="margin-top: 40px; padding: 15px; background: #eff6ff; border-radius: 8px; border: 1px solid #bfdbfe;">
                <h4 style="color: var(--primary-blue); margin-bottom: 5px; font-size: 14px;">Contact École</h4>
                <p style="font-size: 12px; color: var(--text-medium); margin-bottom: 10px;">Pour toute question sur les résultats ou l'inscription.</p>
                <button class="btn btn-primary" style="width: 100%; padding: 8px; font-size: 13px;">Contacter</button>
            </div>
        </aside>

        <!-- Main Content -->
        <main>
            <?php if(isset($_GET['registered'])): ?>
                <div style="background: #dcfce7; color: #166534; padding: 20px; border-radius: var(--radius-md); border: 1px solid #bbf7d0; margin-bottom: 30px; display: flex; align-items: center; gap: 15px;">
                    <i data-feather="check-circle" style="width: 24px; height: 24px;"></i>
                    <div>
                        <strong style="display: block;">Félicitations ! Votre inscription est terminée.</strong>
                        <span style="font-size: 14px;">Votre paiement a été simulé avec succès et votre espace parent est désormais actif.</span>
                    </div>
                </div>
            <?php endif; ?>

            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
                <h2 style="font-size: 28px;">Dossier de <span style="color: var(--primary-blue);"><?= htmlspecialchars($eleve_nom) ?></span></h2>
                <div style="background: white; padding: 8px 15px; border-radius: 20px; font-size: 14px; font-weight: 500; box-shadow: 0 2px 10px rgba(0,0,0,0.05);">
                    Année Scolaire : <span style="color: var(--secondary-blue);">2026-2027</span>
                </div>
            </div>

            <!-- Stats & Profile Info -->
            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px; margin-bottom: 30px;">
                <div class="stat-card">
                    <span class="stat-title">Classe Actuelle</span>
                    <span class="stat-value" style="font-size: 24px;"><?= htmlspecialchars($eleve_info['classe'] ?? 'Non assignée') ?></span>
                    <span class="badge-success stat-badge">En cours</span>
                </div>
                <div class="stat-card">
                    <span class="stat-title">Statut Paiement</span>
                    <span class="stat-value" style="font-size: 24px;"><?= (strpos($eleve_info['statut_paiement'], 'Pay') !== false) ? 'Payé' : htmlspecialchars($eleve_info['statut_paiement'] ?? 'Inconnu') ?></span>
                    <?php if(($eleve_info['statut_paiement'] ?? '') === 'Payé'): ?>
                        <span class="badge-success stat-badge">À jour</span>
                    <?php else: ?>
                        <span class="badge-danger stat-badge" style="background:#fee2e2; color:#b91c1c;">En attente</span>
                    <?php endif; ?>
                </div>
                <div class="stat-card">
                    <span class="stat-title">Sexe / Genre</span>
                    <span class="stat-value" style="font-size: 24px;"><?= ($eleve_info['sexe'] ?? '') == 'M' ? 'Masculin' : 'Féminin' ?></span>
                    <span class="stat-badge" style="background: #e0f2fe; color: #0369a1;">Informations</span>
                </div>
            </div>

            <!-- Detailed Profile Section -->
            <div style="background: white; padding: 25px; border-radius: var(--radius-md); box-shadow: 0 4px 20px rgba(0,0,0,0.03); margin-bottom: 30px;">
                <h3 style="font-size: 20px; margin-bottom: 20px; color: var(--primary-blue); display: flex; align-items: center; gap: 10px;">
                    <i data-feather="user"></i> Profil Complet de l'Élève
                </h3>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px;">
                    <div>
                        <p style="margin-bottom: 10px;"><strong style="color: var(--text-medium);">Lieu et Date de Naissance :</strong> <br><?= htmlspecialchars($eleve_info['lieu_date_naissance'] ?? 'Non renseigné') ?></p>
                        <p style="margin-bottom: 10px;"><strong style="color: var(--text-medium);">École de provenance :</strong> <br><?= htmlspecialchars($eleve_info['ecole_provenance'] ?? 'Non renseigné') ?></p>
                    </div>
                    <div>
                        <p style="margin-bottom: 10px;"><strong style="color: var(--text-medium);">Tuteur Responsable :</strong> <br><?= htmlspecialchars($eleve_info['tuteur_nom'] ?? 'Non renseigné') ?></p>
                        <p style="margin-bottom: 10px;"><strong style="color: var(--text-medium);">Téléphone :</strong> <br><?= htmlspecialchars($eleve_info['tuteur_tel'] ?? 'Non renseigné') ?></p>
                        <p style="margin-bottom: 10px;"><strong style="color: var(--text-medium);">Adresse :</strong> <br><?= htmlspecialchars($eleve_info['tuteur_adresse'] ?? 'Non renseigné') ?></p>
                    </div>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 30px; margin-top: 30px;">
                <!-- Report Cards (Bulletins) -->
                <div>
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                        <h3 style="font-size: 20px;">Derniers Bulletins</h3>
                    </div>
                    
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Période</th>
                                    <th>Classe</th>
                                    <th>Points</th>
                                    <th>%</th>
                                    <th>Statut</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(empty($bulletins)): ?>
                                    <tr>
                                        <td colspan="5" style="text-align: center; color: var(--text-medium); padding: 20px;">Aucun bulletin disponible pour le moment.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach($bulletins as $bull): ?>
                                        <tr>
                                            <td style="font-weight: 500;"><?= htmlspecialchars($bull['periode']) ?></td>
                                            <td><?= htmlspecialchars($bull['classe']) ?></td>
                                            <td><strong><?= htmlspecialchars($bull['points_obtenus']) ?></strong> / <?= htmlspecialchars($bull['points_max']) ?></td>
                                            <td style="color: #166534; font-weight: bold;"><?= htmlspecialchars($bull['pourcentage']) ?>%</td>
                                            <td><span class="badge-success stat-badge"><?= htmlspecialchars($bull['statut']) ?></span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Notifications -->
                <div>
                    <h3 style="font-size: 20px; margin-bottom: 15px;">Avis Importants</h3>
                    <div style="background: white; border-radius: var(--radius-md); padding: 20px; box-shadow: 0 4px 20px rgba(0,0,0,0.03);">
                        <div style="border-left: 3px solid #10b981; padding-left: 15px;">
                            <h5 style="margin-bottom: 5px; font-size: 14px; display: flex; align-items: center; gap: 5px;"><i data-feather="check-circle" style="width: 14px; color: #10b981;"></i> Dossier actif</h5>
                            <p style="font-size: 13px; color: var(--text-medium);">L'inscription de votre enfant est validée et le compte est actif.</p>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="assets/js/main.js"></script>
    <script>
        feather.replace();
    </script>
</body>
</html>


