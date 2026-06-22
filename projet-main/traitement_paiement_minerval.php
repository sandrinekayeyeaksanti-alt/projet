<?php
session_start();
require_once 'config.php';

// ============================================================
//  CONFIGURATION STRIPE — Clé secrète
// ============================================================
$stripe_secret_key = getenv('STRIPE_SECRET_KEY');
// ============================================================

// ============================================================
//  CONFIGURATION SHWARY — Mobile Money (DRC)
// ============================================================
$SHWARY_MERCHANT_ID  = getenv('SHWARY_MERCHANT_ID') ?: "2b176b78-0ba2-4018-a8db-f5948ffba409";   // UUID marchand Shwary
$SHWARY_MERCHANT_KEY = getenv('SHWARY_MERCHANT_KEY') ?: "shwary_f0e0e79a-52d1-49da-b563-f86d4efe0f9c";  // Clé secrète Shwary
$TAUX_USD_CDF        = 2800;                       // 1 USD ≈ 2800 CDF (ajustez si besoin)
// ============================================================

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_SESSION['role'])) {

    // Récupération sécurisée de l'ID élève selon le rôle
    $role = $_SESSION['role'];
    if ($role === 'parent') {
        $eleve_id = isset($_POST['eleve_id']) ? (int)$_POST['eleve_id'] : ($_SESSION['parent_id'] ?? null);
        // Vérification de sécurité : l'élève doit correspondre au tuteur
        if ($eleve_id && isset($pdo)) {
            $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM eleves WHERE id = ? AND tuteur_tel = ?");
            $stmt_check->execute([$eleve_id, $_SESSION['username']]);
            if ($stmt_check->fetchColumn() == 0) {
                die("<p style='color:red; font-family:sans-serif;'>Élève non autorisé.</p><a href='dashboard_parent.php'>Retour</a>");
            }
        }
    } elseif ($role === 'eleve') {
        $eleve_id = $_SESSION['eleve_id'] ?? null;
    } else {
        // Ni parent ni élève : accès non autorisé
        header("Location: portail.php");
        exit();
    }

    if (!$eleve_id) {
        die("<p style='color:red; font-family:sans-serif;'>Session invalide. Veuillez vous reconnecter.</p><a href='login.php'>Connexion</a>");
    }

    $type_frais = $_POST['type_frais'] ?? '';
    $montant   = (int) ($_POST['montant'] ?? 0);
    $methode   = $_POST['methode'] ?? 'card';
    $email     = "parent_" . $eleve_id . "@bellevue.com";
    $reference = "BELLEVUE-" . strtoupper(substr(md5(time() . $eleve_id), 0, 10));

    // Détermination dynamique de l'URL de base du projet
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https" : "http";
    $host = $_SERVER['HTTP_HOST'];
    $base_url = $protocol . "://" . $host . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\') . '/';

    // -------------------------------------------------------
    //  PAIEMENT UNIQUE MOBILE MONEY — via Shwary (DRC / CDF)
    // -------------------------------------------------------
    $phone_momo = trim($_POST['phone_momo'] ?? '');

    if (empty($phone_momo)) {
        die("<p style='color:red; font-family:sans-serif;'>Veuillez entrer votre numéro de téléphone Mobile Money.</p><a href='payer_minerval.php'>Retour</a>");
    }

    // Normalisation du numéro → format E.164 (+243XXXXXXXXX)
    $phone_momo = preg_replace('/\s+/', '', $phone_momo);
    if (!str_starts_with($phone_momo, '+')) {
        $phone_momo = '+243' . ltrim($phone_momo, '0');
    }

    // Conversion USD → CDF (Shwary DRC exige CDF, minimum 2900 CDF)
    $montant_cdf = max(2900, (int)($montant * $TAUX_USD_CDF));

    $body = json_encode([
        'amount'            => $montant_cdf,
        'clientPhoneNumber' => $phone_momo,
        'callbackUrl'       => $base_url . 'shwary_callback.php',
        'referenceId'       => $reference,
        'metadata'          => [
            'eleve_id'      => $eleve_id,
            'type_frais'    => $type_frais,
            'montant_usd'   => $montant
        ]
    ]);

        // Appel API Shwary
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL            => "https://api.shwary.com/api/v1/merchants/payment/DRC",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_HTTPHEADER     => [
                "Content-Type: application/json",
                "x-merchant-id: $SHWARY_MERCHANT_ID",
                "x-merchant-key: $SHWARY_MERCHANT_KEY",
            ],
        ]);

        $response = curl_exec($curl);
        curl_close($curl);
        $res = json_decode($response, true);

        if (isset($res['id']) && ($res['status'] === 'pending' || $res['status'] === 'submitted')) {

            // Sauvegarder les infos en session pour la page d'attente
            $_SESSION['shwary_transaction'] = [
                'id'         => $res['id'],
                'reference'  => $reference,
                'eleve_id'   => $eleve_id,
                'type_frais' => $type_frais,
                'montant'    => $montant,
                'montant_cdf'=> $montant_cdf,
                'phone'      => $phone_momo,
            ];

            header("Location: attente_momo.php");
            exit();

        } else {
            $erreur_brute = $res['message'] ?? ($res['error'] ?? 'Erreur inconnue.');

            // Traduction des messages Shwary courants en français
            $erreur_fr = $erreur_brute;
            if (stripos($erreur_brute, 'Daily collection limit') !== false) {
                $erreur_fr = '⏱️ Limite journalière atteinte';
                $detail_fr = 'Le plafond de collecte quotidien a été atteint. Réessayez dans 24 heures ou contactez l\'équipe Shwary pour augmenter votre limite.';
                $conseil   = 'Contactez Shwary à l\'adresse <strong>support@shwary.com</strong> pour augmenter votre plafond.';
            } elseif (stripos($erreur_brute, 'can not be fully processed') !== false || stripos($erreur_brute, 'can instead process') !== false) {
                // Extraire le montant max que Shwary peut traiter
                preg_match('/process ([\d.]+)/', $erreur_brute, $matches);
                $max_cdf  = $matches[1] ?? '?';
                $max_usd  = $max_cdf ? round($max_cdf / $TAUX_USD_CDF, 2) : '?';
                $erreur_fr = '🏦 Limite du compte de test Shwary';
                $detail_fr = "Ce compte Shwary est limité en collecte. Montant maximum traitable : <strong>{$max_cdf} CDF (~{$max_usd} USD)</strong>.<br>Pour lever cette limite, votre ami doit demander une <strong>augmentation du plafond</strong> sur son tableau de bord Shwary, ou vous pouvez créer votre propre compte marchand Shwary.";
                $conseil   = 'Rendez-vous sur <strong>shwary.com</strong> pour créer votre compte marchand et obtenir vos propres clés API sans limite de test.';
            } elseif (stripos($erreur_brute, 'not found') !== false) {
                $erreur_fr = '❌ Numéro introuvable';
                $detail_fr = 'Le numéro de téléphone saisi n\'est pas enregistré dans le système Shwary. Vérifiez le format (+243XXXXXXXXX).';
                $conseil   = 'Assurez-vous que votre numéro est bien inscrit sur Airtel Money, M-Pesa ou Orange Money.';
            } elseif (stripos($erreur_brute, 'Invalid') !== false || stripos($erreur_brute, 'invalid') !== false) {
                $erreur_fr = '⚠️ Numéro invalide';
                $detail_fr = 'Le format du numéro de téléphone est invalide.';
                $conseil   = 'Utilisez le format international : <strong>+243 8XX XXX XXX</strong>';
            } elseif (stripos($erreur_brute, 'Unauthorized') !== false || stripos($erreur_brute, 'merchant') !== false) {
                $erreur_fr = '🔑 Erreur d\'authentification';
                $detail_fr = 'Les clés marchandes Shwary sont invalides ou expirées.';
                $conseil   = 'Vérifiez votre fichier <code>.env</code> et assurez-vous que SHWARY_MERCHANT_ID et SHWARY_MERCHANT_KEY sont corrects.';
            } else {
                $detail_fr = htmlspecialchars($erreur_brute);
                $conseil   = 'Réessayez dans quelques instants ou contactez l\'administrateur.';
            }

            // Page d'erreur stylée
            echo "<!DOCTYPE html><html lang='fr'><head><meta charset='UTF-8'><title>Erreur de paiement</title>
            <link rel='stylesheet' href='assets/css/style.css?v=" . time() . "'>
            <style>
                body{background:#f8fafc;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;font-family:sans-serif;}
                .err-card{background:white;border-radius:16px;padding:40px;max-width:480px;width:100%;text-align:center;box-shadow:0 20px 60px rgba(0,0,0,.1);}
                .err-icon{font-size:60px;margin-bottom:15px;}
                .err-title{font-size:20px;font-weight:700;color:#0a1931;margin-bottom:10px;}
                .err-detail{background:#fef3c7;border:1px solid #fbbf24;border-radius:10px;padding:15px;font-size:14px;color:#92400e;margin:15px 0;text-align:left;}
                .err-conseil{font-size:13px;color:#64748b;margin:10px 0 25px;}
                .btn-back{display:inline-block;padding:12px 28px;background:#0a1931;color:white;border-radius:10px;text-decoration:none;font-weight:600;font-size:14px;}
                .btn-back:hover{opacity:.85;}
            </style></head><body>
            <div class='err-card'>
                <div class='err-icon'>🚫</div>
                <div class='err-title'>$erreur_fr</div>
                <div class='err-detail'>$detail_fr</div>
                <p class='err-conseil'>$conseil</p>
                <a href='payer_minerval.php' class='btn-back'>← Réessayer le paiement</a>
            </div>
            </body></html>";
            exit();
        }
} else {
    header("Location: portail.php");
    exit();
}

