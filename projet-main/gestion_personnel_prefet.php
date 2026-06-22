<?php
session_start();
require_once 'config.php';

// Vérification du rôle Préfet
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'prefet') {
    header("Location: login_prefet.php");
    exit();
}

$msg = '';
$msg_error = '';

// Actions d'activation / suspension / suppression
if (isset($_GET['action']) && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $action = $_GET['action'];
    
    // Protection pour ne pas toucher à son propre compte
    if ($id !== (int)$_SESSION['user_id']) {
        if ($action === 'activer') {
            $pdo->prepare("UPDATE admins SET statut = 'Actif' WHERE id = ?")->execute([$id]);
            $msg = "Compte activé avec succès.";
        } elseif ($action === 'suspendre') {
            $pdo->prepare("UPDATE admins SET statut = 'Suspendu' WHERE id = ?")->execute([$id]);
            $msg = "Compte suspendu.";
        } elseif ($action === 'supprimer') {
            $pdo->prepare("DELETE FROM admins WHERE id = ?")->execute([$id]);
            $msg = "Compte supprimé définitivement.";
        }
        header("Location: gestion_personnel_prefet.php?msg=" . urlencode($msg));
        exit();
    }
}

if (isset($_GET['msg'])) {
    $msg = htmlspecialchars($_GET['msg']);
}

// Récupération des classes pour le menu déroulant
$classes = [];
if (isset($pdo)) {
    try {
        $classes = $pdo->query("SELECT * FROM classes ORDER BY niveau, nom")->fetchAll();
    } catch (PDOException $e) {
        $msg_error = "Erreur de chargement des classes : " . $e->getMessage();
    }
}

// Chargement des informations du membre en cas de modification
$edit_member = null;
if (isset($_GET['edit_id']) && isset($pdo)) {
    $edit_id = (int)$_GET['edit_id'];
    if ($edit_id !== (int)$_SESSION['user_id']) {
        $stmt = $pdo->prepare("SELECT * FROM admins WHERE id = ?");
        $stmt->execute([$edit_id]);
        $edit_member = $stmt->fetch();
    }
}

