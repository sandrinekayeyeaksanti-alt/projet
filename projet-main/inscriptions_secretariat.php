<?php
session_start();
require_once 'config.php';

// Vérification du rôle Secrétariat
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'secretariat') {
    header("Location: login_secretariat.php");
    exit();
}

// Action: Valider/Refuser Élève
if (isset($_GET['action']) && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    
    if ($_GET['action'] === 'toggle_paiement' && isset($_GET['type'])) {
        $type = $_GET['type'];
        // Liste des montants
        $montants_frais = [
            "Frais de Test" => 1,
            "Test d'admission" => 15,
            "1ère Tranche" => 150,
            "2ème Tranche" => 150,
            "3ème Tranche" => 150,
            "4ème Tranche" => 100,
            "5ème Tranche" => 100,
            "6ème Tranche" => 100,
            "7ème Tranche" => 80,
            "8ème Tranche" => 80,
            "9ème Tranche" => 80,
            "Frais Divers" => 50
        ];
        $montant = $montants_frais[$type] ?? 0;

        // Vérifier si déjà payé
        $stmt_check = $pdo->prepare("SELECT id FROM paiements WHERE eleve_id = ? AND type_frais = ?");
        $stmt_check->execute([$id, $type]);
        $existing = $stmt_check->fetch();
        
        if ($existing) {
            $pdo->prepare("DELETE FROM paiements WHERE id = ?")->execute([$existing['id']]);
        } else {
            $ref = "CASH-" . strtoupper(substr(md5(time() . $id), 0, 10));
            $pdo->prepare("INSERT INTO paiements (eleve_id, type_frais, montant, date_paiement, methode_paiement, reference_transaction, statut) VALUES (?, ?, ?, NOW(), 'Espèces', ?, 'Payé')")
                ->execute([$id, $type, $montant, $ref]);
        }

        // Mettre à jour le statut_paiement général de l'élève
        $stmt_last = $pdo->prepare("SELECT type_frais FROM paiements WHERE eleve_id = ? ORDER BY date_paiement DESC LIMIT 1");
        $stmt_last->execute([$id]);
        $last_frais = $stmt_last->fetchColumn();

        if ($last_frais) {
            if ($last_frais === "Test d'admission") {
                $statut_p = "Test payé";
            } else {
                $statut_p = $last_frais . " payée";
            }
        } else {
            $statut_p = "Non payé";
        }
        
        $pdo->prepare("UPDATE eleves SET statut_paiement = ? WHERE id = ?")->execute([$statut_p, $id]);

        header("Location: inscriptions_secretariat.php?msg=Paiement mis à jour");
        exit();
    }

    $new_status = ($_GET['action'] === 'valider') ? 'Validé' : (($_GET['action'] === 'refuser') ? 'Refusé' : null);
    
    if ($new_status) {
        $stmt = $pdo->prepare("UPDATE eleves SET statut_inscription = :status WHERE id = :id");
        $stmt->execute(['status' => $new_status, 'id' => $id]);
        header("Location: inscriptions_secretariat.php?msg=Statut mis à jour");
        exit();
    }
}

