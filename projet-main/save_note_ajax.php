<?php
session_start();
require_once 'config.php';

header('Content-Type: application/json');

// Vérification de sécurité basique
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'enseignant') {
    echo json_encode(['success' => false, 'error' => 'Non autorisé']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(['success' => false, 'error' => 'Données invalides']);
    exit();
}

try {
    if (isset($input['type']) && $input['type'] === 'note') {
        // Enregistrement d'une note spécifique
        $eleve_id = (int)$input['eleve_id'];
        $cours_id = (int)$input['cours_id'];
        $periode = $input['periode'];
        $note = $input['note'] !== '' ? (float)$input['note'] : null;
        $note_max = (int)$input['note_max'];

        if ($note !== null) {
            // Upsert note
            $stmt_check = $pdo->prepare("SELECT id FROM notes WHERE eleve_id = ? AND cours_id = ? AND periode = ?");
            $stmt_check->execute([$eleve_id, $cours_id, $periode]);
            if ($stmt_check->fetch()) {
                $stmt_upd = $pdo->prepare("UPDATE notes SET note = ?, note_max = ? WHERE eleve_id = ? AND cours_id = ? AND periode = ?");
                $stmt_upd->execute([$note, $note_max, $eleve_id, $cours_id, $periode]);
            } else {
                $stmt_ins = $pdo->prepare("INSERT INTO notes (eleve_id, cours_id, note, note_max, periode) VALUES (?, ?, ?, ?, ?)");
                $stmt_ins->execute([$eleve_id, $cours_id, $note, $note_max, $periode]);
            }
        } else {
            // Delete note si effacée
            $stmt_del = $pdo->prepare("DELETE FROM notes WHERE eleve_id = ? AND cours_id = ? AND periode = ?");
            $stmt_del->execute([$eleve_id, $cours_id, $periode]);
        }
        
        echo json_encode(['success' => true]);

    } elseif (isset($input['type']) && $input['type'] === 'bulletin') {
        // Mise à jour des stats globales du bulletin
        $bulletin_id = (int)$input['bulletin_id'];
        $points_obtenus = (float)$input['points_obtenus'];
        $pourcentage = (float)$input['pourcentage'];
        $statut = $pourcentage >= 50 ? 'Admis' : 'Échoué';
        
        // Mises à jour textuelles (si fournies, sinon on garde l'existant)
        $updates = ["points_obtenus = ?", "pourcentage = ?", "statut = ?"];
        $params = [$points_obtenus, $pourcentage, $statut];
        
        if (isset($input['conduite'])) {
            $updates[] = "conduite = ?";
            $params[] = $input['conduite'];
        }
        if (isset($input['application'])) {
            $updates[] = "application = ?";
            $params[] = $input['application'];
        }
        if (isset($input['decision'])) {
            $updates[] = "decision = ?";
            $params[] = $input['decision'];
        }
        
        $params[] = $bulletin_id;
        
        $sql = "UPDATE bulletins SET " . implode(", ", $updates) . " WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Type d\'action inconnu']);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
