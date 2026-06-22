<?php
session_start();
require_once 'includes/config.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'enseignant') {
    header("Location: login_enseignant.php");
    exit();
}

$prof_id  = $_SESSION['user_id'];
$nom_prof = $_SESSION['nom_complet'] ?? $_SESSION['username'] ?? 'Enseignant';

// Classe du titulaire
$classe = null;
if (isset($pdo)) {
    $st = $pdo->prepare("SELECT c.* FROM classes c JOIN admins a ON a.classe_id = c.id WHERE a.id = ?");
    $st->execute([$prof_id]);
    $classe = $st->fetch();
}
if (!$classe) {
    die("<div style='padding:60px;font-family:sans-serif;color:#b91c1c;text-align:center;'>
        <h2>Aucune classe assignée</h2>
        <p>Contactez l'administrateur pour qu'il vous assigne une classe.</p>
        <a href='logout.php'>Se déconnecter</a>
    </div>");
}

// Élèves de la classe avec infos bulletin
$eleves = [];
if (isset($pdo)) {
    $st = $pdo->prepare("
        SELECT e.*,
               b.pourcentage,
               b.statut AS statut_bulletin,
               b.application,
               b.conduite,
               (SELECT COUNT(*) FROM notes n WHERE n.eleve_id = e.id) AS nb_notes
        FROM eleves e
        LEFT JOIN bulletins b ON b.eleve_id = e.id AND b.periode = '1er Semestre'
        WHERE e.classe_id = ?
        ORDER BY e.nom, e.post_nom_prenom
    ");
    $st->execute([$classe['id']]);
    $eleves = $st->fetchAll();
}

// Stats globales
$total_eleves  = count($eleves);
$bulletins_ok  = count(array_filter($eleves, fn($e) => $e['pourcentage'] !== null));
$moy_classe    = $total_eleves > 0
    ? round(array_sum(array_column(array_filter($eleves, fn($e) => $e['pourcentage'] !== null), 'pourcentage')) / max($bulletins_ok, 1), 1)
    : 0;
$admis         = count(array_filter($eleves, fn($e) => ($e['pourcentage'] ?? 0) >= 50));

// Période sélectionnée
$periode = $_GET['periode'] ?? '1er Semestre';
$periodes_list = ['1er Semestre', '2ème Semestre', 'Annuel'];

// Recherche
$search = trim($_GET['q'] ?? '');
if ($search) {
    $eleves = array_filter($eleves, function($e) use ($search) {
        return stripos($e['nom'] . ' ' . $e['post_nom_prenom'], $search) !== false;
    });
    $eleves = array_values($eleves);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Tableau de bord — <?= htmlspecialchars($nom_prof) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <style>
    :root {
      --primary: #1a3a6b;
      --primary-light: #2451a3;
      --accent: #f59e0b;
      --success: #10b981;
      --danger: #ef4444;
      --bg: #f0f4f8;
      --card: #ffffff;
      --border: #e2e8f0;
      --text: #1e293b;
      --muted: #64748b;
      --sidebar-w: 260px;
    }
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: 'Inter', sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; display: flex; }

    /* ── SIDEBAR ── */
    .sidebar {
      width: var(--sidebar-w);
      background: linear-gradient(160deg, #0f2447 0%, #1a3a6b 60%, #1e4080 100%);
      min-height: 100vh;
      position: fixed;
      top: 0; left: 0;
      display: flex; flex-direction: column;
      box-shadow: 4px 0 20px rgba(0,0,0,0.15);
      z-index: 100;
    }
    .sidebar-header {
      padding: 28px 24px 20px;
      border-bottom: 1px solid rgba(255,255,255,0.1);
    }
    .school-badge {
      display: flex; align-items: center; gap: 10px;
    }
    .school-icon {
      width: 42px; height: 42px;
      background: linear-gradient(135deg, var(--accent), #f97316);
      border-radius: 12px;
      display: flex; align-items: center; justify-content: center;
      font-size: 20px;
    }
    .school-name { color: #fff; font-size: 13px; font-weight: 700; line-height: 1.3; }
    .school-sub  { color: rgba(255,255,255,0.5); font-size: 10px; margin-top: 2px; }

    .prof-card {
      margin: 20px 16px;
      background: rgba(255,255,255,0.08);
      border: 1px solid rgba(255,255,255,0.12);
      border-radius: 14px;
      padding: 16px;
    }
    .prof-avatar {
      width: 48px; height: 48px;
      background: linear-gradient(135deg, var(--accent), #f97316);
      border-radius: 50%;
      display: flex; align-items: center; justify-content: center;
      font-size: 20px; font-weight: 700; color: #fff;
      margin: 0 auto 10px;
    }
    .prof-name  { color: #fff; font-weight: 600; font-size: 13px; text-align: center; }
    .prof-role  { color: rgba(255,255,255,0.5); font-size: 11px; text-align: center; margin-top: 3px; }
    .prof-class {
      margin-top: 10px;
      background: rgba(245,158,11,0.15);
      border: 1px solid rgba(245,158,11,0.3);
      border-radius: 8px;
      padding: 7px 10px;
      text-align: center;
    }
    .prof-class span { color: var(--accent); font-size: 11px; font-weight: 600; }

    .sidebar-nav { padding: 8px 12px; flex: 1; }
    .nav-label { color: rgba(255,255,255,0.35); font-size: 10px; font-weight: 600; letter-spacing: 1px; text-transform: uppercase; padding: 12px 12px 6px; }
    .nav-item {
      display: flex; align-items: center; gap: 10px;
      padding: 11px 14px;
      border-radius: 10px;
      color: rgba(255,255,255,0.65);
      text-decoration: none;
      font-size: 13px; font-weight: 500;
      transition: all 0.2s;
      margin-bottom: 2px;
      cursor: pointer;
    }
    .nav-item:hover, .nav-item.active {
      background: rgba(255,255,255,0.1);
      color: #fff;
    }
    .nav-item.active { background: rgba(245,158,11,0.2); color: var(--accent); }
    .nav-icon { font-size: 16px; width: 20px; text-align: center; }

    .sidebar-footer {
      padding: 16px;
      border-top: 1px solid rgba(255,255,255,0.08);
    }
    .btn-logout {
      display: flex; align-items: center; gap: 8px;
      padding: 10px 14px;
      border-radius: 10px;
      background: rgba(239,68,68,0.15);
      color: #fca5a5;
      text-decoration: none;
      font-size: 13px; font-weight: 500;
      border: 1px solid rgba(239,68,68,0.2);
      transition: all 0.2s;
    }
    .btn-logout:hover { background: rgba(239,68,68,0.25); color: #fff; }

    /* ── MAIN ── */
    .main {
      margin-left: var(--sidebar-w);
      flex: 1;
      min-height: 100vh;
      display: flex; flex-direction: column;
    }

    /* ── TOPBAR ── */
    .topbar {
      background: var(--card);
      border-bottom: 1px solid var(--border);
      padding: 16px 32px;
      display: flex; align-items: center; justify-content: space-between;
      position: sticky; top: 0; z-index: 50;
      box-shadow: 0 1px 8px rgba(0,0,0,0.06);
    }
    .topbar-title { font-size: 18px; font-weight: 700; color: var(--primary); }
    .topbar-sub   { font-size: 12px; color: var(--muted); margin-top: 2px; }
    .topbar-right { display: flex; gap: 12px; align-items: center; }

    .periode-select {
      padding: 8px 14px;
      border: 1px solid var(--border);
      border-radius: 10px;
      font-size: 13px; font-weight: 500;
      color: var(--text);
      background: var(--bg);
      cursor: pointer;
      outline: none;
    }
    .periode-select:focus { border-color: var(--primary-light); }

    /* ── CONTENT ── */
    .content { padding: 28px 32px; flex: 1; }

    /* ── STAT CARDS ── */
    .stats-grid {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 18px;
      margin-bottom: 28px;
    }
    .stat-card {
      background: var(--card);
      border-radius: 16px;
      padding: 22px 20px;
      border: 1px solid var(--border);
      display: flex; align-items: center; gap: 16px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.04);
      transition: transform 0.2s, box-shadow 0.2s;
    }
    .stat-card:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(0,0,0,0.08); }
    .stat-icon {
      width: 52px; height: 52px;
      border-radius: 14px;
      display: flex; align-items: center; justify-content: center;
      font-size: 22px;
      flex-shrink: 0;
    }
    .stat-icon.blue   { background: #dbeafe; }
    .stat-icon.green  { background: #d1fae5; }
    .stat-icon.amber  { background: #fef3c7; }
    .stat-icon.purple { background: #ede9fe; }
    .stat-value { font-size: 26px; font-weight: 800; color: var(--text); line-height: 1; }
    .stat-label { font-size: 12px; color: var(--muted); margin-top: 4px; font-weight: 500; }

    /* ── STUDENT TABLE SECTION ── */
    .section-card {
      background: var(--card);
      border-radius: 18px;
      border: 1px solid var(--border);
      box-shadow: 0 2px 12px rgba(0,0,0,0.05);
      overflow: hidden;
    }
    .section-head {
      padding: 20px 24px;
      border-bottom: 1px solid var(--border);
      display: flex; align-items: center; justify-content: space-between;
      flex-wrap: wrap; gap: 12px;
    }
    .section-title { font-size: 16px; font-weight: 700; color: var(--primary); }
    .section-sub   { font-size: 12px; color: var(--muted); margin-top: 2px; }

    .search-box {
      display: flex; align-items: center; gap: 8px;
      background: var(--bg);
      border: 1px solid var(--border);
      border-radius: 10px;
      padding: 8px 14px;
      min-width: 220px;
    }
    .search-box input {
      border: none; background: transparent;
      font-size: 13px; color: var(--text);
      outline: none; width: 100%;
    }

    /* ── TABLE ── */
    table { width: 100%; border-collapse: collapse; }
    thead tr { background: #f8fafc; }
    thead th {
      padding: 12px 20px;
      font-size: 11px; font-weight: 600;
      color: var(--muted);
      text-transform: uppercase; letter-spacing: 0.5px;
      text-align: left;
      border-bottom: 1px solid var(--border);
    }
    tbody tr {
      border-bottom: 1px solid var(--border);
      transition: background 0.15s;
    }
    tbody tr:last-child { border-bottom: none; }
    tbody tr:hover { background: #f8fafc; }
    tbody td { padding: 14px 20px; font-size: 13px; vertical-align: middle; }

    .student-info { display: flex; align-items: center; gap: 12px; }
    .avatar {
      width: 38px; height: 38px;
      border-radius: 50%;
      display: flex; align-items: center; justify-content: center;
      font-weight: 700; font-size: 14px; color: #fff;
      flex-shrink: 0;
    }
    .avatar.m { background: linear-gradient(135deg, #3b82f6, #1d4ed8); }
    .avatar.f { background: linear-gradient(135deg, #ec4899, #be185d); }
    .student-name  { font-weight: 600; color: var(--text); }
    .student-meta  { font-size: 11px; color: var(--muted); margin-top: 2px; }

    .badge {
      display: inline-flex; align-items: center;
      padding: 4px 10px;
      border-radius: 20px;
      font-size: 11px; font-weight: 600;
    }
    .badge-success { background: #d1fae5; color: #065f46; }
    .badge-danger  { background: #fee2e2; color: #991b1b; }
    .badge-warning { background: #fef3c7; color: #92400e; }
    .badge-gray    { background: #f1f5f9; color: #475569; }

    .progress-bar {
      background: #e2e8f0;
      border-radius: 99px;
      height: 6px;
      width: 80px;
      overflow: hidden;
    }
    .progress-fill {
      height: 100%;
      border-radius: 99px;
      background: linear-gradient(90deg, #10b981, #059669);
    }
    .progress-fill.danger  { background: linear-gradient(90deg, #ef4444, #b91c1c); }
    .progress-fill.warning { background: linear-gradient(90deg, #f59e0b, #d97706); }

    .btn-action {
      display: inline-flex; align-items: center; gap: 6px;
      padding: 7px 14px;
      border-radius: 8px;
      font-size: 12px; font-weight: 600;
      text-decoration: none;
      transition: all 0.2s;
      border: none; cursor: pointer;
    }
    .btn-primary {
      background: var(--primary);
      color: #fff;
    }
    .btn-primary:hover { background: var(--primary-light); transform: translateY(-1px); }
    .btn-outline {
      background: transparent;
      color: var(--primary);
      border: 1px solid var(--border);
    }
    .btn-outline:hover { background: var(--bg); }

    /* ── EMPTY STATE ── */
    .empty-state {
      text-align: center;
      padding: 60px 20px;
      color: var(--muted);
    }
    .empty-icon { font-size: 48px; margin-bottom: 12px; }
    .empty-title { font-size: 16px; font-weight: 600; color: var(--text); margin-bottom: 6px; }

    /* ── RESPONSIVE ── */
    @media (max-width: 1100px) {
      .stats-grid { grid-template-columns: repeat(2, 1fr); }
    }
    @media (max-width: 768px) {
      .sidebar { display: none; }
      .main { margin-left: 0; }
      .content { padding: 16px; }
      .stats-grid { grid-template-columns: 1fr 1fr; }
    }
  </style>
</head>
<body>

<!-- ══════════════════ SIDEBAR ══════════════════ -->
<aside class="sidebar">
  <div class="sidebar-header">
    <div class="school-badge">
      <div class="school-icon">🏫</div>
      <div>
        <div class="school-name">École Belle Vue</div>
        <div class="school-sub">Espace Enseignant</div>
      </div>
    </div>
  </div>

  <div class="prof-card">
    <div class="prof-avatar"><?= strtoupper(substr($nom_prof, 0, 1)) ?></div>
    <div class="prof-name"><?= htmlspecialchars($nom_prof) ?></div>
    <div class="prof-role">Enseignant Titulaire</div>
    <div class="prof-class">
      <span>📚 <?= htmlspecialchars($classe['nom'] . ' — ' . $classe['option_nom']) ?></span>
    </div>
  </div>

  <nav class="sidebar-nav">
    <div class="nav-label">Navigation</div>
    <a class="nav-item active" href="teacher_dashboard.php">
      <span class="nav-icon">🏠</span> Tableau de bord
    </a>
    <a class="nav-item" href="bulletin_rdc.php">
      <span class="nav-icon">📋</span> Gérer les bulletins
    </a>
    <a class="nav-item" href="appel_presences.php">
      <span class="nav-icon">✅</span> Appel / Présences
    </a>
    <a class="nav-item" href="saisie_notes.php">
      <span class="nav-icon">✏️</span> Saisie des notes
    </a>
  </nav>

  <div class="sidebar-footer">
    <a href="logout.php" class="btn-logout">
      <span>🚪</span> Se déconnecter
    </a>
  </div>
</aside>

<!-- ══════════════════ MAIN ══════════════════ -->
<main class="main">

  <!-- TOPBAR -->
  <header class="topbar">
    <div>
      <div class="topbar-title">📊 Mes élèves</div>
      <div class="topbar-sub">Classe : <strong><?= htmlspecialchars($classe['nom'] . ' — ' . $classe['option_nom']) ?></strong> · <?= $total_eleves ?> élève<?= $total_eleves > 1 ? 's' : '' ?> inscrit<?= $total_eleves > 1 ? 's' : '' ?></div>
    </div>
    <div class="topbar-right">
      <select class="periode-select" onchange="window.location.href='teacher_dashboard.php?periode='+this.value+'&q=<?= urlencode($search) ?>'">
        <?php foreach ($periodes_list as $p): ?>
          <option value="<?= $p ?>" <?= $periode === $p ? 'selected' : '' ?>><?= $p ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  </header>

  <div class="content">

    <!-- STATS -->
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-icon blue">👨‍🎓</div>
        <div>
          <div class="stat-value"><?= $total_eleves ?></div>
          <div class="stat-label">Total élèves</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon green">📋</div>
        <div>
          <div class="stat-value"><?= $bulletins_ok ?></div>
          <div class="stat-label">Bulletins saisis</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon amber">📈</div>
        <div>
          <div class="stat-value"><?= $moy_classe ?>%</div>
          <div class="stat-label">Moyenne de classe</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon purple">🏆</div>
        <div>
          <div class="stat-value"><?= $admis ?></div>
          <div class="stat-label">Admis (≥ 50%)</div>
        </div>
      </div>
    </div>

    <!-- TABLE DES ÉLÈVES -->
    <div class="section-card">
      <div class="section-head">
        <div>
          <div class="section-title">Liste des élèves</div>
          <div class="section-sub">Cliquez sur « Bulletin » pour gérer les notes d'un élève</div>
        </div>
        <form method="GET" action="teacher_dashboard.php" style="display:flex;gap:8px;align-items:center;">
          <input type="hidden" name="periode" value="<?= htmlspecialchars($periode) ?>">
          <div class="search-box">
            <span>🔍</span>
            <input type="text" name="q" placeholder="Rechercher un élève…" value="<?= htmlspecialchars($search) ?>">
          </div>
          <button type="submit" class="btn-action btn-primary">Rechercher</button>
          <?php if ($search): ?>
            <a href="teacher_dashboard.php?periode=<?= urlencode($periode) ?>" class="btn-action btn-outline">✕ Effacer</a>
          <?php endif; ?>
        </form>
      </div>

      <?php if (empty($eleves)): ?>
        <div class="empty-state">
          <div class="empty-icon">🎒</div>
          <div class="empty-title"><?= $search ? 'Aucun élève trouvé pour « ' . htmlspecialchars($search) . ' »' : 'Aucun élève inscrit dans votre classe' ?></div>
          <p style="font-size:13px;margin-top:6px;"><?= $search ? '' : 'Les élèves apparaîtront ici après validation par le secrétariat.' ?></p>
        </div>
      <?php else: ?>
      <table>
        <thead>
          <tr>
            <th>#</th>
            <th>Élève</th>
            <th>N° Perm.</th>
            <th>Sexe</th>
            <th>Statut inscript.</th>
            <th>Pourcentage</th>
            <th>Bulletin</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($eleves as $i => $e):
            $pct     = $e['pourcentage'] !== null ? (float)$e['pourcentage'] : null;
            $initiale = strtoupper(substr($e['nom'], 0, 1));
            $sexe    = $e['sexe'] === 'F' ? 'f' : 'm';

            // Badge statut inscription
            if ($e['statut_inscription'] === 'Validé') {
              $badge_insc = '<span class="badge badge-success">✓ Validé</span>';
            } elseif ($e['statut_inscription'] === 'Refusé') {
              $badge_insc = '<span class="badge badge-danger">✗ Refusé</span>';
            } else {
              $badge_insc = '<span class="badge badge-warning">⏳ En attente</span>';
            }

            // Pourcentage + barre
            if ($pct !== null) {
              $fill_class = $pct >= 50 ? '' : ($pct >= 35 ? 'warning' : 'danger');
              $badge_pct  = $pct >= 50
                ? '<span class="badge badge-success">Admis</span>'
                : '<span class="badge badge-danger">En difficulté</span>';
              $bar = "<div class='progress-bar'><div class='progress-fill $fill_class' style='width:{$pct}%'></div></div>
                      <small style='color:var(--muted);font-size:11px;margin-top:2px;display:block;'>{$pct}%</small>";
            } else {
              $badge_pct = '<span class="badge badge-gray">Non saisi</span>';
              $bar = '<small style="color:var(--muted);font-size:11px;">—</small>';
            }
          ?>
          <tr>
            <td style="color:var(--muted);font-weight:600;"><?= $i + 1 ?></td>
            <td>
              <div class="student-info">
                <div class="avatar <?= $sexe ?>"><?= $initiale ?></div>
                <div>
                  <div class="student-name"><?= htmlspecialchars(strtoupper($e['nom']) . ' ' . $e['post_nom_prenom']) ?></div>
                  <div class="student-meta"><?= htmlspecialchars($e['lieu_date_naissance']) ?></div>
                </div>
              </div>
            </td>
            <td style="font-family:monospace;font-size:12px;color:var(--muted);"><?= str_pad($e['id'], 5, '0', STR_PAD_LEFT) ?></td>
            <td><?= $e['sexe'] === 'F' ? '♀ Fille' : '♂ Garçon' ?></td>
            <td><?= $badge_insc ?></td>
            <td>
              <?= $bar ?>
            </td>
            <td><?= $badge_pct ?></td>
            <td>
              <div style="display:flex;gap:6px;">
                <a href="bulletin_rdc.php?eleve_id=<?= $e['id'] ?>&periode=<?= urlencode($periode) ?>"
                   class="btn-action btn-primary" title="Gérer le bulletin">
                  📋 Bulletin
                </a>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </div>

  </div><!-- /.content -->
</main>

<script>
  // Raccourci clavier : Ctrl+K pour focus recherche
  document.addEventListener('keydown', e => {
    if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
      e.preventDefault();
      document.querySelector('.search-box input')?.focus();
    }
  });
</script>
</body>
</html>
