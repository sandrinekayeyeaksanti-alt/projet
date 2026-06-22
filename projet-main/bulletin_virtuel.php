<?php
session_start();
require_once 'config.php';

// Vérification de la session
if (!isset($_SESSION['role'])) {
    header("Location: login.php");
    exit();
}

$bulletin_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$bulletin_id) {
    die("Bulletin non spécifié.");
}

// 1. Récupération des infos du bulletin
$stmt = $pdo->prepare("SELECT * FROM bulletins WHERE id = ?");
$stmt->execute([$bulletin_id]);
$bulletin = $stmt->fetch();

if (!$bulletin) {
    die("Bulletin introuvable.");
}

$eleve_id = $bulletin['eleve_id'];
$periode_bulletin = $bulletin['periode'];

// 2. Infos élève et sa classe
$stmt_e = $pdo->prepare("SELECT e.*, c.nom as classe_nom, c.niveau as classe_niveau, c.option_nom FROM eleves e LEFT JOIN classes c ON e.classe_id = c.id WHERE e.id = ?");
$stmt_e->execute([$eleve_id]);
$eleve = $stmt_e->fetch();

if (!$eleve) {
    die("Élève introuvable.");
}

$classe_id = $eleve['classe_id'];

// Vérification si l'utilisateur est le titulaire de cette classe
$is_titulaire = false;
if (isset($_SESSION['role']) && $_SESSION['role'] === 'enseignant') {
    $stmt_tit = $pdo->prepare("SELECT id FROM admins WHERE id = ? AND classe_id = ?");
    $stmt_tit->execute([$_SESSION['user_id'], $classe_id]);
    if ($stmt_tit->fetch()) {
        $is_titulaire = true;
    }
}

// 3. Récupération des cours de la classe
$stmt_c = $pdo->prepare("SELECT * FROM cours WHERE classe_id = ?");
$stmt_c->execute([$classe_id]);
$cours = $stmt_c->fetchAll();

$bulletin_rows = [
    ['label' => 'MAXIMA', 'type' => 'maxima', 'values' => [10, 10, 20, 10, 10, 20, 40]],
    ['label' => 'RELIGION', 'pattern' => 'religion', 'max' => [10, 10, 20, 10, 10, 20, 40]],
    ['label' => 'EDUC. CIV. & MORALE', 'pattern' => 'civ|morale', 'max' => [10, 10, 20, 10, 10, 20, 40]],
    ['label' => 'EDUCATION A LA VIE', 'pattern' => 'education.*vie|vie', 'max' => [10, 10, 20, 10, 10, 20, 40]],
    ['label' => 'ANGLAIS', 'pattern' => 'anglais', 'max' => [10, 10, 20, 10, 10, 20, 40]],
    ['label' => 'GEO/ACTUALITE', 'pattern' => 'geo|actualite|actualit', 'max' => [10, 10, 20, 10, 10, 20, 40]],
    ['label' => 'HISTOIRE', 'pattern' => 'histoire', 'max' => [10, 10, 20, 10, 10, 20, 40]],
    ['label' => 'CHIMIE', 'pattern' => 'chimie', 'max' => [40, 40, 80, 40, 40, 80, 160]],
    ['label' => 'PHYSIQUE', 'pattern' => 'physique', 'max' => [40, 40, 80, 40, 40, 80, 160]],
    ['label' => 'ELECTRICITE', 'pattern' => 'electricite|électricité', 'max' => [40, 40, 80, 40, 40, 80, 160]],
    ['label' => 'INFORMATIQUE', 'pattern' => 'informatique', 'max' => [40, 40, 80, 40, 40, 80, 160]],
    ['label' => 'METALLURGIE', 'pattern' => 'metallurgie|métallurgie', 'max' => [40, 40, 80, 40, 40, 80, 160]],
    ['label' => 'DESSIN INDUSTRIEL', 'pattern' => 'dessin', 'max' => [40, 40, 80, 40, 40, 80, 160]],
    ['label' => 'MECANIQUE GEN.', 'pattern' => 'mecanique', 'max' => [40, 40, 80, 40, 40, 80, 160]],
    ['label' => 'RESIST. MATERIEL', 'pattern' => 'resist|résist', 'max' => [40, 40, 80, 40, 40, 80, 160]],
    ['label' => 'TECHNO MECANIQUE', 'pattern' => 'techno', 'max' => [40, 40, 80, 40, 40, 80, 160]],
    ['label' => 'MAXIMA', 'type' => 'maxima', 'values' => [40, 40, 80, 40, 40, 80, 160]],
    ['label' => 'FRANCAIS', 'pattern' => 'francais|français', 'max' => [100, 100, 200, 100, 100, 200, 400]],
    ['label' => 'MATHEMATIQUE', 'pattern' => 'math', 'max' => [100, 100, 200, 100, 100, 200, 400]],
    ['label' => 'PRAT. PROFESSIONNEL', 'pattern' => 'prat|professionnel', 'max' => [100, 100, 200, 100, 100, 200, 400]],
    ['label' => 'MAXIMA GENERAUX', 'type' => 'maxima', 'values' => [100, 100, 200, 100, 100, 200, 400]],
    ['label' => 'TOTAUX', 'type' => 'summary'],
    ['label' => 'POURCENTAGE', 'type' => 'percentage'],
    ['label' => 'PLACE / NBRE D\'ELEVES', 'type' => 'place'],
    ['label' => 'APPLICATION', 'type' => 'application'],
    ['label' => 'CONDUITE', 'type' => 'conduite'],
    ['label' => 'SIGN. RESPONSABLE', 'type' => 'signature'],
];

