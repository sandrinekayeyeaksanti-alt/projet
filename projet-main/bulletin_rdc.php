<?php
session_start();
require_once 'includes/config.php';

// --- Authentification ---
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'enseignant') {
    header("Location: login_enseignant.php");
    exit();
}

$prof_id     = $_SESSION['user_id'];
$nom_prof    = $_SESSION['nom_complet'] ?? 'Enseignant';
$message     = '';
$message_type = 'success';

// --- Classe du titulaire ---
$classe = null;
if (isset($pdo)) {
    $st = $pdo->prepare("SELECT c.* FROM classes c JOIN admins a ON a.classe_id = c.id WHERE a.id = ?");
    $st->execute([$prof_id]);
    $classe = $st->fetch();
}
if (!$classe) {
    die("<div style='padding:40px;font-family:sans-serif;color:#b91c1c;'>Accès refusé : vous n'êtes pas titulaire d'une classe. Contactez l'administrateur.</div>");
}

// --- Cours de la classe (depuis BD ou liste fixe) ---
$cours_list = [];
if (isset($pdo)) {
    $st = $pdo->prepare("SELECT * FROM cours WHERE classe_id = ? ORDER BY nom");
    $st->execute([$classe['id']]);
    $cours_list = $st->fetchAll();
}
$branches_fixes = ['RELIGION','EDUC. CIV. & MORALE','EDUCATION A LA VIE','ANGLAIS','GEO/ACTUALITE','HISTOIRE','CHIMIE','PHYSIQUE','ELECTRICITE','INFORMATIQUE','METALLURGIE','DESSIN INDUSTRIEL','MECANIQUE GEN.','RESIST. MATERIEL','TECHNO MECANIQUE','FRANCAIS','MATHEMATIQUE','PRAT. PROFESSIONNEL'];
if (empty($cours_list)) {
    foreach ($branches_fixes as $i => $b) {
        $cours_list[] = ['id' => 10000 + $i, 'nom' => $b, 'classe_id' => $classe['id']];
    }
}

// --- Élèves de la classe ---
$eleves = [];
if (isset($pdo)) {
    $st = $pdo->prepare("SELECT * FROM eleves WHERE classe_id = ? ORDER BY nom, post_nom_prenom");
    $st->execute([$classe['id']]);
    $eleves = $st->fetchAll();
}

// --- Paramètres de navigation ---
$periodes = ['1ère Période', 'Examen 1er Semestre', '3ème Période', 'Examen 2ème Semestre', 'Examen de Repechage'];
$selected_eleve_id = isset($_GET['eleve_id']) ? (int)$_GET['eleve_id'] : ($eleves[0]['id'] ?? 0);
$selected_periode  = $_GET['periode'] ?? '1er Semestre';

// --- Élève sélectionné ---
$eleve = null;
foreach ($eleves as $e) {
    if ($e['id'] === $selected_eleve_id) { $eleve = $e; break; }
}
if (!$eleve && !empty($eleves)) { $eleve = $eleves[0]; $selected_eleve_id = $eleve['id']; }

// --- Lecture des notes existantes ---
function get_note($pdo, $eleve_id, $cours_id, $periode) {
    if (!isset($pdo) || $cours_id >= 10000) return '';
    $q = $pdo->prepare("SELECT note FROM notes WHERE eleve_id=? AND cours_id=? AND periode=? LIMIT 1");
    $q->execute([$eleve_id, $cours_id, $periode]);
    $r = $q->fetch();
    return $r ? $r['note'] : '';
}

// --- Migration colonnes supplémentaires bulletins ---
if (isset($pdo)) {
    $cols_to_add = [
        'province'      => "VARCHAR(100) DEFAULT 'LOMAMI'",
        'ville'         => "VARCHAR(100) DEFAULT 'MWENE-DITU'",
        'commune'       => "VARCHAR(100) DEFAULT 'BONDYOI'",
        'ecole'         => "VARCHAR(150) DEFAULT 'ECOLE BELLE VUE'",
        'code_ecole'    => "VARCHAR(50)  DEFAULT '9006613'",
        'annee_scolaire'=> "VARCHAR(20)  DEFAULT '2025-2026'",
        'no_id'         => "VARCHAR(50)  DEFAULT NULL",
        'place'         => "SMALLINT     DEFAULT NULL",
        'nb_eleves'     => "SMALLINT     DEFAULT NULL",
        'statut_jury'   => "VARCHAR(50)  DEFAULT NULL",
    ];
    foreach ($cols_to_add as $col => $def) {
        try {
            $chk = $pdo->query("SHOW COLUMNS FROM bulletins LIKE '$col'");
            if ($chk->rowCount() == 0) {
                $pdo->exec("ALTER TABLE bulletins ADD COLUMN $col $def");
            }
        } catch (Exception $e) { /* ignore */ }
    }
}

