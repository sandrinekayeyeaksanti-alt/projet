<?php
session_start();
require_once 'config.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portail Numérique | École Belle Vue</title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?= time() ?>">
    <script src="https://unpkg.com/feather-icons"></script>
    <style>
        .portal-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f8fafc;
            padding: 20px;
        }
        .portal-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 30px;
            max-width: 1100px;
            width: 100%;
        }
        .portal-card {
            background: white;
            border-radius: 24px;
            padding: 40px 30px;
            text-align: center;
            text-decoration: none;
            color: inherit;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border: 1px solid #f1f5f9;
            display: flex;
            flex-direction: column;
            align-items: center;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        }
        .portal-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            border-color: var(--primary-blue);
        }
        .icon-box {
            width: 80px;
            height: 80px;
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 25px;
            transition: all 0.3s;
        }
        .portal-card:hover .icon-box {
            transform: scale(1.1) rotate(5deg);
        }
        .bg-blue { background: #eff6ff; color: #1e40af; }
        .bg-purple { background: #faf5ff; color: #6b21a8; }
        .bg-slate { background: #f1f5f9; color: #334155; }
        
        .portal-card h3 { font-size: 20px; margin-bottom: 10px; }
        .portal-card p { font-size: 14px; color: #64748b; line-height: 1.5; }

        @media (max-width: 768px) {
            .portal-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

    <div class="bg-video-container">
        <video autoplay muted loop playsinline>
            <source src="assets/media/video_belle_vue.mp4" type="video/mp4">
        </video>
    </div>

    <div class="portal-container">
        <div style="width: 100%; max-width: 1100px;">
            <div style="text-align: center; margin-bottom: 60px;">
                <img src="assets/img/logo.webp" alt="Logo" style="height: 80px; margin-bottom: 20px;">
                <h1 style="font-size: 42px; color: var(--primary-blue); margin-bottom: 15px;">Portail Numérique</h1>
                <p style="font-size: 18px; color: var(--text-medium);">Choisissez votre espace pour continuer</p>
            </div>

            <div class="portal-grid">
                <!-- Card: Admin -->
                <a href="login_prefet.php" class="portal-card">
                    <div class="icon-box bg-slate">
                        <i data-feather="shield" style="width: 40px; height: 40px;"></i>
                    </div>
                    <h3>Administration</h3>
                    <p>Accès réservé au Préfet et au Staff administratif.</p>
                </a>

                <!-- Card: Parent -->
                <a href="login_parent.php" class="portal-card">
                    <div class="icon-box bg-purple">
                        <i data-feather="users" style="width: 40px; height: 40px;"></i>
                    </div>
                    <h3>Espace Parent</h3>
                    <p>Suivez la scolarité de vos enfants, consultez les bulletins et paiements.</p>
                </a>

                <!-- Card: Student -->
                <a href="login_eleve.php" class="portal-card">
                    <div class="icon-box bg-blue">
                        <i data-feather="book-open" style="width: 40px; height: 40px;"></i>
                    </div>
                    <h3>Espace Élève</h3>
                    <p>Consultez vos notes et votre cursus scolaire en temps réel.</p>
                </a>
            </div>

            <div style="text-align: center; margin-top: 60px;">
                <a href="index.php" style="color: white; text-decoration: none; font-weight: 500; display: inline-flex; align-items: center; gap: 8px; background: rgba(0,0,0,0.5); padding: 10px 20px; border-radius: 30px; backdrop-filter: blur(10px);">
                    <i data-feather="arrow-left" style="width: 16px;"></i> Retour à l'accueil
                </a>
            </div>
        </div>
    </div>

    <script>feather.replace();</script>
</body>
</html>

