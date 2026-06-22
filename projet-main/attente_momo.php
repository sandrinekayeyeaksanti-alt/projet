<?php
session_start();
require_once 'config.php';

// Vérification session
if (!isset($_SESSION['role']) || !isset($_SESSION['shwary_transaction'])) {
    header("Location: payer_minerval.php");
    exit();
}

$tx = $_SESSION['shwary_transaction'];
$shwary_id   = $tx['id'];
$type_frais  = $tx['type_frais'];
$montant     = $tx['montant'];
$montant_cdf = $tx['montant_cdf'];
$phone       = $tx['phone'];
$eleve_id    = $tx['eleve_id'];
$reference   = $tx['reference'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paiement Mobile Money en cours | École Belle Vue</title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?= time() ?>">
    <script src="https://unpkg.com/feather-icons"></script>
    <style>
        body { background: #f0f7ff; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; font-family: 'Inter', sans-serif; }

        .wait-card {
            background: white;
            border-radius: 20px;
            padding: 50px 40px;
            max-width: 480px;
            width: 100%;
            text-align: center;
            box-shadow: 0 20px 60px rgba(0,0,0,0.1);
        }

        .pulse-circle {
            width: 100px; height: 100px;
            border-radius: 50%;
            background: linear-gradient(135deg, #10b981, #059669);
            margin: 0 auto 30px;
            display: flex; align-items: center; justify-content: center;
            animation: pulse 2s infinite;
            color: white;
        }
        @keyframes pulse {
            0%   { box-shadow: 0 0 0 0 rgba(16,185,129,0.5); }
            70%  { box-shadow: 0 0 0 25px rgba(16,185,129,0); }
            100% { box-shadow: 0 0 0 0 rgba(16,185,129,0); }
        }

        .info-row { display: flex; justify-content: space-between; padding: 12px 0; border-bottom: 1px solid #f1f5f9; font-size: 14px; }
        .info-row:last-child { border-bottom: none; }
        .info-label { color: #64748b; }
        .info-value { font-weight: 600; color: #0a1931; }

        .status-badge {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 8px 18px; border-radius: 20px;
            font-size: 13px; font-weight: 600; margin: 20px 0;
        }
        .status-badge.pending  { background: #fef3c7; color: #92400e; }
        .status-badge.completed { background: #dcfce7; color: #166534; }
        .status-badge.failed   { background: #fee2e2; color: #991b1b; }
        .status-badge.cancelled { background: #f1f5f9; color: #475569; }

        .dot-loader { display: inline-flex; gap: 6px; vertical-align: middle; }
        .dot-loader span { width: 8px; height: 8px; border-radius: 50%; background: #92400e; animation: bounce 1.2s infinite; }
        .dot-loader span:nth-child(2) { animation-delay: 0.2s; }
        .dot-loader span:nth-child(3) { animation-delay: 0.4s; }
        @keyframes bounce { 0%,80%,100% { transform: scale(0.6); opacity: 0.4; } 40% { transform: scale(1); opacity: 1; } }

        #status-msg { margin-top: 15px; font-size: 14px; color: #64748b; min-height: 40px; }
        .btn-retour { display: inline-block; margin-top: 25px; padding: 12px 28px; border-radius: 10px; background: #0a1931; color: white; text-decoration: none; font-size: 14px; font-weight: 600; transition: opacity 0.2s; }
        .btn-retour:hover { opacity: 0.85; }
    </style>
</head>
<body>
    <div class="wait-card">
        <div class="pulse-circle">
            <i data-feather="smartphone" style="width: 40px; height: 40px;"></i>
        </div>

        <h1 style="font-size: 22px; margin-bottom: 8px; color: #0a1931;">Confirmez sur votre téléphone</h1>
        <p style="color: #64748b; font-size: 14px; margin-bottom: 25px;">
            Une demande de paiement Mobile Money a été envoyée au numéro ci-dessous.<br>
            <strong>Acceptez l'invite sur votre téléphone pour finaliser.</strong>
        </p>

        <div style="background: #f8fafc; border-radius: 12px; padding: 20px; text-align: left; margin-bottom: 20px;">
            <div class="info-row">
                <span class="info-label">📱 Numéro</span>
                <span class="info-value"><?= htmlspecialchars($phone) ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">💰 Montant</span>
                <span class="info-value"><?= $montant ?> USD (<?= number_format($montant_cdf) ?> CDF)</span>
            </div>
            <div class="info-row">
                <span class="info-label">🧾 Frais</span>
                <span class="info-value"><?= htmlspecialchars($type_frais) ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">🔖 Référence</span>
                <span class="info-value" style="font-size: 12px;"><?= htmlspecialchars($reference) ?></span>
            </div>
        </div>

        <div id="status-badge" class="status-badge pending">
            <div class="dot-loader"><span></span><span></span><span></span></div>
            En attente de confirmation…
        </div>

        <div id="status-msg">Vérification automatique toutes les 5 secondes…</div>

        <a href="payer_minerval.php" class="btn-retour" id="btn-retour" style="display:none;">
            ← Retour au paiement
        </a>
        <a href="portail.php" class="btn-retour" id="btn-portail" style="display:none; background: #10b981;">
            🎉 Aller au portail
        </a>
    </div>

    <script>
        feather.replace();

        const SHWARY_ID = "<?= htmlspecialchars($shwary_id) ?>";
        const ELEVE_ID  = "<?= (int)$eleve_id ?>";
        const TYPE_FRAIS = "<?= urlencode($type_frais) ?>";
        let attempts = 0;
        const MAX_ATTEMPTS = 24; // 2 minutes max (24 × 5s)

        function checkStatus() {
            fetch('check_momo_status.php?id=' + SHWARY_ID)
                .then(r => r.json())
                .then(data => {
                    const badge  = document.getElementById('status-badge');
                    const msg    = document.getElementById('status-msg');
                    const status = data.status;

                    badge.className = 'status-badge ' + status;

                    if (status === 'completed') {
                        badge.innerHTML = '✅ Paiement confirmé !';
                        msg.innerHTML   = 'Votre paiement a bien été reçu. Redirection en cours…';
                        document.getElementById('btn-portail').style.display = 'inline-block';
                        setTimeout(() => { window.location = 'portail.php?payment_success=1&frais=' + TYPE_FRAIS; }, 2500);

                    } else if (status === 'failed') {
                        badge.innerHTML = '❌ Paiement échoué — ' + (data.failureReason || '');
                        msg.innerHTML   = 'Le paiement a échoué. Veuillez réessayer.';
                        document.getElementById('btn-retour').style.display = 'inline-block';

                    } else if (status === 'cancelled') {
                        badge.innerHTML = '🚫 Paiement annulé';
                        msg.innerHTML   = 'Vous avez annulé la demande. Veuillez réessayer.';
                        document.getElementById('btn-retour').style.display = 'inline-block';

                    } else {
                        // pending / submitted — continuer à vérifier
                        attempts++;
                        badge.innerHTML = '<div class="dot-loader"><span></span><span></span><span></span></div> En attente de confirmation…';
                        msg.innerHTML   = 'Vérification ' + attempts + '/' + MAX_ATTEMPTS + ' — prochain dans 5s…';
                        if (attempts < MAX_ATTEMPTS) {
                            setTimeout(checkStatus, 5000);
                        } else {
                            msg.innerHTML = 'Délai dépassé. Vérifiez votre téléphone puis revenez consulter votre portail.';
                            document.getElementById('btn-retour').style.display = 'inline-block';
                        }
                    }
                })
                .catch(() => {
                    attempts++;
                    if (attempts < MAX_ATTEMPTS) setTimeout(checkStatus, 5000);
                });
        }

        // Première vérification après 5 secondes
        setTimeout(checkStatus, 5000);
    </script>
</body>
</html>

