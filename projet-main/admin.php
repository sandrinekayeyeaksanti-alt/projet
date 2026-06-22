<?php
session_start();
require_once 'config.php';

// Vérification de la session admin
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'prefet', 'secretariat', 'enseignant'])) {
    header("Location: login.php");
    exit();
}

// Redirection vers le dashboard spécifique au rôle
if ($_SESSION['role'] === 'prefet') {
    header("Location: dashboard_prefet.php");
    exit();
} elseif ($_SESSION['role'] === 'secretariat') {
    header("Location: dashboard_secretariat.php");
    exit();
} elseif ($_SESSION['role'] === 'enseignant') {
    header("Location: teacher_dashboard.php");
    exit();
}

$nb_eleves = 0;
$eleves = [];

if (isset($pdo)) {
    try {
        $stmt_count = $pdo->query("SELECT COUNT(*) as nb FROM eleves");
        $nb_eleves = $stmt_count->fetch()['nb'];

        $stmt_eleves = $pdo->query("SELECT eleves.*, classes.nom as nom_classe, classes.option_nom FROM eleves LEFT JOIN classes ON eleves.classe_id = classes.id ORDER BY eleves.id DESC");
        $eleves = $stmt_eleves->fetchAll();
    } catch(PDOException $e) {
        $error = "Erreur PDO";
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administration - École Belle Vue</title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?= time() ?>">
    <script src="https://unpkg.com/feather-icons"></script>
    <style>
        .admin-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.03);
        }
        .admin-table th, .admin-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #f1f5f9;
        }
        .admin-table th {
            background: var(--primary-blue);
            color: white;
            font-weight: 500;
        }
        .admin-table tr:hover {
            background: #f8fafc;
        }
    </style>
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
            <a href="#" class="logo"><img src="assets/img/logo.webp" alt="Logo Belle Vue Admin"></a>
            <div style="display: flex; align-items: center; gap: 20px; color: white;">
                <span style="font-weight: 500; font-size: 14px;">
                    <i data-feather="shield" style="width: 16px; margin-right: 5px; vertical-align: text-bottom;"></i> 
                    <?= htmlspecialchars($_SESSION['username']) ?>
                </span>
                <a href="logout.php" class="btn btn-outline" style="padding: 8px 15px; font-size: 14px; border-color: white; color: white;">Déconnexion</a>
            </div>
        </div>
    </nav>

    <div class="container dashboard-layout" style="margin-top: 80px;">
        <aside class="sidebar">
            <ul class="sidebar-nav">
                <li><a href="admin.php" class="active"><i data-feather="users"></i> Liste des élèves</a></li>
                <li><a href="inscription.php"><i data-feather="plus-circle" style="color: var(--secondary-blue);"></i> Inscrire un élève</a></li>
                <li><a href="saisir_bulletin.php"><i data-feather="file-plus"></i> Saisir Bulletins</a></li>
                <li><a href="#"><i data-feather="settings"></i> Paramètres API</a></li>
            </ul>
        </aside>

        <main>
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
                <h2 style="font-size: 28px;">Tableau de bord <span style="color: var(--primary-blue);">Administration</span></h2>
            </div>

            <div class="stat-grid" style="margin-bottom: 30px;">
                <div class="stat-card">
                    <span class="stat-title">Total Inscrits</span>
                    <span class="stat-value" style="font-size: 28px;"><?= $nb_eleves ?></span>
                </div>
            </div>

            <h3 style="font-size: 20px; margin-bottom: 15px;">Dernières inscriptions</h3>
            
            <div style="overflow-x: auto;">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nom & Prénom</th>
                            <th>Classe</th>
                            <th>Tuteur</th>
                            <th>Téléphone</th>
                            <th>Statut Paiement</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($eleves)): ?>
                            <tr><td colspan="7" style="text-align:center;">Aucun élève trouvé.</td></tr>
                        <?php else: ?>
                            <?php foreach ($eleves as $eleve): ?>
                                <tr>
                                    <td>#<?= $eleve['id'] ?></td>
                                    <td style="font-weight: 500;"><?= htmlspecialchars($eleve['nom'] . ' ' . $eleve['post_nom_prenom']) ?></td>
                                    <td>
                                        <?= htmlspecialchars($eleve['nom_classe'] ?? 'Non assignée') ?>
                                        <?= (isset($eleve['option_nom']) && $eleve['option_nom'] !== 'Générale') ? ' - ' . htmlspecialchars($eleve['option_nom']) : '' ?>
                                    </td>
                                    <td><?= htmlspecialchars($eleve['tuteur_nom']) ?></td>
                                    <td><?= htmlspecialchars($eleve['tuteur_tel']) ?></td>
                                    <td>
                                        <?php if ($eleve['statut_paiement'] === 'Payé'): ?>
                                            <span class="badge-success stat-badge" style="margin:0;">Payé</span>
                                        <?php else: ?>
                                            <span class="badge-danger stat-badge" style="margin:0; background:#fee2e2; color:#b91c1c;">En attente</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button class="btn btn-outline" style="padding: 4px 8px; font-size: 12px;"><i data-feather="edit" style="width:14px;"></i></button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        </main>
    </div>

    <script>
        feather.replace();
    </script>
</body>
</html>


