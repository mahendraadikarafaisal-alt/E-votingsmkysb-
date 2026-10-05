<?php
declare(strict_types=1);

$dbDir = __DIR__ . DIRECTORY_SEPARATOR . 'data';
$dbFile = $dbDir . DIRECTORY_SEPARATOR . 'adhiprama.sqlite';
if (!is_dir($dbDir)) {
    mkdir($dbDir, 0775, true);
}

try {
    $db = new PDO('sqlite:' . $dbFile);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec("
        CREATE TABLE IF NOT EXISTS votes (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            created_at TEXT NOT NULL,
            ketua_id TEXT NOT NULL,
            wakil_id TEXT NOT NULL
        );
        CREATE TABLE IF NOT EXISTS voter_profiles (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            created_at TEXT NOT NULL,
            name TEXT NOT NULL,
            type TEXT NOT NULL,
            class_major TEXT
        );
    ");
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>'Database SQLite tidak tersedia: '.$e->getMessage()]);
    exit;
}


if (isset($_GET['action']) && $_GET['action'] !== '') {
    header('Content-Type: application/json; charset=utf-8');
$action = $_GET['action'] ?? '';
$input = json_decode(file_get_contents('php://input'), true) ?: [];

function respond(array $data, int $code=200): never {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function requireAdmin(array $input): void {
    // Ganti PIN ini sebelum digunakan resmi.
    if (($input['pin'] ?? '') !== '1234') {
        respond(['ok'=>false,'error'=>'PIN admin salah.'], 403);
    }
}

try {
    switch ($action) {
        case 'get':
            $votes = $GLOBALS['db']->query(
                "SELECT created_at AS timestamp, ketua_id AS ketuaId, wakil_id AS wakilId FROM votes ORDER BY id ASC"
            )->fetchAll(PDO::FETCH_ASSOC);

            $profiles = $GLOBALS['db']->query(
                "SELECT name, type, class_major AS classMajor FROM voter_profiles ORDER BY id ASC"
            )->fetchAll(PDO::FETCH_ASSOC);

            respond(['ok'=>true,'votes'=>$votes,'profiles'=>$profiles]);

        case 'save':
            $name = trim((string)($input['profile']['name'] ?? ''));
            $type = trim((string)($input['profile']['type'] ?? ''));
            $classMajor = trim((string)($input['profile']['classMajor'] ?? ''));
            $ketuaId = trim((string)($input['ketuaId'] ?? ''));
            $wakilId = trim((string)($input['wakilId'] ?? ''));

            if ($name === '' || $type === '' || $ketuaId === '' || $wakilId === '') {
                respond(['ok'=>false,'error'=>'Data suara belum lengkap.'], 400);
            }

            $db->beginTransaction();
            $stmt = $db->prepare("INSERT INTO votes(created_at,ketua_id,wakil_id) VALUES(?,?,?)");
            $stmt->execute([gmdate('c'), $ketuaId, $wakilId]);

            $stmt = $db->prepare("INSERT INTO voter_profiles(created_at,name,type,class_major) VALUES(?,?,?,?)");
            $stmt->execute([gmdate('c'), $name, $type, $classMajor ?: null]);
            $db->commit();

            respond(['ok'=>true]);

        case 'reset':
            requireAdmin($input);
            $db->beginTransaction();
            $db->exec("DELETE FROM votes");
            $db->exec("DELETE FROM voter_profiles");
            $db->commit();
            respond(['ok'=>true]);

        case 'export':
            requireAdmin(['pin'=>($_GET['pin'] ?? ($input['pin'] ?? ''))]);
            $votes = $db->query("SELECT ketua_id, wakil_id FROM votes ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
            $counts = ['ketua'=>[], 'wakil'=>[]];
            foreach ($votes as $v) {
                $counts['ketua'][$v['ketua_id']] = ($counts['ketua'][$v['ketua_id']] ?? 0) + 1;
                $counts['wakil'][$v['wakil_id']] = ($counts['wakil'][$v['wakil_id']] ?? 0) + 1;
            }
            header_remove('Content-Type');
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="Hasil_Keseluruhan_ADHIPRAMA.csv"');
            $out = fopen('php://output','w');
            fputcsv($out,['Posisi','ID Kandidat','Suara','Persentase']);
            $total = count($votes);
            foreach ($counts['ketua'] as $id=>$n) fputcsv($out,['Ketua',$id,$n,$total ? round($n/$total*100).'%' : '0%']);
            foreach ($counts['wakil'] as $id=>$n) fputcsv($out,['Wakil',$id,$n,$total ? round($n/$total*100).'%' : '0%']);
            fclose($out);
            exit;

        default:
            respond(['ok'=>false,'error'=>'Aksi API tidak dikenal.'], 404);
    }
} catch (Throwable $e) {
    if ($db->inTransaction()) $db->rollBack();
    respond(['ok'=>false,'error'=>'Terjadi kesalahan server: '.$e->getMessage()], 500);
}

}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>E-Voting OSIS ADHIPRAMA 2027–2028</title>
    <style>
:root{
    --mahogany:#4a0e17; --mahogany-2:#61141f; --mahogany-3:#7a1c28;
    --gold:#d4af37; --gold-light:#f3e5ab; --cream:#fdfbf7; --ink:#2d1517;
    --white:#fffdf8; --green:#2f6b43; --red:#8b2531;
}
*{box-sizing:border-box}
html,body{margin:0;min-height:100%;font-family:Inter,Segoe UI,Arial,sans-serif;color:var(--ink)}
body{background:linear-gradient(rgba(253,251,247,.91),rgba(253,251,247,.94)),url('../assets/background-adat.jpg') center/cover fixed;background-attachment:fixed}
body:before{content:"";position:fixed;inset:0;pointer-events:none;background:radial-gradient(circle at 20% 20%,rgba(212,175,55,.08),transparent 25%),radial-gradient(circle at 80% 80%,rgba(74,14,23,.08),transparent 28%);z-index:-1}
.topbar{position:sticky;top:0;z-index:20;background:linear-gradient(135deg,var(--mahogany),var(--mahogany-3));border-bottom:4px solid var(--gold);box-shadow:0 8px 24px #4a0e1728}
.brand{max-width:1180px;margin:auto;padding:10px 18px;display:flex;align-items:center;gap:13px;color:white}.brand img{width:54px;height:54px;border-radius:50%;object-fit:cover;border:2px solid var(--gold)}.brand-title{font-family:Georgia,serif;font-weight:800;letter-spacing:2px;color:var(--gold-light);font-size:19px}.brand-subtitle{font-family:Georgia,serif;font-style:italic;font-size:12px;color:#f4ddaa;margin-top:3px}.motto-bar{background:var(--mahogany-2);color:#f9edcf;text-align:center;padding:9px;border-bottom:1px solid #d4af3740;font-family:Georgia,serif;font-size:13px}.motto-bar span{color:var(--gold);font-weight:800;letter-spacing:2px}
.container{width:min(1180px,calc(100% - 28px));margin:auto;padding:30px 0 60px}.view{display:none}.view.active{display:block}.hidden{display:none!important}
.hero-card{max-width:850px;margin:8px auto 22px;text-align:center;background:linear-gradient(145deg,#4a0e17f5,#61141feb);color:white;border:2px solid var(--gold);border-radius:26px;padding:30px 22px;box-shadow:0 18px 50px #4a0e1730}.hero-logo img{width:150px;height:150px;object-fit:cover;border-radius:50%;border:4px solid var(--gold);box-shadow:0 0 0 8px #d4af3720}.eyebrow{font-size:11px;letter-spacing:2.5px;font-weight:800;color:var(--gold);text-transform:uppercase}.hero-card h1{font-family:Georgia,serif;font-size:48px;letter-spacing:8px;margin:10px 0;color:var(--gold-light)}.slogan{max-width:700px;margin:0 auto;line-height:1.7;color:#f9edcf}.ornament{color:var(--gold);letter-spacing:9px;margin-top:18px}
.panel{background:#fffdf9ee;border:1px solid #d4af3760;border-radius:18px;box-shadow:0 10px 30px #4a0e1717}.login-panel{max-width:560px;margin:auto;padding:26px}.panel h2{font-family:Georgia,serif;color:var(--mahogany);margin:0 0 6px}.muted{color:#74696a;font-size:13px;line-height:1.6}.login-panel form{display:grid;gap:15px;margin-top:18px}.login-panel label{font-size:12px;font-weight:800;color:var(--mahogany);text-transform:uppercase;letter-spacing:.8px}.login-panel input,.login-panel select,.modal-box input{display:block;width:100%;margin-top:6px;border:1px solid #cbbfba;border-radius:9px;padding:12px 13px;background:white;outline:none;font-size:14px}.login-panel input:focus,.login-panel select:focus,.modal-box input:focus{border-color:var(--gold);box-shadow:0 0 0 3px #d4af3725}
.btn{border:0;border-radius:9px;padding:12px 17px;font-weight:800;font-size:12px;letter-spacing:.4px;cursor:pointer;transition:.2s}.btn:hover{transform:translateY(-1px);filter:brightness(1.05)}.btn.primary{background:linear-gradient(135deg,var(--mahogany),var(--mahogany-3));color:var(--gold-light);border:1px solid #d4af3780}.btn.gold{background:linear-gradient(135deg,var(--gold-light),var(--gold));color:var(--mahogany)}.btn.outline{background:white;color:var(--mahogany);border:1px solid #8b5960}.btn.danger{background:#8b2531;color:white}.btn:disabled{opacity:.45;cursor:not-allowed;transform:none}
.voter-status{display:flex;justify-content:space-between;align-items:center;gap:15px;padding:15px 18px;margin-bottom:18px}.voter-status small,.vote-action small,.stat-card small,.winner-label,.result-row small{display:block;font-size:10px;letter-spacing:1.2px;color:#786b6b;font-weight:800}.voter-status strong{display:block;color:var(--mahogany);margin-top:4px}.pill{padding:8px 12px;border-radius:999px;background:#f3e5ab66;color:var(--mahogany);border:1px solid #d4af3766;font-size:10px;font-weight:800}
.tabs{display:flex;gap:8px;margin:18px 0 0}.tab{flex:1;padding:13px;border:1px solid #b9899070;background:#f4eeee;color:var(--mahogany);font-weight:800;border-radius:12px 12px 0 0;cursor:pointer}.tab.active{background:linear-gradient(135deg,var(--mahogany),var(--mahogany-3));color:var(--gold-light);border-color:var(--gold)}.vote-content{padding:18px 0}.section-heading{display:flex;align-items:center;gap:14px;margin-bottom:15px}.section-heading>span{width:48px;height:48px;display:grid;place-items:center;border-radius:50%;background:var(--mahogany);color:var(--gold);font-family:Georgia,serif;font-weight:900}.section-heading h2{margin:0;font-family:Georgia,serif;color:var(--mahogany)}.section-heading p{margin:4px 0;color:#74696a;font-size:12px}.candidate-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:16px}.candidate-grid.three{grid-template-columns:repeat(3,1fr)}.candidate-card{background:var(--white);border:2px solid #d4af3760;border-radius:16px;overflow:hidden;box-shadow:0 8px 25px #4a0e1718;display:flex;flex-direction:column;transition:.2s}.candidate-card:hover{transform:translateY(-3px);border-color:var(--gold)}.candidate-card.selected{border-color:var(--gold);box-shadow:0 0 0 4px #d4af3730,0 12px 28px #4a0e1730}.photo-wrap{height:230px;background:#f5ead5;position:relative;display:flex;align-items:center;justify-content:center;overflow:hidden}.photo-wrap img{width:100%;height:100%;object-fit:cover}.number-badge{position:absolute;top:10px;left:10px;background:linear-gradient(135deg,var(--mahogany),var(--mahogany-3));color:var(--gold);border:1px solid var(--gold);padding:6px 10px;border-radius:999px;font-size:11px;font-weight:900}.candidate-body{padding:15px;flex:1}.candidate-body h3{font-family:Georgia,serif;color:var(--mahogany);margin:0 0 5px;font-size:18px}.candidate-meta{font-size:11px;color:#756b6b}.motto{font-family:Georgia,serif;font-style:italic;color:#8b6a2d;font-size:11px;margin:9px 0;line-height:1.45}.candidate-body .visi{font-size:11px;line-height:1.55;color:#554a4a}.candidate-actions{padding:0 15px 15px;display:grid;gap:8px}.detail-btn,.select-btn{padding:10px;border-radius:9px;font-size:11px;font-weight:800;cursor:pointer}.detail-btn{background:white;border:1px solid #8b596050;color:var(--mahogany)}.select-btn{background:linear-gradient(135deg,var(--gold-light),var(--gold));border:1px solid #aa820a;color:var(--mahogany)}.selected .select-btn{background:var(--green);color:white;border-color:var(--green)}
.vote-action{position:sticky;bottom:12px;margin-top:10px;padding:14px 17px;display:flex;justify-content:space-between;align-items:center;gap:15px;z-index:10}.vote-action strong{display:block;color:var(--mahogany);font-size:13px;margin-top:4px}
.success-card{max-width:650px;margin:50px auto;text-align:center;padding:45px 28px}.success-icon{width:76px;height:76px;margin:auto;border-radius:50%;display:grid;place-items:center;background:#e6f3e9;color:var(--green);border:2px solid #62a173;font-size:40px;font-weight:900}.success-card h1{font-family:Georgia,serif;color:var(--mahogany);margin:20px 0 8px}.result-mini{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin:25px 0;text-align:left}.result-mini>div{background:#f8efe3;border:1px solid #d4af3760;border-radius:12px;padding:14px}.result-mini small{display:block;color:#7d6c65;font-size:10px;font-weight:800}.result-mini strong{display:block;color:var(--mahogany);margin-top:5px;font-size:13px}
.admin-head{padding:20px;display:flex;justify-content:space-between;gap:20px;align-items:center}.admin-head h1{font-family:Georgia,serif;color:var(--mahogany);margin:4px 0}.admin-actions{display:flex;gap:7px;flex-wrap:wrap;justify-content:flex-end}.winner-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin:16px 0}.winner-card{background:linear-gradient(145deg,var(--mahogany),var(--mahogany-3));color:white;border:2px solid var(--gold);border-radius:18px;padding:22px;text-align:center;box-shadow:0 14px 35px #4a0e1725}.winner-card.gold-card{background:linear-gradient(145deg,#5b4210,#8b6917)}.winner-label{color:var(--gold-light)}.winner-number{font-size:32px;font-family:Georgia,serif;color:var(--gold);margin-top:8px;font-weight:900}.winner-name{font-size:21px;font-family:Georgia,serif;font-weight:800;margin-top:3px}.winner-votes{margin-top:8px;color:#f8e9c5;font-size:13px}.stats-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin:16px 0}.stat-card{background:#fffdf9;border-left:5px solid var(--gold);padding:18px;border-radius:12px;box-shadow:0 6px 20px #4a0e1712}.stat-card strong{display:block;font-size:30px;color:var(--mahogany);font-family:Georgia,serif;margin-top:5px}.results-columns{display:grid;grid-template-columns:1fr 1fr;gap:16px}.results-columns .panel,.overall-panel{padding:20px}.result-row{margin:14px 0}.result-top{display:flex;justify-content:space-between;gap:10px;font-size:12px;font-weight:800;color:var(--mahogany)}.bar{height:11px;background:#eadfd5;border-radius:999px;overflow:hidden;margin-top:7px}.bar span{display:block;height:100%;background:linear-gradient(90deg,var(--mahogany),var(--gold));border-radius:999px}.result-row.winner .result-top{color:var(--mahogany)}.conclusion{font-size:15px;line-height:1.7;color:#4d3e3f}.conclusion strong{color:var(--mahogany)}
.modal{position:fixed;inset:0;z-index:100;background:#21070bc9;backdrop-filter:blur(5px);display:flex;align-items:center;justify-content:center;padding:18px}.modal-box{width:min(480px,100%);background:#fffdf9;border:2px solid var(--gold);border-radius:18px;padding:24px;box-shadow:0 25px 70px #0007}.modal-box.small{width:min(370px,100%);text-align:center}.modal-box h2{font-family:Georgia,serif;color:var(--mahogany);margin:0 0 7px}.modal-box p{font-size:12px;color:#6e6262;line-height:1.5}.confirm-row{display:flex;justify-content:space-between;padding:12px;background:#f7eee5;border-radius:10px;margin:8px 0;font-size:12px}.confirm-row strong{color:var(--mahogany)}.modal-actions{display:flex;gap:9px;margin-top:18px}.modal-actions>*{flex:1}.lock{font-size:30px;margin-bottom:8px}
footer{text-align:center;background:var(--mahogany);color:#f3e5abbb;border-top:2px solid var(--gold);padding:18px;font-size:10px;letter-spacing:1.2px}
@media(max-width:980px){.candidate-grid{grid-template-columns:repeat(2,1fr)}.candidate-grid.three{grid-template-columns:repeat(2,1fr)}}
@media(max-width:650px){.hero-card h1{font-size:34px;letter-spacing:5px}.brand-subtitle{display:none}.voter-status,.vote-action,.admin-head{align-items:stretch;flex-direction:column}.candidate-grid,.candidate-grid.three,.winner-grid,.results-columns,.stats-grid{grid-template-columns:1fr}.result-mini{grid-template-columns:1fr}.admin-actions{justify-content:stretch}.admin-actions .btn{flex:1}.container{width:min(100% - 18px,1180px)}.photo-wrap{height:250px}}

/* Keep each public voting view within the visible screen. */
html,body{height:100%;min-height:0;overflow:hidden}
body{height:100dvh;display:flex;flex-direction:column}
.topbar{position:relative;flex:none}
.brand{padding:7px 16px}.brand img{width:54px;height:54px;flex:none}.brand-title{font-size:clamp(13px,1.6vw,19px);letter-spacing:1px}.school-name{font-size:11px;font-weight:800;letter-spacing:1.4px;color:#fff3d4;margin-top:3px}
.container{flex:1;min-height:0;padding:12px 0;display:flex;flex-direction:column}
.view.active{display:flex;flex-direction:column;flex:1;min-height:0;overflow:hidden}
footer{flex:none;padding:7px;font-size:9px}
#view-login.active{display:grid;grid-template-columns:minmax(0,1.25fr) minmax(300px,.9fr);align-items:center;gap:20px}
.hero-card{width:100%;margin:0;padding:14px 18px;border-radius:20px}.hero-logo{display:flex;align-items:center;justify-content:center;gap:14px}.hero-logo img,.school-logo{width:clamp(76px,11vh,112px);height:clamp(76px,11vh,112px);flex:none;border:3px solid var(--gold);border-radius:50%;object-fit:cover}.school-logo{display:grid;place-items:center;background:radial-gradient(circle,#fff3d4 0 43%,#7a1c28 44% 68%,#d4af37 69%);color:#4a0e17;font:bold 22px Georgia,serif;text-shadow:0 1px white}.hero-card h1{font-size:clamp(28px,5vh,44px);margin:5px 0;letter-spacing:5px}.hero-card .slogan{font-size:13px;line-height:1.45}.ornament{margin-top:7px}.eyebrow{font-size:10px}
.login-panel{width:100%;padding:20px}.login-panel form{gap:11px;margin-top:12px}.login-panel input{padding:10px 12px}
#view-voting{gap:0}.voter-status{padding:9px 14px;margin-bottom:8px}.tabs{margin-top:4px}.tab{padding:9px}.vote-content{padding:8px 0;flex:1;min-height:0;overflow:hidden}.section-heading{margin-bottom:8px}.section-heading>span{width:38px;height:38px}.section-heading p{margin:2px 0}.candidate-grid,.candidate-grid.three{grid-template-columns:repeat(4,minmax(0,1fr));gap:9px}.candidate-grid.three{grid-template-columns:repeat(3,minmax(0,1fr))}.candidate-card{min-width:0}.photo-wrap{height:clamp(88px,17vh,155px)}.candidate-body{padding:8px}.candidate-body h3{font-size:clamp(12px,1.3vw,16px)}.candidate-meta,.motto,.candidate-body .visi{font-size:10px}.motto{margin:5px 0}.candidate-body .visi{line-height:1.3;margin:5px 0}.candidate-actions{padding:0 8px 8px;gap:5px}.detail-btn,.select-btn{padding:7px 4px;font-size:9px}.vote-action{position:static;margin-top:5px;padding:9px 12px}
.success-card{margin:auto;padding:24px}.success-card .redirect-note{color:#74696a;font-size:13px}
@media(max-width:760px){#view-login{grid-template-columns:1fr;gap:10px;align-content:center}.hero-card{padding:10px}.hero-logo img,.school-logo{width:60px;height:60px}.hero-card h1{font-size:28px}.login-panel{padding:14px}.login-panel form{grid-template-columns:1fr 1fr;gap:8px}.login-panel form button{grid-column:1/-1}.login-panel h2{font-size:18px}.login-panel .muted{margin:4px 0}.candidate-grid,.candidate-grid.three{grid-template-columns:repeat(2,minmax(0,1fr))}.photo-wrap{height:clamp(64px,11vh,105px)}.candidate-body .visi{display:none}.candidate-actions .detail-btn{display:none}.candidate-body h3{font-size:12px}.candidate-meta,.motto{font-size:9px}.voter-status{flex-direction:row;align-items:center}.pill{font-size:8px;padding:6px}}
@media(max-height:650px){.brand{padding:4px 12px}.brand img{width:42px;height:42px}.hero-card{padding:8px}.hero-logo img,.school-logo{width:60px;height:60px}.hero-card .ornament{display:none}.login-panel{padding:12px}.photo-wrap{height:76px}}
/* Admin results can extend beyond the viewport and remain scrollable. */
html:has(body.admin-view),body.admin-view{height:auto;min-height:100%;overflow-y:auto;overflow-x:hidden}
body.admin-view{min-height:100dvh}
body.admin-view .container{flex:none;min-height:0;padding:18px 0}
body.admin-view .view.active{display:block;min-height:0;overflow:visible}
.student-fields{display:grid;grid-template-columns:1fr 1fr;gap:10px}
.countdown{margin:22px auto 0;color:#74696a;font-size:13px}.countdown-ring{width:78px;height:78px;margin:auto;border:4px solid var(--gold);border-right-color:var(--mahogany);border-radius:50%;display:grid;place-items:center;animation:countdown-spin 5s linear infinite}.countdown-ring strong{font:800 32px Georgia,serif;color:var(--mahogany)}.countdown p{margin:12px 0 0}.countdown p strong{color:var(--mahogany)}
@keyframes countdown-spin{to{transform:rotate(360deg)}}
.voter-profile-panel{padding:18px 20px;margin:0 0 16px}.profile-results{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:8px;margin-top:12px}.profile-row{display:flex;justify-content:space-between;align-items:center;gap:12px;padding:10px 12px;background:#f8efe3;border-radius:9px;color:var(--mahogany);font-size:12px}.profile-row strong{font-size:15px}
.profile-row{align-items:flex-start;flex-direction:column;gap:4px}.profile-row strong{font-size:13px}.profile-row span{font-size:11px;color:#74696a}

    </style>
</head>
<body>
<header class="topbar">
    <div class="brand">
        <img src="assets/logo-adhiprama.jpg" alt="Logo Pemilihan OSIS ADHIPRAMA">
        <div>
            <div class="brand-title">PEMILIHAN KETUA &amp; WAKIL KETUA OSIS ADHIPRAMA</div>
            <div class="brand-subtitle">Pemimpin yang berwawasan · teladan berkualitas · mewujudkan solidaritas</div>
            <div class="school-name">SMK PLUS YSB SURYALAYA</div>
        </div>
    </div>
</header>

<main class="container">
    <!-- PEMILIH -->
    <section id="view-login" class="view active">
        <div class="hero-card">
            <div class="hero-logo"><img src="assets/logo-adhiprama.jpg" alt="Logo Pemilihan ADHIPRAMA"><div class="school-logo" role="img" aria-label="Logo SMK Plus YSB Suryalaya">YSB</div></div>
            <p class="eyebrow">PEMILIHAN KETUA &amp; WAKIL KETUA OSIS</p>
            <h1>ADHIPRAMA</h1>
            <p class="slogan">“Pemimpin yang berwawasan, teladan berkualitas, dan mewujudkan solidaritas.”</p>
            <div class="ornament">◆ · ❖ · ◆</div>
        </div>

        <div class="panel login-panel">
            <h2>Identitas Pemilih</h2>
            <p class="muted">Masukkan data pemilih untuk masuk ke bilik suara.</p>
            <form id="voter-form">
                <label>Nama Lengkap<input id="voter-name" type="text" required autocomplete="off"></label>
                <label>Jenis Pemilih<select id="voter-type" required><option value="">-- Pilih --</option><option value="Siswa">Siswa</option><option value="Guru">Guru</option><option value="Staff">Staff</option></select></label>
                <label id="class-major-field" class="hidden">Kelas / Jurusan<input id="voter-class-major" type="text" autocomplete="off"></label>
                <button class="btn primary" type="submit">MASUK KE BILIK SUARA</button>
            </form>
        </div>
    </section>

    <!-- VOTING -->
    <section id="view-voting" class="view">
        <div class="voter-status panel">
            <div><small>PEMILIH TERDAFTAR</small><strong id="display-voter">-</strong></div>
            <span class="pill">PILIH TERPISAH · NOMOR URUT TETAP</span>
        </div>

        <div class="tabs">
            <button id="tab-ketua" class="tab active">① PILIH KETUA</button>
            <button id="tab-wakil" class="tab">② PILIH WAKIL</button>
        </div>

        <div id="content-ketua" class="vote-content">
            <div class="section-heading"><span>01</span><div><h2>Calon Ketua OSIS</h2><p>Pilih satu kandidat. Urutan kartu tetap sesuai nomor kandidat.</p></div></div>
            <div id="ketua-grid" class="candidate-grid"></div>
        </div>

        <div id="content-wakil" class="vote-content hidden">
            <div class="section-heading"><span>02</span><div><h2>Calon Wakil Ketua OSIS</h2><p>Pilih satu kandidat. Wakil bebas dipasangkan dengan Ketua mana pun.</p></div></div>
            <div id="wakil-grid" class="candidate-grid three"></div>
        </div>

        <div class="vote-action panel">
            <div><small>PILIHAN ANDA</small><strong id="selection-summary">Ketua: — &nbsp; | &nbsp; Wakil: —</strong></div>
            <button id="submit-vote" class="btn primary" disabled>KONFIRMASI SUARA</button>
        </div>
    </section>

    <!-- SUCCESS -->
    <section id="view-success" class="view">
        <div class="panel success-card">
            <div class="success-icon">✓</div>
            <h1>Matur Nuwun · Hatur Nuhun</h1>
            <p>Suara Anda telah tercatat pada sistem pemilihan ADHIPRAMA.</p>
            <div class="countdown" aria-live="polite"><div class="countdown-ring"><strong id="countdown-number">5</strong></div><p>Kembali ke halaman utama dalam <strong id="countdown-label">5 detik</strong></p></div>
        </div>
    </section>

    <!-- ADMIN: tidak memiliki tombol/menu publik; dibuka hanya shortcut + PIN -->
    <section id="view-admin" class="view">
        <div class="admin-head panel">
            <div><span class="eyebrow">PUSAT REKAPITULASI</span><h1>Hasil Keseluruhan Pemilihan</h1><p class="muted">Sistem menghitung suara Ketua dan Wakil secara terpisah.</p></div>
            <div class="admin-actions"><button class="btn outline" id="export-btn">EKSPOR CSV</button><button class="btn danger" id="reset-btn">RESET DATA</button><button class="btn outline" id="admin-exit">KELUAR</button></div>
        </div>

        <div class="winner-grid">
            <div class="winner-card">
                <div class="winner-label">KETUA OSIS TERPILIH</div>
                <div class="winner-number" id="winner-ketua-number">—</div>
                <div class="winner-name" id="winner-ketua-name">Belum ada suara</div>
                <div class="winner-votes" id="winner-ketua-votes">0 suara</div>
            </div>
            <div class="winner-card gold-card">
                <div class="winner-label">WAKIL KETUA TERPILIH</div>
                <div class="winner-number" id="winner-wakil-number">—</div>
                <div class="winner-name" id="winner-wakil-name">Belum ada suara</div>
                <div class="winner-votes" id="winner-wakil-votes">0 suara</div>
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-card"><small>TOTAL SUARA MASUK</small><strong id="stat-total">0</strong></div>
            <div class="stat-card"><small>SUARA KETUA</small><strong id="stat-ketua-total">0</strong></div>
            <div class="stat-card"><small>SUARA WAKIL</small><strong id="stat-wakil-total">0</strong></div>
        </div>

        <div class="panel voter-profile-panel"><h2>Data Diri Pemilih</h2><p class="muted">Ringkasan jenis pemilih dan kelas/jurusan, tanpa mengaitkan data diri dengan pilihan kandidat.</p><div id="voter-profile-results" class="profile-results"></div></div>

        <div class="results-columns">
            <div class="panel"><h2>Perolehan Ketua</h2><div id="ketua-results"></div></div>
            <div class="panel"><h2>Perolehan Wakil</h2><div id="wakil-results"></div></div>
        </div>
        <div class="panel overall-panel">
            <h2>Kesimpulan Otomatis</h2>
            <p id="overall-conclusion" class="conclusion">Belum ada suara yang masuk.</p>
        </div>
    </section>
</main>

<!-- Konfirmasi -->
<div id="modal-confirm" class="modal hidden"><div class="modal-box"><h2>Konfirmasi Pilihan</h2><p>Pastikan pilihan sudah benar. Setelah dikirim, suara tidak dapat diubah.</p><div class="confirm-row"><span>Ketua</span><strong id="confirm-ketua">-</strong></div><div class="confirm-row"><span>Wakil</span><strong id="confirm-wakil">-</strong></div><div class="modal-actions"><button class="btn outline" id="confirm-cancel">PERIKSA KEMBALI</button><button class="btn primary" id="confirm-send">YA, KIRIM SUARA</button></div></div></div>

<!-- Admin PIN -->
<div id="modal-admin" class="modal hidden"><div class="modal-box small"><div class="lock">🔐</div><h2>Akses Panitia</h2><p>Masukkan PIN untuk membuka hasil keseluruhan.</p><input id="admin-pin" type="password" maxlength="12" inputmode="numeric" placeholder="PIN Admin"><div class="modal-actions"><button class="btn outline" id="admin-cancel">BATAL</button><button class="btn primary" id="admin-login">MASUK</button></div></div></div>
<div id="modal-reset" class="modal hidden"><div class="modal-box small"><div class="lock">🔒</div><h2>Reset Data Vote</h2><p>Masukkan password untuk menghapus seluruh suara dan data pemilih.</p><input id="reset-password" type="password" maxlength="12" inputmode="numeric" placeholder="Password"><div class="modal-actions"><button class="btn outline" id="reset-cancel">BATAL</button><button class="btn danger" id="reset-confirm">HAPUS DATA</button></div></div></div>

<footer>ADHIPRAMA · JAWA &amp; SUNDA · 2027–2028</footer>
<script>
// ADHIPRAMA E-VOTING - frontend offline/local prototype
const ADMIN_PIN = "1234"; // ganti sebelum dipakai resmi
const STORAGE_KEY = "adhiprama_votes_v2";
const PROFILE_STORAGE_KEY = "adhiprama_voter_profiles_v1";

// Nomor urut TETAP. Yang diacak hanya posisi kartu di layar.
const candidatesKetua = [
 {id:"k1",number:"01",name:"Raden Arya Wicaksana",class:"XI MIPA 1",origin:"Sunda-Jawa",motto:"Nyantrik, Nyantri, Nyeni ing Paripurna",photo:avatar("#f3e0d0","#4a0e17","01"),visi:"Mewujudkan OSIS ADHIPRAMA sebagai sarana kepemimpinan berintegritas tinggi, berwawasan global, serta teguh menjaga etika luhur budaya Jawa dan Sunda.",misi:["Meningkatkan solidaritas antar siswa.","Mengintegrasikan teknologi ramah pengguna.","Mengadakan panggung ekspresi budaya dan bakat siswa."],programs:["Festival Budaya Nusantara","OSIS Digital Hub","Mentoring Sebaya"]},
 {id:"k2",number:"02",name:"Nyi Mas Galuh Prameshwari",class:"XI IPS 2",origin:"Sunda",motto:"Silih Asah, Silih Asih, Silih Asuh",photo:avatar("#f8ecd1","#7a1c28","02"),visi:"Menjadikan OSIS wadah yang inklusif, responsif, dan mencetak siswa berteladan unggul.",misi:["Menampung aspirasi siswa secara transparan.","Mengembangkan kepedulian sosial.","Memperkuat kedisiplinan dan kepemimpinan."],programs:["Leadership Camp","Green School","Pojok Karya Siswa"]},
 {id:"k3",number:"03",name:"Bagus Satria Ananta",class:"XI MIPA 3",origin:"Jawa",motto:"Jer Basuki Mawa Beya",photo:avatar("#e7ead6","#61141f","03"),visi:"Membawa OSIS ADHIPRAMA menjadi pelopor inovasi akademik dan non-akademik berlandaskan kekeluargaan.",misi:["Mengoptimalkan kompetensi siswa.","Membangun komunikasi antar kelas.","Menciptakan ruang apresiasi bakat."],programs:["Youth Innovation Summit","Pekan Olahraga & Seni","Aspirasi Karsa"]},
 {id:"k4",number:"04",name:"Kandidat Ketua 4",class:"XI TKJ B",origin:"Jawa-Sunda",motto:"Rukun, Raket, Sauyunan",photo:avatar("#eee1c9","#8b6917","04"),visi:"Mewujudkan kepemimpinan siswa yang adaptif, santun, kreatif, dan menjunjung solidaritas.",misi:["Memperkuat kerja sama siswa.","Membuka ruang kreativitas.","Menumbuhkan budaya disiplin."],programs:["Ruang Aspirasi","Pekan Kreativitas","Gerakan Sauyunan"]}
];
const candidatesWakil = [
 {id:"w1",number:"01",name:"Siti Dewi Lestari",class:"X MIPA 2",origin:"Sunda",motto:"Tatap, Tapa, Matapa",photo:avatar("#f3dfdf","#4a0e17","01"),visi:"Mendampingi kepemimpinan OSIS dengan tata kelola yang solid, terbuka, dan sigap.",misi:["Memperbaiki administrasi.","Menjalin komunikasi harmonis.","Mendukung kegiatan ekstrakurikuler."],programs:["Open Dashboard","Klinik Organisasi","Literasi Digital"]},
 {id:"w2",number:"02",name:"Dimas Aji Pangestu",class:"X IPS 1",origin:"Jawa",motto:"Akur Rukun Hambangun Karya",photo:avatar("#e9dfcf","#61141f","02"),visi:"Menjadi jembatan solidaritas yang kuat antar angkatan dan mempererat persaudaraan.",misi:["Merangkul seluruh elemen siswa.","Mengadakan kegiatan kebersamaan.","Menjaga suasana sekolah kondusif."],programs:["Forum Lintas Kelas","E-Sports & Art League","Bhakti Sosial"]},
 {id:"w3",number:"03",name:"Asep Saepullah Tirta",class:"X MIPA 1",origin:"Sunda",motto:"Sarendeu Saigel Sabobot Sapihanean",photo:avatar("#f2e7cc","#8b6917","03"),visi:"Mewujudkan pendampingan OSIS yang cekatan, tanggap isu sosial, serta berakhlak mulia.",misi:["Layanan konseling sebaya.","Aksi kepedulian lingkungan.","Menumbuhkan keteladanan harian."],programs:["Peer Counselor","Gerakan Resik & Asri","Duta Keteladanan"]}
];

let currentVoter=null, selectedKetuaId=null, selectedWakilId=null, chairOrder=[], viceOrder=[];
const $=id=>document.getElementById(id);
function avatar(bg,accent,num){
 return `data:image/svg+xml;charset=UTF-8,${encodeURIComponent(`<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 600 600"><rect width="600" height="600" fill="${bg}"/><circle cx="300" cy="290" r="230" fill="none" stroke="${accent}" stroke-width="14" opacity=".25"/><path d="M90 540c30-150 120-210 210-210s180 60 210 210" fill="${accent}"/><circle cx="300" cy="245" r="105" fill="#d6a77d"/><path d="M185 220c20-120 210-140 235 0-50-38-170-40-235 0" fill="${accent}"/><path d="M230 350q70 55 140 0" fill="none" stroke="#d4af37" stroke-width="15"/><text x="300" y="505" text-anchor="middle" font-family="Georgia" font-size="48" font-weight="700" fill="#d4af37">NO. ${num}</text></svg>`)}`;
}
let serverVotes=[], serverProfiles=[];
async function loadServerData(){
 try{
  const r=await fetch('?action=get',{cache:'no-store'});
  const d=await r.json();
  if(!d.ok) throw new Error(d.error||'Gagal membaca database');
  serverVotes=d.votes||[]; serverProfiles=d.profiles||[];
 }catch(e){console.error(e); alert('Tidak dapat terhubung ke server PHP/database. Pastikan PHP dan SQLite aktif.');}
}
function getVotes(){return serverVotes}
function getVoterProfiles(){return serverProfiles}
async function saveVote(v,profile){
 const r=await fetch('?action=save',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({ketuaId:v.ketuaId,wakilId:v.wakilId,profile})});
 const d=await r.json();
 if(!d.ok) throw new Error(d.error||'Gagal menyimpan suara');
 await loadServerData();
}
function hasVoted(nis){return false}
function showView(id){document.querySelectorAll('.view').forEach(v=>v.classList.remove('active'));$(id).classList.add('active');document.body.classList.toggle('admin-view',id==='view-admin');window.scrollTo({top:0,behavior:'smooth'})}
function renderCards(){
 $('ketua-grid').innerHTML=chairOrder.map(c=>card(c,'ketua')).join('');
 $('wakil-grid').innerHTML=viceOrder.map(c=>card(c,'wakil')).join('');
}
function card(c,role){const selected=role==='ketua'?selectedKetuaId===c.id:selectedWakilId===c.id;return `<article class="candidate-card ${selected?'selected':''}"><div class="photo-wrap"><img src="${c.photo}" alt="Kandidat ${c.number}"><span class="number-badge">URUT ${c.number}</span></div><div class="candidate-body"><h3>${c.name}</h3><div class="candidate-meta">${c.class} · ${c.origin}</div><div class="motto">“${c.motto}”</div><p class="visi"><b>Visi:</b> ${c.visi}</p></div><div class="candidate-actions"><button class="detail-btn" data-detail="${c.id}" data-role="${role}">LIHAT VISI, MISI & PROGRAM</button><button class="select-btn" data-select="${c.id}" data-role="${role}">${selected?'✓ TERPILIH':'PILIH KANDIDAT'}</button></div></article>`}
function findCandidate(id,role){return(role==='ketua'?candidatesKetua:candidatesWakil).find(c=>c.id===id)}
function selectCandidate(id,role){if(role==='ketua')selectedKetuaId=id;else selectedWakilId=id;updateSummary();renderCards();if(role==='ketua'&&!selectedWakilId)switchTab('wakil')}
function updateSummary(){const k=findCandidate(selectedKetuaId,'ketua'),w=findCandidate(selectedWakilId,'wakil');$('selection-summary').textContent=`Ketua: ${k?`No. ${k.number} - ${k.name}`:'—'}  |  Wakil: ${w?`No. ${w.number} - ${w.name}`:'—'}`;$('submit-vote').disabled=!(k&&w)}
function switchTab(tab){$('tab-ketua').classList.toggle('active',tab==='ketua');$('tab-wakil').classList.toggle('active',tab==='wakil');$('content-ketua').classList.toggle('hidden',tab!=='ketua');$('content-wakil').classList.toggle('hidden',tab!=='wakil')}
function openConfirm(){const k=findCandidate(selectedKetuaId,'ketua'),w=findCandidate(selectedWakilId,'wakil');if(!k||!w)return;$('confirm-ketua').textContent=`No. ${k.number} - ${k.name}`;$('confirm-wakil').textContent=`No. ${w.number} - ${w.name}`;$('modal-confirm').classList.remove('hidden')}
function closeModal(id){$(id).classList.add('hidden')}
let homeRedirectTimer=null,countdownTimer=null;
async function submitVote(){const k=findCandidate(selectedKetuaId,'ketua'),w=findCandidate(selectedWakilId,'wakil');if(!k||!w||!currentVoter)return;const btn=$('confirm-send');btn.disabled=true;try{await saveVote({ketuaId:k.id,wakilId:w.id},{name:currentVoter.name,type:currentVoter.type,classMajor:currentVoter.classMajor});closeModal('modal-confirm');showView('view-success');let seconds=5;$('countdown-number').textContent=seconds;$('countdown-label').textContent=`${seconds} detik`;clearInterval(countdownTimer);countdownTimer=setInterval(()=>{seconds--;if(seconds<=0){clearInterval(countdownTimer);return}$('countdown-number').textContent=seconds;$('countdown-label').textContent=`${seconds} detik`},1000);clearTimeout(homeRedirectTimer);homeRedirectTimer=setTimeout(resetForNext,5000)}catch(e){alert(e.message)}finally{btn.disabled=false}}
function resetForNext(){clearTimeout(homeRedirectTimer);clearInterval(countdownTimer);currentVoter=null;selectedKetuaId=null;selectedWakilId=null;$('voter-form').reset();$('class-major-field').classList.add('hidden');$('voter-class-major').required=false;showView('view-login')}
function renderAdmin(){
 const votes=getVotes();$('stat-total').textContent=votes.length;$('stat-ketua-total').textContent=votes.length;$('stat-wakil-total').textContent=votes.length;
 renderVoterProfiles();
 const kc=Object.fromEntries(candidatesKetua.map(c=>[c.id,0])),wc=Object.fromEntries(candidatesWakil.map(c=>[c.id,0]));votes.forEach(v=>{if(kc[v.ketuaId]!==undefined)kc[v.ketuaId]++;if(wc[v.wakilId]!==undefined)wc[v.wakilId]++});
 renderResultList('ketua-results',candidatesKetua,kc);renderResultList('wakil-results',candidatesWakil,wc);
 const kw=winner(candidatesKetua,kc),ww=winner(candidatesWakil,wc);fillWinner('ketua',kw,votes.length);fillWinner('wakil',ww,votes.length);
 let conclusion='Belum ada suara yang masuk.';if(votes.length){const ktxt=kw.tie?`Ketua masih seri: ${kw.items.map(x=>`No. ${x.number} ${x.name}`).join(' dan ')}`:`Ketua terpilih: No. ${kw.item.number} ${kw.item.name} (${kw.max} suara)`;const wtxt=ww.tie?`Wakil masih seri: ${ww.items.map(x=>`No. ${x.number} ${x.name}`).join(' dan ')}`:`Wakil terpilih: No. ${ww.item.number} ${ww.item.name} (${ww.max} suara)`;conclusion=`${ktxt}. ${wtxt}. Perhitungan dilakukan otomatis berdasarkan seluruh suara yang tersimpan di perangkat ini.`}$('overall-conclusion').innerHTML=conclusion}
function renderVoterProfiles(){const root=$('voter-profile-results'),profiles=getVoterProfiles();root.replaceChildren();if(!profiles.length){const empty=document.createElement('p');empty.className='muted';empty.textContent='Belum ada data pemilih.';root.append(empty);return}profiles.forEach(p=>{const row=document.createElement('div'),name=document.createElement('strong'),details=document.createElement('span');row.className='profile-row';name.textContent=p.name||'Nama tidak tersedia';details.textContent=p.classMajor?`${p.type} · ${p.classMajor}`:(p.type||'Tidak diketahui');row.append(name,details);root.append(row)})}
function winner(list,counts){let max=Math.max(...list.map(c=>counts[c.id]),0);const items=list.filter(c=>counts[c.id]===max);return{item:items[0],items,max,tie:max>0&&items.length>1}}
function fillWinner(role,res,total){const p=role==='ketua'?'winner-ketua':'winner-wakil';if(!res.item||!total){$(p+'-number').textContent='—';$(p+'-name').textContent='Belum ada suara';$(p+'-votes').textContent='0 suara';return}if(res.tie){$(p+'-number').textContent='SERI';$(p+'-name').textContent=res.items.map(x=>`No. ${x.number} ${x.name}`).join(' / ');$(p+'-votes').textContent=`${res.max} suara masing-masing`;return}$(p+'-number').textContent=`NO. ${res.item.number}`;$(p+'-name').textContent=res.item.name;$(p+'-votes').textContent=`${res.max} suara`}
function renderResultList(target,list,counts){const total=getVotes().length;$(target).innerHTML=list.map(c=>{const n=counts[c.id],pct=total?Math.round(n/total*100):0;return `<div class="result-row ${n===Math.max(...list.map(x=>counts[x.id]))&&n>0?'winner':''}"><div class="result-top"><span>No. ${c.number} · ${c.name}</span><span>${n} suara · ${pct}%</span></div><div class="bar"><span style="width:${pct}%"></span></div></div>`}).join('')}
function exportCSV(){window.location.href='?action=export&pin='+encodeURIComponent(ADMIN_PIN)}
function resetVotes(){$('reset-password').value='';$('modal-reset').classList.remove('hidden');setTimeout(()=>$('reset-password').focus(),50)}
async function confirmResetVotes(){const pin=$('reset-password').value;if(pin!=='1234'){alert('Password salah. Data tidak dihapus.');$('reset-password').focus();return}try{const r=await fetch('?action=reset',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({pin})});const d=await r.json();if(!d.ok)throw new Error(d.error);await loadServerData();closeModal('modal-reset');renderAdmin()}catch(e){alert(e.message)}}
function openAdmin(){ $('admin-pin').value='';$('modal-admin').classList.remove('hidden');setTimeout(()=>$('admin-pin').focus(),50)}
function verifyAdmin(){if($('admin-pin').value===ADMIN_PIN){closeModal('modal-admin');showView('view-admin');loadServerData().then(renderAdmin)}else alert('PIN admin salah.')}
function detail(id,role){const c=findCandidate(id,role);alert(`NO. ${c.number} - ${c.name}\n\nVisi:\n${c.visi}\n\nMisi:\n- ${c.misi.join('\n- ')}\n\nProgram:\n- ${c.programs.join('\n- ')}`)}

$('voter-type').addEventListener('change',()=>{const type=$('voter-type').value,needsDetails=type==='Siswa'||type==='Guru';$('class-major-field').classList.toggle('hidden',!needsDetails);$('class-major-field').firstChild.textContent=type==='Guru'?'Unit / Bidang':'Kelas / Jurusan';$('voter-class-major').required=needsDetails});
$('voter-form').addEventListener('submit',e=>{e.preventDefault();const name=$('voter-name').value.trim(),type=$('voter-type').value,classMajor=$('voter-class-major').value.trim();if(!name||!type||((type==='Siswa'||type==='Guru')&&!classMajor))return;currentVoter={name,type,classMajor};$('display-voter').textContent=classMajor?`${name} · ${classMajor}`:`${name} · ${type}`;selectedKetuaId=null;selectedWakilId=null;chairOrder=candidatesKetua;viceOrder=candidatesWakil;renderCards();updateSummary();switchTab('ketua');showView('view-voting')});
$('ketua-grid').addEventListener('click',e=>{const b=e.target.closest('button');if(!b)return;if(b.dataset.select)selectCandidate(b.dataset.select,b.dataset.role);if(b.dataset.detail)detail(b.dataset.detail,b.dataset.role)});
$('wakil-grid').addEventListener('click',e=>{const b=e.target.closest('button');if(!b)return;if(b.dataset.select)selectCandidate(b.dataset.select,b.dataset.role);if(b.dataset.detail)detail(b.dataset.detail,b.dataset.role)});
$('tab-ketua').onclick=()=>switchTab('ketua');$('tab-wakil').onclick=()=>switchTab('wakil');$('submit-vote').onclick=openConfirm;$('confirm-cancel').onclick=()=>closeModal('modal-confirm');$('confirm-send').onclick=submitVote;$('admin-cancel').onclick=()=>closeModal('modal-admin');$('admin-login').onclick=verifyAdmin;$('admin-exit').onclick=()=>showView('view-login');$('export-btn').onclick=exportCSV;$('reset-btn').onclick=resetVotes;$('reset-cancel').onclick=()=>closeModal('modal-reset');$('reset-confirm').onclick=confirmResetVotes;

// Shortcut khusus panitia: Ctrl+C lalu B dalam waktu 1,2 detik. Tidak ada tombol Hasil di halaman pemilih.
let adminSequence=false,sequenceTimer=null;document.addEventListener('keydown',e=>{const key=e.key.toLowerCase();if(document.body.classList.contains('admin-view')&&e.ctrlKey&&e.shiftKey&&key==='v'){e.preventDefault();showView('view-login');return}if(e.ctrlKey&&key==='c'){e.preventDefault();adminSequence=true;clearTimeout(sequenceTimer);sequenceTimer=setTimeout(()=>adminSequence=false,1200);return}if(adminSequence&&key==='b'){e.preventDefault();adminSequence=false;clearTimeout(sequenceTimer);openAdmin()}});

// ESC hanya menutup modal, bukan keluar dari aplikasi.
document.addEventListener('keydown',e=>{if(e.key==='Escape'){['modal-confirm','modal-admin','modal-reset'].forEach(id=>$(id).classList.add('hidden'))}});

loadServerData();

</script>
</body>
</html>
