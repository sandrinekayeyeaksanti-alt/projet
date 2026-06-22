<?php
/**
 * check_momo_status.php
 * ----------------------
 * Endpoint AJAX appelé par attente_momo.php toutes les 5 secondes.
 * Interroge l'API Shwary GET /merchants/transactions/{id}
 * et retourne le statut en JSON.
 */
session_start();
require_once 'config.php';

header('Content-Type: application/json');

// ============================================================
//  CONFIGURATION SHWARY — Mêmes clés que traitement_paiement_minerval.php
// ============================================================
$SHWARY_MERCHANT_ID  = getenv('SHWARY_MERCHANT_ID') ?: "d909df62-f14f-461e-acd1-61ced0484915";
$SHWARY_MERCHANT_KEY = getenv('SHWARY_MERCHANT_KEY') ?: "shwary_24e77efe-8678-40fc-921e-f524b110b25a";
// ============================================================

// Vérification session
if (!isset($_SESSION['role'])) {
    echo json_encode(['error' => 'Non authentifié']);
    exit();
}

$transaction_id = trim($_GET['id'] ?? '');

if (empty($transaction_id)) {
    echo json_encode(['error' => 'ID manquant']);
    exit();
}

// Vérifier que cet ID appartient bien à la session en cours (sécurité)
$session_tx_id = $_SESSION['shwary_transaction']['id'] ?? '';
if ($transaction_id !== $session_tx_id) {
    echo json_encode(['error' => 'ID non autorisé']);
    exit();
}

// Appel GET Shwary pour récupérer l'état de la transaction
$curl = curl_init();
curl_setopt_array($curl, [
    CURLOPT_URL            => "https://api.shwary.com/api/v1/merchants/transactions/" . urlencode($transaction_id),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPGET        => true,
    CURLOPT_HTTPHEADER     => [
        "x-merchant-id: $SHWARY_MERCHANT_ID",
        "x-merchant-key: $SHWARY_MERCHANT_KEY",
    ],
]);

$response = curl_exec($curl);
$http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
curl_close($curl);

$data = json_decode($response, true);

if ($http_code === 200 && isset($data['status'])) {

    // Si completed et paiement pas encore enregistré → on l'insère ici
    if ($data['status'] === 'completed' && isset($pdo)) {
        $tx       = $_SESSION['shwary_transaction'];
        $reference = $tx['reference'];
        $eleve_id  = $tx['eleve_id'];
        $type_frais = $tx['type_frais'];
        $montant_usd = $tx['montant'];

        try {
            $check = $pdo->prepare("SELECT id FROM paiements WHERE reference_transaction = ?");
            $check->execute([$reference]);
            if (!$check->fetch()) {
                $stmt = $pdo->prepare("INSERT INTO paiements (eleve_id, montant, date_paiement, type_frais, methode_paiement, reference_transaction, statut) VALUES (?, ?, NOW(), ?, 'Shwary Mobile Money', ?, 'Payé')");
                $stmt->execute([$eleve_id, $montant_usd, $type_frais, $reference]);

                // Mettre à jour le statut_paiement général de l'élève
                if ($type_frais === "Test d'admission") {
                    $up = $pdo->prepare("UPDATE eleves SET statut_paiement = 'Test payé' WHERE id = ?");
                    $up->execute([$eleve_id]);
                } else {
                    $up = $pdo->prepare("UPDATE eleves SET statut_paiement = ? WHERE id = ?");
                    $up->execute([$type_frais . " payée", $eleve_id]);
                }
            }
        } catch (PDOException $e) { /* silencieux */ }
    }

    echo json_encode([
        'status'        => $data['status'],
        'failureReason' => $data['failureReason'] ?? null,
        'amount'        => $data['amount'] ?? null,
        'currency'      => $data['currency'] ?? null,
        'txHash'        => $data['txHash'] ?? null,
    ]);

} else {
    echo json_encode([
        'status' => 'pending', // En cas d'erreur réseau, on reste en attente
        'error'  => $data['message'] ?? 'Erreur de récupération',
    ]);
}
