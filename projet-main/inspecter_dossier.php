<?php
session_start();
require_once 'config.php';

// Vérification du rôle Secrétariat
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'secretariat') {
    header("Location: login_secretariat.php");
    exit();
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$eleve = null;

if ($id && isset($pdo)) {
    $stmt = $pdo->prepare("SELECT e.*, c.nom as classe_nom FROM eleves e LEFT JOIN classes c ON e.classe_id = c.id WHERE e.id = ?");
    $stmt->execute([$id]);
    $eleve = $stmt->fetch();
}

if (!$eleve) {
    die("Dossier introuvable.");
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Inspection Dossier | Belle Vue</title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?= time() ?>">
    <script src="https://unpkg.com/feather-icons"></script>
    <style>
        .inspection-container { max-width: 1000px; margin: 40px auto; display: grid; grid-template-columns: 400px 1fr; gap: 30px; }
        .info-card { background: white; padding: 30px; border-radius: 15px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        .document-viewer { background: #1e293b; border-radius: 15px; padding: 20px; color: white; display: flex; flex-direction: column; align-items: center; justify-content: center; min-height: 500px; }
        .bulletin-img { max-width: 100%; border-radius: 8px; box-shadow: 0 10px 25px rgba(0,0,0,0.5); }
        .label { color: #64748b; font-size: 12px; text-transform: uppercase; letter-spacing: 1px; font-weight: bold; margin-bottom: 5px; display: block; }
        .value { font-size: 16px; color: #1e293b; font-weight: 600; margin-bottom: 20px; display: block; }
    </style>
</head>
<body style="background: #f1f5f9;">
    
    <div class="container" style="margin-top: 30px;">
        <a href="dashboard_secretariat.php" style="text-decoration: none; color: #1e3a8a; font-weight: bold; display: flex; align-items: center; gap: 8px;">
            <i data-feather="arrow-left"></i> Retour au tableau de bord
        </a>
    </div>

    <div class="inspection-container">
        <!-- Infos Élève -->
        <div class="info-card">
            <h2 style="margin-bottom: 25px; color: #1e3a8a; border-bottom: 2px solid #f1f5f9; padding-bottom: 15px;">Détails du Candidat</h2>
            
            <span class="label">Nom Complet</span>
            <span class="value"><?= htmlspecialchars($eleve['nom'] . ' ' . $eleve['post_nom_prenom']) ?></span>

            <span class="label">Classe Sollicitée</span>
            <span class="value"><?= htmlspecialchars($eleve['classe_nom']) ?></span>

            <span class="label">École de provenance</span>
            <span class="value"><?= htmlspecialchars($eleve['ecole_provenance'] ?: 'Non renseigné') ?></span>

            <hr style="border: none; border-top: 1px solid #f1f5f9; margin: 20px 0;">

            <span class="label">Tuteur</span>
            <span class="value"><?= htmlspecialchars($eleve['tuteur_nom']) ?></span>

            <span class="label">Téléphone</span>
            <span class="value"><?= htmlspecialchars($eleve['tuteur_tel']) ?></span>

            <div style="margin-top: 40px; display: flex; flex-direction: column; gap: 10px;">
                <a href="dashboard_secretariat.php?action=valider&id=<?= $eleve['id'] ?>" class="btn btn-primary" style="background: #10b981; text-align: center; padding: 15px;">
                    ✓ APPROUVER (Dossier Conforme)
                </a>
                <a href="dashboard_secretariat.php?action=refuser&id=<?= $eleve['id'] ?>" class="btn btn-danger" style="text-align: center; padding: 12px;">
                    ✕ REJETER LE DOSSIER
                </a>
            </div>
        </div>

        <!-- Visionneuse de document -->
        <div class="document-viewer" style="background: #1e293b; border-radius: 15px; padding: 25px; color: white; min-height: 550px; display: flex; flex-direction: column; align-items: stretch; justify-content: flex-start;">
            <h3 style="margin-bottom: 20px; font-size: 18px; display: flex; align-items: center; gap: 8px; justify-content: center; border-bottom: 1px solid #334155; padding-bottom: 15px; color: #f8fafc;">
                <i data-feather="file-text"></i> Preuves de réussite (Bulletins)
            </h3>
            
            <?php 
            $bulletin_paths = [];
            if (!empty($eleve['bulletin_path'])) {
                $decoded = json_decode($eleve['bulletin_path'], true);
                if (is_array($decoded)) {
                    $bulletin_paths = $decoded;
                } else {
                    $bulletin_paths = [$eleve['bulletin_path']];
                }
            }
            // Filtrer les fichiers existants
            $valid_bulletins = [];
            foreach ($bulletin_paths as $bp) {
                if (file_exists($bp)) {
                    $valid_bulletins[] = $bp;
                }
            }
            ?>
            
            <?php if (!empty($valid_bulletins)): ?>
                <!-- Onglets -->
                <div style="display: flex; gap: 10px; margin-bottom: 20px; overflow-x: auto; padding-bottom: 8px; border-bottom: 1px solid #334155;">
                    <?php foreach ($valid_bulletins as $index => $path): ?>
                        <button class="bulletin-tab-btn <?= $index === 0 ? 'active' : '' ?>" 
                                onclick="switchBulletinTab(<?= $index ?>)"
                                style="background: <?= $index === 0 ? '#3b82f6' : '#334155' ?>; color: white; border: none; padding: 10px 20px; border-radius: 8px; cursor: pointer; font-weight: 600; font-size: 13px; display: flex; align-items: center; gap: 6px; white-space: nowrap; transition: all 0.2s ease;">
                            <i data-feather="file" style="width: 14px; height: 14px;"></i> Bulletin <?= $index + 1 ?>
                        </button>
                    <?php endforeach; ?>
                </div>
                
                <!-- Contenus des onglets -->
                <div id="bulletin-contents-container" style="flex: 1; display: flex; flex-direction: column; justify-content: center; align-items: center; width: 100%;">
                    <?php foreach ($valid_bulletins as $index => $path): 
                        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
                        $is_img = in_array($ext, ['jpg', 'jpeg', 'png', 'webp']);
                    ?>
                        <div class="bulletin-tab-content" id="bulletin-content-<?= $index ?>" style="display: <?= $index === 0 ? 'flex' : 'none' ?>; flex-direction: column; align-items: center; justify-content: center; width: 100%;">
                            <?php if ($is_img): ?>
                                <img src="<?= $path ?>" class="bulletin-img" alt="Bulletin <?= $index + 1 ?>" style="max-height: 450px; border-radius: 8px; box-shadow: 0 10px 25px rgba(0,0,0,0.5);">
                            <?php else: ?>
                                <div style="text-align: center; padding: 40px;">
                                    <i data-feather="file" style="width: 64px; height: 64px; margin-bottom: 20px; opacity: 0.5; color: #3b82f6;"></i>
                                    <p style="font-weight: 600; margin-bottom: 10px;">Document PDF détecté</p>
                                    <a href="<?= $path ?>" target="_blank" class="btn btn-primary" style="margin-top: 15px; background: #3b82f6; border-color: #3b82f6; display: inline-flex; align-items: center; gap: 8px; text-decoration: none; color: white; padding: 10px 20px; border-radius: 8px; font-weight: bold;">
                                        <i data-feather="external-link" style="width: 16px;"></i> Ouvrir le PDF
                                    </a>
                                </div>
                            <?php endif; ?>
                            
                            <div style="margin-top: 20px; text-align: center;">
                                <a href="<?= $path ?>" download style="color: #94a3b8; text-decoration: none; font-size: 13px; font-weight: 600; display: inline-flex; align-items: center; gap: 6px;">
                                    <i data-feather="download" style="width: 14px;"></i> Télécharger le Bulletin <?= $index + 1 ?>
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div style="flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; opacity: 0.5; padding: 60px 0; width: 100%;">
                    <i data-feather="slash" style="width: 48px; height: 48px; margin-bottom: 15px;"></i>
                    <p>Aucun bulletin valide n'a été soumis avec cette demande.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function switchBulletinTab(index) {
            // Cacher tous les contenus
            const contents = document.querySelectorAll('.bulletin-tab-content');
            contents.forEach(content => {
                content.style.display = 'none';
            });
            
            // Réinitialiser les boutons d'onglets
            const buttons = document.querySelectorAll('.bulletin-tab-btn');
            buttons.forEach(btn => {
                btn.style.background = '#334155';
            });
            
            // Afficher le contenu actif et activer le bouton
            document.getElementById('bulletin-content-' + index).style.display = 'flex';
            buttons[index].style.background = '#3b82f6';
        }

        feather.replace();
    </script>
</body>
</html>

