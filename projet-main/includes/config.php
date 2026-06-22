<?php
// Ajouter le dossier racine du projet à l'include path de PHP pour résoudre facilement les inclusions
set_include_path(get_include_path() . PATH_SEPARATOR . dirname(__DIR__));

// Charger les variables d'environnement depuis le fichier .env s'il existe
$env_path = dirname(dirname(__DIR__)) . '/.env';
if (!file_exists($env_path)) {
    $env_path = dirname(__DIR__) . '/.env';
}
if (file_exists($env_path)) {
    $lines = file($env_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) {
            continue; // Ignore les commentaires
        }
        $parts = explode('=', $line, 2);
        if (count($parts) === 2) {
            $key = trim($parts[0]);
            $val = trim($parts[1]);
            // Supprimer les guillemets/quotes autour de la valeur si présents
            if ((str_starts_with($val, '"') && str_ends_with($val, '"')) ||
                (str_starts_with($val, "'") && str_ends_with($val, "'"))) {
                $val = substr($val, 1, -1);
            }
            putenv("$key=$val");
            $_ENV[$key] = $val;
            $_SERVER[$key] = $val;
        }
    }
}

// Gestion de l'affichage des erreurs (Production vs Développement)
$app_env = getenv('APP_ENV') ?: 'production';
if ($app_env === 'production') {
    ini_set('display_errors', '0');
    error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED & ~E_STRICT);
} else {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
}

// Paramètres de connexion à la base de données
$host = getenv('DB_HOST') ?: "localhost";
$dbname = getenv('DB_NAME') ?: "bellevue_db";
$username = getenv('DB_USER') ?: "root";
$password = getenv('DB_PASS') !== false ? getenv('DB_PASS') : "";

// Fonction pour exécuter un fichier SQL en découpant par point-virgule et en ignorant les commandes de DB globales
if (!function_exists('executeSqlFile')) {
    function executeSqlFile($pdo, $filePath) {
        if (!file_exists($filePath)) return false;
        $sql = file_get_contents($filePath);
        // Supprimer les commandes CREATE DATABASE et USE spécifiques pour rester générique
        $sql = preg_replace('/CREATE DATABASE.*?;/i', '', $sql);
        $sql = preg_replace('/USE .*?;/i', '', $sql);
        
        // Supprimer les commentaires SQL
        $sql = preg_replace('/--.*\n/', '', $sql);
        $sql = preg_replace('/\/\*.*?\*\//s', '', $sql);
        
        // Découper par point-virgule
        $statements = explode(';', $sql);
        foreach ($statements as $stmt) {
            $stmt = trim($stmt);
            if ($stmt !== '') {
                $pdo->exec($stmt);
            }
        }
        return true;
    }
}