// --- Traitement sauvegarde ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_bulletin']) && isset($pdo) && $eleve) {
    $eid = (int)$_POST['eleve_id'];
    $per = $_POST['periode'] ?? '1er Semestre';
    $notes_post = $_POST['notes'] ?? [];

    // Sauvegarder chaque note
    foreach ($notes_post as $cours_id => $periode_notes) {
        foreach ($periode_notes as $p_label => $val) {
            if ($val === '' || $val === null) continue;
            $note_val = (float)$val;
            $note_max = (stripos($p_label, 'Examen') !== false) ? 10 : 10;
            if ($cours_id >= 10000) continue;
            $chk = $pdo->prepare("SELECT id FROM notes WHERE eleve_id=? AND cours_id=? AND periode=? LIMIT 1");
            $chk->execute([$eid, $cours_id, $p_label]);
            $ex = $chk->fetch();
            if ($ex) {
                $pdo->prepare("UPDATE notes SET note=?, note_max=? WHERE id=?")->execute([$note_val, $note_max, $ex['id']]);
            } else {
                $pdo->prepare("INSERT INTO notes (eleve_id,cours_id,periode,note,note_max) VALUES (?,?,?,?,?)")->execute([$eid, $cours_id, $p_label, $note_val, $note_max]);
            }
        }
    }

    // Recalculer le bulletin
    $sm = $pdo->prepare("SELECT SUM(note) as obt, SUM(note_max) as mx FROM notes WHERE eleve_id=?");
    $sm->execute([$eid]);
    $res = $sm->fetch();
    $tot_obt = $res['obt'] ?? 0;
    $tot_max = $res['mx'] ?? 0;
    $pct = $tot_max > 0 ? round(($tot_obt / $tot_max) * 100, 2) : 0;
    $statut = $pct >= 50 ? 'Admis' : 'A revoir';

    // Tous les champs éditables
    $app        = trim($_POST['application']   ?? 'Bonne');
    $cond       = trim($_POST['conduite']      ?? 'TB');
    $dec        = trim($_POST['decision']      ?? '');
    $province   = trim($_POST['province']      ?? 'LOMAMI');
    $ville      = trim($_POST['ville']         ?? 'MWENE-DITU');
    $commune    = trim($_POST['commune']       ?? 'BONDYOI');
    $ecole      = trim($_POST['ecole']         ?? 'ECOLE BELLE VUE');
    $code_ecole = trim($_POST['code_ecole']    ?? '9006613');
    $annee      = trim($_POST['annee_scolaire']?? '2025-2026');
    $no_id      = trim($_POST['no_id']         ?? '');
    $place      = $_POST['place']      !== '' ? (int)$_POST['place']      : null;
    $nb_eleves  = $_POST['nb_eleves']  !== '' ? (int)$_POST['nb_eleves']  : null;
    $statut_jury= trim($_POST['statut_jury']   ?? '');

    // Mettre à jour infos élève si modifiées
    $nom_new    = trim($_POST['nom_eleve']   ?? '');
    $naissance  = trim($_POST['naissance']   ?? '');
    $date_nais  = trim($_POST['date_nais']   ?? '');
    if ($nom_new) {
        $parts = explode(' ', $nom_new, 2);
        $nom_db = strtoupper($parts[0]);
        $prenom = $parts[1] ?? '';
        $lieu_date = $naissance . ($date_nais ? ', ' . $date_nais : '');
        $pdo->prepare("UPDATE eleves SET nom=?, post_nom_prenom=?, lieu_date_naissance=? WHERE id=?")
            ->execute([$nom_db, $prenom, $lieu_date ?: $eleve['lieu_date_naissance'], $eid]);
    }

    $chkb = $pdo->prepare("SELECT id FROM bulletins WHERE eleve_id=? AND periode=? LIMIT 1");
    $chkb->execute([$eid, $per]);
    $exb = $chkb->fetch();
    if ($exb) {
        $pdo->prepare("UPDATE bulletins SET points_obtenus=?,points_max=?,pourcentage=?,statut=?,application=?,conduite=?,decision=?,province=?,ville=?,commune=?,ecole=?,code_ecole=?,annee_scolaire=?,no_id=?,place=?,nb_eleves=?,statut_jury=? WHERE id=?")
            ->execute([$tot_obt,$tot_max,$pct,$statut,$app,$cond,$dec,$province,$ville,$commune,$ecole,$code_ecole,$annee,$no_id,$place,$nb_eleves,$statut_jury,$exb['id']]);
    } else {
        $pdo->prepare("INSERT INTO bulletins (eleve_id,periode,points_obtenus,points_max,pourcentage,statut,application,conduite,decision,province,ville,commune,ecole,code_ecole,annee_scolaire,no_id,place,nb_eleves,statut_jury) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
            ->execute([$eid,$per,$tot_obt,$tot_max,$pct,$statut,$app,$cond,$dec,$province,$ville,$commune,$ecole,$code_ecole,$annee,$no_id,$place,$nb_eleves,$statut_jury]);
    }

    header("Location: bulletin_rdc.php?eleve_id=$eid&periode=" . urlencode($per) . "&ok=1");
    exit();
}

if (isset($_GET['ok'])) {
    $message = "✅ Bulletin enregistré avec succès.";
}

