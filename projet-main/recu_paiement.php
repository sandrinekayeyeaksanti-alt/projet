<?php
session_start();
require_once 'config.php';

// Vérification de l'authentification générale
if (!isset($_SESSION['role'])) {
    header("Location: login.php");
    exit();
}

$payment_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($payment_id <= 0) {
    die("<p style='color:red; font-family:sans-serif; text-align:center; margin-top:50px;'>ID de paiement invalide.</p>");
}

$payment = null;
$eleve = null;

if (isset($pdo)) {
    try {
        // Récupérer le paiement
        $stmt_pay = $pdo->prepare("SELECT * FROM paiements WHERE id = ?");
        $stmt_pay->execute([$payment_id]);
        $payment = $stmt_pay->fetch();

        if ($payment) {
            // Récupérer l'élève lié
            $stmt_el = $pdo->prepare("
                SELECT e.*, c.nom as classe_nom, c.option_nom 
                FROM eleves e 
                LEFT JOIN classes c ON e.classe_id = c.id 
                WHERE e.id = ?
            ");
            $stmt_el->execute([$payment['eleve_id']]);
            $eleve = $stmt_el->fetch();
        }
    } catch (PDOException $e) {
        die("<p style='color:red; font-family:sans-serif; text-align:center;'>Erreur base de données.</p>");
    }
}

if (!$payment || !$eleve) {
    die("<p style='color:red; font-family:sans-serif; text-align:center; margin-top:50px;'>Reçu introuvable.</p>");
}

// Sécurité : vérification des accès par rôle
$role = $_SESSION['role'];
$access_granted = false;

if ($role === 'prefet' || $role === 'secretariat') {
    $access_granted = true;
} elseif ($role === 'parent') {
    // Le parent ne peut voir que les reçus de ses propres enfants (liés par tuteur_tel)
    if (isset($_SESSION['username']) && trim($eleve['tuteur_tel']) === trim($_SESSION['username'])) {
        $access_granted = true;
    }
} elseif ($role === 'eleve') {
    // L'élève ne peut voir que ses propres reçus
    if (isset($_SESSION['eleve_id']) && (int)$eleve['id'] === (int)$_SESSION['eleve_id']) {
        $access_granted = true;
    }
}

if (!$access_granted) {
    die("<p style='color:red; font-family:sans-serif; text-align:center; margin-top:50px;'>Accès non autorisé à ce reçu.</p>");
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reçu de Paiement #<?= sprintf('%05d', $payment['id']) ?> | École Belle Vue</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/feather-icons"></script>
    <style>
        :root {
            --primary: #0a1931;
            --secondary: #15305b;
            --success: #10b981;
            --text-dark: #0f172a;
            --text-muted: #64748b;
            --border: #e2e8f0;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: #f1f5f9;
            color: var(--text-dark);
            margin: 0;
            padding: 40px 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .actions-bar {
            width: 100%;
            max-width: 750px;
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
            background: white;
            padding: 15px 25px;
            border-radius: 16px;
            box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.05), 0 2px 4px -2px rgb(0 0 0 / 0.05);
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background: var(--secondary);
            transform: translateY(-1px);
        }

        .btn-outline {
            background: white;
            color: var(--text-muted);
            border: 1px solid var(--border);
        }

        .btn-outline:hover {
            background: #f8fafc;
            color: var(--text-dark);
        }

        /* Card Receipt Layout */
        .receipt-card {
            background: white;
            width: 100%;
            max-width: 750px;
            padding: 50px;
            border-radius: 24px;
            box-shadow: 0 20px 25px -5px rgb(0 0 0 / 0.1), 0 8px 10px -6px rgb(0 0 0 / 0.1);
            position: relative;
            box-sizing: border-box;
        }

        .receipt-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 8px;
            background: linear-gradient(90deg, #f59e0b, #10b981, #3b82f6);
            border-top-left-radius: 24px;
            border-top-right-radius: 24px;
        }

        .header-section {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 2px dashed var(--border);
            padding-bottom: 30px;
            margin-bottom: 30px;
        }

        .school-title {
            font-family: 'Outfit', sans-serif;
            font-size: 26px;
            font-weight: 800;
            color: var(--primary);
            margin: 0;
            letter-spacing: -0.5px;
        }

        .school-subtitle {
            font-size: 13px;
            color: var(--text-muted);
            margin-top: 4px;
            line-height: 1.5;
        }

        .receipt-badge {
            background: #e0f2fe;
            color: #0369a1;
            padding: 6px 16px;
            border-radius: 50px;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 12px;
            display: inline-block;
        }

        .details-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 40px;
        }

        .info-block h4 {
            font-size: 11px;
            text-transform: uppercase;
            color: var(--text-muted);
            margin: 0 0 8px 0;
            letter-spacing: 1px;
        }

        .info-block p {
            margin: 0;
            font-size: 15px;
            font-weight: 600;
            line-height: 1.5;
        }

        .info-block span {
            display: block;
            font-size: 13px;
            color: var(--text-muted);
            font-weight: 400;
            margin-top: 2px;
        }

        /* Table */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 40px;
        }

        .items-table th {
            text-align: left;
            padding: 12px 16px;
            background: #f8fafc;
            border-bottom: 2px solid var(--border);
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-muted);
        }

        .items-table td {
            padding: 20px 16px;
            border-bottom: 1px solid var(--border);
            font-size: 14px;
        }

        .total-box {
            background: #f8fafc;
            border-radius: 16px;
            padding: 20px 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border: 1px solid var(--border);
        }

        .total-label {
            font-size: 15px;
            font-weight: 700;
            color: var(--primary);
        }

        .total-amount {
            font-size: 24px;
            font-weight: 800;
            color: var(--success);
        }

        /* Seal / Stamp */
        .seal-container {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            margin-top: 50px;
            padding-top: 30px;
            border-top: 1px solid var(--border);
        }

        .signature-line {
            width: 200px;
            text-align: center;
            font-size: 12px;
            color: var(--text-muted);
        }

        .signature-line div {
            border-top: 1px solid var(--text-muted);
            margin-top: 50px;
            padding-top: 8px;
            font-weight: 600;
            color: var(--text-dark);
        }

        .official-seal {
            border: 3px double var(--success);
            color: var(--success);
            font-size: 12px;
            font-weight: 800;
            text-transform: uppercase;
            padding: 10px 15px;
            border-radius: 8px;
            transform: rotate(-5deg);
            opacity: 0.85;
            user-select: none;
            letter-spacing: 1px;
            display: inline-block;
        }

        @media print {
            body {
                background: white !important;
                padding: 0;
            }
            .actions-bar {
                display: none !important;
            }
            .receipt-card {
                box-shadow: none !important;
                padding: 30px !important;
                border: 1px solid var(--border);
            }
            .receipt-card::before {
                display: none;
            }
        }
    </style>
