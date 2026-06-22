<?php
session_start();
require_once 'config.php';
$error = "";
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $login = trim($_POST['login'] ?? '');
    $password = $_POST['password'] ?? '';
    if (isset($pdo)) {
        $stmt_eleve = $pdo->prepare("SELECT * FROM eleves WHERE 
            TRIM(tuteur_tel) = :login 
            OR LOWER(TRIM(nom)) = LOWER(:login) 
            OR LOWER(CONCAT(TRIM(nom), ' ', TRIM(post_nom_prenom))) = LOWER(:login)
            OR LOWER(TRIM(tuteur_nom)) = LOWER(:login)");
        $stmt_eleve->execute([':login' => $login]);
        $eleve = $stmt_eleve->fetch();
        if ($eleve && password_verify($password, $eleve['code_pin'])) {
            $_SESSION['user_id'] = $eleve['id'];
            $_SESSION['username'] = $eleve['tuteur_tel'];
            $_SESSION['parent_nom'] = $eleve['tuteur_nom'];
            $_SESSION['eleve_nom'] = $eleve['nom'] . ' ' . $eleve['post_nom_prenom'];
            $_SESSION['role'] = 'parent';
            header("Location: dashboard_parent.php");
            exit();
        } else { $error = "Identifiants parent incorrects."; }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Espace Parent | École Belle Vue</title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?= time() ?>">
    <script src="https://unpkg.com/feather-icons"></script>
    <style>
        body { background: #f5f3ff; height: 100vh; display: flex; align-items: center; justify-content: center; font-family: 'Outfit'; }
        .login-card { background: white; padding: 40px; border-radius: 20px; width: 400px; box-shadow: 0 10px 25px rgba(0,0,0,0.05); border-top: 5px solid #7c3aed; }
    </style>
</head>
<body>
    <div class="login-card">
        <div style="text-align: center; margin-bottom: 30px;">
            <i data-feather="users" style="width: 48px; height: 48px; color: #7c3aed; margin-bottom: 10px;"></i>
            <h2>Espace <span style="color: #7c3aed;">Parent</span></h2>
        </div>
        <?php if($error): ?><div style="color:red; margin-bottom:15px; font-size:14px;"><?= $error ?></div><?php endif; ?>
        <form method="POST" autocomplete="off">
            <div class="form-group" style="position: relative;">
                <label>Nom de l'élève ou Tuteur (ou Téléphone)</label>
                <input type="text" id="login_input" name="login" class="form-control" required placeholder="Saisissez le nom ou le téléphone..." autocomplete="off">
                <div id="autocomplete-suggestions" style="position: absolute; top: 100%; left: 0; right: 0; z-index: 1000; background: white; border: 1px solid #ddd; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); display: none; max-height: 250px; overflow-y: auto; margin-top: 5px;"></div>
            </div>
            <div class="form-group" style="margin-top:15px;"><label>Code PIN Parent</label><input type="password" name="password" class="form-control" required placeholder="****" autocomplete="new-password"></div>
            <button type="submit" class="btn btn-primary" style="width:100%; margin-top:25px; background:#7c3aed; border:none;">Suivre la scolarité</button>
        </form>
        <div style="text-align: center; margin-top: 20px;"><a href="index.php" style="font-size:12px; color:#64748b;">Retour à l'accueil</a></div>
    </div>
    <script>
        feather.replace();

        // Autocomplete JS
        const loginInput = document.getElementById('login_input');
        const suggestionsDiv = document.getElementById('autocomplete-suggestions');

        loginInput.addEventListener('input', function() {
            const q = this.value.trim();
            if (q.length < 2) {
                suggestionsDiv.style.display = 'none';
                return;
            }
            
            fetch('get_students_autocomplete.php?q=' + encodeURIComponent(q))
                .then(response => response.json())
                .then(data => {
                    if (data.length === 0) {
                        suggestionsDiv.style.display = 'none';
                        return;
                    }
                    suggestionsDiv.innerHTML = '';
                    data.forEach(item => {
                        const row = document.createElement('div');
                        row.style.padding = '12px 15px';
                        row.style.cursor = 'pointer';
                        row.style.borderBottom = '1px solid #f1f5f9';
                        row.style.fontSize = '14px';
                        row.style.color = '#334155';
                        row.style.transition = 'background 0.2s';
                        row.innerHTML = `
                            <div style="font-weight: 600; color: #7c3aed;">${item.nom_complet}</div>
                            <div style="font-size: 12px; color: #64748b; margin-top: 2px;">Tuteur: ${item.tuteur_nom} | Tél: ${item.tuteur_tel}</div>
                        `;
                        
                        row.addEventListener('mouseover', () => {
                            row.style.background = '#f5f3ff';
                        });
                        row.addEventListener('mouseout', () => {
                            row.style.background = 'white';
                        });
                        
                        row.addEventListener('click', () => {
                            loginInput.value = item.nom_complet;
                            suggestionsDiv.style.display = 'none';
                        });
                        
                        suggestionsDiv.appendChild(row);
                    });
                    suggestionsDiv.style.display = 'block';
                })
                .catch(err => console.error('Error fetching autocomplete:', err));
        });

        // Fermer les suggestions si on clique ailleurs
        document.addEventListener('click', function(e) {
            if (e.target !== loginInput && e.target !== suggestionsDiv) {
                suggestionsDiv.style.display = 'none';
            }
        });
    </script>
</body>
</html>

