<?php
/**
 * shwary_callback.php
 * -------------------
 * Reçoit les callbacks POST asynchrones de Shwary.
 * Appelé par Shwary à chaque changement d'état : pending → submitted → completed / failed / cancelled
 *
 * ⚠️  Cette URL doit être publiquement accessible (pas localhost en production).
 *     Exemple : https://bellevue.cd/PROJET_aksanti/shwary_callback.php
 */

require_once 'config.php';

// ============================================================
//  CONFIGURATION SHWARY
// ============================================================
$SHWARY_MERCHANT_ID  = getenv('SHWARY_MERCHANT_ID') ?: "d909df62-f14f-461e-acd1-61ced0484915";
$SHWARY_MERCHANT_KEY = getenv('SHWARY_MERCHANT_KEY') ?: "shwary_24e77efe-8678-40fc-921e-f524b110b25a";
// ============================================================

// Récupérer le corps de la requête JSON
$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

// Journalisation basique (optionnel, utile pour déboguer)
$log_dir = __DIR__ . '/uploads/bulletins/';
if (is_writable($log_dir)) {
    file_put_contents($log_dir . 'shwary_callbacks.log', date('[Y-m-d H:i:s] ') . $raw . "\n", FILE_APPEND);
}

// Validation minimale
if (!is_array($data) || empty($data['id']) || empty($data['status'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Payload invalide']);
    exit();
}

$transaction_id = $data['id'];
$status         = $data['status'];          // pending | submitted | completed | failed | cancelled
$amount_cdf     = (float) ($data['amount'] ?? 0);
$phone          = $data['recipientPhoneNumber'] ?? '';
$reference      = $data['referenceId'] ?? $transaction_id;
$failure_reason = $data['failureReason'] ?? null;

// On n'agit que lorsque la transaction est terminée
if ($status === 'completed') {

    if (!isset($pdo)) {
        http_response_code(500);
        echo json_encode(['error' => 'Base de données indisponible']);
        exit();
    }

    try {
        // Anti-doublon : vérifier si cette transaction est déjà enregistrée
        $check = $pdo->prepare("SELECT id FROM paiements WHERE reference_transaction = ?");
        $check->execute([$reference]);

        if ($check->fetch()) {
            // Déjà enregistré — répondre 200 pour éviter les retries Shwary
            http_response_code(200);
            echo json_encode(['status' => 'already_recorded']);
            exit();
        }

        // Retrouver eleve_id et type_frais depuis la référence (format BELLEVUE-XXXXXXXXXX)
        // Ces infos sont aussi contenues dans la session lors de l'initiation.
        // Ici on les retrouve via la table paiements_pending si elle existe,
        // ou on utilise les metadata si disponibles dans le payload.
        $eleve_id   = $data['metadata']['eleve_id']   ?? null;
        $type_frais = $data['metadata']['type_frais']  ?? 'Mobile Money';
        $montant_usd = $data['metadata']['montant_usd'] ?? round($amount_cdf / 2800, 2);

        if (!$eleve_id) {
            // Si metadata absent, on log et on répond 200 pour ne pas bloquer Shwary
            http_response_code(200);
            echo json_encode(['status' => 'metadata_missing', 'note' => 'eleve_id introuvable dans metadata']);
            exit();
        }

        // Enregistrement du paiement
        $stmt = $pdo->prepare("
            INSERT INTO paiements (eleve_id, montant, date_paiement, type_frais, methode_paiement, reference_transaction, statut)
            VALUES (?, ?, NOW(), ?, 'Shwary Mobile Money', ?, 'Payé')
        ");
        $stmt->execute([$eleve_id, $montant_usd, $type_frais, $reference]);

        // Mettre à jour le statut_paiement général de l'élève
        if ($type_frais === "Test d'admission") {
            $up = $pdo->prepare("UPDATE eleves SET statut_paiement = 'Test payé' WHERE id = ?");
            $up->execute([$eleve_id]);
        } else {
            $up = $pdo->prepare("UPDATE eleves SET statut_paiement = ? WHERE id = ?");
            $up->execute([$type_frais . " payée", $eleve_id]);
        }

        http_response_code(200);
        echo json_encode(['status' => 'recorded', 'reference' => $reference]);

    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }

} elseif ($status === 'failed' || $status === 'cancelled') {

    // Optionnel : journaliser l'échec
    if (is_writable($log_dir)) {
        file_put_contents(
            $log_dir . 'shwary_callbacks.log',
            date('[Y-m-d H:i:s] ') . "ÉCHEC [$status] ref=$reference raison=" . ($failure_reason ?? 'N/A') . "\n",
            FILE_APPEND
        );
    }

    http_response_code(200);
    echo json_encode(['status' => 'acknowledged', 'transaction_status' => $status]);

} else {
    // pending ou submitted — rien à faire, juste accuser réception
    http_response_code(200);
    echo json_encode(['status' => 'acknowledged', 'transaction_status' => $status]);
}
