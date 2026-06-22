<?php
session_start();
require_once 'config.php';

// Vérification du rôle Préfet
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'prefet') {
    header("Location: login_prefet.php");
    exit();
}

// Récupération des statistiques globales pour le Préfet
$stats = [
    'total_eleves' => 0,
    'total_profs' => 0,
    'total_secretaires' => 0,
    'staff_attente' => 0
];

if (isset($pdo)) {
    try {
        $stats['total_eleves'] = $pdo->query("SELECT COUNT(*) FROM eleves WHERE statut_inscription = 'Validé'")->fetchColumn();
        $stats['total_profs'] = $pdo->query("SELECT COUNT(*) FROM admins WHERE role = 'enseignant' AND statut = 'Actif'")->fetchColumn();
        $stats['total_secretaires'] = $pdo->query("SELECT COUNT(*) FROM admins WHERE role = 'secretariat' AND statut = 'Actif'")->fetchColumn();
        $stats['staff_attente'] = $pdo->query("SELECT COUNT(*) FROM admins WHERE statut = 'En attente'")->fetchColumn();
    } catch(Exception $e) {}
}

// Action: Valider/Refuser Staff (Rôle exclusif du Préfet)
if (isset($_GET['staff_action']) && isset($_GET['staff_id'])) {
    $id = (int)$_GET['staff_id'];
    $new_status = ($_GET['staff_action'] === 'valider') ? 'Actif' : (($_GET['staff_action'] === 'refuser') ? 'Refusé' : null);
    
    if ($new_status) {
        $stmt = $pdo->prepare("UPDATE admins SET statut = :status WHERE id = :id");
        $stmt->execute(['status' => $new_status, 'id' => $id]);
        header("Location: dashboard_prefet.php?msg=Statut du personnel mis à jour");
        exit();
    }
}

// Liste du staff en attente de validation
$staff_attente = $pdo->query("SELECT * FROM admins WHERE statut = 'En attente' ORDER BY id DESC")->fetchAll();

