<?php
session_start();
require_once 'config.php';

// Vérification de la session parent
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'parent') {
    header("Location: login.php");
    exit();
}

$parent_tel = $_SESSION['username'] ?? '';
$parent_nom = $_SESSION['parent_nom'] ?? 'Parent';

$enfants = [];
$historique_paiements = [];

if (isset($pdo) && !empty($parent_tel)) {
    try {
        // 1. Récupérer tous les enfants du parent
        $stmt_enfants = $pdo->prepare("
            SELECT e.*, c.nom as classe_nom, c.option_nom 
            FROM eleves e
            LEFT JOIN classes c ON e.classe_id = c.id
            WHERE TRIM(e.tuteur_tel) = ?
        ");
        $stmt_enfants->execute([$parent_tel]);
        $enfants = $stmt_enfants->fetchAll();

        if (!empty($enfants)) {
            // Créer une liste d'IDs des enfants pour la requête suivante
            $ids_enfants = array_column($enfants, 'id');
            $in_clause = implode(',', array_fill(0, count($ids_enfants), '?'));

            // 2. Récupérer l'historique de tous les paiements de ces enfants
            $stmt_pay = $pdo->prepare("
                SELECT p.*, e.nom as eleve_nom, e.post_nom_prenom as eleve_prenom 
                FROM paiements p
                JOIN eleves e ON p.eleve_id = e.id
                WHERE p.eleve_id IN ($in_clause)
                ORDER BY p.date_paiement DESC
            ");
            $stmt_pay->execute($ids_enfants);
            $historique_paiements = $stmt_pay->fetchAll();
        }
    } catch(PDOException $e) {
        $error = "Erreur de récupération des données de paiement.";
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historique des Paiements - École Belle Vue</title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?= time() ?>">
    <script src="https://unpkg.com/feather-icons"></script>
    <style>
        .dashboard-layout {
            display: grid;
            grid-template-columns: 280px 1fr;
            gap: 30px;
            margin-top: 40px;
            align-items: start;
        }
        .main-content {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.05);
            border: 1px solid rgba(255, 255, 255, 0.5);
        }
        .payment-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin-top: 20px;
            border-radius: 12px;
            overflow: hidden;
            border: 1px solid #e2e8f0;
        }
        .payment-table th, .payment-table td {
            padding: 16px 20px;
            text-align: left;
        }
        .payment-table th {
            background: #0a1931;
            color: white;
            font-weight: 600;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .payment-table td {
            background: white;
            border-bottom: 1px solid #f1f5f9;
            font-size: 14px;
        }
        .payment-table tr:last-child td {
            border-bottom: none;
        }
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 6px 12px;
            border-radius: 50px;
            font-size: 12px;
            font-weight: 600;
        }
        .badge-success { background: #dcfce7; color: #166534; }
        .badge-info { background: #e0f2fe; color: #0369a1; }
        .btn-receipt {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #10b981;
            color: white;
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s;
            box-shadow: 0 4px 6px -1px rgba(16, 185, 129, 0.2);
        }
        .btn-receipt:hover {
            background: #059669;
            transform: translateY(-1px);
        }
    </style>
</head>
<body>

    <!-- Background Video -->
    <div class="bg-video-container">
        <video autoplay muted loop playsinline>
            <source src="assets/media/video_belle_vue.mp4" type="video/mp4">
        </video>
    </div>

    <!-- Navbar -->
    <nav class="navbar" style="position: fixed; width: 100%; top: 0; z-index: 1000;">
        <div class="container" style="display: flex; justify-content: space-between; align-items: center;">
            <a href="portail.php" class="logo"><img src="assets/img/logo.webp" alt="Logo Belle Vue" style="height: 40px;"></a>
            <div style="display: flex; align-items: center; gap: 20px;">
                <span style="font-weight: 500; font-size: 14px; background: white; padding: 8px 15px; border-radius: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); display: flex; align-items: center; gap: 8px;">
                    <i data-feather="user" style="width: 16px; color: #0a1931;"></i> 
                    Famille <?= htmlspecialchars($parent_nom) ?>
                </span>
                <a href="logout.php" class="btn btn-outline" style="padding: 8px 15px; font-size: 14px;">Déconnexion</a>
            </div>
        </div>
    </nav>

    <div class="container dashboard-layout" style="margin-top: 100px; padding-bottom: 50px;">
        <!-- Sidebar -->
        <aside class="sidebar" style="background: white; border-radius: var(--radius-lg); padding: 20px; box-shadow: 0 4px 20px rgba(0,0,0,0.03); height: fit-content;">
            <ul class="sidebar-nav">
                <li><a href="portail.php"><i data-feather="grid"></i> Tableau de bord</a></li>
                <li><a href="suivi_cursus.php"><i data-feather="trending-up"></i> Suivi de Cursus</a></li>
                <li><a href="mes_paiements.php" class="active" style="background: var(--light-blue); color: var(--primary-blue); font-weight: 500;"><i data-feather="credit-card"></i> Historique Paiements</a></li>
            </ul>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <div style="margin-bottom: 30px;">
                <h2 style="font-size: 26px; display: flex; align-items: center; gap: 12px; color: #0a1931; font-family: 'Outfit', sans-serif; font-weight: 700; margin: 0;">
                    <i data-feather="file-text" style="color: #0a1931; width: 30px; height: 30px;"></i>
                    Historique des <span style="color: #f59e0b;">Paiements</span>
                </h2>
                <p style="color: #64748b; margin-top: 8px; font-size: 15px;">Retrouvez ici tous les reçus officiels des frais scolaires réglés pour vos enfants.</p>
            </div>

            <?php if (isset($error)): ?>
                <div style="background: #fee2e2; color: #b91c1c; padding: 15px; border-radius: 10px; margin-bottom: 25px; font-size: 14px;">
                    <?= $error ?>
                </div>
            <?php endif; ?>

            <!-- Section Enfants -->
            <div style="margin-bottom: 40px;">
                <h3 style="font-size: 18px; color: #0a1931; margin-bottom: 15px; font-weight: 600; display: flex; align-items: center; gap: 8px;">
                    <i data-feather="users" style="width: 18px;"></i> Vos Enfants Enregistrés
                </h3>
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 20px;">
                    <?php foreach ($enfants as $e): ?>
                        <div style="background: white; border-radius: 16px; padding: 20px; border: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <strong style="font-size: 15px; color: #0a1931; display: block;"><?= htmlspecialchars($e['nom'] . ' ' . $e['post_nom_prenom']) ?></strong>
                                <span style="font-size: 12px; color: #64748b;"><?= htmlspecialchars($e['classe_nom']) ?> <?= htmlspecialchars($e['option_nom']) ?></span>
                            </div>
                            <a href="payer_minerval.php?eleve_id=<?= $e['id'] ?>" class="btn btn-primary" style="padding: 6px 12px; font-size: 12px; border-radius: 8px;">
                                <i data-feather="dollar-sign" style="width: 12px;"></i> Payer
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Table historique -->
            <div>
                <h3 style="font-size: 18px; color: #0a1931; margin-bottom: 15px; font-weight: 600; display: flex; align-items: center; gap: 8px;">
                    <i data-feather="clock" style="width: 18px;"></i> Liste des paiements effectués
                </h3>
                <table class="payment-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Enfant</th>
                            <th>Type de frais</th>
                            <th>Montant</th>
                            <th>Méthode</th>
                            <th>Référence</th>
                            <th style="text-align: right;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($historique_paiements as $p): ?>
                        <tr>
                            <td><?= date('d/m/Y H:i', strtotime($p['date_paiement'])) ?></td>
                            <td><strong><?= htmlspecialchars($p['eleve_nom'] . ' ' . $p['eleve_prenom']) ?></strong></td>
                            <td><?= htmlspecialchars($p['type_frais']) ?></td>
                            <td style="font-weight: 700; color: #0a1931;"><?= number_format($p['montant'], 2) ?> $</td>
                            <td>
                                <span class="badge badge-info">
                                    <i data-feather="credit-card" style="width: 12px;"></i> <?= htmlspecialchars($p['methode_paiement']) ?>
                                </span>
                            </td>
                            <td style="font-family: monospace; font-size: 12px; color: #64748b;"><?= htmlspecialchars($p['reference_transaction'] ?: 'N/A') ?></td>
                            <td style="text-align: right;">
                                <a href="recu_paiement.php?id=<?= $p['id'] ?>" target="_blank" class="btn-receipt">
                                    <i data-feather="printer" style="width: 14px;"></i> Reçu PDF
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($historique_paiements)): ?>
                        <tr>
                            <td colspan="7" style="text-align: center; color: #94a3b8; padding: 40px 20px;">
                                <i data-feather="inbox" style="width: 48px; height: 48px; margin-bottom: 15px; opacity: 0.5;"></i>
                                <p style="margin: 0; font-size: 15px;">Aucun paiement trouvé dans l'historique.</p>
                            </td>
                        </tr>
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
