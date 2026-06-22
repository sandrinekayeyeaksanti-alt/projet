<?php
require_once 'config.php';
$classes = [];
if (isset($pdo)) {
    $classes = $pdo->query("SELECT * FROM classes ORDER BY FIELD(niveau, 'Maternelle', 'Primaire', 'Secondaire'), id ASC")->fetchAll();
    $classes_groupes = [];
    foreach ($classes as $c) {
        $classes_groupes[$c['niveau']][] = $c;
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscription | École Belle Vue</title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?= time() ?>">
    <script src="https://unpkg.com/feather-icons"></script>
    <style>
        .admission-stepper { display: flex; flex-direction: column; gap: 15px; margin: 20px 0; }
        .admission-step { display: flex; gap: 15px; align-items: flex-start; padding: 15px; background: #f8fafc; border-radius: 10px; border: 1px solid #e2e8f0; }
        .admission-step.active { border-color: var(--primary-blue); background: #f0f7ff; }
        .step-num { width: 28px; height: 28px; background: var(--primary-blue); color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 14px; flex-shrink: 0; }
        .step-info h4 { margin: 0 0 5px 0; font-size: 15px; color: var(--primary-blue); }
        .step-info p { margin: 0; font-size: 13px; color: var(--text-medium); line-height: 1.4; }
    </style>
</head>
<body>
    <div class="bg-video-container">
        <video autoplay muted loop playsinline><source src="assets/media/video_belle_vue.mp4" type="video/mp4"></video>
    </div>

    <nav class="navbar">
        <div class="container">
            <a href="index.php" class="logo"><img src="assets/img/logo.webp" alt="Logo Belle Vue"></a>
            <ul class="nav-links">
                <li><a href="index.php">Accueil</a></li>
                <li><a href="inscription.php" class="active">S'inscrire</a></li>
                <li><a href="portail.php">Espace Portail</a></li>
            </ul>
        </div>
    </nav>

    <header class="page-header">
        <div class="container">
            <h1>Demande d'Admission</h1>
            <p>Soumettez votre dossier pour l'année scolaire 2026-2027.</p>
        </div>
    </header>

    <section style="padding: 60px 0; background: var(--off-white); min-height: 60vh;">
        <div class="container" style="max-width: 800px;">
            <div class="glass-card" style="background: var(--white); padding: 40px;">
                
                <div class="steps-container">
                    <div class="step active" id="indicator-1">1</div>
                    <div class="step" id="indicator-2">2</div>
                    <div class="step" id="indicator-3">3</div>
                    <div class="step" id="indicator-4"><i data-feather="send" style="width: 16px;"></i></div>
                </div>

                <form id="inscriptionForm" method="POST" action="traitement_inscription.php" enctype="multipart/form-data">
                    
                    <!-- Step 1: Info Élève -->
                    <div class="form-section active" id="step-1">
                        <h3 style="margin-bottom: 25px;">Identification de l'élève</h3>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                            <div class="form-group"><label class="form-label">Nom</label><input type="text" name="nom" class="form-control" required></div>
                            <div class="form-group"><label class="form-label">Post-nom & Prénom</label><input type="text" name="post_nom_prenom" class="form-control" required></div>
                            <div class="form-group"><label class="form-label">Lieu et Date de naissance</label><input type="text" name="lieu_date_naissance" class="form-control" required></div>
                            <div class="form-group">
                                <label class="form-label">Sexe</label>
                                <select name="sexe" class="form-control" required><option value="">Sélectionner...</option><option value="M">Masculin</option><option value="F">Féminin</option></select>
                            </div>
                        </div>
                        <div style="text-align: right; margin-top: 25px;"><button type="button" class="btn btn-primary" onclick="nextStep(1)">Suivant →</button></div>
                    </div>

                    <!-- Step 2: Info Parents -->
                    <div class="form-section" id="step-2">
                        <h3 style="margin-bottom: 25px;">Coordonnées du Tuteur</h3>
                        <div class="form-group"><label class="form-label">Nom Complet du Tuteur</label><input type="text" name="tuteur_nom" class="form-control" required></div>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                            <div class="form-group"><label class="form-label">Téléphone</label><input type="tel" name="tuteur_tel" class="form-control" required></div>
                            <div class="form-group"><label class="form-label">Email</label><input type="email" name="tuteur_email" class="form-control"></div>
                        </div>
                        <div class="form-group"><label class="form-label">Adresse Domicile</label><input type="text" name="tuteur_adresse" class="form-control" required></div>
                        <div style="padding: 15px; background: #e0f2fe; border-left: 4px solid var(--primary-blue); border-radius: 8px; margin-top: 15px;">
                            <label class="form-label">Code PIN Portail (4-6 chiffres)</label>
                            <input type="password" name="code_pin" class="form-control" required pattern="[0-9]{4,6}" placeholder="Votre futur mot de passe">
                        </div>
                        <div style="display: flex; justify-content: space-between; margin-top: 25px;">
                            <button type="button" class="btn btn-outline" onclick="prevStep(2)">← Précédent</button>
                            <button type="button" class="btn btn-primary" onclick="nextStep(2)">Suivant →</button>
                        </div>
                    </div>

                    <!-- Step 3: Scolarité -->
                    <div class="form-section" id="step-3">
                        <h3 style="margin-bottom: 25px;">Choix Pédagogique</h3>
                        <div class="form-group">
                            <label class="form-label">Classe sollicitée</label>
                            <select name="classe_id" class="form-control" required>
                                <option value="">Choisir...</option>
                                <?php foreach ($classes_groupes as $niveau => $liste): ?>
                                    <optgroup label="<?= htmlspecialchars($niveau) ?>">
                                        <?php foreach ($liste as $classe): ?>
                                            <option value="<?= $classe['id'] ?>"><?= htmlspecialchars($classe['nom']) ?> <?= ($classe['option_nom'] !== 'Générale') ? ' - ' . htmlspecialchars($classe['option_nom']) : '' ?></option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group"><label class="form-label">École de provenance</label><input type="text" name="ecole_provenance" class="form-control"></div>
                        <div class="bulletins-container" style="background: #f8fafc; border: 1px dashed #cbd5e1; border-radius: 12px; padding: 20px; margin-bottom: 20px; margin-top: 15px;">
                            <label class="form-label" style="font-weight: 700; color: var(--primary-blue); display: flex; align-items: center; gap: 8px; margin-bottom: 10px;">
                                <i data-feather="file-text" style="width: 18px;"></i> Bulletins des classes précédentes (PDF / Image - Min. 1, Max. 4)
                            </label>
                            <p style="font-size: 12px; color: var(--text-medium); margin-bottom: 15px;">Vous pouvez ajouter jusqu'à 4 bulletins de vos classes précédentes pour appuyer votre demande d'admission.</p>
                            
                            <div id="bulletin-inputs-list" style="display: flex; flex-direction: column; gap: 12px;">
                                <div class="bulletin-input-item" style="display: flex; align-items: center; gap: 10px;">
                                    <span style="font-size: 13px; font-weight: 600; color: #475569; min-width: 80px;">Bulletin 1 :</span>
                                    <input type="file" name="bulletin_files[]" class="form-control" style="flex: 1;">
                                </div>
                            </div>
                            
                            <div style="margin-top: 15px;">
                                <button type="button" id="btn-add-bulletin" class="btn btn-outline" style="padding: 8px 15px; font-size: 13px; display: inline-flex; align-items: center; gap: 6px; border-color: var(--primary-blue); color: var(--primary-blue); background: transparent; transition: all 0.2s ease;">
                                    <i data-feather="plus" style="width: 14px;"></i> Ajouter un bulletin
                                </button>
                            </div>
                        </div>
                        <div style="display: flex; justify-content: space-between; margin-top: 25px;">
                            <button type="button" class="btn btn-outline" onclick="prevStep(3)">← Précédent</button>
                            <button type="button" class="btn btn-primary" onclick="nextStep(3)">Vérifier ma demande →</button>
                        </div>
                    </div>

                    <!-- Step 4: Soumission Finale -->
                    <div class="form-section" id="step-4">
                        <div style="text-align: center; margin-bottom: 30px;">
                            <h3 style="color: var(--primary-blue); margin-bottom: 10px;">Récapitulatif de votre demande</h3>
                            <p style="color: var(--text-medium);">Veuillez prendre connaissance de la procédure de test avant de valider.</p>
                        </div>

                        <div class="admission-stepper">
                            <div class="admission-step active">
                                <div class="step-num">1</div>
                                <div class="step-info">
                                    <h4>Dépôt du dossier</h4>
                                    <p>Vos informations sont transmises au secrétariat pour une première vérification administrative.</p>
                                </div>
                            </div>
                            <div class="admission-step">
                                <div class="step-num">2</div>
                                <div class="step-info">
                                    <h4>Test d'admission</h4>
                                    <p>Vous devez vous présenter à l'école <strong>Belle Vue</strong> pour passer le test d'admission obligatoire.</p>
                                </div>
                            </div>
                            <div class="admission-step">
                                <div class="step-num">3</div>
                                <div class="step-info">
                                    <h4>Validation Finale</h4>
                                    <p>Une fois les résultats du test obtenus, le secrétaire validera définitivement votre inscription sur cette plateforme.</p>
                                </div>
                            </div>
                        </div>

                        <div style="background: #fffbeb; border: 1px solid #fde68a; padding: 20px; border-radius: 12px; margin-bottom: 30px;">
                            <label style="display: flex; gap: 12px; cursor: pointer;">
                                <input type="checkbox" required style="width: 20px; height: 20px; margin-top: 3px;">
                                <span style="font-size: 14px; color: #92400e; line-height: 1.5;">
                                    Je confirme vouloir soumettre cette demande et je m'engage à me présenter à l'école pour le test d'admission.
                                </span>
                            </label>
                        </div>

                        <div style="display: flex; justify-content: space-between;">
                            <button type="button" class="btn btn-outline" onclick="prevStep(4)">← Modifier</button>
                            <button type="submit" class="btn btn-primary" style="background: var(--primary-blue); padding: 15px 40px; font-weight: bold; text-transform: uppercase; letter-spacing: 1px;">
                                Soumettre ma demande
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </section>

    <footer class="footer"><div class="container"><p>&copy; 2026 École Belle Vue - Lubumbashi.</p></div></footer>
    <script src="assets/js/main.js"></script>
    <script>
        // Gestion de l'ajout dynamique des bulletins
        document.addEventListener('DOMContentLoaded', () => {
            const btnAdd = document.getElementById('btn-add-bulletin');
            const listContainer = document.getElementById('bulletin-inputs-list');
            let bulletinCount = 1;
            const maxBulletins = 4;
            
            if (btnAdd && listContainer) {
                btnAdd.addEventListener('click', () => {
                    if (bulletinCount >= maxBulletins) {
                        alert('Vous ne pouvez pas ajouter plus de ' + maxBulletins + ' bulletins.');
                        return;
                    }
                    
                    bulletinCount++;
                    
                    const item = document.createElement('div');
                    item.className = 'bulletin-input-item';
                    item.style.display = 'flex';
                    item.style.alignItems = 'center';
                    item.style.gap = '10px';
                    item.style.opacity = '0';
                    item.style.transition = 'all 0.3s ease';
                    
                    item.innerHTML = `
                        <span style="font-size: 13px; font-weight: 600; color: #475569; min-width: 80px;">Bulletin ${bulletinCount} :</span>
                        <input type="file" name="bulletin_files[]" class="form-control" style="flex: 1;">
                        <button type="button" class="btn-remove-bulletin" style="background: #fee2e2; color: #ef4444; border: 1px solid #fecaca; border-radius: 8px; padding: 10px; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all 0.2s ease;">
                            <i data-feather="trash-2" style="width: 16px; height: 16px;"></i>
                        </button>
                    `;
                    
                    listContainer.appendChild(item);
                    
                    // Trigger animation
                    setTimeout(() => {
                        item.style.opacity = '1';
                    }, 10);
                    
                    // Initialize feather icons for the new remove button
                    if (typeof feather !== 'undefined') {
                        feather.replace();
                    }
                    
                    // Event listener for the remove button
                    const btnRemove = item.querySelector('.btn-remove-bulletin');
                    btnRemove.addEventListener('click', () => {
                        item.style.opacity = '0';
                        setTimeout(() => {
                            item.remove();
                            reindexBulletins();
                        }, 300);
                    });
                    
                    // Update button visibility / state
                    updateAddButtonState();
                });
            }
            
            function reindexBulletins() {
                const items = listContainer.querySelectorAll('.bulletin-input-item');
                bulletinCount = 0;
                items.forEach((item, index) => {
                    bulletinCount = index + 1;
                    const label = item.querySelector('span');
                    if (label) {
                        label.textContent = `Bulletin ${bulletinCount} :`;
                    }
                });
                updateAddButtonState();
            }
            
            function updateAddButtonState() {
                if (bulletinCount >= maxBulletins) {
                    btnAdd.style.opacity = '0.5';
                    btnAdd.style.cursor = 'not-allowed';
                } else {
                    btnAdd.style.opacity = '1';
                    btnAdd.style.cursor = 'pointer';
                }
            }
        });
        
        feather.replace();
    </script>
</body>
</html>