// --- Bulletin existant ---
$bulletin = null;
if (isset($pdo) && $eleve) {
    $st = $pdo->prepare("SELECT * FROM bulletins WHERE eleve_id=? AND periode=? LIMIT 1");
    $st->execute([$eleve['id'], $selected_periode]);
    $bulletin = $st->fetch();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Bulletin RDC</title>
  <style>
    :root {
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      font-size: 11px;
      color: #111;
    }
    * { box-sizing: border-box; }
    body {
      margin: 0;
      padding: 0;
      background: #e4e7ec;
    }
    .page {
      width: 210mm;
      min-height: 297mm;
      margin: 14px auto;
      padding: 15mm;
      background: #fff;
      border: 1px solid #999;
    }
    .print-only {
      display: none;
    }
    .controls {
      width: 210mm;
      margin: 16px auto 0;
      padding: 0 15mm;
      display: flex;
      justify-content: flex-end;
      gap: 10px;
    }
    .controls button {
      padding: 8px 14px;
      font-size: 12px;
      border: 1px solid #222;
      background: #fff;
      cursor: pointer;
    }
    h1 {
      margin: 0;
      font-size: 14px;
    }
    .header-table,
    .info-table,
    .main-table,
    .summary-table,
    .footer-table {
      width: 100%;
      border-collapse: collapse;
    }
    .header-table td,
    .info-table td,
    .main-table th,
    .main-table td,
    .summary-table td,
    .footer-table td {
      border: 1px solid #000;
      vertical-align: middle;
    }
    .header-table td {
      padding: 3px;
    }
    .info-table td {
      padding: 5px;
      font-size: 10px;
    }
    .main-table th,
    .main-table td {
      padding: 4px 5px;
      font-size: 10px;
    }
    .main-table th {
      background: #f1f1f1;
      text-align: center;
      font-weight: 700;
    }
    .gray-cell {
      background: #e8e8e8;
    }
    .header-logo {
      width: 76px;
      height: 56px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      border: 1px solid #000;
      overflow: hidden;
    }
    .header-logo img {
      max-width: 100%;
      max-height: 100%;
      object-fit: contain;
    }
    .header-center {
      text-align: center;
      padding: 4px 2px;
      line-height: 1.2;
    }
    .header-center .main-title {
      font-size: 13px;
      font-weight: 700;
      letter-spacing: 0.4px;
    }
    .header-center .sub-title,
    .header-center .third-title {
      font-size: 10.5px;
      margin-top: 2px;
      font-weight: 600;
    }
    .id-row {
      display: flex;
      align-items: center;
      gap: 8px;
      padding: 6px 0;
    }
    .id-label {
      font-size: 10px;
      font-weight: 700;
      white-space: nowrap;
    }
    .id-cells {
      display: grid;
      grid-template-columns: repeat(12, 1fr);
      gap: 2px;
      flex: 1;
    }
    .id-cell {
      width: 100%;
      min-height: 18px;
      border: 1px solid #000;
      text-align: center;
      font-size: 10px;
      line-height: 18px;
    }
    .info-label {
      font-size: 10px;
      font-weight: 700;
      margin-bottom: 2px;
      display: block;
      text-transform: uppercase;
    }
    .info-input {
      width: 100%;
      border: 1px solid #000;
      padding: 4px 6px;
      font-size: 10px;
      height: 24px;
      background: #fff;
    }
    .info-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 6px;
    }
    .full-width {
      grid-column: span 2;
    }
    .student-grid {
      display: grid;
      grid-template-columns: repeat(4, minmax(0, 1fr));
      gap: 6px;
      margin-top: 6px;
    }
    .student-grid .field {
      border: 1px solid #000;
      padding: 6px 6px 4px;
      min-height: 34px;
    }
    .student-grid .field label {
      display: block;
      font-size: 9px;
      font-weight: 700;
      margin-bottom: 3px;
      text-transform: uppercase;
    }
    .student-grid .field input {
      width: 100%;
      border: none;
      outline: none;
      font-size: 10px;
      padding: 0;
    }
    .center { text-align: center; }
    .small-center { text-align: center; font-size: 9.5px; }
    .note-input {
      width: 100%;
      border: none;
      font-size: 10px;
      padding: 2px 4px;
      text-align: center;
      outline: none;
      background: transparent;
    }
    .note-input:focus {
      background: #f4fbff;
    }
    .summary-row {
      display: grid;
      grid-template-columns: 1fr 1fr 1fr;
      gap: 6px;
      margin-top: 8px;
    }
    .summary-box,
    .status-box {
      border: 1px solid #000;
      padding: 6px;
      background: #f6f6f6;
    }
    .status-box {
      display: grid;
      grid-template-columns: 1fr;
      gap: 4px;
    }
    .status-item {
      display: flex;
      justify-content: space-between;
      align-items: center;
      font-size: 10px;
      padding: 4px 0;
    }
    .status-item span {
      display: inline-block;
      width: 16px;
      height: 16px;
      border: 1px solid #000;
    }
    .footer-notes {
      margin-top: 8px;
      font-size: 9px;
      line-height: 1.2;
    }
    .footer-signatures {
      display: grid;
      grid-template-columns: 1fr 1fr 1fr;
      gap: 6px;
      margin-top: 12px;
    }
    .signature-block {
      border: 1px solid #000;
      min-height: 72px;
      padding: 8px;
      position: relative;
    }
    .signature-block .line {
      border-top: 1px solid #000;
      position: absolute;
      bottom: 10px;
      left: 8px;
      right: 8px;
    }
    .signature-block .label {
      font-size: 9px;
      display: block;
      margin-bottom: 16px;
    }
    @media print {
      body { background: #fff; }
      .controls { display: none; }
      .page { border: none; box-shadow: none; margin: 0; padding: 12mm; }
      @page { size: A4 portrait; margin: 12mm; }
    }
  </style>
</head>
<body>
  <!-- BARRE ENSEIGNANT (cachée à l'impression) -->
  <div class="controls" style="display:flex;justify-content:space-between;align-items:center;padding:10px 20px;background:#0b3d91;color:#fff;position:sticky;top:0;z-index:100;">
    <div style="display:flex;align-items:center;gap:12px;">
      <span style="font-weight:700;font-size:13px;">👤 <?= htmlspecialchars($nom_prof) ?> — Classe : <?= htmlspecialchars($classe['nom'] . ' (' . $classe['option_nom'] . ')') ?></span>
    </div>
    <div style="display:flex;gap:10px;align-items:center;">
      <!-- Sélecteur élève -->
      <select id="eleve_select" onchange="changeEleve()" style="padding:6px 10px;border-radius:8px;border:none;font-size:12px;">
        <?php foreach ($eleves as $e): ?>
          <option value="<?= $e['id'] ?>" <?= $e['id'] == $selected_eleve_id ? 'selected' : '' ?>>
            <?= htmlspecialchars($e['nom'] . ' ' . $e['post_nom_prenom']) ?>
          </option>
        <?php endforeach; ?>
        <?php if (empty($eleves)): ?><option disabled>Aucun élève inscrit</option><?php endif; ?>
      </select>
      <!-- Sélecteur période -->
      <select id="periode_select" onchange="changeEleve()" style="padding:6px 10px;border-radius:8px;border:none;font-size:12px;">
        <option value="1er Semestre" <?= $selected_periode==='1er Semestre'?'selected':'' ?>>1er Semestre</option>
        <option value="2ème Semestre" <?= $selected_periode==='2ème Semestre'?'selected':'' ?>>2ème Semestre</option>
        <option value="Annuel" <?= $selected_periode==='Annuel'?'selected':'' ?>>Annuel</option>
      </select>
      <button onclick="window.print()" style="padding:6px 14px;border-radius:8px;border:none;background:#fff;color:#0b3d91;font-weight:700;cursor:pointer;">🖨 Imprimer</button>
      <a href="teacher_dashboard.php" style="padding:6px 14px;border-radius:8px;background:rgba(255,255,255,0.15);color:#fff;font-weight:600;text-decoration:none;font-size:12px;">← Tableau de bord</a>
      <a href="logout.php" style="padding:6px 14px;border-radius:8px;background:#ef4444;color:#fff;font-weight:700;text-decoration:none;font-size:12px;">Déconnexion</a>
    </div>
  </div>

  <?php if ($message): ?>
  <div style="background:#dcfce7;color:#166534;padding:12px 20px;border-bottom:2px solid #bbf7d0;font-size:13px;text-align:center;">
    <?= $message ?>
  </div>
  <?php endif; ?>

  <?php if (!$eleve): ?>
  <div style="padding:60px;text-align:center;font-family:sans-serif;color:#64748b;">
    <p style="font-size:18px;">Aucun élève inscrit dans votre classe pour le moment.</p>
    <p>Les élèves apparaîtront ici une fois inscrits et validés par le secrétariat.</p>
  </div>
  <?php else: ?>

  <!-- FORMULAIRE ENVELOPPANT LE BULLETIN -->
  <form method="POST" action="bulletin_rdc.php?eleve_id=<?= $selected_eleve_id ?>&periode=<?= urlencode($selected_periode) ?>">
    <input type="hidden" name="save_bulletin" value="1">
    <input type="hidden" name="eleve_id" value="<?= $selected_eleve_id ?>">
    <input type="hidden" name="periode" value="<?= htmlspecialchars($selected_periode) ?>">

  <div class="page" id="bulletinPage">
    <table class="header-table">
      <tr>
        <td style="width: 14%; text-align:center; padding:4px;">
          <div class="header-logo"><img src="assets/img/drapeau.png" alt="Drapeau"></div>
        </td>
        <td style="width: 72%; text-align:center;">
          <div class="header-center">
            <div class="main-title">REPUBLIQUE DEMOCRATIQUE DU CONGO</div>
            <div class="sub-title">MINISTERE DE L'ENSEIGNEMENT PRIMAIRE, SECONDAIRE ET INITIATION A LA NOUVELLE CITOYENNETE</div>
            <div class="third-title">BULLETIN — <?= htmlspecialchars(strtoupper($classe['nom'] . ' — ' . $classe['option_nom'])) ?> — ANNÉE <?= htmlspecialchars($classe['annee'] ?? '2025-2026') ?></div>
          </div>
        </td>
        <td style="width: 14%; text-align:center; padding:4px;">
          <div class="header-logo"><img src="assets/img/leopart.png" alt="Emblème"></div>
        </td>
      </tr>
    </table>

    <table class="info-table" style="margin-top:8px;">
      <tr>
        <td style="width: 28%;">
          <div class="id-row">
            <span class="id-label">N° ID.</span>
            <div class="id-cells">
              <?php
              $no_id_val = $bulletin['no_id'] ?? '';
              $chars = str_split(str_pad($no_id_val, 12));
              foreach ($chars as $c) echo '<div class="id-cell">' . htmlspecialchars($c) . '</div>';
              ?>
            </div>
          </div>
          <div style="margin-top:4px;">
            <label class="info-label">N° ID (modifiable)</label>
            <input type="text" name="no_id" class="info-input" maxlength="12"
              value="<?= htmlspecialchars($bulletin['no_id'] ?? '') ?>"
              placeholder="ex: 9006613">
          </div>
        </td>
        <td style="width: 72%;">
          <div class="info-grid">
            <div><label class="info-label">Province</label><input type="text" name="province" class="info-input" value="<?= htmlspecialchars($bulletin['province'] ?? 'LOMAMI') ?>"></div>
            <div><label class="info-label">Ville</label><input type="text" name="ville" class="info-input" value="<?= htmlspecialchars($bulletin['ville'] ?? 'MWENE-DITU') ?>"></div>
            <div><label class="info-label">Commune</label><input type="text" name="commune" class="info-input" value="<?= htmlspecialchars($bulletin['commune'] ?? 'BONDYOI') ?>"></div>
            <div><label class="info-label">Ecole</label><input type="text" name="ecole" class="info-input" value="<?= htmlspecialchars($bulletin['ecole'] ?? 'ECOLE BELLE VUE') ?>"></div>
            <div><label class="info-label">Code école</label><input type="text" name="code_ecole" class="info-input" value="<?= htmlspecialchars($bulletin['code_ecole'] ?? '9006613') ?>"></div>
            <div><label class="info-label">Année scolaire</label><input type="text" name="annee_scolaire" class="info-input" value="<?= htmlspecialchars($bulletin['annee_scolaire'] ?? ($classe['annee'] ?? '2025-2026')) ?>"></div>
          </div>
        </td>
      </tr>
    </table>

    <div style="border:1px solid #000; padding:8px; margin-top:4px; display:flex; align-items:center; gap:8px; font-size:11px; font-weight:700; flex-wrap:wrap;">
      <span>BULLETIN —</span>
      <input type="text" name="titre_bulletin" class="info-input"
        value="<?= htmlspecialchars($bulletin['titre_bulletin'] ?? strtoupper($classe['nom'] . ' ' . $classe['option_nom'])) ?>"
        style="flex:1;min-width:180px;font-weight:700;font-size:11px;">
      <span>ANNÉE SCOLAIRE</span>
      <input type="text" name="annee_scolaire" class="info-input"
        value="<?= htmlspecialchars($bulletin['annee_scolaire'] ?? ($classe['annee'] ?? '2025-2026')) ?>"
        style="width:100px;font-weight:700;font-size:11px;">
      <span>PÉRIODE :</span>
      <strong><?= htmlspecialchars($selected_periode) ?></strong>
    </div>

    <div class="student-grid">
      <div class="field"><label>ELEVE (Nom Prénom)</label><input type="text" name="nom_eleve" value="<?= htmlspecialchars($eleve['nom'] . ' ' . $eleve['post_nom_prenom']) ?>"></div>
      <div class="field"><label>SEXE</label>
        <select name="sexe_eleve" style="width:100%;border:none;outline:none;font-size:10px;padding:0;">
          <option value="M" <?= $eleve['sexe']==='M'?'selected':'' ?>>M</option>
          <option value="F" <?= $eleve['sexe']==='F'?'selected':'' ?>>F</option>
        </select>
      </div>
      <div class="field"><label>NE(E) A</label><input type="text" name="naissance" value="<?= htmlspecialchars(explode(',', $eleve['lieu_date_naissance'])[0] ?? '') ?>"></div>
      <div class="field"><label>LE</label><input type="text" name="date_nais" value="<?= htmlspecialchars(trim(explode(',', $eleve['lieu_date_naissance'])[1] ?? '')) ?>"></div>
      <div class="field"><label>CLASSE</label><input type="text" value="<?= htmlspecialchars($classe['nom'] . ' ' . $classe['option_nom']) ?>" readonly style="background:#f8f8f8;"></div>
      <div class="field"><label>N° PERM.</label><input type="text" value="<?= htmlspecialchars($eleve['id']) ?>" readonly style="background:#f8f8f8;"></div>
      <div class="field" style="grid-column: span 2;"><label></label></div>
      <div class="field" style="grid-column: span 2;"><label></label></div>
    </div>

    <table class="main-table" style="margin-top:8px;">
      <colgroup>
        <col style="width: 30%;"><col style="width: 8%;"><col style="width: 8%;"><col style="width: 8%;">
        <col style="width: 8%;"><col style="width: 8%;"><col style="width: 8%;"><col style="width: 10%;"><col style="width: 12%;">
      </colgroup>
      <thead>
        <tr>
          <th rowspan="2">BRANCHES</th>
          <th colspan="3">PREMIER SEMESTRE</th>
          <th colspan="3">SECOND SEMESTRE</th>
          <th rowspan="2">EXAMEN DE REPECHAGE</th>
          <th rowspan="2">SIG. PROF.</th>
        </tr>
        <tr>
          <th>TR./ JOURNAL</th><th>EXAM</th><th>TOT.</th>
          <th>TR./ JOURNAL</th><th>EXAM</th><th>TOT.</th>
        </tr>
      </thead>
      <tbody>
        <tr class="gray-cell">
          <td>MAXIMA</td>
          <td class="center">10</td><td class="center">10</td><td class="center">20</td>
          <td class="center">10</td><td class="center">10</td><td class="center">20</td>
          <td class="center">40</td><td></td>
        </tr>
        <?php foreach ($cours_list as $cours):
          $cid = $cours['id'];
          $v1  = get_note($pdo, $selected_eleve_id, $cid, '1ère Période');
          $e1  = get_note($pdo, $selected_eleve_id, $cid, 'Examen 1er Semestre');
          $v2  = get_note($pdo, $selected_eleve_id, $cid, '3ème Période');
          $e2  = get_note($pdo, $selected_eleve_id, $cid, 'Examen 2ème Semestre');
          $rep = get_note($pdo, $selected_eleve_id, $cid, 'Examen de Repechage');
          $tot1 = ($v1 !== '' && $e1 !== '') ? ((float)$v1 + (float)$e1) : '';
          $tot2 = ($v2 !== '' && $e2 !== '') ? ((float)$v2 + (float)$e2) : '';
          $name_field = "notes[$cid]";
        ?>
        <tr>
          <td><?= htmlspecialchars($cours['nom']) ?></td>
          <td><input class="note-input" type="number" step="0.5" min="0" max="10" name="<?= $name_field ?>[1ère Période]" value="<?= htmlspecialchars($v1) ?>"></td>
          <td><input class="note-input" type="number" step="0.5" min="0" max="10" name="<?= $name_field ?>[Examen 1er Semestre]" value="<?= htmlspecialchars($e1) ?>"></td>
          <td class="center"><span class="row-total"><?= $tot1 !== '' ? $tot1 : '0' ?></span></td>
          <td><input class="note-input" type="number" step="0.5" min="0" max="10" name="<?= $name_field ?>[3ème Période]" value="<?= htmlspecialchars($v2) ?>"></td>
          <td><input class="note-input" type="number" step="0.5" min="0" max="10" name="<?= $name_field ?>[Examen 2ème Semestre]" value="<?= htmlspecialchars($e2) ?>"></td>
          <td class="center"><span class="row-total"><?= $tot2 !== '' ? $tot2 : '0' ?></span></td>
          <td><input class="note-input" type="number" step="0.5" min="0" max="40" name="<?= $name_field ?>[Examen de Repechage]" value="<?= htmlspecialchars($rep) ?>"></td>
          <td></td>
        </tr>
        <?php endforeach; ?>
        <tr class="gray-cell">
          <td><strong>MAXIMA GENERAUX</strong></td>
          <td class="center" id="mx_v1">0</td><td class="center" id="mx_e1">0</td><td class="center" id="mx_t1">0</td>
          <td class="center" id="mx_v2">0</td><td class="center" id="mx_e2">0</td><td class="center" id="mx_t2">0</td>
          <td class="center" id="mx_rep">0</td><td></td>
        </tr>
      </tbody>
    </table>

    <div class="summary-row">
      <div class="summary-box">
        <div class="status-item"><strong>Total général</strong> <span id="totalGeneral"><?= $bulletin ? $bulletin['points_obtenus'] : 0 ?></span></div>
        <div class="status-item"><strong>Pourcentage</strong> <span id="percentageGeneral"><?= $bulletin ? $bulletin['pourcentage'].'%' : '0%' ?></span></div>
        <div class="status-item" style="gap:4px;">
          <strong>Place</strong>
          <input class="note-input" type="number" name="place" min="1"
            value="<?= htmlspecialchars($bulletin['place'] ?? '') ?>"
            style="width:40px;border:1px solid #ccc;text-align:center;" placeholder="—">
          <strong>/</strong>
          <input class="note-input" type="number" name="nb_eleves" min="1"
            value="<?= htmlspecialchars($bulletin['nb_eleves'] ?? '') ?>"
            style="width:40px;border:1px solid #ccc;text-align:center;" placeholder="—">
        </div>
      </div>
      <div class="status-box">
        <?php $jury = $bulletin['statut_jury'] ?? ''; ?>
        <div class="status-item">
          <label style="display:flex;align-items:center;gap:4px;font-size:10px;cursor:pointer;">
            <input type="radio" name="statut_jury" value="Passé" <?= $jury==='Passé'?'checked':'' ?>> Passé
          </label>
        </div>
        <div class="status-item">
          <label style="display:flex;align-items:center;gap:4px;font-size:10px;cursor:pointer;">
            <input type="radio" name="statut_jury" value="Doublé" <?= $jury==='Doublé'?'checked':'' ?>> Doublé
          </label>
        </div>
        <div class="status-item">
          <label style="display:flex;align-items:center;gap:4px;font-size:10px;cursor:pointer;">
            <input type="radio" name="statut_jury" value="Ajourné" <?= $jury==='Ajourné'?'checked':'' ?>> Ajourné
          </label>
        </div>
      </div>
      <div class="summary-box">
        <div class="status-item"><strong>APPLICATION</strong>
          <input class="note-input" type="text" name="application"
            value="<?= htmlspecialchars($bulletin['application'] ?? 'Bonne') ?>"
            style="width:80px;border:1px solid #ccc;">
        </div>
        <div class="status-item"><strong>CONDUITE</strong>
          <input class="note-input" type="text" name="conduite"
            value="<?= htmlspecialchars($bulletin['conduite'] ?? 'TB') ?>"
            style="width:80px;border:1px solid #ccc;">
        </div>
        <div class="status-item"><strong>DÉCISION DU JURY</strong>
          <input class="note-input" type="text" name="decision"
            value="<?= htmlspecialchars($bulletin['decision'] ?? '') ?>"
            style="width:100px;border:1px solid #ccc;">
        </div>
      </div>
    </div>

    <div class="footer-notes">
      <p>(* ) L'élève ne pourra passer dans la classe supérieure s'il ne subit avec succès un examen de repêchage.</p>
      <p>(** ) L'élève ne passe dans la classe supérieure que si son pourcentage est d'au moins 50%.</p>
    </div>

    <!-- Bouton Enregistrer (caché à l'impression) -->
    <div style="margin-top:14px;text-align:right;" class="controls">
      <button type="submit" style="padding:10px 24px;background:#0b3d91;color:#fff;border:none;border-radius:10px;font-size:14px;font-weight:700;cursor:pointer;">
        💾 Enregistrer le bulletin
      </button>
    </div>

    <div class="footer-signatures">
      <div class="signature-block"><span class="label">Signature de l'élève</span><span class="line"></span></div>
      <div class="signature-block"><span class="label">Sceau de l'école</span><span class="line"></span></div>
      <div class="signature-block"><span class="label">Nom et signature du Chef d'établissement</span><span class="line"></span></div>
    </div>
  </div><!-- /.page -->
  </form>
  <?php endif; /* fin if $eleve */ ?>

  <script>
    function changeEleve() {
      const id = document.getElementById('eleve_select').value;
      const per = document.getElementById('periode_select').value;
      window.location.href = 'bulletin_rdc.php?eleve_id=' + encodeURIComponent(id) + '&periode=' + encodeURIComponent(per);
    }

    function updateTotals() {
      const rows = document.querySelectorAll('.main-table tbody tr:not(.gray-cell)');
      let gTotal = 0, cntV1=0, cntE1=0, cntV2=0, cntE2=0, cntRep=0;
      rows.forEach(row => {
        const inputs = row.querySelectorAll('input[type=number]');
        if (inputs.length < 5) return;
        const v1 = Number(inputs[0].value)||0;
        const e1 = Number(inputs[1].value)||0;
        const v2 = Number(inputs[2].value)||0;
        const e2 = Number(inputs[3].value)||0;
        const rep = Number(inputs[4].value)||0;
        const tots = row.querySelectorAll('.row-total');
        if (tots[0]) tots[0].textContent = v1 + e1;
        if (tots[1]) tots[1].textContent = v2 + e2;
        gTotal += v1 + e1 + v2 + e2 + rep;
        cntV1+=v1; cntE1+=e1; cntV2+=v2; cntE2+=e2; cntRep+=rep;
      });
      if (document.getElementById('mx_v1'))   document.getElementById('mx_v1').textContent   = cntV1;
      if (document.getElementById('mx_e1'))   document.getElementById('mx_e1').textContent   = cntE1;
      if (document.getElementById('mx_t1'))   document.getElementById('mx_t1').textContent   = cntV1+cntE1;
      if (document.getElementById('mx_v2'))   document.getElementById('mx_v2').textContent   = cntV2;
      if (document.getElementById('mx_e2'))   document.getElementById('mx_e2').textContent   = cntE2;
      if (document.getElementById('mx_t2'))   document.getElementById('mx_t2').textContent   = cntV2+cntE2;
      if (document.getElementById('mx_rep'))  document.getElementById('mx_rep').textContent  = cntRep;
      if (document.getElementById('totalGeneral'))      document.getElementById('totalGeneral').textContent = gTotal;
      const maxTotal = rows.length * 40;
      const pct = maxTotal > 0 ? Math.round((gTotal / maxTotal) * 100) : 0;
      if (document.getElementById('percentageGeneral')) document.getElementById('percentageGeneral').textContent = pct + '%';
    }

    document.querySelectorAll('.main-table input[type=number]').forEach(inp => {
      inp.addEventListener('input', updateTotals);
    });
    updateTotals();
  </script>
</body>
</html>
<?php exit(); ?>


        </tr>
        <tr>
          <td>RELIGION</td>
          <td><input class="note-input" type="number" min="0" max="10"></td>
          <td><input class="note-input" type="number" min="0" max="10"></td>
          <td class="center"><span class="row-total">0</span></td>
          <td><input class="note-input" type="number" min="0" max="10"></td>
          <td><input class="note-input" type="number" min="0" max="10"></td>
          <td class="center"><span class="row-total">0</span></td>
          <td><input class="note-input" type="number" min="0" max="40"></td>
          <td></td>
          <td></td>
        </tr>
        <tr>
          <td>EDUC. CIV. & MORALE</td>
          <td><input class="note-input" type="number" min="0" max="10"></td>
          <td><input class="note-input" type="number" min="0" max="10"></td>
          <td class="center"><span class="row-total">0</span></td>
          <td><input class="note-input" type="number" min="0" max="10"></td>
          <td><input class="note-input" type="number" min="0" max="10"></td>
          <td class="center"><span class="row-total">0</span></td>
          <td><input class="note-input" type="number" min="0" max="40"></td>
          <td></td>
          <td></td>
        </tr>
        <tr>
          <td>EDUCATION A LA VIE</td>
          <td><input class="note-input" type="number" min="0" max="10"></td>
          <td><input class="note-input" type="number" min="0" max="10"></td>
          <td class="center"><span class="row-total">0</span></td>
          <td><input class="note-input" type="number" min="0" max="10"></td>
          <td><input class="note-input" type="number" min="0" max="10"></td>
          <td class="center"><span class="row-total">0</span></td>
          <td><input class="note-input" type="number" min="0" max="40"></td>
          <td></td>
          <td></td>
        </tr>
        <tr class="gray">
          <td>MAXIMA</td>
          <td class="center">20</td>
          <td class="center">20</td>
          <td class="center">40</td>
          <td class="center">20</td>
          <td class="center">20</td>
          <td class="center">40</td>
          <td class="center">160</td>
          <td></td>
          <td></td>
        </tr>
        <tr>
          <td>ANGLAIS</td>
          <td><input class="note-input" type="number" min="0" max="10"></td>
          <td><input class="note-input" type="number" min="0" max="10"></td>
          <td class="center"><span class="row-total">0</span></td>
          <td><input class="note-input" type="number" min="0" max="10"></td>
          <td><input class="note-input" type="number" min="0" max="10"></td>
          <td class="center"><span class="row-total">0</span></td>
          <td><input class="note-input" type="number" min="0" max="40"></td>
          <td></td>
          <td></td>
        </tr>
        <tr>
          <td>GEO/ACTUALITE</td>
          <td><input class="note-input" type="number" min="0" max="10"></td>
          <td><input class="note-input" type="number" min="0" max="10"></td>
          <td class="center"><span class="row-total">0</span></td>
          <td><input class="note-input" type="number" min="0" max="10"></td>
          <td><input class="note-input" type="number" min="0" max="10"></td>
          <td class="center"><span class="row-total">0</span></td>
          <td><input class="note-input" type="number" min="0" max="40"></td>
          <td></td>
          <td></td>
        </tr>
        <tr>
          <td>HISTOIRE</td>
          <td><input class="note-input" type="number" min="0" max="10"></td>
          <td><input class="note-input" type="number" min="0" max="10"></td>
          <td class="center"><span class="row-total">0</span></td>
          <td><input class="note-input" type="number" min="0" max="10"></td>
          <td><input class="note-input" type="number" min="0" max="10"></td>
          <td class="center"><span class="row-total">0</span></td>
          <td><input class="note-input" type="number" min="0" max="40"></td>
          <td></td>
          <td></td>
        </tr>
        <tr>
          <td>CHIMIE</td>
          <td><input class="note-input" type="number" min="0" max="10"></td>
          <td><input class="note-input" type="number" min="0" max="10"></td>
          <td class="center"><span class="row-total">0</span></td>
          <td><input class="note-input" type="number" min="0" max="10"></td>
          <td><input class="note-input" type="number" min="0" max="10"></td>
          <td class="center"><span class="row-total">0</span></td>
          <td><input class="note-input" type="number" min="0" max="40"></td>
          <td></td>
          <td></td>
        </tr>
        <tr>
          <td>PHYSIQUE</td>
          <td><input class="note-input" type="number" min="0" max="10"></td>
          <td><input class="note-input" type="number" min="0" max="10"></td>
          <td class="center"><span class="row-total">0</span></td>
          <td><input class="note-input" type="number" min="0" max="10"></td>
          <td><input class="note-input" type="number" min="0" max="10"></td>
          <td class="center"><span class="row-total">0</span></td>
          <td><input class="note-input" type="number" min="0" max="40"></td>
          <td></td>
          <td></td>
        </tr>
        <tr>
          <td>ELECTRICITE</td>
          <td><input class="note-input" type="number" min="0" max="10"></td>
          <td><input class="note-input" type="number" min="0" max="10"></td>
          <td class="center"><span class="row-total">0</span></td>
          <td><input class="note-input" type="number" min="0" max="10"></td>
          <td><input class="note-input" type="number" min="0" max="10"></td>
          <td class="center"><span class="row-total">0</span></td>
          <td><input class="note-input" type="number" min="0" max="40"></td>
          <td></td>
          <td></td>
        </tr>
        <tr>
          <td>INFORMATIQUE</td>
          <td><input class="note-input" type="number" min="0" max="10"></td>
          <td><input class="note-input" type="number" min="0" max="10"></td>
          <td class="center"><span class="row-total">0</span></td>
          <td><input class="note-input" type="number" min="0" max="10"></td>
          <td><input class="note-input" type="number" min="0" max="10"></td>
          <td class="center"><span class="row-total">0</span></td>
          <td><input class="note-input" type="number" min="0" max="40"></td>
          <td></td>
          <td></td>
        </tr>
        <tr>
          <td>METALLURGIE</td>
          <td><input class="note-input" type="number" min="0" max="10"></td>
          <td><input class="note-input" type="number" min="0" max="10"></td>
          <td class="center"><span class="row-total">0</span></td>
          <td><input class="note-input" type="number" min="0" max="10"></td>
          <td><input class="note-input" type="number" min="0" max="10"></td>
          <td class="center"><span class="row-total">0</span></td>
          <td><input class="note-input" type="number" min="0" max="40"></td>
          <td></td>
          <td></td>
        </tr>
        <tr class="gray">
          <td>MAXIMA</td>
          <td class="center">40</td>
          <td class="center">40</td>
          <td class="center">80</td>
          <td class="center">40</td>
          <td class="center">40</td>
          <td class="center">80</td>
          <td class="center">320</td>
          <td></td>
          <td></td>
        </tr>
        <tr>
          <td>DESSIN INDUSTRIEL</td>
          <td><input class="note-input" type="number" min="0" max="10"></td>
          <td><input class="note-input" type="number" min="0" max="10"></td>
          <td class="center"><span class="row-total">0</span></td>
          <td><input class="note-input" type="number" min="0" max="10"></td>
          <td><input class="note-input" type="number" min="0" max="10"></td>
          <td class="center"><span class="row-total">0</span></td>
          <td><input class="note-input" type="number" min="0" max="40"></td>
          <td></td>
          <td></td>
        </tr>
        <tr>
          <td>MECANIQUE GEN.</td>
          <td><input class="note-input" type="number" min="0" max="10"></td>
          <td><input class="note-input" type="number" min="0" max="10"></td>
          <td class="center"><span class="row-total">0</span></td>
          <td><input class="note-input" type="number" min="0" max="10"></td>
          <td><input class="note-input" type="number" min="0" max="10"></td>
          <td class="center"><span class="row-total">0</span></td>
          <td><input class="note-input" type="number" min="0" max="40"></td>
          <td></td>
          <td></td>
        </tr>
        <tr>
          <td>RESIST. MATERIEL</td>
          <td><input class="note-input" type="number" min="0" max="10"></td>
          <td><input class="note-input" type="number" min="0" max="10"></td>
          <td class="center"><span class="row-total">0</span></td>
          <td><input class="note-input" type="number" min="0" max="10"></td>
          <td><input class="note-input" type="number" min="0" max="10"></td>
          <td class="center"><span class="row-total">0</span></td>
          <td><input class="note-input" type="number" min="0" max="40"></td>
          <td></td>
          <td></td>
        </tr>
        <tr>
          <td>TECHNO MECANIQUE</td>
          <td><input class="note-input" type="number" min="0" max="10"></td>
          <td><input class="note-input" type="number" min="0" max="10"></td>
          <td class="center"><span class="row-total">0</span></td>
          <td><input class="note-input" type="number" min="0" max="10"></td>
          <td><input class="note-input" type="number" min="0" max="10"></td>
          <td class="center"><span class="row-total">0</span></td>
          <td><input class="note-input" type="number" min="0" max="40"></td>
          <td></td>
          <td></td>
        </tr>
        <tr class="gray">
          <td>MAXIMA</td>
          <td class="center">50</td>
          <td class="center">50</td>
          <td class="center">100</td>
          <td class="center">50</td>
          <td class="center">50</td>
          <td class="center">100</td>
          <td class="center">200</td>
          <td></td>
          <td></td>
        </tr>
        <tr>
          <td>FRANCAIS</td>
          <td><input class="note-input" type="number" min="0" max="20"></td>
          <td><input class="note-input" type="number" min="0" max="20"></td>
          <td class="center"><span class="row-total">0</span></td>
          <td><input class="note-input" type="number" min="0" max="20"></td>
          <td><input class="note-input" type="number" min="0" max="20"></td>
          <td class="center"><span class="row-total">0</span></td>
          <td><input class="note-input" type="number" min="0" max="40"></td>
          <td></td>
          <td></td>
        </tr>
        <tr>
          <td>MATHEMATIQUE</td>
          <td><input class="note-input" type="number" min="0" max="20"></td>
          <td><input class="note-input" type="number" min="0" max="20"></td>
          <td class="center"><span class="row-total">0</span></td>
          <td><input class="note-input" type="number" min="0" max="20"></td>
          <td><input class="note-input" type="number" min="0" max="20"></td>
          <td class="center"><span class="row-total">0</span></td>
          <td><input class="note-input" type="number" min="0" max="40"></td>
          <td></td>
          <td></td>
        </tr>
        <tr class="gray">
          <td>MAXIMA</td>
          <td class="center">100</td>
          <td class="center">100</td>
          <td class="center">200</td>
          <td class="center">100</td>
          <td class="center">100</td>
          <td class="center">200</td>
          <td class="center">400</td>
          <td></td>
          <td></td>
        </tr>
        <tr>
          <td>PRAT. PROFESSIONNEL</td>
          <td><input class="note-input" type="number" min="0" max="20"></td>
          <td><input class="note-input" type="number" min="0" max="20"></td>
          <td class="center"><span class="row-total">0</span></td>
          <td><input class="note-input" type="number" min="0" max="20"></td>
          <td><input class="note-input" type="number" min="0" max="20"></td>
          <td class="center"><span class="row-total">0</span></td>
          <td><input class="note-input" type="number" min="0" max="40"></td>
          <td></td>
          <td></td>
        </tr>
        <tr class="gray">
          <td>MAXIMA GENERAUX</td>
          <td class="center">0</td>
          <td class="center">0</td>
          <td class="center">0</td>
          <td class="center">0</td>
          <td class="center">0</td>
          <td class="center">0</td>
          <td class="center">0</td>
          <td></td>
          <td></td>
        </tr>
      </tbody>
    </table>

    <div class="summary-row">
      <div class="summary-box">
        <div class="status-item"><strong>Total général</strong> <span id="totalGeneral">0</span></div>
        <div class="status-item"><strong>Pourcentage</strong> <span id="percentageGeneral">0%</span></div>
        <div class="status-item"><strong>Place / Nbre élèves</strong> <span style="width:auto; border:none;">__ / __</span></div>
      </div>
      <div class="status-box">
        <div class="status-item"><span></span> Passé</div>
        <div class="status-item"><span></span> Doublé</div>
        <div class="status-item"><span></span> Ajourné</div>
      </div>
      <div class="summary-box">
        <div class="status-item"><strong>APPLICATION</strong></div>
        <div class="status-item"><strong>CONDUITE</strong></div>
        <div class="status-item"><strong>SIGN. RESPONSABLE</strong></div>
      </div>
    </div>

    <div class="footer-notes">
      <p>(* ) L'élève ne pourra passer dans la classe supérieure s'il ne subit avec succès un examen de repêchage en ...</p>
      <p>(** ) L'élève ne passe dans la classe supérieure que si ...</p>
      <p>(***) L'élève est orienté vers ...</p>
    </div>

    <div class="footer-signatures">
      <div class="signature-block"><span class="label">Signature de l'élève</span><span class="line"></span></div>
      <div class="signature-block"><span class="label">Sceau de l'école</span><span class="line"></span></div>
      <div class="signature-block"><span class="label">Nom et signature du Chef d'établissement</span><span class="line"></span></div>
    </div>
  </div>
  <script>
    function updateTotals() {
      const rows = document.querySelectorAll('.main-table tbody tr:not(.gray)');
      let total = 0;
      rows.forEach(row => {
        const inputs = row.querySelectorAll('input[type=number]');
        let rowSum = 0;
        inputs.forEach((input, index) => {
          if (index === 2 || index === 5) return; // skip total span columns
          rowSum += Number(input.value) || 0;
        });
        const totalCells = row.querySelectorAll('.row-total');
        if (totalCells[0]) totalCells[0].textContent = (Number(row.querySelectorAll('input')[0].value) || 0) + (Number(row.querySelectorAll('input')[1].value) || 0);
        if (totalCells[1]) totalCells[1].textContent = (Number(row.querySelectorAll('input')[3].value) || 0) + (Number(row.querySelectorAll('input')[4].value) || 0);
      });
      const scoreInputs = document.querySelectorAll('.main-table tbody input[type=number]');
      scoreInputs.forEach(input => {
        total += Number(input.value) || 0;
      });
      document.getElementById('totalGeneral').textContent = total;
      document.getElementById('percentageGeneral').textContent = total ? Math.round(total / 5) + '%' : '0%';
    }
    document.querySelectorAll('.main-table input[type=number]').forEach(input => {
      input.addEventListener('input', updateTotals);
    });
    updateTotals();
  </script>
</body>
</html>

