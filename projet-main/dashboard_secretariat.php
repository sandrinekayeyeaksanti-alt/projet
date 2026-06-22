<?php
session_start();
require_once 'config.php';

// Vérification du rôle Secrétariat
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'secretariat') {
    header("Location: login_secretariat.php");
    exit();
}

// Action: Valider/Refuser Élève (Le secrétariat gère désormais cela)
if (isset($_GET['action']) && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $new_status = ($_GET['action'] === 'valider') ? 'Validé' : (($_GET['action'] === 'refuser') ? 'Refusé' : null);
    
    if ($new_status) {
        $stmt = $pdo->prepare("UPDATE eleves SET statut_inscription = :status WHERE id = :id");
        $stmt->execute(['status' => $new_status, 'id' => $id]);
        header("Location: dashboard_secretariat.php?msg=Statut de l'élève mis à jour");
        exit();
    }
}

// Récupération des statistiques pour le secrétariat
$stats = [
    'total_eleves' => 0,
    'en_attente' => 0,
    'inscrits_aujourdhui' => 0
];

if (isset($pdo)) {
    $stats['total_eleves'] = $pdo->query("SELECT COUNT(*) FROM eleves")->fetchColumn();
    $stats['en_attente'] = $pdo->query("SELECT COUNT(*) FROM eleves WHERE statut_inscription = 'En attente'")->fetchColumn();
    $stats['inscrits_aujourdhui'] = $pdo->query("SELECT COUNT(*) FROM eleves WHERE DATE(date_inscription) = CURDATE()")->fetchColumn();
}

// Liste des élèves en attente de validation
$eleves_attente = $pdo->query("SELECT e.*, c.nom as classe_nom FROM eleves e LEFT JOIN classes c ON e.classe_id = c.id WHERE e.statut_inscription = 'En attente' ORDER BY e.date_inscription DESC")->fetchAll();

