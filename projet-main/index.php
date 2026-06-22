<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>École Belle Vue | Inscriptions en ligne</title>
    <meta name="description" content="Plateforme d'inscription numérique et portail scolaire de l'école Belle Vue à Lubumbashi.">
    <link rel="stylesheet" href="assets/css/style.css?v=<?= time() ?>">
    <script src="https://unpkg.com/feather-icons"></script>
</head>
<body>
    <!-- Background Video -->
    <div class="bg-video-container">
        <video autoplay muted loop playsinline>
            <source src="assets/media/video_belle_vue.mp4" type="video/mp4">
        </video>
    </div>

    <!-- Navbar -->
    <nav class="navbar">
        <div class="container">
            <a href="index.php" class="logo"><img src="assets/img/logo.webp" alt="Logo Belle Vue"></a>
            <ul class="nav-links">
                <li><a href="index.php" class="active">Accueil</a></li>
                <li><a href="inscription.php">S'inscrire</a></li>
                <li><a href="choix_portail.php">Mon Espace</a></li>
            </ul>
            <a href="choix_portail.php" class="btn btn-outline">Connexion</a>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero">
        <div class="hero-shapes">
            <div class="shape shape-1"></div>
            <div class="shape shape-2"></div>
        </div>
        <div class="container">
            <div class="hero-content animate delay-1">
                <div style="display: inline-flex; align-items: center; gap: 8px; background: rgba(255, 255, 255, 0.9); padding: 8px 20px; border-radius: 50px; border: 2px solid var(--secondary-blue); margin-bottom: 25px; box-shadow: 0 8px 25px rgba(0,0,0,0.1);">
                    <span style="background: linear-gradient(90deg, var(--primary-blue), var(--secondary-blue)); -webkit-background-clip: text; -webkit-text-fill-color: transparent; font-size: 14px; font-weight: 900; text-transform: uppercase; letter-spacing: 2px;">✦ Devenir Exceptionnel ✦</span>
                </div>
                <h1>L'excellence éducative, <br><span style="color: var(--secondary-blue);">désormais connectée.</span></h1>
                <p>Bienvenue sur le portail officiel de l'école Belle Vue à Lubumbashi. Simplifiez vos démarches administratives, inscrivez vos enfants à distance, et suivez leur parcours scolaire en temps réel.</p>
                <div class="hero-actions">
                    <a href="inscription.php" class="btn btn-primary">Commencer l'inscription</a>
                    <a href="#comparaison" class="btn btn-outline">En savoir plus</a>
                </div>
            </div>
            <div class="glass-card animate delay-2">
                <div style="text-align: center; margin-bottom: 20px;">
                    <i data-feather="check-circle" style="color: var(--secondary-blue); width: 48px; height: 48px; margin-bottom: 10px;"></i>
                    <h3 style="font-family: 'Outfit'; font-size: 24px;">Admissions Ouvertes</h3>
                    <p style="color: var(--text-medium); font-size: 14px;">Année scolaire 2026-2027</p>
                </div>
                
                <!-- Maternelle -->
                <div style="background: rgba(0,0,0,0.03); padding: 12px; border-radius: 8px; margin-bottom: 10px; display: flex; align-items: center; justify-content: space-between;">
                    <div>
                        <strong style="display: block; font-size: 13px;">Maternelle</strong>
                        <span style="font-size: 11px; color: var(--text-medium);">Petite, Moyenne, Grande</span>
                    </div>
                    <span class="badge-success stat-badge" style="margin: 0; font-size: 10px;">Ouvert</span>
                </div>

                <!-- Primaire -->
                <div style="background: rgba(0,0,0,0.03); padding: 12px; border-radius: 8px; margin-bottom: 10px; display: flex; align-items: center; justify-content: space-between;">
                    <div>
                        <strong style="display: block; font-size: 13px;">Primaire</strong>
                        <span style="font-size: 11px; color: var(--text-medium);">De la 1ere à la 6eme</span>
                    </div>
                    <span class="badge-success stat-badge" style="margin: 0; font-size: 10px;">Ouvert</span>
                </div>

                <!-- Secondaire -->
                <div style="background: rgba(0,0,0,0.03); padding: 12px; border-radius: 8px; margin-bottom: 10px; display: flex; align-items: center; justify-content: space-between;">
                    <div>
                        <strong style="display: block; font-size: 13px;">Secondaire (7e & 8e)</strong>
                        <span style="font-size: 11px; color: var(--text-medium);">Éducation de Base</span>
                    </div>
                    <span class="badge-success stat-badge" style="margin: 0; font-size: 10px;">Ouvert</span>
                </div>

                <!-- Humanités -->
                <div style="background: rgba(0,0,0,0.03); padding: 12px; border-radius: 8px; margin-bottom: 20px; display: flex; align-items: center; justify-content: space-between;">
                    <div>
                        <strong style="display: block; font-size: 13px;">Humanités (Options)</strong>
                        <span style="font-size: 11px; color: var(--text-medium);">Scientifique, Littéraire, Technique...</span>
                    </div>
                    <span class="badge-success stat-badge" style="margin: 0; font-size: 10px;">Ouvert</span>
                </div>

                <a href="inscription.php" class="btn btn-primary" style="width: 100%; text-align: center; border-radius: 8px; padding: 10px;">Ouvrir un dossier d'inscription</a>
            </div>
        </div>
    </section>

    <!-- Comparison Section -->
    <section id="comparaison" class="comparison">
        <div class="container">
            <h2 class="section-title">Pourquoi passer au numérique ?</h2>
            <div class="comparison-grid">
                <!-- Old Process -->
                <div class="comp-card comp-old">
                    <h3><i data-feather="x-circle" class="icon"></i> Procédure Manuelle</h3>
                    <ul class="comp-list">
                        <li><i data-feather="clock" class="icon"></i> Longues files d'attente aux guichets.</li>
                        <li><i data-feather="file-text" class="icon"></i> Utilisation excessive de papier et registres.</li>
                        <li><i data-feather="alert-triangle" class="icon"></i> Risque d'erreur humaine dans la saisie.</li>
                        <li><i data-feather="map-pin" class="icon"></i> Obligation de se déplacer physiquement à l'école.</li>
                        <li><i data-feather="eye-off" class="icon"></i> Accès difficile aux résultats scolaires en temps réel.</li>
                    </ul>
                </div>

                <!-- New Process -->
                <div class="comp-card comp-new">
                    <h3><i data-feather="check-circle" class="icon" style="color: var(--white);"></i> Plateforme Belle Vue</h3>
                    <ul class="comp-list">
                        <li><i data-feather="zap" class="icon"></i> Inscription en 5 minutes, 24h/24 et 7j/7.</li>
                        <li><i data-feather="leaf" class="icon"></i> Démarche 100% écologique, zéro papier (Zéro déchet).</li>
                        <li><i data-feather="shield" class="icon"></i> Données sécurisées et dossiers centralisés numériquement.</li>
                        <li><i data-feather="globe" class="icon"></i> Accessible de n'importe où, même à l'étranger ou en province.</li>
                        <li><i data-feather="trending-up" class="icon"></i> Consultation instantanée des bulletins et points.</li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    <!-- Cursus Section -->
    <section id="cursus-preview" style="padding: 100px 0; background: var(--off-white);">
        <div class="container">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 60px; align-items: center;">
                <div>
                    <h2 style="font-size: 36px; margin-bottom: 20px;">Le suivi de cursus <span style="color: var(--secondary-blue);">réinventé</span>.</h2>
                    <p style="color: var(--text-medium); font-size: 18px; margin-bottom: 25px;">Notre plateforme offre une visibilité totale sur le parcours scolaire. De la maternelle aux humanités, suivez l'évolution académique, les diplômes obtenus et les points en temps réel.</p>
                    <ul style="list-style: none; margin-bottom: 30px;">
                        <li style="margin-bottom: 15px; display: flex; align-items: center; gap: 10px;">
                            <i data-feather="trending-up" style="color: var(--secondary-blue);"></i> <strong>Timeline d'évolution</strong> : Visualisez chaque année scolaire.
                        </li>
                        <li style="margin-bottom: 15px; display: flex; align-items: center; gap: 10px;">
                            <i data-feather="award" style="color: var(--secondary-blue);"></i> <strong>Diplômes & Certificats</strong> : Archivage numérique sécurisé.
                        </li>
                        <li style="margin-bottom: 15px; display: flex; align-items: center; gap: 10px;">
                            <i data-feather="pie-chart" style="color: var(--secondary-blue);"></i> <strong>Analyses de résultats</strong> : Statistiques de réussite par cycle.
                        </li>
                    </ul>
                    <a href="login_portail.php" class="btn btn-primary">Accéder au suivi en direct</a>
                </div>
                <div style="position: relative;">
                    <div style="background: white; padding: 20px; border-radius: 20px; box-shadow: 0 20px 40px rgba(0,0,0,0.1); border: 1px solid #e2e8f0;">
                        <img src="assets/img/photo.jpg" alt="Preview Cursus" style="width: 100%; border-radius: 12px; filter: grayscale(20%);">
                        <div style="position: absolute; bottom: -20px; right: -20px; background: var(--primary-blue); color: white; padding: 20px; border-radius: 15px; box-shadow: 0 10px 20px rgba(0,0,0,0.2);">
                            <span style="display: block; font-size: 12px; opacity: 0.8;">Progression</span>
                            <span style="font-size: 24px; font-weight: bold;">75% de réussite</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <footer class="footer">
        <div class="container">
            <a href="index.php" class="logo"><img src="assets/img/logo.webp" alt="Logo Belle Vue"></a>
            <p style="font-style: italic; color: var(--secondary-blue); font-weight: 600; margin-bottom: 10px; letter-spacing: 1px;">" Devenir Exceptionnel "</p>
            <p>&copy; 2026 École Belle Vue - Lubumbashi, RDC. Tous droits réservés.</p>
            <div style="margin-top: 15px;">
                <a href="choix_portail.php" style="color: #94a3b8; text-decoration: none; font-size: 12px; margin: 0 10px;">Portail Famille</a> | 
                <a href="login_admin.php" style="color: #94a3b8; text-decoration: none; font-size: 12px; margin: 0 10px;">Espace Personnel (Staff)</a>
            </div>
            <p style="font-size: 14px; margin-top: 10px;">Conception et réalisation d’une plateforme web d’inscription.</p>
        </div>
    </footer>

    <script>
        feather.replace();
    </script>
</body>
</html>