try {
    // Essayer de se connecter directement à la base de données
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    // Si la base de données n'existe pas, on la crée automatiquement
    if ($e->getCode() == 1049 || strpos($e->getMessage(), 'Unknown database') !== false || strpos($e->getMessage(), 'base de données inconnue') !== false) {
        try {
            // Connexion au serveur MySQL sans spécifier de base
            $tmp_pdo = new PDO("mysql:host=$host;charset=utf8mb4", $username, $password);
            $tmp_pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // Créer la base de données
            $tmp_pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            
            // Connexion à la base nouvellement créée
            $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch (PDOException $ex) {
            $pdo_error = "Impossible de se connecter ou de créer la base de données : " . $ex->getMessage();
        }
    } else {
        $pdo_error = "Impossible de se connecter à la base de données : " . $e->getMessage();
    }
}

// Si la connexion a réussi, on vérifie et initialise les tables si nécessaire
if (isset($pdo) && !isset($pdo_error)) {
    try {
        // Fonctions d'initialisation propre
        if (!function_exists('initializePrefetAccount')) {
            function initializePrefetAccount($pdo) {
                $username = 'prefet';
                $password = 'prefet_bellevue';
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $nom = 'Directeur Général';
                
                $stmt = $pdo->prepare("INSERT IGNORE INTO admins (username, password_hash, role, nom_complet, statut) VALUES (:u, :p, 'prefet', :n, 'Actif')");
                $stmt->execute([':u' => $username, ':p' => $hash, ':n' => $nom]);
            }
        }

        if (!function_exists('initializeProductionData')) {
            function initializeProductionData($pdo) {
                // 1. S'assurer de la présence de la table paiements
                $pdo->exec("CREATE TABLE IF NOT EXISTS paiements (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    eleve_id INT NOT NULL,
                    type_frais VARCHAR(100) NOT NULL,
                    montant DECIMAL(10,2) NOT NULL DEFAULT 0,
                    date_paiement DATETIME DEFAULT CURRENT_TIMESTAMP,
                    methode_paiement VARCHAR(50) DEFAULT 'Espèces',
                    reference_transaction VARCHAR(100),
                    statut VARCHAR(20) DEFAULT 'Confirmé',
                    FOREIGN KEY (eleve_id) REFERENCES eleves(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

                // 2. Vider les anciennes tables si présentes
                $pdo->exec("SET FOREIGN_KEY_CHECKS=0");
                $pdo->exec("TRUNCATE TABLE horaires");
                $pdo->exec("TRUNCATE TABLE cours");
                $pdo->exec("SET FOREIGN_KEY_CHECKS=1");

                // 3. Créer le compte préfet initial
                initializePrefetAccount($pdo);

                // 4. Générer les matières par défaut propres pour toutes les classes
                $classes = $pdo->query("SELECT * FROM classes")->fetchAll();
                
                $matieres = [
                    'Maternelle' => ['Dessin', 'Langage', 'Psychomotricité', 'Chant & Poésie', 'Éveil', 'Jeux de construction', 'Coloriage'],
                    'Primaire' => ['Mathématiques', 'Français', 'Sciences Naturelles', 'Histoire & Géographie', 'Religion / Morale', 'Éducation Physique', 'Anglais', 'Dessin'],
                    'Secondaire' => ['Mathématiques', 'Français', 'Anglais', 'Physique', 'Chimie', 'Biologie', 'Histoire', 'Géographie', 'Informatique', 'Éducation Civique', 'Éducation Physique'],
                    'Literaire' => ['Littérature', 'Latin', 'Français', 'Anglais', 'Histoire', 'Philosophie', 'Mathématiques'],
                    'HP' => ['Pédagogie', 'Psychologie', 'Méthodologie', 'Français', 'Mathématiques', 'Sciences'],
                    'Scientifique' => ['Mathématiques', 'Physique', 'Chimie', 'Informatique', 'Français', 'Anglais', 'Biologie'],
                    'Biologie Chimie' => ['Biologie', 'Chimie', 'Anatomie', 'Physique', 'Mathématiques', 'Français', 'Anglais'],
                    'Commercial et Gestion' => ['Comptabilité', 'Droit Commercial', 'Économie', 'Mathématiques', 'Informatique', 'Français', 'Anglais'],
                    'Technique' => ['Mécanique & Électronique', 'Ateliers Pratiques', 'Mathématiques', 'Physique', 'Informatique', 'Français'],
                    'Hotellerie' => ['Gestion Hôtelière', 'Cuisine & Restauration', 'Hygiène', 'Anglais', 'Français', 'Comptabilité'],
                ];

                $cours_insert = $pdo->prepare("INSERT INTO cours (nom, classe_id, enseignant_id) VALUES (?, ?, NULL)");
                
                foreach ($classes as $classe) {
                    $cid = $classe['id'];
                    $niveau = $classe['niveau'];
                    $option = $classe['option_nom'];
                    $nom = strtolower($classe['nom']);

                    if ($niveau === 'Maternelle') {
                        $mes_matieres = $matieres['Maternelle'];
                    } elseif ($niveau === 'Primaire') {
                        $mes_matieres = $matieres['Primaire'];
                    } elseif (strpos($nom, '7eme') !== false || strpos($nom, '8eme') !== false) {
                        $mes_matieres = $matieres['Secondaire'];
                    } else {
                        $mes_matieres = $matieres[$option] ?? $matieres['Secondaire'];
                    }

                    foreach ($mes_matieres as $m) {
                        $cours_insert->execute([$m, $cid]);
                    }
                }
            }
        }

        // Vérifier si la table principale 'classes' existe
        $stmt = $pdo->query("SHOW TABLES LIKE 'classes'");
        $classesTableExists = $stmt->rowCount() > 0;
        
        if (!$classesTableExists) {
            // 1. Importer le fichier SQL principal
            $sql_path = __DIR__ . '/../maintenance/database.sql';
            executeSqlFile($pdo, $sql_path);
            
            // 2. Exécuter la restructuration de la base de données
            $restructure_path = __DIR__ . '/../maintenance/restructure_db.php';
            if (file_exists($restructure_path)) {
                ob_start();
                include $restructure_path;
                ob_end_clean();
            }
            
            // 3. Générer les cours propres et le compte préfet initial
            initializeProductionData($pdo);
        } else {
            // Si la base existe mais que certaines tables secondaires créées par les scripts de fix/restructure manquent
            $stmt_horaires = $pdo->query("SHOW TABLES LIKE 'horaires'");
            if ($stmt_horaires->rowCount() == 0) {
                $restructure_path = __DIR__ . '/../maintenance/restructure_db.php';
                if (file_exists($restructure_path)) {
                    ob_start();
                    include $restructure_path;
                    ob_end_clean();
                }
            }
            
            // S'assurer que le compte prefet existe
            $stmt_prefet = $pdo->query("SELECT COUNT(*) as nb FROM admins WHERE role = 'prefet'");
            if ($stmt_prefet->fetch()['nb'] == 0) {
                initializePrefetAccount($pdo);
            }
        }
        
        // S'assurer que la colonne 'bulletin_path' existe dans la table 'eleves'
        $stmt_col = $pdo->query("SHOW COLUMNS FROM eleves LIKE 'bulletin_path'");
        if ($stmt_col->rowCount() == 0) {
            $pdo->exec("ALTER TABLE eleves ADD COLUMN bulletin_path TEXT DEFAULT NULL AFTER ecole_provenance");
        }

        // S'assurer que la colonne 'email' existe dans la table 'admins'
        $stmt_col_email = $pdo->query("SHOW COLUMNS FROM admins LIKE 'email'");
        if ($stmt_col_email->rowCount() == 0) {
            $pdo->exec("ALTER TABLE admins ADD COLUMN email VARCHAR(150) DEFAULT NULL AFTER nom_complet");
        }

        // S'assurer que la colonne 'heure_debut' existe dans la table 'horaires' au lieu de 'heure_deb'
        $stmt_col_horaires = $pdo->query("SHOW COLUMNS FROM horaires LIKE 'heure_deb'");
        if ($stmt_col_horaires->rowCount() > 0) {
            $pdo->exec("ALTER TABLE horaires CHANGE COLUMN heure_deb heure_debut TIME NOT NULL");
        }

        // S'assurer de la présence et de la conformité des colonnes de la table 'paiements'
        $stmt_col_tf = $pdo->query("SHOW COLUMNS FROM paiements LIKE 'type_frais'");
        if ($stmt_col_tf->rowCount() == 0) {
            $pdo->exec("ALTER TABLE paiements ADD COLUMN type_frais VARCHAR(100) NOT NULL AFTER eleve_id");
        }

        $stmt_col_mp = $pdo->query("SHOW COLUMNS FROM paiements LIKE 'methode_paiement'");
        if ($stmt_col_mp->rowCount() == 0) {
            $stmt_col_m = $pdo->query("SHOW COLUMNS FROM paiements LIKE 'methode'");
            if ($stmt_col_m->rowCount() > 0) {
                $pdo->exec("ALTER TABLE paiements CHANGE COLUMN methode methode_paiement VARCHAR(50) NOT NULL DEFAULT 'Espèces'");
            } else {
                $pdo->exec("ALTER TABLE paiements ADD COLUMN methode_paiement VARCHAR(50) NOT NULL DEFAULT 'Espèces'");
            }
        }

        $stmt_col_rt = $pdo->query("SHOW COLUMNS FROM paiements LIKE 'reference_transaction'");
        if ($stmt_col_rt->rowCount() == 0) {
            $stmt_col_r = $pdo->query("SHOW COLUMNS FROM paiements LIKE 'reference'");
            if ($stmt_col_r->rowCount() > 0) {
                $pdo->exec("ALTER TABLE paiements CHANGE COLUMN reference reference_transaction VARCHAR(100) DEFAULT NULL");
            } else {
                $pdo->exec("ALTER TABLE paiements ADD COLUMN reference_transaction VARCHAR(100) DEFAULT NULL");
            }
        }
    } catch (Exception $ex) {
        // Enregistrer l'erreur ou continuer pour ne pas planter l'application en prod
    }
}
?>
