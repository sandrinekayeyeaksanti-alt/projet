<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'enseignant') {
    header("Location: login_enseignant.php");
    exit();
}

$prof_id = $_SESSION['user_id'];
$periode = isset($_GET['periode']) ? $_GET['periode'] : '1er Semestre';
$selected_student_id = isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0;

// Récupérer la classe du titulaire
$classe_titulaire = null;
if (isset($pdo)) {
    $stmt = $pdo->prepare("SELECT c.* FROM classes c JOIN admins a ON a.classe_id = c.id WHERE a.id = :id");
    $stmt->execute(['id' => $prof_id]);
    $classe_titulaire = $stmt->fetch();
}

if (!$classe_titulaire) {
    die("Accès refusé : vous n'êtes pas titulaire d'une classe.");
}

// Élèves de la classe
$eleves = [];
if (isset($pdo)) {
    $stmt = $pdo->prepare("SELECT * FROM eleves WHERE classe_id = ? AND statut_inscription = 'Validé' ORDER BY nom, post_nom_prenom");
    $stmt->execute([$classe_titulaire['id']]);
    $eleves = $stmt->fetchAll();
}

if (!$selected_student_id && count($eleves) > 0) {
    $selected_student_id = $eleves[0]['id'];
}

$eleve = null;
if ($selected_student_id && isset($pdo)) {
    $stmt = $pdo->prepare("SELECT e.*, c.nom as classe_nom, c.option_nom FROM eleves e LEFT JOIN classes c ON e.classe_id = c.id WHERE e.id = ?");
    $stmt->execute([$selected_student_id]);
    $eleve = $stmt->fetch();
}

$is_demo = ($selected_student_id >= 9000);

// Bulletin existant
$bulletin = null;
if ($eleve && isset($pdo)) {
    $stmt = $pdo->prepare("SELECT * FROM bulletins WHERE eleve_id = ? AND periode = ? LIMIT 1");
    $stmt->execute([$selected_student_id, $periode]);
    $bulletin = $stmt->fetch();
}

function get_note_value($pdo, $eleve_id, $cours_id, $periode_label) {
    $q = $pdo->prepare("SELECT note FROM notes WHERE eleve_id = ? AND cours_id = ? AND periode = ? LIMIT 1");
    $q->execute([$eleve_id, $cours_id, $periode_label]);
    $r = $q->fetch();
    return $r ? $r['note'] : '';
}

$get_default_max = function($periode_label) {
    if (stripos($periode_label, 'Examen') !== false) {
        return 40;
    }
    return 20;
};

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_bulletin'])) {
    $selected_student_id = isset($_POST['student_id']) ? (int)$_POST['student_id'] : $selected_student_id;
    $notes_post = $_POST['notes'] ?? [];
    $new_courses = $_POST['new_courses'] ?? [];

    foreach ($notes_post as $cours_id => $periods) {
        foreach ($periods as $periode_label => $val) {
            if ($val === '' || $val === null) {
                continue;
            }
            $note_val = (float)$val;
            $note_max = $get_default_max($periode_label);
            $q = $pdo->prepare("SELECT id FROM notes WHERE eleve_id = ? AND cours_id = ? AND periode = ? LIMIT 1");
            $q->execute([$selected_student_id, $cours_id, $periode_label]);
            $existing = $q->fetch();
            if ($existing) {
                $u = $pdo->prepare("UPDATE notes SET note = ?, note_max = ? WHERE id = ?");
                $u->execute([$note_val, $note_max, $existing['id']]);
            } else {
                $i = $pdo->prepare("INSERT INTO notes (eleve_id, cours_id, periode, note, note_max) VALUES (?, ?, ?, ?, ?)");
                $i->execute([$selected_student_id, $cours_id, $periode_label, $note_val, $note_max]);
            }
        }
    }

    foreach ($new_courses as $nc) {
        $name = trim($nc['name'] ?? '');
        if ($name === '') continue;
        $ins = $pdo->prepare("INSERT INTO cours (nom, classe_id) VALUES (?, ?)");
        $ins->execute([$name, $classe_titulaire['id']]);
        $new_id = $pdo->lastInsertId();
        $periods = $nc['notes'] ?? [];
        foreach ($periods as $pl => $v) {
            if ($v === '' || $v === null) continue;
            $note_val = (float)$v;
            $note_max = $get_default_max($pl);
            $ii = $pdo->prepare("INSERT INTO notes (eleve_id, cours_id, periode, note, note_max) VALUES (?, ?, ?, ?, ?)");
            $ii->execute([$selected_student_id, $new_id, $pl, $note_val, $note_max]);
        }
    }

    $period_names = ['1ère Période','Examen 1er Semestre','3ème Période','Examen 2ème Semestre','Examen de Repechage'];
    $placeholders = implode(',', array_fill(0, count($period_names), '?'));
    $params = array_merge([$selected_student_id], $period_names);
    $stmtt = $pdo->prepare("SELECT SUM(note) as total_obt, SUM(note_max) as total_max FROM notes WHERE eleve_id = ? AND periode IN ($placeholders)");
    $stmtt->execute($params);
    $res = $stmtt->fetch();
    $total_obt = $res['total_obt'] ?? 0;
    $total_max = $res['total_max'] ?? 0;
    $pct = $total_max > 0 ? round(($total_obt / $total_max) * 100, 2) : 0;

    if ($bulletin) {
        $up = $pdo->prepare("UPDATE bulletins SET points_obtenus = ?, points_max = ?, pourcentage = ?, application = ?, conduite = ?, decision = ? WHERE id = ?");
        $up->execute([$total_obt, $total_max, $pct, $_POST['application'] ?? '', $_POST['conduite'] ?? '', $_POST['decision'] ?? '', $bulletin['id']]);
    } else {
        $insb = $pdo->prepare("INSERT INTO bulletins (eleve_id, periode, points_obtenus, points_max, pourcentage, application, conduite, decision) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $insb->execute([$selected_student_id, $periode, $total_obt, $total_max, $pct, $_POST['application'] ?? '', $_POST['conduite'] ?? '', $_POST['decision'] ?? '']);
    }

    header('Location: bulletin_enseignant.php?student_id=' . $selected_student_id . '&periode=' . urlencode($periode));
    exit();
}