$branch_course_ids = [];
foreach ($cours as $c) {
    $nom = mb_strtolower($c['nom']);
    foreach ($bulletin_rows as $row) {
        if (!empty($row['pattern']) && preg_match('/' . $row['pattern'] . '/iu', $nom)) {
            if (!isset($branch_course_ids[$row['label']])) {
                $branch_course_ids[$row['label']] = [];
            }
            $branch_course_ids[$row['label']][] = $c['id'];
            break;
        }
    }
}

function getNoteValue(PDO $pdo, int $eleve_id, array $course_ids, string $periode) {
    if (empty($course_ids)) {
        return null;
    }
    $placeholders = implode(',', array_fill(0, count($course_ids), '?'));
    $params = array_merge([$eleve_id], $course_ids, [$periode]);
    $stmt = $pdo->prepare("SELECT AVG(note) AS note FROM notes WHERE eleve_id = ? AND cours_id IN ($placeholders) AND periode = ?");
    $stmt->execute($params);
    $result = $stmt->fetch();
    return $result && $result['note'] !== null ? round((float)$result['note'], 2) : null;
}

function getAverageNoteValue(PDO $pdo, int $eleve_id, array $course_ids, array $periodes) {
    if (empty($course_ids)) {
        return null;
    }
    $course_placeholders = implode(',', array_fill(0, count($course_ids), '?'));
    $period_placeholders = implode(',', array_fill(0, count($periodes), '?'));
    $params = array_merge([$eleve_id], $course_ids, $periodes);
    $stmt = $pdo->prepare("SELECT AVG(note) AS note FROM notes WHERE eleve_id = ? AND cours_id IN ($course_placeholders) AND periode IN ($period_placeholders)");
    $stmt->execute($params);
    $result = $stmt->fetch();
    return $result && $result['note'] !== null ? round((float)$result['note'], 2) : null;
}

