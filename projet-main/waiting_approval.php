<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Dossier en cours | École Belle Vue</title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?= time() ?>">
    <script src="https://unpkg.com/feather-icons"></script>
    <style>
        .waiting-card { max-width: 600px; margin: 100px auto; background: white; border-radius: 20px; padding: 40px; text-align: center; box-shadow: 0 10px 30px rgba(0,0,0,0.1); border-top: 5px solid #f59e0b; }
        .status-dot { width: 12px; height: 12px; border-radius: 50%; background: #f59e0b; display: inline-block; animation: blink 1s infinite; }
        @keyframes blink { 0% { opacity: 0.2; } 50% { opacity: 1; } 100% { opacity: 0.2; } }
        .step-list { text-align: left; margin: 30px 0; background: #f8fafc; padding: 20px; border-radius: 12px; }
        .step-item { display: flex; gap: 15px; margin-bottom: 15px; align-items: center; }
        .step-icon { width: 30px; height: 30px; border-radius: 50%; background: #e2e8f0; display: flex; align-items: center; justify-content: center; font-size: 14px; }
        .step-item.done .step-icon { background: #10b981; color: white; }
        .step-item.active .step-icon { background: #f59e0b; color: white; }
    </style>
</head>
<body style="background: #f0f4f8;">
    <div class="waiting-card">
        <img src="assets/img/logo.webp" alt="Logo" style="height: 60px; margin-bottom: 20px;">
        <h2 style="color: #0a1931;">Dossier en cours d'examen</h2>
        <p style="color: #64748b; margin-top: 10px;">Bonjour <strong><?= htmlspecialchars($eleve['nom']) ?></strong>, votre test d'admission a bien été enregistré.</p>
        
        <div class="step-list">
            <div class="step-item done">
                <div class="step-icon"><i data-feather="check" style="width: 16px;"></i></div>
                <div><strong style="color: #10b981;">Inscription soumise avec succès</strong></div>
            </div>
            <div class="step-item active">
                <div class="step-icon"><span class="status-dot"></span></div>
                <div><strong>Test d'admission à l'école</strong><br><small style="color: #64748b;">Présentez-vous à l'école Belle Vue pour passer le test obligatoire.</small></div>
            </div>
            <div class="step-item">
                <div class="step-icon"><i data-feather="lock" style="width: 16px;"></i></div>
                <div style="color: #94a3b8;">Accès complet au Portail scolaire</div>
            </div>
        </div>

        <div style="background: #eff6ff; padding: 15px; border-radius: 10px; color: #1e40af; font-size: 14px; margin-bottom: 25px;">
            <i data-feather="info" style="width: 16px; vertical-align: middle;"></i> Un email ou un SMS vous sera envoyé dès que votre compte sera activé.
        </div>

        <a href="logout.php" class="btn btn-outline" style="width: 100%; padding: 12px; display: block; text-decoration: none;">Se déconnecter</a>
    </div>
    <script>feather.replace();</script>
</body>
</html>

