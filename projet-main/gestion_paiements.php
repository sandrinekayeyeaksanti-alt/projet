<?php
session_start();
require_once 'config.php';

// Vérification du rôle Secrétariat ou Préfet
if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'secretariat' && $_SESSION['role'] !== 'prefet')) {
    header("Location: login.php");
    exit();
}

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$fee_type = isset($_GET['fee_type']) ? trim($_GET['fee_type']) : '';
$method = isset($_GET['method']) ? trim($_GET['method']) : '';

$payments = [];
$total_collected = 0;
$total_momo = 0;
$total_cash = 0;

if (isset($pdo)) {
    try {
        // Construction de la requête avec filtres
        $query = "
            SELECT p.*, e.nom as eleve_nom, e.post_nom_prenom as eleve_prenom, c.nom as classe_nom
            FROM paiements p
            JOIN eleves e ON p.eleve_id = e.id
            LEFT JOIN classes c ON e.classe_id = c.id
            WHERE 1=1
        ";
        $params = [];

        if (!empty($search)) {
            $query .= " AND (e.nom LIKE ? OR e.post_nom_prenom LIKE ? OR p.reference_transaction LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }

        if (!empty($fee_type)) {
            $query .= " AND p.type_frais = ?";
            $params[] = $fee_type;
        }

        if (!empty($method)) {
            $query .= " AND p.methode_paiement = ?";
            $params[] = $method;
        }

        $query .= " ORDER BY p.date_paiement DESC";

        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $payments = $stmt->fetchAll();

        // Calculer les statistiques globales de collecte
        $total_collected = $pdo->query("SELECT SUM(montant) FROM paiements")->fetchColumn() ?: 0;
        $total_momo = $pdo->query("SELECT SUM(montant) FROM paiements WHERE methode_paiement = 'Shwary Mobile Money'")->fetchColumn() ?: 0;
        $total_cash = $pdo->query("SELECT SUM(montant) FROM paiements WHERE methode_paiement = 'Espèces'")->fetchColumn() ?: 0;

    } catch (PDOException $e) {
        $error = "Erreur de chargement des transactions.";
    }
}

$frais_list = [
    "Frais de Test", "Test d'admission", "1ère Tranche", "2ème Tranche", "3ème Tranche", 
    "4ème Tranche", "5ème Tranche", "6ème Tranche", "7ème Tranche", 
    "8ème Tranche", "9ème Tranche", "Frais Divers"
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Historique des Paiements | Secrétariat</title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?= time() ?>">
    <script src="https://unpkg.com/feather-icons"></script>
    <style>
        .dashboard-container { display: flex; min-height: 100vh; }
        .sidebar { width: 250px; background: #1e3a8a; color: white; padding: 20px; }
        .main-content { flex: 1; padding: 40px; background: #f1f5f9; }
        .table-container { background: white; border-radius: 12px; padding: 25px; margin-top: 30px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
        
        /* Stats grid */
        .stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; padding: 20px; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); display: flex; align-items: center; gap: 15px; }
        .stat-icon { width: 50px; height: 50px; border-radius: 10px; display: flex; align-items: center; justify-content: center; }

        /* Filter bar */
        .filter-bar { background: white; padding: 20px; border-radius: 12px; display: flex; gap: 15px; align-items: flex-end; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
        .filter-group { display: flex; flex-direction: column; gap: 5px; flex: 1; }
        
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 12px; border-bottom: 2px solid #e2e8f0; color: #64748b; font-size: 13px; }
        td { padding: 14px 12px; border-bottom: 1px solid #e2e8f0; font-size: 14px; }
        
        .badge { padding: 4px 10px; border-radius: 50px; font-size: 11px; font-weight: bold; display: inline-flex; align-items: center; gap: 4px; }
        .badge-momo { background: #e0f2fe; color: #0369a1; }
        .badge-cash { background: #dcfce7; color: #166534; }
        
        .btn-action { padding: 6px 12px; border-radius: 6px; text-decoration: none; font-size: 13px; font-weight: 600; display: inline-flex; align-items: center; gap: 5px; }
        .btn-primary { background: #1e3a8a; color: white; }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <h2 style="margin-bottom: 30px;">Belle Vue <span style="font-weight: 300;">Secrétariat</span></h2>
            <nav>
                <ul style="list-style: none; padding: 0;">
                    <li style="margin-bottom: 15px;"><a href="dashboard_secretariat.php" style="color: #bfdbfe; text-decoration: none; display: flex; align-items: center; gap: 10px;"><i data-feather="home"></i> Accueil</a></li>
                    <li style="margin-bottom: 15px;"><a href="inscriptions_secretariat.php" style="color: #bfdbfe; text-decoration: none; display: flex; align-items: center; gap: 10px;"><i data-feather="user-check"></i> Inscriptions</a></li>
                    <li style="margin-bottom: 15px;"><a href="gestion_paiements.php" style="color: white; text-decoration: none; display: flex; align-items: center; gap: 10px; font-weight: 600;"><i data-feather="credit-card"></i> Paiements & Reçus</a></li>
                    <li style="margin-bottom: 15px;"><a href="gestion_horaires.php" style="color: #bfdbfe; text-decoration: none; display: flex; align-items: center; gap: 10px;"><i data-feather="calendar"></i> Horaires</a></li>
                    <li style="margin-top: 50px;"><a href="logout.php" style="color: #fca5a5; text-decoration: none; display: flex; align-items: center; gap: 10px;"><i data-feather="log-out"></i> Déconnexion</a></li>
                </ul>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <header style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
                <div>
                    <h1 style="font-size: 26px; color: #0a1931; margin: 0;">Gestion Financière & Recettes</h1>
                    <p style="color: #64748b; margin-top: 5px;">Suivi de tous les règlements de frais scolaires effectués en ligne et à la caisse.</p>
                </div>
                <div style="display: flex; align-items: center; gap: 10px;">
                    <i data-feather="user"></i> <span><?= htmlspecialchars($_SESSION['nom_complet']) ?></span>
                </div>
            </header>

            <!-- Stats -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon" style="background: #e0e7ff; color: #4338ca;"><i data-feather="trending-up"></i></div>
                    <div>
                        <div style="color: #64748b; font-size: 13px;">Total Recettes</div>
                        <div style="font-size: 22px; font-weight: bold; color: #0a1931;"><?= number_format($total_collected, 2) ?> $</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background: #e0f2fe; color: #0369a1;"><i data-feather="smartphone"></i></div>
                    <div>
                        <div style="color: #64748b; font-size: 13px;">Collecte Mobile Money (Shwary)</div>
                        <div style="font-size: 22px; font-weight: bold; color: #0369a1;"><?= number_format($total_momo, 2) ?> $</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background: #dcfce7; color: #166534;"><i data-feather="dollar-sign"></i></div>
                    <div>
                        <div style="color: #64748b; font-size: 13px;">Caisse Physique (Espèces)</div>
                        <div style="font-size: 22px; font-weight: bold; color: #166534;"><?= number_format($total_cash, 2) ?> $</div>
                    </div>
                </div>
            </div>

            <!-- Filter Bar -->
            <form method="GET" action="" class="filter-bar">
                <div class="filter-group">
                    <label style="font-size: 12px; font-weight: 600; color: #64748b;">Rechercher élève / référence</label>
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Ex: Kabange..." class="form-control" style="padding: 10px;">
                </div>
                <div class="filter-group">
                    <label style="font-size: 12px; font-weight: 600; color: #64748b;">Type de frais</label>
                    <select name="fee_type" class="form-control" style="padding: 10px;">
                        <option value="">Tous les frais</option>
                        <?php foreach($frais_list as $f): ?>
                            <option value="<?= htmlspecialchars($f) ?>" <?= $fee_type === $f ? 'selected' : '' ?>><?= htmlspecialchars($f) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label style="font-size: 12px; font-weight: 600; color: #64748b;">Méthode</label>
                    <select name="method" class="form-control" style="padding: 10px;">
                        <option value="">Toutes les méthodes</option>
                        <option value="Shwary Mobile Money" <?= $method === 'Shwary Mobile Money' ? 'selected' : '' ?>>Shwary Mobile Money</option>
                        <option value="Espèces" <?= $method === 'Espèces' ? 'selected' : '' ?>>Espèces</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary" style="padding: 12px 24px; border-radius: 8px;"><i data-feather="filter" style="width: 16px;"></i> Filtrer</button>
                <a href="gestion_paiements.php" class="btn btn-outline" style="padding: 12px 24px; border-radius: 8px; text-decoration: none; border: 1px solid #cbd5e1; color: #475569;">Réinitialiser</a>
            </form>

            <!-- Table of payments -->
            <div class="table-container">
                <h3 style="margin-bottom: 20px; color: #0a1931;">Transactions Enregistrées</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Élève</th>
                            <th>Classe</th>
                            <th>Type de frais</th>
                            <th>Montant</th>
                            <th>Mode</th>
                            <th>Référence</th>
                            <th style="text-align: right;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($payments as $p): ?>
                        <tr>
                            <td><?= date('d/m/Y H:i', strtotime($p['date_paiement'])) ?></td>
                            <td><strong><?= htmlspecialchars($p['eleve_nom'] . ' ' . $p['eleve_prenom']) ?></strong></td>
                            <td><?= htmlspecialchars($p['classe_nom'] ?: 'N/A') ?></td>
                            <td><?= htmlspecialchars($p['type_frais']) ?></td>
                            <td style="font-weight: 700; color: #0a1931;"><?= number_format($p['montant'], 2) ?> $</td>
                            <td>
                                <?php if($p['methode_paiement'] === 'Shwary Mobile Money'): ?>
                                    <span class="badge badge-momo"><i data-feather="smartphone" style="width: 12px;"></i> Shwary</span>
                                <?php else: ?>
                                    <span class="badge badge-cash"><i data-feather="dollar-sign" style="width: 12px;"></i> Espèces</span>
                                <?php endif; ?>
                            </td>
                            <td style="font-family: monospace; font-size: 12px; color: #64748b;"><?= htmlspecialchars($p['reference_transaction'] ?: 'N/A') ?></td>
                            <td style="text-align: right;">
                                <a href="recu_paiement.php?id=<?= $p['id'] ?>" target="_blank" class="btn-action btn-primary" style="background: #10b981; color: white;">
                                    <i data-feather="printer" style="width: 14px;"></i> Reçu
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(empty($payments)): ?>
                        <tr><td colspan="8" style="text-align: center; color: #94a3b8; padding: 30px;">Aucune transaction trouvée.</td></tr>
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
