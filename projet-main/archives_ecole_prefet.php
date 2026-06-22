<?php
session_start();
require_once 'config.php';

// Vérification du rôle Préfet
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'prefet') {
    header("Location: login_prefet.php");
    exit();
}

$filtre_classe_id = isset($_GET['filtre_classe']) ? (int)$_GET['filtre_classe'] : 0;

$classes = [];
$eleves = [];

if (isset($pdo)) {
    $classes = $pdo->query("SELECT * FROM classes ORDER BY niveau, nom")->fetchAll();
    
    $sql = "SELECT e.*, c.nom as classe_nom, c.option_nom FROM eleves e LEFT JOIN classes c ON e.classe_id = c.id";
    $params = [];
    
    if ($filtre_classe_id > 0) {
        $sql .= " WHERE e.classe_id = :cid";
        $params[':cid'] = $filtre_classe_id;
    }
    
    $sql .= " ORDER BY c.nom, c.option_nom, e.nom";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $eleves = $stmt->fetchAll();
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Archives École | École Belle Vue</title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?= time() ?>">
    <script src="https://unpkg.com/feather-icons"></script>
    <style>
        .dashboard-container { display: flex; min-height: 100vh; }
        .sidebar { width: 250px; background: #0f172a; color: white; padding: 20px; }
        .main-content { flex: 1; padding: 40px; background: #f8fafc; }
        .table-container { background: white; border-radius: 15px; padding: 30px; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.05); }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th { text-align: left; padding: 15px; background: #f1f5f9; color: #475569; font-weight: 600; font-size: 14px; }
        td { padding: 15px; border-bottom: 1px solid #f1f5f9; font-size: 14px; }
        .badge { padding: 6px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; }
        .badge-attente { background: #fef3c7; color: #d97706; }
        .badge-valide { background: #dcfce7; color: #166534; }
        .badge-refuse { background: #fee2e2; color: #b91c1c; }
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
                    <li style="margin-bottom: 10px;"><a href="dashboard_prefet.php" style="color: #94a3b8; text-decoration: none; display: flex; align-items: center; gap: 12px; padding: 12px;"><i data-feather="shield" style="width: 18px;"></i> Direction</a></li>
                    <li style="margin-bottom: 10px;"><a href="gestion_personnel_prefet.php" style="color: #94a3b8; text-decoration: none; display: flex; align-items: center; gap: 12px; padding: 12px;"><i data-feather="users" style="width: 18px;"></i> Gestion Personnel</a></li>
                    <li style="margin-bottom: 10px;"><a href="archives_ecole_prefet.php" class="active" style="background: rgba(255,255,255,0.1); color: white; text-decoration: none; display: flex; align-items: center; gap: 12px; padding: 12px; border-radius: 8px;"><i data-feather="database" style="width: 18px;"></i> Archives École</a></li>
                    <li style="margin-top: 60px;"><a href="logout.php" style="color: #fca5a5; text-decoration: none; display: flex; align-items: center; gap: 12px; padding: 12px;"><i data-feather="log-out" style="width: 18px;"></i> Déconnexion</a></li>
                </ul>
            </nav>
        </aside>
        
        <main class="main-content">
            <header style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 40px;">
                <div>
                    <h1 style="font-size: 28px; color: #0f172a;">Archives de l'École</h1>
                    <p style="color: #64748b;">Vue globale sur tous les élèves inscrits.</p>
                </div>
                <div style="background: white; padding: 10px 20px; border-radius: 30px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); display: flex; align-items: center; gap: 10px;">
                    <div style="width: 32px; height: 32px; background: #fbbf24; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #020617; font-weight: bold;">P</div>
                    <span style="font-weight: 600; font-size: 14px;"><?= htmlspecialchars($_SESSION['nom_complet']) ?></span>
                </div>
            </header>

            <div class="table-container">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h3 style="color: #0f172a; margin: 0;"><i data-feather="archive"></i> Registre des Élèves</h3>
                    <form method="GET" style="display: flex; gap: 10px;">
                        <select name="filtre_classe" class="form-control" onchange="this.form.submit()" style="padding: 10px; border-radius: 8px; border: 1px solid #cbd5e1;">
                            <option value="0">Toutes les classes de l'école</option>
                            <?php foreach($classes as $c): ?>
                                <option value="<?= $c['id'] ?>" <?= ($filtre_classe_id == $c['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($c['nom']) ?> <?= $c['option_nom'] ? '('.htmlspecialchars($c['option_nom']).')' : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                </div>

                <div style="overflow-x: auto;">
                    <table>
                        <thead>
                            <tr>
                                <th>Nom Complet</th>
                                <th>Sexe / Date N.</th>
                                <th>Classe & Option</th>
                                <th>Tuteur Légal</th>
                                <th>Téléphone Tuteur</th>
                                <th>Statut d'Inscription</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $current_classe = "";
                            foreach ($eleves as $e): 
                                $classe_complete = $e['classe_nom'] . ($e['option_nom'] ? ' - ' . $e['option_nom'] : '');
                                if ($filtre_classe_id == 0 && $classe_complete !== $current_classe) {
                                    $current_classe = $classe_complete;
                                    echo "<tr style='background: #f8fafc;'><td colspan='6' style='color: #475569; font-weight: bold; padding: 15px;'>CLASSE : ".htmlspecialchars($current_classe)."</td></tr>";
                                }
                            ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($e['nom'] . ' ' . $e['post_nom_prenom']) ?></strong></td>
                                <td><?= $e['sexe'] ?> <br><span style="font-size:12px; color:#64748b;"><?= htmlspecialchars($e['lieu_date_naissance']) ?></span></td>
                                <td><?= htmlspecialchars($e['classe_nom']) ?><br><span style="font-size:12px; color:#64748b;"><?= htmlspecialchars($e['option_nom']) ?></span></td>
                                <td><?= htmlspecialchars($e['tuteur_nom']) ?></td>
                                <td><?= htmlspecialchars($e['tuteur_tel']) ?></td>
                                <td>
                                    <?php 
                                        $s = $e['statut_inscription'];
                                        $badge = 'badge-attente';
                                        if ($s === 'Validé' || strpos($s, 'Valid') !== false) $badge = 'badge-valide';
                                        if ($s === 'Refusé' || strpos($s, 'Refus') !== false) $badge = 'badge-refuse';
                                    ?>
                                    <span class="badge <?= $badge ?>"><?= htmlspecialchars($s) ?></span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if(empty($eleves)): ?>
                                <tr><td colspan="6" style="text-align: center; color: #94a3b8; padding: 30px;">Aucun élève trouvé.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
    <script>feather.replace();</script>
</body>
</html>

