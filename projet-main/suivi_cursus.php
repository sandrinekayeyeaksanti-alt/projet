<?php
session_start();
require_once 'config.php';

// Vérification de l'accès (Élève ou Parent)
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['eleve', 'parent'])) {
    header("Location: login.php");
    exit();
}

$eleve_id = ($_SESSION['role'] === 'eleve') ? $_SESSION['eleve_id'] : $_SESSION['eleve_id']; 
// Note: Dans portail.php, l'ID est stocké dans parent_id parfois, mais uniformisons.
if (isset($_SESSION['parent_id'])) $eleve_id = $_SESSION['parent_id'];

$eleve = [];
$bulletins = [];

if (isset($pdo)) {
    // Infos élève avec sa classe actuelle
    $stmt = $pdo->prepare("SELECT e.*, c.nom as classe_nom, c.niveau as classe_niveau, c.option_nom FROM eleves e LEFT JOIN classes c ON e.classe_id = c.id WHERE e.id = :id");
    $stmt->execute(['id' => $eleve_id]);
    $eleve = $stmt->fetch();

    // Récupération de tous les bulletins pour construire le cursus
    $stmt_b = $pdo->prepare("SELECT * FROM bulletins WHERE eleve_id = :id ORDER BY id ASC");
    $stmt_b->execute(['id' => $eleve_id]);
    $bulletins = $stmt_b->fetchAll();
}

