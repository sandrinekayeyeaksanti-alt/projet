<?php
session_start();
require_once 'config.php';

// ============================================================
//  CONFIGURATION STRIPE — Même clé que traitement_paiement_minerval.php
// ============================================================
$stripe_secret_key = getenv('STRIPE_SECRET_KEY'); // Clé secrète Stripe
// ============================================================

if (isset($_GET['status']) && $_GET['status'] === 'successful') {

    $session_id = $_GET['session_id'] ?? '';   // ID de session Stripe passé dans success_url
    $tx_ref     = $_GET['tx_ref']     ?? '';
    $eleve_id   = (int) ($_GET['eleve_id']   ?? 0);
    $type_frais = $_GET['type_frais'] ?? '';
    $montant    = (float) ($_GET['montant']  ?? 0);

    if (!empty($session_id)) {

        // 1. Vérification de la session auprès de Stripe
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL            => "https://api.stripe.com/v1/checkout/sessions/" . urlencode($session_id),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                "Authorization: Bearer $stripe_secret_key",
            ],
        ]);
        $response = curl_exec($curl);
        curl_close($curl);
        $session = json_decode($response);

        // 2. On vérifie que le paiement est bien confirmé par Stripe
        if (isset($session->payment_status) && $session->payment_status === 'paid') {

            $amount_total = $session->amount_total / 100; // Stripe renvoie en centimes
            $reference    = $session->client_reference_id ?? $tx_ref;

            if (isset($pdo) && $eleve_id > 0 && !empty($type_frais)) {
                try {
                    // 3. Vérifier que ce paiement n'est pas déjà enregistré (anti-doublon)
                    $check = $pdo->prepare("SELECT id FROM paiements WHERE reference_transaction = ?");
                    $check->execute([$reference]);

                    if (!$check->fetch()) {
                        // 4. Enregistrement en base de données
                        $stmt = $pdo->prepare("INSERT INTO paiements (eleve_id, montant, date_paiement, type_frais, methode_paiement, reference_transaction, statut) VALUES (?, ?, NOW(), ?, ?, ?, 'Payé')");
                        $stmt->execute([$eleve_id, $amount_total, $type_frais, 'Stripe', $reference]);
                    }

                    // 5. Redirection vers le portail avec succès
                    header("Location: portail.php?payment_success=1&frais=" . urlencode($type_frais));
                    exit();

                } catch (PDOException $e) {
                    // Erreur BDD — on redirige quand même avec l'erreur
                    header("Location: portail.php?payment_error=1");
                    exit();
                }
            }
        }
    }
}

// Si on arrive ici, c'est qu'il y a eu un problème
header("Location: portail.php?payment_error=1");
exit();