// Tous les élèves
$tous_les_eleves = [];
if (isset($pdo)) {
    $tous_les_eleves = $pdo->query("SELECT e.*, c.nom as classe_nom FROM eleves e LEFT JOIN classes c ON e.classe_id = c.id ORDER BY e.date_inscription DESC")->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Inscriptions | Secrétariat</title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?= time() ?>">
    <script src="https://unpkg.com/feather-icons"></script>
    <style>
        .dashboard-container { display: flex; min-height: 100vh; }
        .sidebar { width: 250px; background: #1e3a8a; color: white; padding: 20px; }
        .main-content { flex: 1; padding: 40px; background: #f1f5f9; }
        .table-container { background: white; border-radius: 12px; padding: 25px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 12px; border-bottom: 2px solid #e2e8f0; color: #64748b; }
        td { padding: 12px; border-bottom: 1px solid #e2e8f0; }
        .btn-action { padding: 6px 10px; border-radius: 6px; text-decoration: none; font-size: 12px; font-weight: 600; margin-right: 5px; }
        .btn-success { background: #10b981; color: white; }
        .btn-danger { background: #ef4444; color: white; }
        .badge { padding: 6px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; display: inline-block; min-width: 80px; text-align: center; }
        .badge-attente { background: #fef3c7; color: #d97706; border: 1px solid #fde68a; }
        .badge-valide { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .badge-refuse { background: #fee2e2; color: #b91c1c; border: 1px solid #fecaca; }

        /* Modal Styles */
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); backdrop-filter: blur(4px); }
        .modal-content { background: white; margin: 10% auto; padding: 30px; border-radius: 24px; width: 420px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.3); position: relative; border: 1px solid #e2e8f0; }
        .close-modal { position: absolute; right: 25px; top: 25px; cursor: pointer; color: #94a3b8; transition: color 0.2s; }
        .close-modal:hover { color: #1e293b; }
        
        .payment-list { margin-top: 20px; }
        .payment-row { display: flex; justify-content: space-between; align-items: center; padding: 18px 20px; border-radius: 16px; margin-bottom: 12px; transition: all 0.2s ease; text-decoration: none; border: 2px solid #f1f5f9; background: #f8fafc; }
        .payment-row:hover { border-color: #cbd5e1; transform: translateY(-2px); box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
        
        .payment-row.is-paid { background: #f0f7ff; border-color: #3b82f6; color: #1e40af; }
        .payment-row.is-unpaid { background: #fffefe; border-color: #fee2e2; color: #991b1b; }
        
        .check-box { width: 28px; height: 28px; border-radius: 8px; display: flex; align-items: center; justify-content: center; transition: all 0.2s; border: 2px solid; }
        .box-paid { background: #3b82f6; border-color: #3b82f6; color: white; box-shadow: 0 0 10px rgba(59, 130, 246, 0.3); }
        .box-unpaid { background: white; border-color: #ef4444; color: #ef4444; }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <aside class="sidebar">
            <h2 style="margin-bottom: 30px;">Belle Vue <span style="font-weight: 300;">Secrétariat</span></h2>
            <nav>
                <ul style="list-style: none; padding: 0;">
                    <li style="margin-bottom: 15px;"><a href="dashboard_secretariat.php" style="color: #bfdbfe; text-decoration: none; display: flex; align-items: center; gap: 10px;"><i data-feather="home"></i> Accueil</a></li>
                    <li style="margin-bottom: 15px;"><a href="inscriptions_secretariat.php" style="color: white; text-decoration: none; display: flex; align-items: center; gap: 10px;"><i data-feather="user-check"></i> Inscriptions</a></li>
                    <li style="margin-bottom: 15px;"><a href="gestion_paiements.php" style="color: #bfdbfe; text-decoration: none; display: flex; align-items: center; gap: 10px;"><i data-feather="credit-card"></i> Paiements & Reçus</a></li>
                    <li style="margin-bottom: 15px;"><a href="gestion_horaires.php" style="color: #bfdbfe; text-decoration: none; display: flex; align-items: center; gap: 10px;"><i data-feather="calendar"></i> Horaires</a></li>
                    <li style="margin-top: 50px;"><a href="logout.php" style="color: #fca5a5; text-decoration: none; display: flex; align-items: center; gap: 10px;"><i data-feather="log-out"></i> Déconnexion</a></li>
                </ul>
            </nav>
        </aside>

        <main class="main-content">
            <header style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
                <h1>Gestion des Inscriptions</h1>
                <div style="display: flex; align-items: center; gap: 10px;">
                    <i data-feather="user"></i> <span><?= htmlspecialchars($_SESSION['nom_complet']) ?></span>
                </div>
            </header>

            <div class="table-container">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h3>Liste complète des élèves inscrits / en attente</h3>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>Nom complet</th>
                            <th>Classe</th>
                            <th>Tuteur</th>
                            <th>Paiement</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tous_les_eleves as $eleve): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($eleve['nom'] . ' ' . $eleve['post_nom_prenom']) ?></strong></td>
                            <td><?= htmlspecialchars($eleve['classe_nom']) ?></td>
                            <td><?= htmlspecialchars($eleve['tuteur_nom']) ?><br><span style="font-size:12px;color:#64748b;"><?= htmlspecialchars($eleve['tuteur_tel']) ?></span></td>
                            <td>
                                $frais_scolaires_full = [
                                    "Frais de Test", "Test d'admission", "1ère Tranche", "2ème Tranche", "3ème Tranche", 
                                    "4ème Tranche", "5ème Tranche", "6ème Tranche", "7ème Tranche", 
                                    "8ème Tranche", "9ème Tranche", "Frais Divers"
                                ];
                                $payes_stmt = $pdo->prepare("SELECT id, type_frais FROM paiements WHERE eleve_id = ?");
                                $payes_stmt->execute([$eleve['id']]);
                                $payes_records = $payes_stmt->fetchAll(PDO::FETCH_KEY_PAIR); // [type_frais => id]
                                $nb_payes = count($payes_records);
                                ?>
                                <button onclick="openPaymentModal(<?= $eleve['id'] ?>)" 
                                        style="background: #f8fafc; border: 1px solid #e2e8f0; padding: 8px 12px; border-radius: 8px; cursor: pointer; font-size: 12px; font-weight: 600; color: #1e3a8a; display: flex; align-items: center; gap: 8px;">
                                    <i data-feather="dollar-sign" style="width: 14px;"></i>
                                    <span><?= $nb_payes ?> / <?= count($frais_scolaires_full) ?> Payés</span>
                                    <i data-feather="chevron-right" style="width: 14px;"></i>
                                </button>
                                
                                <!-- Modal de Paiement pour cet élève -->
                                <div id="modal-<?= $eleve['id'] ?>" class="modal">
                                    <div class="modal-content" style="width: 460px; max-height: 90vh; display: flex; flex-direction: column; overflow: hidden;">
                                        <i data-feather="x" class="close-modal" onclick="closePaymentModal(<?= $eleve['id'] ?>)"></i>
                                        <h3 style="margin-bottom: 5px;">Paiements</h3>
                                        <p style="color: #64748b; font-size: 14px; margin-bottom: 20px;"><?= htmlspecialchars($eleve['nom'] . ' ' . $eleve['post_nom_prenom']) ?></p>
                                        
                                        <div class="payment-list" style="overflow-y: auto; padding-right: 5px; flex: 1;">
                                            <?php foreach ($frais_scolaires_full as $frais): 
                                                $is_paye = array_key_exists($frais, $payes_records);
                                                $p_id = $is_paye ? $payes_records[$frais] : null;
                                            ?>
                                            <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 12px;">
                                                <a href="?action=toggle_paiement&id=<?= $eleve['id'] ?>&type=<?= urlencode($frais) ?>" 
                                                   class="payment-row <?= $is_paye ? 'is-paid' : 'is-unpaid' ?>" style="flex: 1; margin-bottom: 0; padding: 12px 16px;">
                                                    <div style="display: flex; align-items: center; gap: 10px;">
                                                        <i data-feather="<?= $is_paye ? 'file-text' : 'file' ?>" style="width: 16px; opacity: 0.7;"></i>
                                                        <span style="font-weight: 600; font-size: 13px;"><?= $frais ?></span>
                                                    </div>
                                                    <div class="check-box <?= $is_paye ? 'box-paid' : 'box-unpaid' ?>" style="width: 22px; height: 22px;">
                                                        <?php if($is_paye): ?>
                                                            <i data-feather="check" style="width: 14px; stroke-width: 3;"></i>
                                                        <?php else: ?>
                                                            <i data-feather="square" style="width: 12px; opacity: 0.3;"></i>
                                                        <?php endif; ?>
                                                    </div>
                                                </a>
                                                <?php if($is_paye && $p_id): ?>
                                                    <a href="recu_paiement.php?id=<?= $p_id ?>" target="_blank" class="btn-action" 
                                                       style="background: #10b981; color: white; display: flex; align-items: center; justify-content: center; height: 48px; width: 48px; border-radius: 12px; margin-right: 0;" 
                                                       title="Générer le reçu">
                                                        <i data-feather="printer" style="width: 18px;"></i>
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                        
                                        <button onclick="closePaymentModal(<?= $eleve['id'] ?>)" style="width: 100%; padding: 14px; background: #f1f5f9; border: none; border-radius: 12px; margin-top: 15px; cursor: pointer; color: #475569; font-weight: 700; transition: background 0.2s;">Terminer</button>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <?php 
                                    $s = $eleve['statut_inscription'];
                                    $badge_class = 'badge-attente';
                                    $display_status = 'En attente';
                                    if ($s === 'Validé') { $badge_class = 'badge-valide'; $display_status = 'Validé'; }
                                    if ($s === 'Refusé') { $badge_class = 'badge-refuse'; $display_status = 'Refusé'; }
                                ?>
                                <span class="badge <?= $badge_class ?>"><?= $display_status ?></span>
                            </td>
                            <td>
                                <?php if ($s === 'En attente'): ?>
                                    <a href="?action=valider&id=<?= $eleve['id'] ?>" class="btn-action btn-success">Valider</a>
                                    <a href="?action=refuser&id=<?= $eleve['id'] ?>" class="btn-action btn-danger">Refuser</a>
                                <?php elseif ($s === 'Validé'): ?>
                                    <a href="?action=refuser&id=<?= $eleve['id'] ?>" class="btn-action btn-danger">Bloquer</a>
                                    <a href="modifier_eleve.php?id=<?= $eleve['id'] ?>" class="btn-action" style="background: #6366f1; color: white;">Modifier</a>
                                <?php else: ?>
                                    <a href="?action=valider&id=<?= $eleve['id'] ?>" class="btn-action btn-success">Valider</a>
                                    <a href="modifier_eleve.php?id=<?= $eleve['id'] ?>" class="btn-action" style="background: #6366f1; color: white;">Modifier</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($tous_les_eleves)): ?>
                        <tr><td colspan="6" style="text-align: center; color: #94a3b8; padding: 20px;">Aucun élève enregistré.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
    <script>
        feather.replace();

        function openPaymentModal(id) {
            document.getElementById('modal-' + id).style.display = 'block';
        }

        function closePaymentModal(id) {
            document.getElementById('modal-' + id).style.display = 'none';
        }

        // Fermer le modal si on clique en dehors
        window.onclick = function(event) {
            if (event.target.className === 'modal') {
                event.target.style.display = "none";
            }
        }
    </script>
</body>
</html>