// Simulation d'un cursus complet pour la démonstration si le bulletin est vide
// Dans un vrai système, cela viendrait des archives annuelles.
$cursus_theorique = [
    ['niveau' => 'Maternelle', 'status' => 'Terminé', 'icon' => 'sun'],
    ['niveau' => 'Primaire', 'status' => 'Terminé', 'icon' => 'book-open'],
    ['niveau' => 'Secondaire (Base)', 'status' => 'Terminé', 'icon' => 'shield'],
    ['niveau' => 'Humanités', 'status' => 'En cours', 'icon' => 'award']
];

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Suivi de Cursus | École Belle Vue</title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?= time() ?>">
    <script src="https://unpkg.com/feather-icons"></script>
    <style>
        .cursus-container { max-width: 1000px; margin: 40px auto; padding: 20px; }
        
        /* Timeline Styles */
        .timeline {
            position: relative;
            padding: 20px 0;
            list-style: none;
        }
        .timeline:before {
            content: '';
            position: absolute;
            top: 0;
            bottom: 0;
            left: 40px;
            width: 4px;
            background: #e2e8f0;
            border-radius: 2px;
        }
        .timeline-item {
            position: relative;
            margin-bottom: 50px;
            padding-left: 80px;
        }
        .timeline-marker {
            position: absolute;
            left: 22px;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: white;
            border: 4px solid var(--primary-blue);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 2;
            color: var(--primary-blue);
        }
        .timeline-item.completed .timeline-marker {
            background: var(--primary-blue);
            color: white;
        }
        .timeline-item.active .timeline-marker {
            background: var(--secondary-blue);
            border-color: var(--secondary-blue);
            color: white;
            box-shadow: 0 0 0 5px rgba(30, 96, 208, 0.2);
        }
        .timeline-content {
            background: white;
            padding: 25px;
            border-radius: 16px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            border: 1px solid #f1f5f9;
        }
        .timeline-content h3 {
            margin-bottom: 10px;
            font-size: 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .badge-status {
            font-size: 11px;
            padding: 4px 10px;
            border-radius: 20px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        /* Progress Circle */
        .progress-overview {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 30px;
            margin-bottom: 40px;
        }
        .progress-card {
            background: linear-gradient(135deg, var(--primary-blue), var(--secondary-blue));
            color: white;
            padding: 30px;
            border-radius: 20px;
            text-align: center;
        }
        .circular-progress {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: conic-gradient(var(--white) 75%, transparent 0);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            position: relative;
        }
        .circular-progress::before {
            content: "75%";
            position: absolute;
            width: 100px;
            height: 100px;
            background: var(--primary-blue);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            font-weight: bold;
        }
    </style>
</head>
<body style="background: #f8fafc;">
    
    <div class="bg-video-container">
        <video autoplay muted loop playsinline>
            <source src="assets/media/video_belle_vue.mp4" type="video/mp4">
        </video>
    </div>

    <!-- Navbar -->
    <nav class="navbar" style="position: sticky; top: 0;">
        <div class="container" style="display: flex; justify-content: space-between; align-items: center;">
            <a href="index.php" class="logo"><img src="assets/img/logo.webp" alt="Logo"></a>
            <div style="display: flex; gap: 20px; align-items: center;">
                <a href="dashboard_eleve.php" style="font-weight: 500; font-size: 14px;">Tableau de Bord</a>
                <a href="logout.php" class="btn btn-outline" style="padding: 8px 15px; font-size: 13px;">Déconnexion</a>
            </div>
        </div>
    </nav>

    <div class="cursus-container">
        <header style="margin-bottom: 40px; text-align: center;">
            <h1 style="font-size: 36px; margin-bottom: 10px;">Cursus Scolaire Numérique</h1>
            <p style="color: var(--text-medium); font-size: 18px;">Suivi de la progression de <strong><?= htmlspecialchars($eleve['nom'] . ' ' . $eleve['post_nom_prenom']) ?></strong></p>
        </header>

        <div class="progress-overview">
            <div class="progress-card">
                <div class="circular-progress"></div>
                <h3>Progression Globale</h3>
                <p style="font-size: 14px; opacity: 0.9;">Dernière étape : Humanités</p>
            </div>
            <div style="background: white; padding: 30px; border-radius: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); display: flex; flex-direction: column; justify-content: center;">
                <h4 style="margin-bottom: 15px;">Résumé du Parcours</h4>
                <div style="display: flex; gap: 40px;">
                    <div>
                        <span style="display: block; font-size: 12px; color: var(--text-light); text-transform: uppercase;">Années validées</span>
                        <span style="font-size: 24px; font-weight: bold; color: var(--primary-blue);">11 ans</span>
                    </div>
                    <div>
                        <span style="display: block; font-size: 12px; color: var(--text-light); text-transform: uppercase;">Moyenne Générale</span>
                        <span style="font-size: 24px; font-weight: bold; color: #10b981;">72.5%</span>
                    </div>
                    <div>
                        <span style="display: block; font-size: 12px; color: var(--text-light); text-transform: uppercase;">Statut</span>
                        <span style="font-size: 24px; font-weight: bold; color: var(--secondary-blue);">Actif</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="timeline">
            <!-- Timeline Item: Maternelle -->
            <div class="timeline-item completed">
                <div class="timeline-marker"><i data-feather="sun"></i></div>
                <div class="timeline-content">
                    <h3>Maternelle <span class="badge-status badge-success">Terminé</span></h3>
                    <p style="color: var(--text-medium); font-size: 14px; margin-bottom: 15px;">Cycle de 3 ans complété avec succès à l'école Belle Vue.</p>
                    <div style="display: flex; gap: 15px;">
                        <span style="font-size: 12px; background: #f1f5f9; padding: 5px 10px; border-radius: 4px;">Certificat d'études maternelles obtenu</span>
                    </div>
                </div>
            </div>

            <!-- Timeline Item: Primaire -->
            <div class="timeline-item completed">
                <div class="timeline-marker"><i data-feather="book-open"></i></div>
                <div class="timeline-content">
                    <h3>Primaire <span class="badge-status badge-success">Terminé</span></h3>
                    <p style="color: var(--text-medium); font-size: 14px; margin-bottom: 15px;">Cycle de 6 ans. Félicitations pour l'obtention du TENAFEP.</p>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                        <div style="border: 1px solid #e2e8f0; padding: 10px; border-radius: 8px; font-size: 13px;">
                            <strong>6eme Primaire</strong><br>
                            Moyenne : 68% | Réussi
                        </div>
                    </div>
                </div>
            </div>

            <!-- Timeline Item: Secondaire (EB) -->
            <div class="timeline-item completed">
                <div class="timeline-marker"><i data-feather="shield"></i></div>
                <div class="timeline-content">
                    <h3>Éducation de Base (7e & 8e) <span class="badge-status badge-success">Terminé</span></h3>
                    <p style="color: var(--text-medium); font-size: 14px; margin-bottom: 15px;">Cycle d'orientation réussi.</p>
                </div>
            </div>

            <!-- Timeline Item: Humanités (Current) -->
            <div class="timeline-item active">
                <div class="timeline-marker"><i data-feather="award"></i></div>
                <div class="timeline-content">
                    <h3>Humanités - <?= htmlspecialchars($eleve['option_nom'] ?? 'Générale') ?> <span class="badge-status badge-warning" style="background: #fef3c7; color: #92400e;">En cours</span></h3>
                    <p style="color: var(--text-medium); font-size: 14px; margin-bottom: 15px;">Classe actuelle : <strong><?= htmlspecialchars($eleve['classe_nom']) ?></strong></p>
                    
                    <h4 style="font-size: 15px; margin-bottom: 10px; color: var(--primary-blue);">Bulletins de l'année</h4>
                    <div style="display: grid; gap: 10px;">
                        <?php foreach($bulletins as $b): ?>
                        <div style="background: #f8fafc; padding: 12px; border-radius: 8px; display: flex; justify-content: space-between; align-items: center; border-left: 4px solid var(--secondary-blue);">
                            <div>
                                <span style="font-weight: 600;"><?= htmlspecialchars($b['periode']) ?></span><br>
                                <span style="font-size: 12px; color: var(--text-medium);"><?= $b['pourcentage'] ?>% de réussite</span>
                            </div>
                            <div style="text-align: right;">
                                <span style="font-weight: bold; color: var(--primary-blue);"><?= $b['points_obtenus'] ?>/<?= $b['points_max'] ?></span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php if(empty($bulletins)): ?>
                            <p style="font-size: 13px; color: var(--text-light); font-style: italic;">En attente des premiers résultats périodiques...</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <footer class="footer">
        <div class="container">
            <p>&copy; 2026 École Belle Vue - Système de Suivi de Cursus Numérique.</p>
        </div>
    </footer>

    <script>feather.replace();</script>
</body>
</html>

