<?php
session_start();
require_once 'config.php';

// On vérifie que c'est bien un parent ou un élève connecté
if (!isset($_SESSION['role'])) {
    header("Location: login.php");
    exit();
}

if ($_SESSION['role'] === 'parent') {
    $requested_id = isset($_GET['eleve_id']) ? (int)$_GET['eleve_id'] : ($_SESSION['parent_id'] ?? 0);
    // Vérification de sécurité pour s'assurer que l'élève appartient au parent connecté
    $stmt_check = $pdo->prepare("SELECT * FROM eleves WHERE id = ? AND tuteur_tel = ?");
    $stmt_check->execute([$requested_id, $_SESSION['username']]);
    $eleve = $stmt_check->fetch();
    if ($eleve) {
        $eleve_id = $eleve['id'];
        $eleve_nom = $eleve['nom'] . ' ' . $eleve['post_nom_prenom'];
    } else {
        die("<p style='color:red; font-family:sans-serif;'>Élève non trouvé ou non lié à votre compte parent.</p><a href='dashboard_parent.php'>Retour au tableau de bord</a>");
    }
} else {
    $eleve_id = $_SESSION['eleve_id'] ?? 0;
    $eleve_nom = $_SESSION['eleve_nom'] ?? 'Élève';
}

// Liste des tranches à payer
$frais_scolaires = [
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

// On récupère ce qui est déjà payé
$payes = [];
if (isset($pdo) && $eleve_id > 0) {
    $stmt = $pdo->prepare("SELECT type_frais FROM paiements WHERE eleve_id = ?");
    $stmt->execute([$eleve_id]);
    $payes = $stmt->fetchAll(PDO::FETCH_COLUMN);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Payer le Minerval | École Belle Vue</title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?= time() ?>">
    <script src="https://unpkg.com/feather-icons"></script>
    <style>
        .payment-container { max-width: 900px; margin: 40px auto; padding: 0 20px; }
        .fee-item { 
            background: white; border-radius: 12px; padding: 20px; margin-bottom: 15px; 
            display: flex; justify-content: space-between; align-items: center;
            border: 1px solid #e2e8f0; transition: all 0.2s;
        }
        .fee-item.paid { border-left: 5px solid #10b981; opacity: 0.8; }
        .fee-item.unpaid { border-left: 5px solid #f59e0b; cursor: pointer; }
        .fee-item.unpaid:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(0,0,0,0.05); border-color: #f59e0b; }
        
        .method-card { border: 2px solid #f1f5f9; border-radius: 12px; padding: 20px; cursor: pointer; transition: 0.3s; text-align: center; }
        .method-card:hover, .method-card.active { border-color: #0a1931; background: #f0f7ff; }
        .method-card i { margin-bottom: 10px; color: #0a1931; }
    </style>
</head>
<body style="background: #f8fafc;">

    <nav class="navbar">
        <div class="container" style="display: flex; justify-content: space-between; align-items: center;">
            <a href="portail.php" class="logo"><img src="assets/img/logo.webp" alt="Logo" style="height: 40px;"></a>
            <h2 style="font-size: 18px; color: #0a1931;">Paiement des Frais Scolaires</h2>
            <a href="portail.php" class="btn btn-outline">Retour au Portail</a>
        </div>
    </nav>

    <div class="payment-container">
        <div style="margin-bottom: 30px;">
            <h1 style="font-size: 24px;">Règlement du Minerval</h1>
            <p style="color: #64748b;">Élève : <strong><?= htmlspecialchars($eleve_nom) ?></strong></p>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 350px; gap: 30px;">
            <!-- Liste des tranches -->
            <div>
                <h3 style="margin-bottom: 20px; font-size: 16px; color: #64748b; text-transform: uppercase; letter-spacing: 1px;">Tranches de l'année scolaire</h3>
                <?php foreach ($frais_scolaires as $nom => $montant): 
                    $is_paye = in_array($nom, $payes);
                ?>
                    <div class="fee-item <?= $is_paye ? 'paid' : 'unpaid' ?>"
                         <?php if (!$is_paye): ?>
                         data-nom="<?= htmlspecialchars($nom, ENT_QUOTES) ?>"
                         data-montant="<?= $montant ?>"
                         onclick="selectFee(this.dataset.nom, this.dataset.montant)"
                         <?php endif; ?>>
                        <div style="display: flex; align-items: center; gap: 15px;">
                            <div style="width: 40px; height: 40px; border-radius: 50%; background: <?= $is_paye ? '#dcfce7' : '#fff7ed' ?>; display: flex; align-items: center; justify-content: center; color: <?= $is_paye ? '#10b981' : '#f59e0b' ?>;">
                                <i data-feather="<?= $is_paye ? 'check' : 'clock' ?>"></i>
                            </div>
                            <div>
                                <strong style="display: block; font-size: 16px;"><?= htmlspecialchars($nom) ?></strong>
                                <span style="font-size: 13px; color: #64748b;">Frais de scolarité obligatoires</span>
                            </div>
                        </div>
                        <div style="text-align: right;">
                            <div style="font-size: 18px; font-weight: 800; color: #0a1931;"><?= $montant ?> $ USD</div>
                            <span style="font-size: 11px; font-weight: 700; color: <?= $is_paye ? '#10b981' : '#f59e0b' ?>;"><?= $is_paye ? 'DÉJÀ PAYÉ' : 'À RÉGLER' ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Panier de paiement -->
            <div style="position: sticky; top: 100px; height: fit-content;">
                <div class="card" style="padding: 25px; border: none; box-shadow: 0 10px 25px rgba(0,0,0,0.1);">
                    <h3 style="margin-bottom: 20px;">Finaliser le paiement</h3>
                    <div id="selected-fee-box" style="padding: 15px; background: #fff7ed; border-radius: 10px; margin-bottom: 20px; border: 2px dashed #f59e0b; text-align: center; animation: pulse-border 2s infinite;">
                        <p style="color: #92400e; font-size: 14px; font-weight: 600; margin: 0;">👈 Cliquez d'abord sur une tranche à gauche</p>
                    </div>

                    <div style="background: #f0f7ff; border: 2px solid #0a1931; border-radius: 12px; padding: 15px; margin-bottom: 20px; text-align: center; display: flex; align-items: center; justify-content: center; gap: 10px; color: #0a1931;">
                        <i data-feather="smartphone" style="width: 20px; height: 20px;"></i>
                        <span style="font-weight: 600; font-size: 14px;">Paiement Mobile Money (Shwary)</span>
                    </div>

                    <form action="traitement_paiement_minerval.php" method="POST" id="paymentForm">
                        <input type="hidden" name="eleve_id" value="<?= $eleve_id ?>">
                        <input type="hidden" name="type_frais" id="input-frais">
                        <input type="hidden" name="montant" id="input-montant">
                        <input type="hidden" name="methode" id="input-methode" value="momo">

                        <div id="momo-fields">
                            <div class="form-group">
                                <label style="font-size: 12px; font-weight: 600; color: #0a1931;">Numéro Mobile Money (M-Pesa, Orange, Airtel…)</label>
                                <input type="tel" name="phone_momo" id="phone_momo" class="form-control" value="0990808984" placeholder="+243 8XX XXX XXX" autocomplete="tel" required>
                                <small style="color:#64748b; font-size:11px; display: block; margin-top: 5px;">Format : +243 suivi de votre numéro (ex : +243812345678)</small>
                            </div>
                        </div>


                        <button type="submit" class="btn btn-primary" id="btn-payer" disabled
                            title="Sélectionnez d'abord une tranche à gauche"
                            style="width: 100%; margin-top: 25px; padding: 15px; font-weight: bold; background: #cbd5e1; cursor: not-allowed; transition: all 0.3s;">
                            CONFIRMER LE PAIEMENT
                        </button>
                    </form>
                    <p style="font-size: 11px; color: #94a3b8; text-align: center; margin-top: 15px;">Paiement sécurisé par cryptage SSL 256 bits.</p>
                </div>
            </div>
        </div>
    </div>

    <script>
        feather.replace();

        function selectFee(nom, montant) {
            document.getElementById('selected-fee-box').innerHTML = `
                <strong style="font-size: 18px; color: #0a1931;">${nom}</strong>
                <div style="font-size: 24px; font-weight: 800; color: #f59e0b; margin-top: 5px;">${montant} $ USD</div>
            `;
            document.getElementById('selected-fee-box').style.background  = '#f0fdf4';
            document.getElementById('selected-fee-box').style.border      = '2px solid #10b981';
            document.getElementById('input-frais').value   = nom;
            document.getElementById('input-montant').value = montant;

            const btn = document.getElementById('btn-payer');
            btn.disabled   = false;
            btn.innerText  = `PAYER ${montant} $ USD`;
            btn.style.background  = '#0a1931';
            btn.style.cursor      = 'pointer';
            btn.removeAttribute('title');
        }

        // Validation avant soumission
        document.getElementById('paymentForm').addEventListener('submit', function(e) {
            const frais   = document.getElementById('input-frais').value;
            const montant = document.getElementById('input-montant').value;

            if (!frais || !montant) {
                e.preventDefault();
                alert('Veuillez sélectionner une tranche à gauche avant de confirmer.');
                return;
            }

            const phone = document.getElementById('phone_momo').value.trim();
            if (!phone) {
                e.preventDefault();
                document.getElementById('phone_momo').focus();
                document.getElementById('phone_momo').style.border = '2px solid #ef4444';
                alert('Veuillez entrer votre numéro Mobile Money avant de confirmer.');
                return;
            }
            // Réinitialiser le style si OK
            document.getElementById('phone_momo').style.border = '';

            // Désactiver le bouton pour éviter le double-clic
            const btn = document.getElementById('btn-payer');
            btn.disabled  = true;
            btn.innerText = 'Traitement en cours…';
        });
    </script>
</body>
</html>

