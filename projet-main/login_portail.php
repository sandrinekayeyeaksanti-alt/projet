<?php
session_start();
require_once 'config.php';

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $login = trim($_POST['login'] ?? '');
    $password = $_POST['password'] ?? '';
    $user_type = $_POST['user_type'] ?? 'eleve';

    if (isset($pdo)) {
        // Recherche par téléphone, nom de famille, nom complet de l'élève ou nom du tuteur
        $stmt_eleve = $pdo->prepare("SELECT * FROM eleves WHERE 
            TRIM(tuteur_tel) = :login 
            OR LOWER(TRIM(nom)) = LOWER(:login) 
            OR LOWER(CONCAT(TRIM(nom), ' ', TRIM(post_nom_prenom))) = LOWER(:login)
            OR LOWER(TRIM(tuteur_nom)) = LOWER(:login)");
        $stmt_eleve->execute([':login' => $login]);
        $eleve = $stmt_eleve->fetch();

        if ($eleve && password_verify($password, $eleve['code_pin'])) {
            $_SESSION['eleve_id'] = $eleve['id'];
            $_SESSION['parent_id'] = $eleve['id'];
            $_SESSION['user_id'] = $eleve['id'];
            $_SESSION['username'] = $eleve['tuteur_tel'];
            $_SESSION['eleve_nom'] = $eleve['nom'] . ' ' . $eleve['post_nom_prenom'];
            $_SESSION['parent_nom'] = $eleve['tuteur_nom'];
            $_SESSION['role'] = $user_type;
            
            if ($user_type === 'parent') {
                header("Location: dashboard_parent.php");
            } else {
                header("Location: dashboard_eleve.php");
            }
            exit();
        } else {
            $error = "Identifiants incorrects pour cet espace.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Espace Personnel | École Belle Vue</title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?= time() ?>">
    <script src="https://unpkg.com/feather-icons"></script>
    <style>
        .portail-body {
            background: #f8fafc;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-card {
            background: white;
            padding: 40px;
            border-radius: 24px;
            width: 100%;
            max-width: 450px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }
        .user-type-toggle {
            display: flex;
            background: #f1f5f9;
            padding: 5px;
            border-radius: 12px;
            margin-bottom: 30px;
        }
        .type-btn {
            flex: 1;
            padding: 10px;
            border: none;
            background: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            color: #64748b;
            transition: all 0.2s;
        }
        .type-btn.active {
            background: white;
            color: var(--primary-blue);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>
<body class="portail-body">

    <div class="login-card">
        <div style="text-align: center; margin-bottom: 30px;">
            <img src="assets/img/logo.webp" alt="Logo" style="height: 50px; margin-bottom: 15px;">
            <h2 style="font-size: 24px;">Bienvenue sur votre <span style="color: var(--primary-blue);">Portail</span></h2>
            <p style="color: #64748b; font-size: 14px;">Accédez à votre suivi scolaire en temps réel</p>
        </div>

        <div class="user-type-toggle">
            <button type="button" class="type-btn active" onclick="setType('eleve', this)">Élève</button>
            <button type="button" class="type-btn" onclick="setType('parent', this)">Parent</button>
        </div>

        <?php if($error): ?>
            <div style="background: #fef2f2; color: #991b1b; padding: 12px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; text-align: center; border: 1px solid #fee2e2;">
                <?= $error ?>
            </div>
        <?php endif; ?>

        <form method="POST" autocomplete="off">
            <input type="hidden" name="user_type" id="user_type" value="eleve">
            
            <div class="form-group" style="position: relative;">
                <label class="form-label">Identifiant (Nom de l'élève ou Tél)</label>
                <div style="position: relative;">
                    <i data-feather="user" style="position: absolute; left: 12px; top: 12px; width: 16px; color: #94a3b8;"></i>
                    <input type="text" id="login_input" name="login" class="form-control" style="padding-left: 40px;" required placeholder="Ex: Mutombo ou Téléphone" autocomplete="off">
                </div>
                <div id="autocomplete-suggestions" style="position: absolute; top: 100%; left: 0; right: 0; z-index: 1000; background: white; border: 1px solid #ddd; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); display: none; max-height: 200px; overflow-y: auto; margin-top: 5px;"></div>
            </div>
            
            <div class="form-group" style="margin-top: 20px;">
                <label class="form-label">Code PIN secret</label>
                <div style="position: relative;">
                    <i data-feather="lock" style="position: absolute; left: 12px; top: 12px; width: 16px; color: #94a3b8;"></i>
                    <input type="password" name="password" class="form-control" style="padding-left: 40px;" required placeholder="••••" autocomplete="new-password">
                </div>
            </div>
            
            <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 25px; padding: 12px;">Se connecter au portail</button>
        </form>

        <div style="text-align: center; margin-top: 25px; border-top: 1px solid #f1f5f9; padding-top: 20px;">
            <p style="font-size: 13px; color: #64748b;">Pas encore de compte ? <a href="inscription.php" style="color: var(--primary-blue); font-weight: 600; text-decoration: none;">Inscrire mon enfant</a></p>
        </div>
    </div>

    <script>
        feather.replace();
        function setType(type, btn) {
            document.getElementById('user_type').value = type;
            document.querySelectorAll('.type-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            
            // Hide suggestions if changing profile
            suggestionsDiv.style.display = 'none';
        }

        // Autocomplete JS
        const loginInput = document.getElementById('login_input');
        const suggestionsDiv = document.getElementById('autocomplete-suggestions');

        loginInput.addEventListener('input', function() {
            const userType = document.getElementById('user_type').value;
            // Only suggest for student / parent
            if (userType !== 'eleve' && userType !== 'parent') {
                suggestionsDiv.style.display = 'none';
                return;
            }

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
                            <div style="font-weight: 600; color: var(--primary-blue);">${item.nom_complet}</div>
                            <div style="font-size: 12px; color: #64748b; margin-top: 2px;">Tuteur: ${item.tuteur_nom} | Tél: ${item.tuteur_tel}</div>
                        `;
                        
                        row.addEventListener('mouseover', () => {
                            row.style.background = '#f8fafc';
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