</head>
<body>

    <!-- Barre d'actions -->
    <div class="actions-bar">
        <button onclick="window.close();" class="btn btn-outline">
            <i data-feather="x-circle" style="width: 16px;"></i> Fermer la page
        </button>
        <button onclick="window.print();" class="btn btn-primary">
            <i data-feather="printer" style="width: 16px;"></i> Imprimer ce Reçu
        </button>
    </div>

    <!-- Bordereau de paiement -->
    <div class="receipt-card">
        <div class="header-section">
            <div>
                <h1 class="school-title">École Belle Vue</h1>
                <p class="school-subtitle">
                    Enseignement Maternel, Primaire et Secondaire<br>
                    Lubumbashi, Haut-Katanga, RD Congo<br>
                    Contact : contact@bellevue.com
                </p>
            </div>
            <div style="text-align: right;">
                <div class="receipt-badge">Bordereau Officiel</div>
                <div style="font-size: 13px; font-weight: 700; color: var(--primary);">Reçu N° REC-<?= sprintf('%06d', $payment['id']) ?></div>
                <div style="font-size: 12px; color: var(--text-muted); margin-top: 4px;">Date : <?= date('d/m/Y H:i', strtotime($payment['date_paiement'])) ?></div>
            </div>
        </div>

        <div class="details-grid">
            <div class="info-block">
                <h4>Élève / Bénéficiaire</h4>
                <p><?= htmlspecialchars($eleve['nom'] . ' ' . $eleve['post_nom_prenom']) ?></p>
                <span>Classe : <?= htmlspecialchars($eleve['classe_nom']) ?> <?= htmlspecialchars($eleve['option_nom']) ?></span>
                <span>ID Élève : #<?= sprintf('%05d', $eleve['id']) ?></span>
            </div>
            <div class="info-block" style="text-align: right;">
                <h4>Détails de la Transaction</h4>
                <p>Moyen : <?= htmlspecialchars($payment['methode_paiement']) ?></p>
                <span>Référence : <?= htmlspecialchars($payment['reference_transaction'] ?: 'N/A') ?></span>
                <span style="font-weight: bold; color: var(--success);">Statut : Paiement Confirmé</span>
            </div>
        </div>

        <table class="items-table">
            <thead>
                <tr>
                    <th>Désignation des frais</th>
                    <th style="text-align: right;">Montant (USD)</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                        <div style="font-weight: 700; font-size: 15px; color: var(--primary);"><?= htmlspecialchars($payment['type_frais']) ?></div>
                        <div style="font-size: 12px; color: var(--text-muted); margin-top: 4px;">Règlement des frais de scolarité obligatoires - Exercice 2026-2027</div>
                    </td>
                    <td style="text-align: right; font-weight: 700; font-size: 16px; color: var(--primary);">
                        <?= number_format($payment['montant'], 2) ?> $ USD
                    </td>
                </tr>
            </tbody>
        </table>

        <div class="total-box">
            <div class="total-label">Montant Total Payé</div>
            <div class="total-amount"><?= number_format($payment['montant'], 2) ?> $ USD</div>
        </div>

        <div class="seal-container">
            <div class="official-seal">
                <i data-feather="shield" style="width: 14px; vertical-align: text-bottom; margin-right: 4px;"></i>
                École Belle Vue - Payé
            </div>
            <div class="signature-line">
                Le Guichet / La Caisse
                <div>Bureau de la Comptabilité</div>
            </div>
        </div>
    </div>

    <script>
        feather.replace();
    </script>
</body>
</html>