// Traitement du formulaire d'ajout / modification
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_personnel'])) {
    $nom_complet = trim($_POST['nom_complet'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $role = $_POST['role'] ?? 'enseignant';
    $classe_id = ($role === 'enseignant' && !empty($_POST['classe_id'])) ? (int)$_POST['classe_id'] : null;
    $password = $_POST['password'] ?? '';
    $edit_id = isset($_POST['edit_id']) ? (int)$_POST['edit_id'] : null;

    if (empty($nom_complet) || empty($username)) {
        $msg_error = "Veuillez remplir le nom complet et l'identifiant.";
    } elseif ($edit_id === null && empty($password)) {
        $msg_error = "Le mot de passe est obligatoire pour un nouveau compte.";
    } else {
        try {
            if ($edit_id) {
                // Vérifier si l'identifiant existe déjà pour un autre compte
                $check = $pdo->prepare("SELECT COUNT(*) FROM admins WHERE username = ? AND id != ?");
                $check->execute([$username, $edit_id]);
                if ($check->fetchColumn() > 0) {
                    $msg_error = "Cet identifiant (<strong>" . htmlspecialchars($username) . "</strong>) est déjà utilisé par un autre membre du personnel. Veuillez en choisir un autre unique.";
                } else {
                    // Si un mot de passe est fourni, on le met à jour
                    if (!empty($password)) {
                        $hash = password_hash($password, PASSWORD_DEFAULT);
                        $stmt = $pdo->prepare("UPDATE admins SET nom_complet = :nom_complet, username = :username, email = :email, role = :role, classe_id = :classe_id, password_hash = :hash WHERE id = :id");
                        $stmt->execute([
                            ':nom_complet' => $nom_complet,
                            ':username' => $username,
                            ':email' => $email,
                            ':role' => $role,
                            ':classe_id' => $classe_id,
                            ':hash' => $hash,
                            ':id' => $edit_id
                        ]);
                    } else {
                        $stmt = $pdo->prepare("UPDATE admins SET nom_complet = :nom_complet, username = :username, email = :email, role = :role, classe_id = :classe_id WHERE id = :id");
                        $stmt->execute([
                            ':nom_complet' => $nom_complet,
                            ':username' => $username,
                            ':email' => $email,
                            ':role' => $role,
                            ':classe_id' => $classe_id,
                            ':id' => $edit_id
                        ]);
                    }
                    $msg = "Profil mis à jour avec succès.";
                    header("Location: gestion_personnel_prefet.php?msg=" . urlencode($msg));
                    exit();
                }
            } else {
                // Vérifier si l'identifiant existe déjà
                $check = $pdo->prepare("SELECT COUNT(*) FROM admins WHERE username = ?");
                $check->execute([$username]);
                if ($check->fetchColumn() > 0) {
                    $msg_error = "Cet identifiant (<strong>" . htmlspecialchars($username) . "</strong>) est déjà utilisé. Veuillez en choisir un autre unique (par exemple: " . htmlspecialchars($username) . "2, ou en utilisant le format prenom.nom).";
                } else {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("INSERT INTO admins (nom_complet, username, email, role, classe_id, password_hash, statut) VALUES (:nom_complet, :username, :email, :role, :classe_id, :hash, 'Actif')");
                    $stmt->execute([
                        ':nom_complet' => $nom_complet,
                        ':username' => $username,
                        ':email' => $email,
                        ':role' => $role,
                        ':classe_id' => $classe_id,
                        ':hash' => $hash
                    ]);
                    $msg = "Profil créé avec succès.";
                    header("Location: gestion_personnel_prefet.php?msg=" . urlencode($msg));
                    exit();
                }
            }
        } catch(PDOException $e) {
            $msg_error = "Erreur de base de données : " . $e->getMessage();
        }
    }
}

// Liste de tout le personnel (sauf le Préfet lui-même) avec leur classe respective
$personnel = [];
if (isset($pdo)) {
    try {
        $personnel = $pdo->query("
            SELECT a.*, c.nom AS classe_nom 
            FROM admins a 
            LEFT JOIN classes c ON a.classe_id = c.id 
            WHERE a.id != " . (int)$_SESSION['user_id'] . " 
            ORDER BY a.role, a.nom_complet
        ")->fetchAll();
    } catch (PDOException $e) {
        $msg_error = "Erreur lors de la récupération du personnel : " . $e->getMessage();
    }
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Gestion Personnel | École Belle Vue</title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?= time() ?>">
    <script src="https://unpkg.com/feather-icons"></script>
    <style>
        /* ============================================================
           BASE
        ============================================================ */
        *, *::before, *::after { box-sizing: border-box; }
        body { margin: 0; font-family: 'Inter', sans-serif; background: #f1f5f9; color: #1e293b; overflow-x: hidden; }

        /* ============================================================
           LAYOUT PRINCIPAL
        ============================================================ */
        .dashboard-container { display: flex; min-height: 100vh; }

        /* ============================================================
           SIDEBAR
        ============================================================ */
        .sidebar {
            width: 230px;
            min-width: 230px;
            flex-shrink: 0;
            background: #0f172a;
            color: white;
            padding: 22px 14px;
            position: sticky;
            top: 0;
            height: 100vh;
            overflow-y: auto;
        }
        .sidebar a {
            display: flex; align-items: center; gap: 10px;
            padding: 10px 13px; border-radius: 8px;
            color: #94a3b8; text-decoration: none;
            font-size: 14px; font-weight: 500;
            transition: all 0.2s; margin-bottom: 3px;
        }
        .sidebar a:hover, .sidebar a.active { background: rgba(255,255,255,0.1); color: white; }
        .sidebar a.logout { color: #fca5a5; }
        .sidebar a.logout:hover { background: rgba(239,68,68,0.12); }

        /* ============================================================
           CONTENU PRINCIPAL
        ============================================================ */
        .main-content {
            flex: 1;
            min-width: 0;
            padding: 30px 26px;
            background: #f1f5f9;
        }

        /* ============================================================
           EN-TÊTE
        ============================================================ */
        .page-header {
            display: flex; justify-content: space-between;
            align-items: flex-start; flex-wrap: wrap;
            gap: 14px; margin-bottom: 26px;
        }
        .page-header h1 { font-size: 22px; font-weight: 700; color: #0f172a; margin: 0; }
        .page-header p  { color: #64748b; font-size: 13px; margin: 3px 0 0; }
        .user-badge {
            display: flex; align-items: center; gap: 9px;
            background: white; padding: 7px 16px; border-radius: 30px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06); white-space: nowrap; flex-shrink: 0;
            font-size: 13px; font-weight: 600;
        }
        .user-avatar {
            width: 30px; height: 30px; background: #fbbf24; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            color: #020617; font-weight: 700; font-size: 13px;
        }

        /* ============================================================
           ALERTES
        ============================================================ */
        .alert {
            display: flex; align-items: flex-start; gap: 10px;
            padding: 13px 16px; border-radius: 10px;
            font-size: 13.5px; margin-bottom: 18px;
        }
        .alert-success { background: #dcfce7; border: 1px solid #bbf7d0; color: #166534; }
        .alert-error   { background: #fee2e2; border: 1px solid #fca5a5; color: #991b1b; }

        /* ============================================================
           GRILLE FORMULAIRE + TABLEAU
        ============================================================ */
        .grid-layout {
            display: grid;
            grid-template-columns: 320px 1fr;
            gap: 22px;
            align-items: start;
        }

        /* ============================================================
           FORMULAIRE
        ============================================================ */
        .form-container {
            background: white; border-radius: 13px;
            padding: 24px 22px;
            box-shadow: 0 4px 14px rgba(0,0,0,0.06);
            position: sticky; top: 20px;
        }
        .form-container h3 {
            font-size: 15px; font-weight: 700; color: #0f172a;
            margin: 0 0 20px; display: flex; align-items: center; gap: 8px;
        }
        .form-group { margin-bottom: 15px; }
        .form-label { display: block; margin-bottom: 5px; font-weight: 500; color: #334155; font-size: 13px; }
        .form-label small { font-weight: 400; color: #94a3b8; }
        .form-control {
            width: 100%; padding: 9px 12px; border-radius: 7px;
            border: 1px solid #cbd5e1; background: #fff;
            font-size: 13.5px; font-family: inherit; transition: all 0.25s;
        }
        .form-control:focus { outline: none; border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59,130,246,0.12); }
        .btn-form {
            display: block; width: 100%; padding: 10px 16px; border-radius: 8px; border: none;
            font-size: 13.5px; font-weight: 600; font-family: inherit; cursor: pointer;
            text-align: center; text-decoration: none; transition: all 0.2s; margin-top: 8px;
        }
        .btn-form-success { background: #10b981; color: white; }
        .btn-form-success:hover { background: #059669; transform: translateY(-1px); }
        .btn-form-cancel { background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; }
        .btn-form-cancel:hover { background: #e2e8f0; }

        /* ============================================================
           TABLEAU
        ============================================================ */
        .table-container { background: white; border-radius: 13px; box-shadow: 0 4px 14px rgba(0,0,0,0.06); overflow: hidden; }
        .table-header { padding: 20px 22px 14px; border-bottom: 1px solid #f1f5f9; }
        .table-header h3 { font-size: 15px; font-weight: 700; color: #0f172a; margin: 0; display: flex; align-items: center; gap: 8px; }
        .table-scroll { overflow-x: auto; -webkit-overflow-scrolling: touch; }
        table { width: 100%; border-collapse: collapse; min-width: 580px; }
        th {
            text-align: left; padding: 10px 15px;
            background: #f8fafc; color: #64748b;
            font-weight: 600; font-size: 11.5px;
            text-transform: uppercase; letter-spacing: 0.4px;
            border-bottom: 1px solid #f1f5f9; white-space: nowrap;
        }
        td { padding: 12px 15px; border-bottom: 1px solid #f8fafc; font-size: 13.5px; vertical-align: middle; }
        tr:last-child td { border-bottom: none; }
        tbody tr:hover td { background: #fafbfc; }

        /* ============================================================
           BADGES & BOUTONS
        ============================================================ */
        .badge { display: inline-block; padding: 3px 9px; border-radius: 20px; font-size: 11.5px; font-weight: 600; }
        .badge-actif    { background: #dcfce7; color: #166534; }
        .badge-attente  { background: #fef3c7; color: #d97706; }
        .badge-suspendu { background: #fee2e2; color: #b91c1c; }
        .badge-role { background: #eef2ff; color: #4338ca; padding: 3px 9px; border-radius: 6px; font-size: 11px; font-weight: 600; }

        .action-btns { display: flex; gap: 5px; }
        .btn-icon {
            display: inline-flex; align-items: center; justify-content: center;
            width: 30px; height: 30px; border-radius: 7px;
            text-decoration: none; transition: opacity 0.2s, transform 0.15s;
            border: none; cursor: pointer; flex-shrink: 0;
        }
        .btn-icon:hover { opacity: 0.82; transform: translateY(-1px); }
        .btn-icon svg { width: 13px; height: 13px; }
        .btn-edit { background: #3b82f6; color: white; }
        .btn-act  { background: #10b981; color: white; }
        .btn-susp { background: #f59e0b; color: white; }
        .btn-del  { background: #ef4444; color: white; }

        /* ============================================================
           RESPONSIVE
        ============================================================ */
        @media (max-width: 1080px) {
            .grid-layout { grid-template-columns: 1fr; }
            .form-container { position: static; }
        }
        @media (max-width: 720px) {
            .sidebar { display: none; }
            .main-content { padding: 18px 12px; }
            .page-header h1 { font-size: 18px; }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <aside class="sidebar">
            <div style="text-align: center; margin-bottom: 40px;">
                <img src="assets/img/logo.webp" alt="Logo" style="height: 50px; filter: brightness(0) invert(1);">
                <p style="font-size: 12px; color: #94a3b8; margin-top: 10px;">DIRECTION GÉNÉRALE</p>
            </div>
            <nav>
                <ul style="list-style: none; padding: 0;">
                    <li style="margin-bottom: 10px;"><a href="dashboard_prefet.php" style="color: #94a3b8; text-decoration: none; display: flex; align-items: center; gap: 12px; padding: 12px;"><i data-feather="shield" style="width: 18px;"></i> Direction</a></li>
                    <li style="margin-bottom: 10px;"><a href="gestion_personnel_prefet.php" class="active" style="background: rgba(255,255,255,0.1); color: white; text-decoration: none; display: flex; align-items: center; gap: 12px; padding: 12px; border-radius: 8px;"><i data-feather="users" style="width: 18px;"></i> Gestion Personnel</a></li>
                    <li style="margin-bottom: 10px;"><a href="archives_ecole_prefet.php" style="color: #94a3b8; text-decoration: none; display: flex; align-items: center; gap: 12px; padding: 12px;"><i data-feather="database" style="width: 18px;"></i> Archives École</a></li>
                    <li style="margin-top: 60px;"><a href="logout.php" style="color: #fca5a5; text-decoration: none; display: flex; align-items: center; gap: 12px; padding: 12px;"><i data-feather="log-out" style="width: 18px;"></i> Déconnexion</a></li>
                </ul>
            </nav>
        </aside>
        
        <main class="main-content">
            <header style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 40px;">
                <div>
                    <h1 style="font-size: 28px; color: #0f172a;">Gestion du Personnel</h1>
                    <p style="color: #64748b;">Administration, création et modification des profils d'enseignants et de secrétaires.</p>
                </div>
                <div style="background: white; padding: 10px 20px; border-radius: 30px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); display: flex; align-items: center; gap: 10px;">
                    <div style="width: 32px; height: 32px; background: #fbbf24; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #020617; font-weight: bold;">P</div>
                    <span style="font-weight: 600; font-size: 14px;"><?= htmlspecialchars($_SESSION['nom_complet']) ?></span>
                </div>
            </header>

            <?php if($msg): ?>
                <div style="background: #dcfce7; border: 1px solid #bbf7d0; color: #166534; padding: 15px; border-radius: 10px; margin-bottom: 20px;">
                    <i data-feather="check-circle" style="width: 18px; vertical-align: middle; margin-right: 5px;"></i> <?= $msg ?>
                </div>
            <?php endif; ?>

            <?php if($msg_error): ?>
                <div style="background: #fee2e2; border: 1px solid #fca5a5; color: #991b1b; padding: 15px; border-radius: 10px; margin-bottom: 20px;">
                    <i data-feather="alert-circle" style="width: 18px; vertical-align: middle; margin-right: 5px;"></i> <?= $msg_error ?>
                </div>
            <?php endif; ?>

            <div class="grid-layout">
                <!-- FORM CONTAINER (AJOUT / MODIFICATION) -->
                <div class="form-container">
                    <h3 style="color: #0f172a; margin-bottom: 20px; font-size: 18px; display: flex; align-items: center; gap: 8px;">
                        <i data-feather="<?= $edit_member ? 'edit' : 'user-plus' ?>" style="color: #3b82f6;"></i>
                        <?= $edit_member ? 'Modifier le Profil' : 'Créer un Compte' ?>
                    </h3>
                    
                    <form method="POST" action="gestion_personnel_prefet.php">
                        <?php if ($edit_member): ?>
                            <input type="hidden" name="edit_id" value="<?= $edit_member['id'] ?>">
                        <?php endif; ?>

                        <div class="form-group">
                            <label class="form-label">Nom complet *</label>
                            <input type="text" name="nom_complet" class="form-control" required placeholder="Ex: Jean-Pierre Kalombo" value="<?= htmlspecialchars($edit_member['nom_complet'] ?? '') ?>">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Identifiant de connexion *</label>
                            <input type="text" name="username" class="form-control" required placeholder="Ex: jp_kalombo" value="<?= htmlspecialchars($edit_member['username'] ?? '') ?>">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Adresse E-mail</label>
                            <input type="email" name="email" class="form-control" placeholder="Ex: jp@bellevue.com" value="<?= htmlspecialchars($edit_member['email'] ?? '') ?>">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Rôle *</label>
                            <select name="role" id="role_select" class="form-control" onchange="toggleClasseField()">
                                <option value="enseignant" <?= (isset($edit_member['role']) && $edit_member['role'] === 'enseignant') ? 'selected' : '' ?>>Enseignant</option>
                                <option value="secretariat" <?= (isset($edit_member['role']) && $edit_member['role'] === 'secretariat') ? 'selected' : '' ?>>Secrétaire</option>
                            </select>
                        </div>

                        <div class="form-group" id="classe_group">
                            <label class="form-label">Classe attribuée</label>
                            <select name="classe_id" class="form-control">
                                <option value="">-- Aucune classe attribuée --</option>
                                <?php foreach ($classes as $c): ?>
                                    <option value="<?= $c['id'] ?>" <?= (isset($edit_member['classe_id']) && (int)$edit_member['classe_id'] === (int)$c['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($c['nom']) ?> (<?= htmlspecialchars($c['option_nom']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">
                                Mot de passe 
                                <?php if ($edit_member): ?>
                                    <span style="font-size:11px; color:#64748b; font-weight:normal;">(laisser vide pour ne pas modifier)</span>
                                <?php else: ?>
                                    *
                                <?php endif; ?>
                            </label>
                            <input type="password" name="password" class="form-control" <?= $edit_member ? '' : 'required' ?> placeholder="••••••••">
                        </div>

                        <button type="submit" name="save_personnel" class="btn btn-action btn-success" style="width: 100%; padding: 12px; margin-top: 10px;">
                            <i data-feather="check" style="width: 16px; vertical-align: middle;"></i>
                            <?= $edit_member ? 'Enregistrer les modifications' : 'Créer le profil' ?>
                        </button>

                        <?php if ($edit_member): ?>
                            <a href="gestion_personnel_prefet.php" class="btn btn-action btn-warning" style="width: 100%; padding: 12px; margin-top: 10px; text-align: center;">
                                Annuler la modification
                            </a>
                        <?php endif; ?>
                    </form>
                </div>

                <!-- TABLE CONTAINER (LISTE DU PERSONNEL) -->
                <div class="table-container">
                    <h3 style="color: #0f172a; margin-bottom: 20px; font-size: 18px; display: flex; align-items: center; gap: 8px;">
                        <i data-feather="list" style="color: #3b82f6;"></i>
                        Liste du Personnel
                    </h3>
                    <table>
                        <thead>
                            <tr>
                                <th>Nom Complet</th>
                                <th>Identifiant</th>
                                <th>Rôle</th>
                                <th>Classe</th>
                                <th>Statut</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($personnel as $p): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($p['nom_complet']) ?></strong></td>
                                <td><code><?= htmlspecialchars($p['username']) ?></code></td>
                                <td>
                                    <span style="background: #eef2ff; color: #4338ca; padding: 4px 8px; border-radius: 6px; font-size: 11px; font-weight: 600;">
                                        <?= ($p['role'] === 'enseignant') ? 'Enseignant' : 'Secrétaire' ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($p['role'] === 'enseignant'): ?>
                                        <span style="font-weight: 500; color: #0f172a;">
                                            <?= htmlspecialchars($p['classe_nom'] ?? 'Aucune') ?>
                                        </span>
                                    <?php else: ?>
                                        <span style="color: #94a3b8;">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                        $badge = 'badge-attente';
                                        if ($p['statut'] === 'Actif') $badge = 'badge-actif';
                                        if ($p['statut'] === 'Suspendu' || $p['statut'] === 'Refusé') $badge = 'badge-suspendu';
                                    ?>
                                    <span class="badge <?= $badge ?>"><?= htmlspecialchars($p['statut'] ?? 'Inconnu') ?></span>
                                </td>
                                <td>
                                    <div style="display: flex; gap: 5px;">
                                        <a href="?edit_id=<?= $p['id'] ?>" class="btn-action btn-primary" title="Modifier">
                                            <i data-feather="edit" style="width: 14px; height: 14px; vertical-align: middle;"></i>
                                        </a>
                                        <?php if ($p['statut'] !== 'Actif'): ?>
                                            <a href="?action=activer&id=<?= $p['id'] ?>" class="btn-action btn-success" title="Activer">
                                                <i data-feather="check-circle" style="width: 14px; height: 14px; vertical-align: middle;"></i>
                                            </a>
                                        <?php else: ?>
                                            <a href="?action=suspendre&id=<?= $p['id'] ?>" class="btn-action btn-warning" title="Suspendre">
                                                <i data-feather="slash" style="width: 14px; height: 14px; vertical-align: middle;"></i>
                                            </a>
                                        <?php endif; ?>
                                        <a href="?action=supprimer&id=<?= $p['id'] ?>" class="btn-action btn-danger" title="Supprimer" onclick="return confirm('Êtes-vous sûr de vouloir supprimer définitivement ce compte ?')">
                                            <i data-feather="trash-2" style="width: 14px; height: 14px; vertical-align: middle;"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if(empty($personnel)): ?>
                                <tr><td colspan="6" style="text-align: center; color: #94a3b8; padding: 20px;">Aucun membre du personnel trouvé.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <script>
        feather.replace();

        function toggleClasseField() {
            const roleSelect = document.getElementById('role_select');
            const classeGroup = document.getElementById('classe_group');
            if (roleSelect.value === 'enseignant') {
                classeGroup.style.display = 'block';
            } else {
                classeGroup.style.display = 'none';
            }
        }

        // Exécuter au chargement pour initialiser l'affichage correct
        document.addEventListener('DOMContentLoaded', function() {
            toggleClasseField();
            
            // Génération automatique d'un identifiant à partir du nom complet
            const nomCompletInput = document.querySelector('input[name="nom_complet"]');
            const usernameInput = document.querySelector('input[name="username"]');
            
            if (nomCompletInput && usernameInput) {
                nomCompletInput.addEventListener('input', function() {
                    // N'auto-remplit que si le champ identifiant n'a pas été modifié manuellement
                    if (!usernameInput.dataset.edited) {
                        let name = this.value.toLowerCase();
                        // Enlever les accents
                        name = name.normalize("NFD").replace(/[\u0300-\u036f]/g, "");
                        // Remplacer les espaces et caractères spéciaux par un point
                        let slug = name.trim().replace(/[^a-z0-9]+/g, '.');
                        // Nettoyer les points en début/fin
                        slug = slug.replace(/^\.|\.$/g, '');
                        usernameInput.value = slug;
                    }
                });

                usernameInput.addEventListener('input', function() {
                    this.dataset.edited = "true";
                });
            }
        });
    </script>
</body>
</html>

