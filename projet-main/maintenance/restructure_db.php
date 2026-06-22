<?php
require_once 'config.php';
try {
    echo "<h1>Migration de Restructuration</h1>";

    // 1. Table Horaires
    $pdo->exec("CREATE TABLE IF NOT EXISTS horaires (
        id INT AUTO_INCREMENT PRIMARY KEY,
        classe_id INT NOT NULL,
        cours_id INT NOT NULL,
        jour ENUM('Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi') NOT NULL,
        heure_debut TIME NOT NULL,
        heure_fin TIME NOT NULL,
        FOREIGN KEY (classe_id) REFERENCES classes(id) ON DELETE CASCADE,
        FOREIGN KEY (cours_id) REFERENCES cours(id) ON DELETE CASCADE
    )");
    echo "<p>Table 'horaires' créée/vérifiée.</p>";

    // 2. Mise à jour Admins pour Titulariat et Statut
    $check = $pdo->query("SHOW COLUMNS FROM admins LIKE 'statut'")->fetch();
    if (!$check) {
        $pdo->exec("ALTER TABLE admins ADD COLUMN statut VARCHAR(20) DEFAULT 'En attente'");
    }
    
    // 3. Table des Notes (plus précis que bulletins pour les moyennes)
    $pdo->exec("CREATE TABLE IF NOT EXISTS notes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        eleve_id INT NOT NULL,
        cours_id INT NOT NULL,
        note DECIMAL(5, 2) NOT NULL,
        note_max INT DEFAULT 20,
        periode VARCHAR(50) NOT NULL,
        date_examen DATE DEFAULT NULL,
        FOREIGN KEY (eleve_id) REFERENCES eleves(id) ON DELETE CASCADE,
        FOREIGN KEY (cours_id) REFERENCES cours(id) ON DELETE CASCADE
    )");
    echo "<p>Table 'notes' créée/vérifiée.</p>";

    $pdo->exec("CREATE TABLE IF NOT EXISTS cours_moyennes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        cours_id INT NOT NULL,
        periode VARCHAR(50) NOT NULL,
        moyenne DECIMAL(6,2) NOT NULL,
        note_max INT NOT NULL DEFAULT 20,
        enseignant_id INT NOT NULL,
        classe_id INT NOT NULL,
        statut VARCHAR(20) NOT NULL DEFAULT 'Envoyée',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_cours_periode (cours_id, periode),
        FOREIGN KEY (cours_id) REFERENCES cours(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "<p>Table 'cours_moyennes' créée/vérifiée.</p>";

    echo "<p style='color:green;'>Phase 1 terminée avec succès !</p>";
} catch (Exception $e) {
    echo "<p style='color:red;'>Erreur : " . $e->getMessage() . "</p>";
}
?>
