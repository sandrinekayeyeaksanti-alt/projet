<?php
ob_start();
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once 'config.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Récupération des données du formulaire
    $nom = htmlspecialchars(trim($_POST['nom'] ?? ''));
    $post_nom_prenom = htmlspecialchars(trim($_POST['post_nom_prenom'] ?? ''));
    $lieu_date_naissance = htmlspecialchars(trim($_POST['lieu_date_naissance'] ?? ''));
    $sexe = htmlspecialchars(trim($_POST['sexe'] ?? ''));
    $classe_id = (int)($_POST['classe_id'] ?? 0);
    $ecole_provenance = htmlspecialchars(trim($_POST['ecole_provenance'] ?? ''));
    $tuteur_nom = htmlspecialchars(trim($_POST['tuteur_nom'] ?? ''));
    $tuteur_tel = htmlspecialchars(trim($_POST['tuteur_tel'] ?? ''));
    $tuteur_email = htmlspecialchars(trim($_POST['tuteur_email'] ?? ''));
    $tuteur_adresse = htmlspecialchars(trim($_POST['tuteur_adresse'] ?? ''));
    
    // Hashage du code PIN pour la sécurité
    $code_pin_raw = $_POST['code_pin'] ?? '';
    $code_pin = password_hash($code_pin_raw, PASSWORD_DEFAULT);
    
    $statut_paiement = "À payer à l'école (Frais de test)"; // Le paiement se fera sur place

    // Traitement des fichiers bulletins (Optionnels, 1 à 4 fichiers)
    $bulletin_paths = [];
    if (isset($_FILES['bulletin_files']) && is_array($_FILES['bulletin_files']['name'])) {
        $upload_dir = 'uploads/bulletins/';
        if (!is_dir($upload_dir)) {
            @mkdir($upload_dir, 0777, true);
        }
        
        $allowed_extensions = ['pdf', 'png', 'jpg', 'jpeg', 'gif'];
        $allowed_mimes = [
            'application/pdf',
            'image/png',
            'image/jpeg',
            'image/jpg',
            'image/gif'
        ];
        
        $total_files = count($_FILES['bulletin_files']['name']);
        for ($i = 0; $i < $total_files; $i++) {
            if ($_FILES['bulletin_files']['error'][$i] === UPLOAD_ERR_OK) {
                $file_name = $_FILES['bulletin_files']['name'][$i];
                $tmp_name = $_FILES['bulletin_files']['tmp_name'][$i];
                
                // 1. Validation de l'extension du fichier
                $file_extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                if (!in_array($file_extension, $allowed_extensions)) {
                    continue; // Ignore le fichier si l'extension n'est pas autorisée
                }
                
                // 2. Validation du type MIME
                $mime_type = '';
                if (function_exists('finfo_open')) {
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    $mime_type = finfo_file($finfo, $tmp_name);
                    finfo_close($finfo);
                } elseif (function_exists('mime_content_type')) {
                    $mime_type = mime_content_type($tmp_name);
                }
                
                if (!empty($mime_type) && !in_array($mime_type, $allowed_mimes)) {
                    continue; // Ignore le fichier si le type MIME est suspect
                }
                
                // Sanitize student name for filename
                $sanitized_nom = preg_replace('/[^a-zA-Z0-9]/', '_', $nom);
                $new_filename = 'bulletin_' . $sanitized_nom . '_' . time() . '_' . ($i + 1) . '.' . $file_extension;
                $destination = $upload_dir . $new_filename;
                
                if (@move_uploaded_file($tmp_name, $destination)) {
                    $bulletin_paths[] = $destination;
                }
            }
        }
    }
    
    // Encoder en JSON pour stockage dans la colonne bulletin_path
    $bulletin_path = !empty($bulletin_paths) ? json_encode($bulletin_paths, JSON_UNESCAPED_SLASHES) : null;

    if(isset($pdo)) {
        try {
            // Requête SQL préparée
            $sql = "INSERT INTO eleves (nom, post_nom_prenom, lieu_date_naissance, sexe, classe_id, ecole_provenance, bulletin_path, tuteur_nom, tuteur_tel, tuteur_email, tuteur_adresse, code_pin, statut_paiement, statut_inscription) 
                    VALUES (:nom, :post_nom_prenom, :lieu_date_naissance, :sexe, :classe_id, :ecole_provenance, :bulletin_path, :tuteur_nom, :tuteur_tel, :tuteur_email, :tuteur_adresse, :code_pin, :statut_paiement, 'En attente')";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':nom' => $nom,
                ':post_nom_prenom' => $post_nom_prenom,
                ':lieu_date_naissance' => $lieu_date_naissance,
                ':sexe' => $sexe,
                ':classe_id' => $classe_id,
                ':ecole_provenance' => $ecole_provenance,
                ':bulletin_path' => $bulletin_path,
                ':tuteur_nom' => $tuteur_nom,
                ':tuteur_tel' => $tuteur_tel,
                ':tuteur_email' => $tuteur_email,
                ':tuteur_adresse' => $tuteur_adresse,
                ':code_pin' => $code_pin,
                ':statut_paiement' => $statut_paiement
            ]);
            
            // Récupérer l'ID de l'élève qui vient d'être inséré
            $new_eleve_id = $pdo->lastInsertId();

            $_SESSION['eleve_id'] = $new_eleve_id;
            $_SESSION['parent_nom'] = $tuteur_nom;
            $_SESSION['eleve_nom'] = $nom . ' ' . $post_nom_prenom;
            $_SESSION['role'] = 'eleve'; // Par défaut après inscription, on peut aller à l'espace élève

            // Redirection vers le portail (en attente de validation après test à l'école)
            session_write_close();
            header("Location: portail.php?registered=1");
            exit();

        } catch(PDOException $e) {
            echo "Erreur d'enregistrement : " . $e->getMessage();
        }
    } else {
        echo "<h3>Erreur : Base de données non connectée.</h3>";
        echo "<p>Veuillez vérifier que XAMPP est lancé et que la base 'bellevue_db' est créée.</p>";
        if(isset($pdo_error)) echo "<p>Détail : $pdo_error</p>";
        echo "<br><a href='inscription.php'>Retour</a>";
    }
} else {
    // Redirection si accès direct
    header("Location: inscription.php");
    exit();
}
?>