$grand_totals = [
    'max' => 0,
    'obt' => 0,
];

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Bulletin Officiel - <?= htmlspecialchars($eleve['nom'] . ' ' . $eleve['post_nom_prenom']) ?></title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?= time() ?>">
    <script src="https://unpkg.com/feather-icons"></script>
    <style>
        body { background: #f1f5f9; color: #0f172a; font-family: 'Inter', sans-serif; padding: 20px; }
        
        .bulletin-container {
            max-width: 950px;
            margin: 0 auto;
            background: #ffffff;
            border: 8px double #0b3d91;
            padding: 40px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            position: relative;
            border-radius: 4px;
        }
        
        .bulletin-container::before {
            content: "";
            position: absolute;
            top: 50%;
            left: 50%;
            width: 320px;
            height: 320px;
            background-image: url('logo.webp');
            background-repeat: no-repeat;
            background-position: center;
            background-size: contain;
            opacity: 0.06;
            transform: translate(-50%, -50%);
            pointer-events: none;
            z-index: 1;
        }

        .header-official {
            display: grid;
            grid-template-columns: 1fr 120px 1fr;
            align-items: center;
            border-bottom: 2px solid #0b3d91;
            padding-bottom: 15px;
            margin-bottom: 20px;
            position: relative;
        }
        .header-official .left, .header-official .right { font-size: 11px; line-height: 1.4; font-weight: bold; color: #1e293b; }
        .header-official .center { text-align: center; }
        .header-official .center img { width: 75px; height: 75px; object-fit: contain; }
        .header-official .right { text-align: right; }
        
        .title-doc {
            text-align: center;
            background: #0b3d91;
            color: white;
            padding: 10px;
            font-weight: bold;
            font-family: 'Outfit';
            font-size: 18px;
            letter-spacing: 1px;
            margin-bottom: 20px;
            text-transform: uppercase;
            border-radius: 4px;
        }
        
        .student-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            font-size: 13px;
            line-height: 1.6;
            background: #f8fafc;
            padding: 20px;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            margin-bottom: 25px;
        }
        .student-details strong { color: #0b3d91; }
        
        table.bulletin-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
            margin-bottom: 30px;
            z-index: 2;
            position: relative;
        }
        table.bulletin-table th, table.bulletin-table td {
            border: 1px solid #000;
            padding: 6px 8px;
        }
        table.bulletin-table th {
            background-color: #e8f0fe;
            color: #0b3d91;
            font-weight: bold;
            text-align: center;
            text-transform: uppercase;
        }
        table.bulletin-table td.branch-cat { background-color: #f1f5f9; font-weight: bold; text-transform: uppercase; color: #1e293b; }
        table.bulletin-table td.subtotal-row { background-color: #f8fafc; font-weight: bold; text-align: right; }
        
        /* Styles pour l'édition en direct */
        .note-edit-input {
            width: 100%;
            box-sizing: border-box;
            text-align: center;
            font-weight: bold;
            color: #0b3d91;
            border: 1px dashed transparent;
            background: transparent;
            padding: 2px;
            font-size: 12px;
            transition: all 0.2s;
        }
        .note-edit-input:hover, .note-edit-input:focus {
            border-color: #3b82f6;
            background: #eff6ff;
            outline: none;
        }
        /* Cache les flèches des inputs number */
        .note-edit-input::-webkit-outer-spin-button, .note-edit-input::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
        .note-edit-input[type=number] { -moz-appearance: textfield; }
        
        .editable-select {
            width: 100%;
            border: none;
            background: transparent;
            font-size: 13px;
            font-weight: bold;
            color: #0f172a;
            cursor: pointer;
            text-align: center;
            text-align-last: center;
        }
        .editable-select:hover { background: #eff6ff; }
        .editable-text {
            width: 100%;
            border: 1px dashed transparent;
            background: transparent;
            font-family: inherit;
            font-size: 12px;
            font-weight: 600;
            color: #b45309;
            padding: 2px;
            transition: all 0.2s;
        }
        .editable-text:hover, .editable-text:focus { border-color: #d97706; background: #fffbeb; outline: none; }
        
        .edit-mode-badge {
            background: #10b981;
            color: white;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: bold;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.4); }
            70% { box-shadow: 0 0 0 6px rgba(16, 185, 129, 0); }
            100% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
        }
        
        .saving-indicator {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: #0f172a;
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: bold;
            display: none;
            align-items: center;
            gap: 8px;
            z-index: 1000;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        .footer-signatures {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-top: 30px;
            font-size: 12px;
            border-top: 1px solid #cbd5e1;
            padding-top: 20px;
        }
        .signature-box { text-align: center; height: 120px; display: flex; flex-direction: column; justify-content: space-between; position: relative; }
        .signature-box .stamp { position: absolute; top: 25px; left: 50%; transform: translateX(-50%); width: 90px; opacity: 0.75; pointer-events: none; }
        
        .no-print-header { max-width: 950px; margin: 0 auto 20px; display: flex; justify-content: space-between; align-items: center; }
        .btn-print { background: #0b3d91; color: white; padding: 10px 20px; border-radius: 8px; font-weight: bold; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; border: none; cursor: pointer; transition: all 0.3s; }
        .btn-print:hover { background: #1e60d0; }

        @media print {
            body { background: transparent; padding: 0; }
            .no-print-header, .edit-mode-badge { display: none; }
            .bulletin-container { border: none; box-shadow: none; padding: 0; max-width: 100%; }
            .bulletin-container::before { opacity: 0.08; }
            table.bulletin-table th { background-color: #f1f5f9 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            table.bulletin-table td.branch-cat { background-color: #f8fafc !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .note-edit-input { border: none !important; background: transparent !important; }
            .editable-select { appearance: none; -webkit-appearance: none; -moz-appearance: none; }
            .editable-text { border: none !important; background: transparent !important; }
        }
    </style>
</head>
<body>

    <div class="saving-indicator" id="savingIndicator">
        <i data-feather="loader" style="animation: spin 1s linear infinite;"></i> Sauvegarde...
    </div>

    <div class="no-print-header">
        <a href="javascript:history.back()" class="btn-print" style="background:#64748b;"><i data-feather="arrow-left"></i> Retour</a>
        
        <?php if ($is_titulaire): ?>
            <div class="edit-mode-badge"><i data-feather="edit-2" style="width:14px;"></i> Mode Saisie Directe Activé</div>
        <?php endif; ?>

        <button onclick="window.print()" class="btn-print"><i data-feather="printer"></i> Imprimer le Bulletin (A4 / PDF)</button>
    </div>

    <div class="bulletin-container" id="bulletinData" data-bulletin-id="<?= $bulletin_id ?>" data-eleve-id="<?= $eleve_id ?>">
        
        <header class="header-official">
            <div class="left">
                REPUBLIQUE DEMOCRATIQUE DU CONGO<br>
                PROVINCE DU SUD-KIVU<br>
                VILLE : UVIRA<br>
                COMMUNE/TER. : UVIRA<br>
                ECOLE : BELLE VUE
            </div>
            <div class="center">
                <img src="assets/img/logo.webp" alt="Armoiries RDC / Logo">
            </div>
            <div class="right">
                MINISTERE DE L'ENSEIGNEMENT PRIMAIRE,<br>
                SECONDAIRE ET PROFESSIONNEL<br>
                N° ID : 63031-<?= str_pad($eleve['id'], 6, '0', STR_PAD_LEFT) ?><br>
                CODE : 1-630113
            </div>
        </header>

        <div class="title-doc">
            Bulletin de l'élève : <?= htmlspecialchars($periode_bulletin) ?>
        </div>

        <div class="student-details">
            <div>
                <p>Élève : <strong><?= htmlspecialchars($eleve['nom'] . ' ' . $eleve['post_nom_prenom']) ?></strong></p>
                <p>Sexe : <strong><?= htmlspecialchars($eleve['sexe']) ?></strong></p>
                <p>Lieu et date de naissance : <strong><?= htmlspecialchars($eleve['lieu_date_naissance'] ?: 'Uvira, RDC') ?></strong></p>
            </div>
            <div style="border-left: 1px solid #cbd5e1; padding-left: 20px;">
                <p>Classe : <strong><?= htmlspecialchars($eleve['classe_nom']) ?></strong></p>
                <p>Option : <strong><?= htmlspecialchars($eleve['option_nom']) ?></strong></p>
                <p>N° Permanent : <strong>7-63013601<?= str_pad($eleve['id'], 3, '0', STR_PAD_LEFT) ?></strong></p>
            </div>
        </div>

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
            <tbody>
                <?php
                $grand_total_obt = 0;
                $grand_total_max = 0;

                foreach ($bulletin_rows as $row):
                    if (isset($row['type']) && $row['type'] === 'maxima'):
                ?>
                    <tr>
                        <td class="branch-cat"><?= htmlspecialchars($row['label']) ?></td>
                        <?php foreach ($row['values'] as $value): ?>
                            <td style="text-align:center; font-weight:bold; background:#f8fafc; color:#0b3d91;"><?= $value ?></td>
                        <?php endforeach; ?>
                        <td></td>
                        <td></td>
                    </tr>
                <?php elseif (isset($row['type']) && $row['type'] === 'summary'):
                    $summary_percentage = $grand_total_max > 0 ? round(($grand_total_obt / $grand_total_max) * 100, 1) : 0;
                ?>
                    <tr style="font-weight:bold; background:#e2e8f0;">
                        <td><?= htmlspecialchars($row['label']) ?></td>
                        <td colspan="7" style="text-align:right; padding-right:12px;">TOTAL DES POINTS</td>
                        <td style="text-align:center; font-weight:bold; color:#0b3d91;"><?= $grand_total_obt ?></td>
                    </tr>
                <?php elseif (isset($row['type']) && $row['type'] === 'percentage'):
                    $final_pct_calc = $grand_total_max > 0 ? round(($grand_total_obt / $grand_total_max) * 100, 1) : 0;
                ?>
                    <tr style="font-weight:bold; background:#dbeafe;">
                        <td><?= htmlspecialchars($row['label']) ?></td>
                        <td colspan="7"></td>
                        <td style="text-align:center; color:<?= $final_pct_calc >= 50 ? 'green' : 'red' ?>;"><?= $final_pct_calc ?>%</td>
                    </tr>
                <?php elseif (isset($row['type']) && $row['type'] === 'place'): ?>
                    <tr style="font-weight:bold; background:#f1f5f9;">
                        <td><?= htmlspecialchars($row['label']) ?></td>
                        <td colspan="7"></td>
                        <td style="text-align:center;"><?= htmlspecialchars($bulletin['place'] . ' / ' . $bulletin['nb_eleves']) ?></td>
                    </tr>
                <?php elseif (isset($row['type']) && $row['type'] === 'application'): ?>
                    <tr style="font-weight:bold; background:#fff7ed;">
                        <td><?= htmlspecialchars($row['label']) ?></td>
                        <td colspan="7">&nbsp;</td>
                        <td style="text-align:center;">
                            <?php if ($is_titulaire): ?>
                                <select id="applicationSelect" class="editable-select">
                                    <option value="Élite" <?= $bulletin['application'] == 'Élite' ? 'selected' : '' ?>>Élite</option>
                                    <option value="T. Bonne" <?= $bulletin['application'] == 'T. Bonne' ? 'selected' : '' ?>>T. Bonne</option>
                                    <option value="Bonne" <?= $bulletin['application'] == 'Bonne' ? 'selected' : '' ?>>Bonne</option>
                                    <option value="Médiocre" <?= $bulletin['application'] == 'Médiocre' ? 'selected' : '' ?>>Médiocre</option>
                                </select>
                            <?php else: ?>
                                <?= htmlspecialchars($bulletin['application'] ?: '-') ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php elseif (isset($row['type']) && $row['type'] === 'conduite'): ?>
                    <tr style="font-weight:bold; background:#fff7ed;">
                        <td><?= htmlspecialchars($row['label']) ?></td>
                        <td colspan="7">&nbsp;</td>
                        <td style="text-align:center;">
                            <?php if ($is_titulaire): ?>
                                <select id="conduiteSelect" class="editable-select">
                                    <option value="TB" <?= $bulletin['conduite'] == 'TB' ? 'selected' : '' ?>>TB</option>
                                    <option value="B" <?= $bulletin['conduite'] == 'B' ? 'selected' : '' ?>>B</option>
                                    <option value="M" <?= $bulletin['conduite'] == 'M' ? 'selected' : '' ?>>M</option>
                                    <option value="A" <?= $bulletin['conduite'] == 'A' ? 'selected' : '' ?>>A</option>
                                </select>
                            <?php else: ?>
                                <?= htmlspecialchars($bulletin['conduite'] ?: '-') ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php elseif (isset($row['type']) && $row['type'] === 'signature'): ?>
                    <tr style="font-weight:bold; background:#f8fafc;">
                        <td><?= htmlspecialchars($row['label']) ?></td>
                        <td colspan="8">&nbsp;</td>
                    </tr>
                <?php else:
                    $course_ids = $branch_course_ids[$row['label']] ?? [];
                    $tr1 = getNoteValue($pdo, $eleve_id, $course_ids, '1ère Période');
                    $exam1 = getNoteValue($pdo, $eleve_id, $course_ids, 'Examen 1er Semestre');
                    $tot1 = $tr1 !== null || $exam1 !== null ? round((float)($tr1 ?? 0) + (float)($exam1 ?? 0), 2) : null;
                    $tr2 = getNoteValue($pdo, $eleve_id, $course_ids, '3ème Période');
                    $exam2 = getNoteValue($pdo, $eleve_id, $course_ids, 'Examen 2ème Semestre');
                    $tot2 = $tr2 !== null || $exam2 !== null ? round((float)($tr2 ?? 0) + (float)($exam2 ?? 0), 2) : null;
                    $repechage = getNoteValue($pdo, $eleve_id, $course_ids, 'Examen de Repechage');
                    $row_total_max = array_sum($row['max']);
                    $row_obt = ($tot1 ?? 0) + ($tot2 ?? 0) + ($repechage ?? 0);
                    $grand_total_max += $row_total_max;
                    $grand_total_obt += $row_obt;
                ?>
                    <tr class="course-row" data-course-id="<?= htmlspecialchars(implode(',', $course_ids)) ?>">
                        <td><?= htmlspecialchars($row['label']) ?></td>
                        <td style="text-align:center;"><?= $tr1 !== null ? '<strong>'.$tr1.'</strong>' : '<span style="color:#cbd5e1;">-</span>' ?></td>
                        <td style="text-align:center;"><?= $exam1 !== null ? '<strong>'.$exam1.'</strong>' : '<span style="color:#cbd5e1;">-</span>' ?></td>
                        <td style="text-align:center; font-weight:bold; color:#0b3d91;"><?= $tot1 !== null ? $tot1 : '-' ?></td>
                        <td style="text-align:center;"><?= $tr2 !== null ? '<strong>'.$tr2.'</strong>' : '<span style="color:#cbd5e1;">-</span>' ?></td>
                        <td style="text-align:center;"><?= $exam2 !== null ? '<strong>'.$exam2.'</strong>' : '<span style="color:#cbd5e1;">-</span>' ?></td>
                        <td style="text-align:center; font-weight:bold; color:#0b3d91;"><?= $tot2 !== null ? $tot2 : '-' ?></td>
                        <td style="text-align:center;"><?= $repechage !== null ? '<strong>'.$repechage.'</strong>' : '<span style="color:#cbd5e1;">-</span>' ?></td>
                        <td></td>
                    </tr>
                <?php endif; endforeach; ?>
            </tbody>
        </table>

        <!-- Pied de page officiel RDC et Signatures -->
        <div style="font-size:12px; border:1px solid #000; padding:15px; border-radius:6px; background:#fffbeb; border-left:5px solid #d97706; margin-bottom:30px;">
            <p style="margin: 0 0 5px; font-weight:bold; color:#b45309;"><i data-feather="info" style="vertical-align:middle; width:16px;"></i> DECISION DU JURY / TITULAIRE :</p>
            <div style="display:flex; gap:30px; margin-top:10px;">
                <label style="display:flex; align-items:center; gap:5px; font-weight:600;">
                    <input type="checkbox" id="checkPass" disabled <?= $final_pct >= 50 && strpos(strtolower($bulletin['decision']), 'échoué') === false ? 'checked' : '' ?>> L'Élève passe dans la classe supérieure
                </label>
                <label style="display:flex; align-items:center; gap:5px; font-weight:600;">
                    <input type="checkbox" id="checkFail" disabled <?= $final_pct < 50 || strpos(strtolower($bulletin['decision']), 'échoué') !== false ? 'checked' : '' ?>> L'Élève a échoué / double la classe
                </label>
            </div>
            <p style="margin: 15px 0 0; font-style:italic; font-weight:600; display: flex; align-items: center; gap: 5px;">
                Mention du Titulaire : 
                <?php if ($is_titulaire): ?>
                    "<input type="text" id="decisionInput" class="editable-text" value="<?= htmlspecialchars($bulletin['decision'] ?: '') ?>" placeholder="Saisir la décision finale..." style="width: 300px;">"
                <?php else: ?>
                    "<?= htmlspecialchars($bulletin['decision'] ?: '-') ?>"
                <?php endif; ?>
            </p>
        </div>

        <div class="footer-signatures">
            <div class="signature-box">
                <span style="font-weight:bold;">Le Parent / Responsable</span>
                <div style="margin-top:20px; font-style:italic; color:#64748b;">Signature du tuteur</div>
            </div>
            <div class="signature-box">
                <span style="font-weight:bold;">L'Enseignant Titulaire</span>
                <div style="margin-top:20px; font-family:'Outfit'; font-weight:bold; color:#0b3d91;">KM5</div>
                <div style="font-size:10px; color:#64748b;">Signé numériquement</div>
            </div>
            <div class="signature-box">
                <span style="font-weight:bold;">Le Chef d'Établissement</span>
                <div style="margin-top:20px; font-family:'Outfit'; font-weight:bold; color:#b91c1c; font-size:13px; text-transform:uppercase;">Fota Bin Munga Jean</div>
                <div style="font-size:10px; color:#64748b;">Signé & Scellé</div>
                <svg class="stamp" viewBox="0 0 100 100">
                    <circle cx="50" cy="50" r="45" fill="none" stroke="#b91c1c" stroke-width="2" stroke-dasharray="3,3" />
                    <circle cx="50" cy="50" r="40" fill="none" stroke="#b91c1c" stroke-width="1.5" />
                    <path id="stamp-curve" fill="none" d="M 15 50 A 35 35 0 0 1 85 50" />
                    <text font-size="6" fill="#b91c1c" font-weight="bold" letter-spacing="1">
                        <textPath href="#stamp-curve" startOffset="50%" text-anchor="middle">* ECOLE BELLE VUE *</textPath>
                    </text>
                    <path id="stamp-curve-bottom" fill="none" d="M 85 50 A 35 35 0 0 1 15 50" />
                    <text font-size="6" fill="#b91c1c" font-weight="bold" letter-spacing="1">
                        <textPath href="#stamp-curve-bottom" startOffset="50%" text-anchor="middle">* PROV. SUD-KIVU *</textPath>
                    </text>
                    <text x="50" y="52" font-size="8" font-family="'Outfit'" font-weight="bold" fill="#b91c1c" text-anchor="middle">SCEAU</text>
                    <text x="50" y="60" font-size="5" fill="#b91c1c" text-anchor="middle">OFFICIEL</text>
                </svg>
            </div>
        </div>

        <div style="text-align:center; font-size:10px; color:#94a3b8; margin-top:50px; border-top:1px dashed #cbd5e1; padding-top:10px;">
            Fait à UVIRA, le <?= date('d/m/Y') ?>. Ce bulletin numérique est certifié conforme et scellé électroniquement par l'École Belle Vue.
        </div>
    </div>

    <script>
        feather.replace();
        
        <?php if ($is_titulaire): ?>
        document.addEventListener('DOMContentLoaded', function() {
            const eleveId = document.getElementById('bulletinData').dataset.eleveId;
            const bulletinId = document.getElementById('bulletinData').dataset.bulletinId;
            const savingIndicator = document.getElementById('savingIndicator');
            
            let saveTimeout;
            
            function showSaving() {
                savingIndicator.style.display = 'flex';
                savingIndicator.innerHTML = '<i data-feather="loader" style="animation: spin 1s linear infinite;"></i> Sauvegarde...';
                feather.replace();
            }
            
            function showSaved() {
                savingIndicator.innerHTML = '<i data-feather="check"></i> Enregistré';
                feather.replace();
                setTimeout(() => { savingIndicator.style.display = 'none'; }, 2000);
            }
            
            function saveNoteAJAX(coursId, periode, note, noteMax) {
                showSaving();
                fetch('save_note_ajax.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        type: 'note',
                        eleve_id: eleveId,
                        cours_id: coursId,
                        periode: periode,
                        note: note,
                        note_max: noteMax
                    })
                }).then(res => res.json()).then(data => {
                    if (data.success) {
                        saveBulletinStatsAJAX(); // Trigger bulletin stats update after note saves
                    }
                });
            }
            
            function saveBulletinStatsAJAX() {
                const totalObt = parseFloat(document.getElementById('grandTotalObt').innerText) || 0;
                const pctText = document.getElementById('finalPct').innerText;
                const pct = parseFloat(pctText.replace('%', '')) || 0;
                const conduite = document.getElementById('conduiteSelect').value;
                const application = document.getElementById('applicationSelect').value;
                const decision = document.getElementById('decisionInput').value;
                
                fetch('save_note_ajax.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        type: 'bulletin',
                        bulletin_id: bulletinId,
                        points_obtenus: totalObt,
                        pourcentage: pct,
                        conduite: conduite,
                        application: application,
                        decision: decision
                    })
                }).then(res => res.json()).then(data => {
                    if (data.success) showSaved();
                });
            }

            // Recalculer tout le tableau
            function recalculateTable() {
                let grandTotalObt = 0;
                let grandTotalMax = 0;
                let colTotalsObt = {};
                let colTotalsMax = {};
                
                // Init colonnes
                document.querySelectorAll('.grand-col-max').forEach(el => {
                    colTotalsMax[el.dataset.periode] = 0;
                    colTotalsObt[el.dataset.periode] = 0;
                });
                
                // Parcourir chaque ligne de cours
                document.querySelectorAll('.course-row').forEach(row => {
                    let courseTotalObt = 0;
                    let courseTotalMax = 0;
                    
                    row.querySelectorAll('.note-edit-input').forEach(input => {
                        const val = input.value;
                        const max = parseInt(input.dataset.max);
                        const periode = input.dataset.periode;
                        
                        courseTotalMax += max;
                        colTotalsMax[periode] += max;
                        
                        if (val !== '') {
                            const note = parseFloat(val);
                            courseTotalObt += note;
                            colTotalsObt[periode] += note;
                        }
                    });
                    
                    row.querySelector('.course-total-max').innerText = courseTotalMax;
                    row.querySelector('.course-total-obt').innerText = courseTotalObt;
                    
                    grandTotalMax += courseTotalMax;
                    grandTotalObt += courseTotalObt;
                });
                
                // Mettre à jour sous-totaux de catégories (optionnel de recalculer précisemment si structure complexe, 
                // ici on fait simple on met à jour les totaux généraux et colonnes)
                
                document.querySelectorAll('.grand-col-max').forEach(el => {
                    el.innerText = colTotalsMax[el.dataset.periode];
                });
                document.querySelectorAll('.grand-col-obt').forEach(el => {
                    el.innerText = colTotalsObt[el.dataset.periode];
                });
                
                document.getElementById('grandTotalMax').innerText = grandTotalMax;
                document.getElementById('grandTotalObt').innerText = grandTotalObt;
                
                // Pourcentages
                document.querySelectorAll('.col-pct').forEach(el => {
                    const periode = el.dataset.periode;
                    const max = colTotalsMax[periode];
                    const obt = colTotalsObt[periode];
                    const pct = max > 0 ? (obt / max * 100).toFixed(1) : 0;
                    el.innerText = pct + '%';
                    el.style.color = pct >= 50 ? 'green' : 'red';
                });
                
                const finalPct = grandTotalMax > 0 ? (grandTotalObt / grandTotalMax * 100).toFixed(1) : 0;
                const pctEl = document.getElementById('finalPct');
                pctEl.innerText = finalPct + '%';
                pctEl.style.color = finalPct >= 50 ? 'green' : 'red';
                
                // Décision automatique
                const pass = document.getElementById('checkPass');
                const fail = document.getElementById('checkFail');
                if (finalPct >= 50) {
                    pass.checked = true; fail.checked = false;
                } else {
                    pass.checked = false; fail.checked = true;
                }
            }

            // Listeners sur inputs notes
            document.querySelectorAll('.note-edit-input').forEach(input => {
                // Validation pendant la frappe
                input.addEventListener('input', function() {
                    let max = parseFloat(this.dataset.max);
                    if (this.value !== '' && parseFloat(this.value) > max) {
                        this.value = max;
                    }
                    if (this.value !== '' && parseFloat(this.value) < 0) {
                        this.value = 0;
                    }
                    recalculateTable();
                });
                
                // Sauvegarde à la perte de focus
                input.addEventListener('blur', function() {
                    const coursId = this.closest('tr').dataset.coursId;
                    const periode = this.dataset.periode;
                    const max = this.dataset.max;
                    saveNoteAJAX(coursId, periode, this.value, max);
                });
            });
            
            // Listeners sur appréciations
            ['conduiteSelect', 'applicationSelect', 'decisionInput'].forEach(id => {
                const el = document.getElementById(id);
                if(el) {
                    el.addEventListener('change', saveBulletinStatsAJAX);
                    if(id === 'decisionInput') {
                        el.addEventListener('blur', saveBulletinStatsAJAX);
                    }
                }
            });
        });
        <?php endif; ?>
    </script>
</body>
</html>