// Liste de tout le personnel actif
$staff_actif = $pdo->query("SELECT * FROM admins WHERE statut = 'Actif' AND role != 'prefet' ORDER BY role, nom_complet")->fetchAll();

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Direction Générale | École Belle Vue</title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?= time() ?>">
    <script src="https://unpkg.com/feather-icons"></script>
    <style>
        .dashboard-container { display: flex; min-height: 100vh; }
        .sidebar { width: 250px; background: #0f172a; color: white; padding: 20px; }
        .main-content { flex: 1; padding: 40px; background: #f8fafc; }
        .stat-card { background: white; padding: 25px; border-radius: 15px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); border: 1px solid #e2e8f0; transition: all 0.3s ease; cursor: pointer; text-decoration: none; display: block; }
        .stat-card:hover { transform: translateY(-5px); box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); border-color: #6366f1; }
        .table-container { background: white; border-radius: 15px; padding: 30px; margin-top: 30px; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.05); }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th { text-align: left; padding: 15px; background: #f1f5f9; color: #475569; font-weight: 600; font-size: 14px; }
        td { padding: 15px; border-bottom: 1px solid #f1f5f9; font-size: 14px; }
        .badge { padding: 6px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; }
        .btn-action { padding: 8px 16px; border-radius: 8px; font-size: 13px; text-decoration: none; display: inline-block; font-weight: 600; transition: all 0.2s; }
        .btn-success { background: #10b981; color: white; margin-right: 5px; }
        .btn-danger { background: #ef4444; color: white; }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <aside class="sidebar">
            <div style="text-align: center; margin-bottom: 40px;">
                <img src="assets/img/logo.webp" alt="Logo" style="height: 50px; filter: brightness(0) invert(1);">
                <p style="font-size: 12px; color: #94a3b8; margin-top: 10px;">DIRECTION GÉNÉRALE</p>
            </div>
            <nav>
                <ul style="list-style: none; padding: 0;">
                    <li style="margin-bottom: 10px;"><a href="dashboard_prefet.php" class="active" style="background: rgba(255,255,255,0.1); color: white; text-decoration: none; display: flex; align-items: center; gap: 12px; padding: 12px; border-radius: 8px;"><i data-feather="shield" style="width: 18px;"></i> Direction</a></li>
                    <li style="margin-bottom: 10px;"><a href="gestion_personnel_prefet.php" style="color: #94a3b8; text-decoration: none; display: flex; align-items: center; gap: 12px; padding: 12px;"><i data-feather="users" style="width: 18px;"></i> Gestion Personnel</a></li>
                    <li style="margin-bottom: 10px;"><a href="archives_ecole_prefet.php" style="color: #94a3b8; text-decoration: none; display: flex; align-items: center; gap: 12px; padding: 12px;"><i data-feather="database" style="width: 18px;"></i> Archives École</a></li>
                    <li style="margin-top: 60px;"><a href="logout.php" style="color: #fca5a5; text-decoration: none; display: flex; align-items: center; gap: 12px; padding: 12px;"><i data-feather="log-out" style="width: 18px;"></i> Déconnexion</a></li>
                </ul>
            </nav>
        </aside>
        
        <main class="main-content">
            <header style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 40px;">
                <div>
                    <h1 style="font-size: 28px; color: #0f172a;">Espace Direction (Préfet)</h1>
                    <p style="color: #64748b;">Contrôle et validation du personnel de l'établissement.</p>
                </div>
                <div style="background: white; padding: 10px 20px; border-radius: 30px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); display: flex; align-items: center; gap: 10px;">
                    <div style="width: 32px; height: 32px; background: #fbbf24; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #020617; font-weight: bold;">P</div>
                    <span style="font-weight: 600; font-size: 14px;"><?= htmlspecialchars($_SESSION['nom_complet']) ?></span>
                </div>
            </header>

            <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 25px;">
                <a href="archives_ecole_prefet.php" class="stat-card">
                    <div style="color: #64748b; font-size: 14px; margin-bottom: 10px;">Élèves Inscrits</div>
                    <div style="font-size: 32px; font-weight: bold; color: #0f172a;"><?= $stats['total_eleves'] ?></div>
                    <div style="font-size: 12px; color: #6366f1; margin-top: 5px;">Voir la liste →</div>
                </a>
                <a href="gestion_personnel_prefet.php?role=enseignant" class="stat-card">
                    <div style="color: #64748b; font-size: 14px; margin-bottom: 10px;">Professeurs Actifs</div>
                    <div style="font-size: 32px; font-weight: bold; color: #10b981;"><?= $stats['total_profs'] ?></div>
                    <div style="font-size: 12px; color: #10b981; margin-top: 5px;">Gérer les profs →</div>
                </a>
                <a href="gestion_personnel_prefet.php?role=secretariat" class="stat-card">
                    <div style="color: #64748b; font-size: 14px; margin-bottom: 10px;">Secrétaires Actifs</div>
                    <div style="font-size: 32px; font-weight: bold; color: #3b82f6;"><?= $stats['total_secretaires'] ?></div>
                    <div style="font-size: 12px; color: #3b82f6; margin-top: 5px;">Gérer le secrétariat →</div>
                </a>
                <a href="#staff-validation" class="stat-card">
                    <div style="color: #64748b; font-size: 14px; margin-bottom: 10px;">Demandes Staff</div>
                    <div style="font-size: 32px; font-weight: bold; color: #6366f1;"><?= $stats['staff_attente'] ?></div>
                    <div style="font-size: 12px; color: #6366f1; margin-top: 5px;">Vérifier les demandes ↓</div>
                </a>
            </div>

            <!-- Staff Validation -->
            <div id="staff-validation" class="table-container" style="border-top: 4px solid #6366f1;">
                <h3 style="color: #4338ca;"><i data-feather="user-plus"></i> Demandes de nouveaux comptes Personnel</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Nom Complet</th>
                            <th>Identifiant</th>
                            <th>Rôle Sollicité</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($staff_attente as $staff): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($staff['nom_complet']) ?></strong></td>
                            <td><?= htmlspecialchars($staff['username']) ?></td>
                            <td><span class="badge" style="background: #eef2ff; color: #4338ca;"><?= ucfirst($staff['role']) ?></span></td>
                            <td>
                                <a href="?staff_action=valider&staff_id=<?= $staff['id'] ?>" class="btn-action btn-success">Autoriser l'accès</a>
                                <a href="?staff_action=refuser&staff_id=<?= $staff['id'] ?>" class="btn-action btn-danger">Refuser</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($staff_attente)): ?>
                        <tr><td colspan="4" style="text-align: center; color: #94a3b8; padding: 30px;">Aucune nouvelle demande de personnel.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Global Staff View -->
            <div class="table-container" style="margin-top: 40px;">
                <h3><i data-feather="users"></i> Liste du Personnel Actif</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Nom Complet</th>
                            <th>Rôle</th>
                            <th>Statut</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($staff_actif as $s): ?>
                        <tr>
                            <td><?= htmlspecialchars($s['nom_complet']) ?></td>
                            <td><?= ucfirst($s['role']) ?></td>
                            <td><span class="badge" style="background: #dcfce7; color: #166534;">Actif</span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
    <script>feather.replace();</script>
</body>
</html>