if (!$eleve) {
    die('Élève introuvable.');
}

$place = $bulletin['place'] ?? '-';
$nb_eleves = $bulletin['nb_eleves'] ?? '-';
$pourcentage = isset($bulletin['pourcentage']) ? $bulletin['pourcentage'] . '%' : 'N/A';
$application = $bulletin['application'] ?? 'Bonne';
$conduite = $bulletin['conduite'] ?? 'TB';
$decision = $bulletin['decision'] ?? 'Bonne progression';

$courses = [];
if (isset($pdo) && $eleve && isset($eleve['classe_id'])) {
    $s = $pdo->prepare("SELECT * FROM cours WHERE classe_id = ? ORDER BY nom");
    $s->execute([$eleve['classe_id']]);
    $courses = $s->fetchAll();
}

$branches_fixes = [
    'RELIGION','EDUC. CIV. & MORALE','EDUCATION A LA VIE','ANGLAIS',
    'GEO/ACTUALITE','HISTOIRE','CHIMIE','PHYSIQUE','ELECTRICITE',
    'INFORMATIQUE','METALLURGIE','DESSIN INDUSTRIEL','MECANIQUE GEN.',
    'RESIST. MATERIEL','TECHNO MECANIQUE','FRANCAIS','MATHEMATIQUE',
    'PRAT. PROFESSIONNEL','MAXIMA GENERAUX'
];

if (empty($courses)) {
    foreach ($branches_fixes as $index => $name) {
        $courses[] = ['id' => 10000 + $index, 'nom' => $name, 'classe_id' => $eleve['classe_id']];
    }
}