// Liste des dossiers déjà traités (Validés ou Refusés)
$dossiers_traites = $pdo->query("SELECT e.*, c.nom as classe_nom FROM eleves e LEFT JOIN classes c ON e.classe_id = c.id WHERE e.statut_inscription IN ('Validé', 'Refusé') ORDER BY e.date_inscription DESC LIMIT 10")->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Secrétariat | École Belle Vue</title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?= time() ?>">
    <script src="https://unpkg.com/feather-icons"></script>
    <style>
        .dashboard-container { display: flex; min-height: 100vh; }
        .sidebar { width: 250px; background: #1e3a8a; color: white; padding: 20px; }
        .main-content { flex: 1; padding: 40px; background: #f1f5f9; }
        .stat-card { background: white; padding: 25px; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); transition: all 0.3s ease; cursor: pointer; text-decoration: none; display: block; }
        .stat-card:hover { transform: translateY(-5px); box-shadow: 0 10px 15px rgba(0,0,0,0.1); border-color: #3b82f6; }
        .table-container { background: white; border-radius: 12px; padding: 25px; margin-top: 30px; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 12px; border-bottom: 2px solid #e2e8f0; color: #64748b; }
        td { padding: 12px; border-bottom: 1px solid #e2e8f0; }
        .btn-action { padding: 6px 12px; border-radius: 6px; text-decoration: none; font-size: 13px; font-weight: 600; }
        .btn-success { background: #10b981; color: white; }
        .btn-danger { background: #ef4444; color: white; }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <aside class="sidebar">
            <h2 style="margin-bottom: 30px;">Belle Vue <span style="font-weight: 300;">Secrétariat</span></h2>
            <nav>
                <ul style="list-style: none; padding: 0;">
                    <li style="margin-bottom: 15px;"><a href="dashboard_secretariat.php" style="color: white; text-decoration: none; display: flex; align-items: center; gap: 10px;"><i data-feather="home"></i> Accueil</a></li>
                    <li style="margin-bottom: 15px;"><a href="inscriptions_secretariat.php" style="color: #bfdbfe; text-decoration: none; display: flex; align-items: center; gap: 10px;"><i data-feather="user-check"></i> Inscriptions</a></li>
                    <li style="margin-bottom: 15px;"><a href="gestion_paiements.php" style="color: #bfdbfe; text-decoration: none; display: flex; align-items: center; gap: 10px;"><i data-feather="credit-card"></i> Paiements & Reçus</a></li>
                    <li style="margin-bottom: 15px;"><a href="gestion_horaires.php" style="color: #bfdbfe; text-decoration: none; display: flex; align-items: center; gap: 10px;"><i data-feather="calendar"></i> Horaires</a></li>
                    <li style="margin-top: 50px;"><a href="logout.php" style="color: #fca5a5; text-decoration: none; display: flex; align-items: center; gap: 10px;"><i data-feather="log-out"></i> Déconnexion</a></li>
                </ul>
            </nav>
        </aside>

        <main class="main-content">
            <header style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
                <h1>Gestion Administrative</h1>
                <div style="display: flex; align-items: center; gap: 10px;">
                    <i data-feather="user"></i> <span><?= htmlspecialchars($_SESSION['nom_complet']) ?></span>
                </div>
            </header>

            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px;">
                <a href="inscriptions_secretariat.php" class="stat-card">
                    <div style="color: #64748b; font-size: 14px;">Total Élèves</div>
                    <div style="font-size: 28px; font-weight: bold;"><?= $stats['total_eleves'] ?></div>
                    <div style="font-size: 12px; color: #3b82f6; margin-top: 5px;">Voir tout →</div>
                </a>
                <a href="#candidatures" class="stat-card">
                    <div style="color: #64748b; font-size: 14px;">Demandes en attente</div>
                    <div style="font-size: 28px; font-weight: bold; color: #f59e0b;"><?= $stats['en_attente'] ?></div>
                    <div style="font-size: 12px; color: #f59e0b; margin-top: 5px;">Traiter maintenant ↓</div>
                </a>
                <a href="#historique" class="stat-card">
                    <div style="color: #64748b; font-size: 14px;">Inscriptions ce jour</div>
                    <div style="font-size: 28px; font-weight: bold; color: #3b82f6;"><?= $stats['inscrits_aujourdhui'] ?></div>
                    <div style="font-size: 12px; color: #3b82f6; margin-top: 5px;">Voir l'historique ↓</div>
                </a>
            </div>

            <div class="table-container" id="candidatures">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
                    <div>
                        <h3 style="color: var(--primary-blue); font-size: 20px; margin-bottom: 5px;">Candidatures & Tests d'Admission</h3>
                        <p style="font-size: 13px; color: var(--text-medium);">Liste des dossiers en attente des résultats du test à l'école.</p>
                    </div>
                    <a href="inscription.php" class="btn-action btn-success" style="display: flex; align-items: center; gap: 8px; padding: 12px 24px; border-radius: 10px; text-decoration: none;">
                        <i data-feather="user-plus" style="width: 18px;"></i> Nouveau Dossier
                    </a>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>Candidat</th>
                            <th>Classe</th>
                            <th>Statut</th>
                            <th>Date Demande</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($eleves_attente as $eleve): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($eleve['nom'] . ' ' . $eleve['post_nom_prenom']) ?></strong></td>
                            <td><?= htmlspecialchars($eleve['classe_nom']) ?></td>
                            <td>
                                <span style="background: #fff7ed; color: #c2410c; padding: 5px 12px; border-radius: 50px; font-size: 11px; font-weight: bold; border: 1px solid #fdba74;">
                                    📝 À TESTER À L'ÉCOLE
                                </span>
                            </td>
                            <td><?= date('d/m/Y', strtotime($eleve['date_inscription'])) ?></td>
                            <td style="display: flex; gap: 5px;">
                                <a href="inspecter_dossier.php?id=<?= $eleve['id'] ?>" class="btn-action" style="background: #1e3a8a; color: white; font-size: 11px; white-space: nowrap; display: flex; align-items: center; gap: 5px;">
                                    <i data-feather="folder" style="width: 14px;"></i> Inspecter Dossier
                                </a>
                                <a href="?action=valider&id=<?= $eleve['id'] ?>" class="btn-action btn-success" style="font-size: 11px; white-space: nowrap;">✓ Valider (Test OK)</a>
                                <a href="?action=refuser&id=<?= $eleve['id'] ?>" class="btn-action btn-danger" style="font-size: 11px; white-space: nowrap;">✕ Refuser</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($eleves_attente)): ?>
                        <tr><td colspan="5" style="text-align: center; color: #94a3b8; padding: 20px;">Aucun dossier en attente de vérification.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Nouveau tableau : Dossiers Traités -->
            <div class="table-container" id="historique" style="margin-top: 40px; border-top: 4px solid #cbd5e1;">
                <h3 style="color: #64748b;">Historique des Dossiers Traités</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Nom de l'élève</th>
                            <th>Classe</th>
                            <th>Statut</th>
                            <th>Date Traitement</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($dossiers_traites as $eleve): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($eleve['nom'] . ' ' . $eleve['post_nom_prenom']) ?></strong></td>
                            <td><?= htmlspecialchars($eleve['classe_nom']) ?></td>
                            <td>
                                <?php if($eleve['statut_inscription'] === 'Validé'): ?>
                                    <span style="background: #dcfce7; color: #166534; padding: 5px 12px; border-radius: 50px; font-size: 11px; font-weight: bold; border: 1px solid #bbf7d0;">
                                        ✓ Admis
                                    </span>
                                <?php else: ?>
                                    <span style="background: #fee2e2; color: #991b1b; padding: 5px 12px; border-radius: 50px; font-size: 11px; font-weight: bold; border: 1px solid #fecaca;">
                                        ✕ Refusé
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td><?= date('d/m/Y', strtotime($eleve['date_inscription'])) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($dossiers_traites)): ?>
                        <tr><td colspan="4" style="text-align: center; color: #94a3b8; padding: 20px;">Aucun dossier traité pour le moment.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                <div style="margin-top: 15px; text-align: center;">
                    <a href="inscriptions_secretariat.php" style="font-size: 14px; color: var(--primary-blue); text-decoration: none;">Voir la liste complète →</a>
                </div>
            </div>
        </main>
    </div>
    <script>feather.replace();</script>
</body>
</html>