$periods = ['1ère Période','Examen 1er Semestre','3ème Période','Examen 2ème Semestre','Examen de Repechage'];
?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Bulletin - <?= htmlspecialchars($eleve['nom'] . ' ' . $eleve['post_nom_prenom']) ?></title>
  <link rel="stylesheet" href="assets/css/style.css?v=<?= time() ?>">
  <style>
    body { background:#f5f7fb; margin:0; padding:20px; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
    .page-shell { max-width:1200px; margin:0 auto; }
    .student-selector { background:#fff; border:1px solid #cbd5e1; border-radius:12px; padding:16px; margin-bottom:18px; display:flex; gap:14px; align-items:center; }
    .student-selector label { font-weight:700; color:#0b3d91; }
    .student-selector select { flex:1; min-width:260px; padding:10px 12px; border-radius:10px; border:1px solid #cbd5e1; }
    .bulletin-wrapper { background:#fff; border-radius:14px; padding:24px; box-shadow:0 18px 50px rgba(15,23,42,0.08); }
    .bulletin-table { width:100%; border-collapse:collapse; margin-top:16px; }
    .bulletin-table th, .bulletin-table td { border:1px solid #0b3d91; padding:10px 8px; font-size:13px; }
    .bulletin-table th { background:#f8f9fc; color:#0b3d91; font-weight:700; }
    .branch-cat { background:#fefce8; font-weight:700; }
    .bulletin-input { width:100%; border:1px solid #cbd5e1; border-radius:8px; padding:6px 8px; font-size:13px; text-align:center; }
    .footer-signatures { margin-top:22px; display:flex; gap:18px; justify-content:space-between; }
    .signature-box { background:#f8fafc; border:1px solid #cbd5e1; border-radius:12px; padding:18px 14px; flex:1; min-height:110px; }
    .action-row { margin-top:14px; display:flex; gap:10px; flex-wrap:wrap; align-items:center; }
    .action-row input { min-width:180px; }
    .btn-save { margin-left:auto; background:#0b3d91; border:none; color:#fff; border-radius:12px; padding:12px 20px; cursor:pointer; font-weight:700; }
    .btn-save:hover { background:#102d6f; }
  </style>
</head>
<body>
  <div class="page-shell">
    <div class="student-selector">
      <label for="student_selector">Sélectionner un élève :</label>
      <select id="student_selector" onchange="changeStudent()">
        <?php foreach ($eleves as $e): ?>
          <option value="<?= $e['id'] ?>" <?= $e['id'] === $selected_student_id ? 'selected' : '' ?>>
            <?= htmlspecialchars($e['nom'] . ' ' . $e['post_nom_prenom']) ?>
          </option>
        <?php endforeach; ?>
      </select>
      <label for="periode_select">Période :</label>
      <select id="periode_select" onchange="changePeriode()">
        <option value="1er Semestre" <?= $periode === '1er Semestre' ? 'selected' : '' ?>>1er Semestre</option>
        <option value="2ème Semestre" <?= $periode === '2ème Semestre' ? 'selected' : '' ?>>2ème Semestre</option>
        <option value="Annuel" <?= $periode === 'Annuel' ? 'selected' : '' ?>>Annuel</option>
      </select>
    </div>

    <div class="bulletin-wrapper">
      <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:20px; margin-bottom:16px;">
        <div>
          <div style="font-weight:700; font-size:18px; color:#0b3d91;">ECOLE BELLE VUE</div>
          <div style="font-size:13px; color:#64748b; margin-top:4px;">Classe : <?= htmlspecialchars($eleve['classe_nom'] ?? '') ?> - Option : <?= htmlspecialchars($eleve['option_nom'] ?? '') ?></div>
        </div>
        <div style="text-align:right;">
          <div style="font-weight:700;">Nom : <?= htmlspecialchars($eleve['nom'] . ' ' . $eleve['post_nom_prenom']) ?></div>
          <div style="font-size:13px; color:#64748b; margin-top:4px;">N° Permanent : <?= '7-63013601' . str_pad($eleve['id'], 3, '0', STR_PAD_LEFT) ?></div>
        </div>
      </div>

      <form method="POST" id="bulletinEditForm">
        <input type="hidden" name="save_bulletin" value="1">
        <input type="hidden" name="student_id" value="<?= $selected_student_id ?>">

        <table class="bulletin-table" id="gradesTable">
          <thead>
            <tr>
              <th rowspan="2">BRANCHES</th>
              <th colspan="3">PREMIER SEMESTRE</th>
              <th colspan="3">SECOND SEMESTRE</th>
              <th rowspan="2">EXAMEN DE REPECHAGE</th>
              <th rowspan="2">SIG. PROF.</th>
            </tr>
            <tr>
              <th>TR. JOURNAL</th>
              <th>EXAM</th>
              <th>TOT.</th>
              <th>TR. JOURNAL</th>
              <th>EXAM</th>
              <th>TOT.</th>
            </tr>
          </thead>
          <tbody id="gradesBody">
            <tr>
              <td class="branch-cat">MAXIMA</td>
              <td style="text-align:center; font-weight:bold;">10</td>
              <td style="text-align:center; font-weight:bold;">10</td>
              <td style="text-align:center; font-weight:bold;">20</td>
              <td style="text-align:center; font-weight:bold;">10</td>
              <td style="text-align:center; font-weight:bold;">10</td>
              <td style="text-align:center; font-weight:bold;">20</td>
              <td style="text-align:center; font-weight:bold;">40</td>
              <td></td>
            </tr>
            <?php foreach ($courses as $idx => $c):
              $v1 = isset($c['id']) ? get_note_value($pdo, $selected_student_id, $c['id'], $periods[0]) : '';
              $e1 = isset($c['id']) ? get_note_value($pdo, $selected_student_id, $c['id'], $periods[1]) : '';
              $v2 = isset($c['id']) ? get_note_value($pdo, $selected_student_id, $c['id'], $periods[2]) : '';
              $e2 = isset($c['id']) ? get_note_value($pdo, $selected_student_id, $c['id'], $periods[3]) : '';
              $rep = isset($c['id']) ? get_note_value($pdo, $selected_student_id, $c['id'], $periods[4]) : '';
              $tot1 = ($v1 !== '' && $e1 !== '') ? round((float)$v1 + (float)$e1, 1) : '&nbsp;';
              $tot2 = ($v2 !== '' && $e2 !== '') ? round((float)$v2 + (float)$e2, 1) : '&nbsp;';
            ?>
              <tr class="course-row" data-cours-id="<?= htmlspecialchars($c['id']) ?>">
                <td><?= htmlspecialchars($c['nom']) ?></td>
                <td style="text-align:center;"><input type="number" step="0.5" min="0" name="notes[<?= htmlspecialchars($c['id']) ?>][<?= $periods[0] ?>]" value="<?= htmlspecialchars($v1) ?>" class="bulletin-input"></td>
                <td style="text-align:center;"><input type="number" step="0.5" min="0" name="notes[<?= htmlspecialchars($c['id']) ?>][<?= $periods[1] ?>]" value="<?= htmlspecialchars($e1) ?>" class="bulletin-input"></td>
                <td style="text-align:center; font-weight:bold; color:#0b3d91;"><?= $tot1 ?></td>
                <td style="text-align:center;"><input type="number" step="0.5" min="0" name="notes[<?= htmlspecialchars($c['id']) ?>][<?= $periods[2] ?>]" value="<?= htmlspecialchars($v2) ?>" class="bulletin-input"></td>
                <td style="text-align:center;"><input type="number" step="0.5" min="0" name="notes[<?= htmlspecialchars($c['id']) ?>][<?= $periods[3] ?>]" value="<?= htmlspecialchars($e2) ?>" class="bulletin-input"></td>
                <td style="text-align:center; font-weight:bold; color:#0b3d91;"><?= $tot2 ?></td>
                <td style="text-align:center;"><input type="number" step="0.5" min="0" name="notes[<?= htmlspecialchars($c['id']) ?>][<?= $periods[4] ?>]" value="<?= htmlspecialchars($rep) ?>" class="bulletin-input"></td>
                <td></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>

        <div class="action-row">
          <button type="button" onclick="addCustomCourse()" class="btn-save" style="background:#fff;color:#0b3d91;border:1px solid #0b3d91;">Ajouter matière</button>
          <input type="text" name="application" placeholder="Application" value="<?= htmlspecialchars($application) ?>" class="bulletin-input">
          <input type="text" name="conduite" placeholder="Conduite" value="<?= htmlspecialchars($conduite) ?>" class="bulletin-input">
          <input type="text" name="decision" placeholder="Mention du titulaire" value="<?= htmlspecialchars($decision) ?>" class="bulletin-input">
          <button type="submit" class="btn-save">Enregistrer</button>
        </div>
      </form>

      <table class="bulletin-table" style="margin-top:18px;">
        <tbody>
          <tr style="background:#e8f0fe; font-weight:bold;">
            <td style="text-transform:uppercase;">MAXIMA GENERAL</td>
            <td style="text-align:center;">20</td>
            <td style="text-align:center;">20</td>
            <td style="text-align:center;">40</td>
            <td style="text-align:center;">20</td>
            <td style="text-align:center;">20</td>
            <td style="text-align:center;">40</td>
            <td style="text-align:center;">40</td>
            <td style="text-align:center;">82</td>
            <td></td>
          </tr>
          <tr style="font-weight:bold;">
            <td>POURCENTAGE</td>
            <td colspan="6" style="text-align:center;">&nbsp;</td>
            <td colspan="2" style="text-align:center; background:#dbeafe; color:green; font-weight:700;"><?= htmlspecialchars($pourcentage) ?></td>
          </tr>
          <tr style="font-weight:bold;">
            <td>PLACE / NBRE D'ELEVES</td>
            <td colspan="6" style="text-align:center; color:#94a3b8; font-style:italic;">-</td>
            <td colspan="2" style="text-align:center; background:#f1f5f9; color:#0b3d91;"><?= htmlspecialchars($place) ?> / <?= htmlspecialchars($nb_eleves) ?></td>
          </tr>
          <tr style="font-weight:bold;">
            <td>APPLICATION</td>
            <td colspan="6" style="text-align:center; color:#64748b;">ÉVALUATION CONTINUE</td>
            <td colspan="2" style="text-align:center; background:#f1f5f9;"><?= htmlspecialchars($application) ?></td>
          </tr>
          <tr style="font-weight:bold;">
            <td>CONDUITE</td>
            <td colspan="6" style="text-align:center; color:#64748b;">COMPORTEMENT DISCIPLINAIRE</td>
            <td colspan="2" style="text-align:center; background:#f1f5f9;"><?= htmlspecialchars($conduite) ?></td>
          </tr>
          <tr style="font-weight:bold; background:#f8fafc;">
            <td>SIGN. RESPONSABLE</td>
            <td colspan="8">&nbsp;</td>
          </tr>
        </tbody>
      </table>

      <div style="margin-top:18px;">
        <div style="font-weight:700; color:#b45309;">DECISION DU JURY / TITULAIRE :</div>
        <div style="display:flex; gap:18px; margin-top:10px;">
          <label style="display:flex; align-items:center; gap:8px;"><input type="checkbox" <?= (isset($bulletin['pourcentage']) && $bulletin['pourcentage'] >= 50) ? 'checked' : '' ?> disabled> L'élève passe</label>
          <label style="display:flex; align-items:center; gap:8px;"><input type="checkbox" <?= (isset($bulletin['pourcentage']) && $bulletin['pourcentage'] < 50) ? 'checked' : '' ?> disabled> L'élève a échoué</label>
        </div>
        <div style="margin-top:10px; font-style:italic;">Mention du Titulaire : "<?= htmlspecialchars($decision) ?>"</div>
      </div>

      <div class="footer-signatures">
        <div class="signature-box">
          <span style="font-weight:bold;">Le Parent / Responsable</span>
          <div style="margin-top:18px; font-style:italic; color:#64748b;">Signature</div>
        </div>
        <div class="signature-box">
          <span style="font-weight:bold;">L'Enseignant Titulaire</span>
          <div style="margin-top:18px; font-weight:bold; color:#0b3d91;">KM5</div>
          <div style="font-size:10px; color:#64748b;">Signé numériquement</div>
        </div>
        <div class="signature-box">
          <span style="font-weight:bold;">Le Chef d'Établissement</span>
          <div style="margin-top:18px; font-weight:bold; color:#b91c1c;">Fota Bin Munga Jean</div>
          <div style="font-size:10px; color:#64748b;">Signé & Scellé</div>
        </div>
      </div>
    </div>
  </div>

  <script>
    function changeStudent() {
      const studentId = document.getElementById('student_selector').value;
      const periode = document.getElementById('periode_select').value;
      if (studentId) {
        window.location.href = 'bulletin_enseignant.php?student_id=' + encodeURIComponent(studentId) + '&periode=' + encodeURIComponent(periode);
      }
    }

    function changePeriode() {
      const studentId = document.getElementById('student_selector').value;
      const periode = document.getElementById('periode_select').value;
      if (studentId) {
        window.location.href = 'bulletin_enseignant.php?student_id=' + encodeURIComponent(studentId) + '&periode=' + encodeURIComponent(periode);
      }
    }

    function addCustomCourse() {
      const tbody = document.getElementById('gradesBody');
      const tr = document.createElement('tr');
      tr.className = 'course-row';
      tr.innerHTML = `
        <td><input type="text" name="new_courses[][name]" placeholder="Nouvelle matière" style="width:100%; padding:6px 8px; border:1px solid #cbd5e1; border-radius:8px;"></td>
        <td style="text-align:center;"><input type="number" step="0.5" min="0" name="new_courses[][notes][1ère Période]" class="bulletin-input"></td>
        <td style="text-align:center;"><input type="number" step="0.5" min="0" name="new_courses[][notes][Examen 1er Semestre]" class="bulletin-input"></td>
        <td style="text-align:center; font-weight:bold; color:#0b3d91;">&nbsp;</td>
        <td style="text-align:center;"><input type="number" step="0.5" min="0" name="new_courses[][notes][3ème Période]" class="bulletin-input"></td>
        <td style="text-align:center;"><input type="number" step="0.5" min="0" name="new_courses[][notes][Examen 2ème Semestre]" class="bulletin-input"></td>
        <td style="text-align:center; font-weight:bold; color:#0b3d91;">&nbsp;</td>
        <td style="text-align:center;"><input type="number" step="0.5" min="0" name="new_courses[][notes][Examen de Repechage]" class="bulletin-input"></td>
        <td></td>
      `;
      tbody.appendChild(tr);
    }
  </script>
</body>
</html>

