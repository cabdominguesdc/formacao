<?php
// ============================================================
//  AUTENTICAÇÃO
// ============================================================
session_start();

define('AUTH_USER', 'admin');
define('AUTH_PASS', 'administrador');

$login_error = '';

if (isset($_POST['logout'])) {
    session_destroy();
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

if (isset($_POST['username'])) {
    if ($_POST['username'] === AUTH_USER && $_POST['password'] === AUTH_PASS) {
        $_SESSION['auth'] = true;
        $_SESSION['user'] = AUTH_USER;
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    } else {
        $login_error = 'Utilizador ou password incorretos.';
    }
}

if (empty($_SESSION['auth'])) {
    ?><!DOCTYPE html>
<html lang="pt">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>FormaTIC – Autenticação</title>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html,body{height:100%}
body{font-family:'Courier New',Courier,monospace;background:#0d0f11;color:#c8cfd8;display:flex;align-items:center;justify-content:center;min-height:100vh}
.login-wrap{width:100%;max-width:380px;padding:1rem}
.login-box{background:#111418;border:1px solid #1e2329;border-radius:10px;padding:2.2rem 2rem}
.login-brand{text-align:center;margin-bottom:2rem}
.login-brand-name{font-size:1.6rem;font-weight:700;color:#e2e8f0;letter-spacing:-0.5px}
.login-brand-sub{font-size:.72rem;color:#4a5568;text-transform:uppercase;letter-spacing:.1em;margin-top:.3rem}
.login-field{margin-bottom:1rem}
.login-field label{display:block;font-size:.72rem;text-transform:uppercase;letter-spacing:.08em;color:#6b7280;margin-bottom:.4rem;font-weight:600}
.login-field input{width:100%;background:#0d0f11;border:1px solid #1e2329;border-radius:5px;padding:.6rem .85rem;color:#c8cfd8;font-family:inherit;font-size:.9rem;outline:none;transition:border-color .2s}
.login-field input:focus{border-color:#374151}
.login-btn{width:100%;background:#1e3a5f;border:1px solid #1e40af;border-radius:5px;padding:.65rem;color:#93c5fd;font-family:inherit;font-size:.88rem;font-weight:700;cursor:pointer;letter-spacing:.04em;margin-top:.5rem;transition:background .2s}
.login-btn:hover{background:#1e40af}
.login-error{background:#450a0a;border:1px solid #7f1d1d;border-radius:5px;padding:.55rem .85rem;color:#fca5a5;font-size:.82rem;margin-bottom:1rem;text-align:center}
.login-footer{text-align:center;margin-top:1.5rem;font-size:.72rem;color:#374151}
</style>
</head>
<body>
<div class="login-wrap">
    <div class="login-box">
        <div class="login-brand">
            <div class="login-brand-name">FormaTIC</div>
            <div class="login-brand-sub">Gestão de Formação em Informática</div>
        </div>
        <?php if ($login_error): ?>
        <div class="login-error">⚠ <?=htmlspecialchars($login_error)?></div>
        <?php endif; ?>
        <form method="post">
            <div class="login-field">
                <label for="username">Utilizador</label>
                <input type="text" id="username" name="username" autocomplete="username" autofocus>
            </div>
            <div class="login-field">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" autocomplete="current-password">
            </div>
            <button type="submit" class="login-btn">Entrar →</button>
        </form>
    </div>
    <div class="login-footer">FormaTIC v1.0</div>
</div>
</body>
</html>
<?php
    exit;
}

// ============================================================
//  CONFIGURAÇÃO DA BASE DE DADOS
// ============================================================
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'formacao_informatica');

function db(): PDO {
    static $pdo = null;
    if (!$pdo) {
        try {
            $pdo = new PDO(
                "mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4",
                DB_USER, DB_PASS,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                 PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
            );
        } catch (PDOException $e) {
            die(render_error("Erro de ligação à base de dados: " . $e->getMessage()));
        }
    }
    return $pdo;
}

function q(string $sql, array $params = []): array {
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st->fetchAll();
}

function q1(string $sql, array $params = []): ?array {
    $r = q($sql, $params);
    return $r[0] ?? null;
}

// ============================================================
//  ROTEAMENTO
// ============================================================
$page    = $_GET['p'] ?? 'dashboard';
$id      = isset($_GET['id']) ? (int)$_GET['id'] : null;
$search  = trim($_GET['s'] ?? '');

// ============================================================
//  DADOS POR PÁGINA
// ============================================================
function get_dashboard(): array {
    return [
        'stats' => q1("SELECT
            (SELECT COUNT(*) FROM formandos)    AS formandos,
            (SELECT COUNT(*) FROM disciplinas)  AS disciplinas,
            (SELECT COUNT(*) FROM aulas)        AS aulas,
            (SELECT COUNT(*) FROM testes)       AS testes,
            (SELECT COUNT(*) FROM classificacoes) AS classificacoes,
            (SELECT ROUND(AVG(nota_final),1) FROM notas_finais) AS media_geral,
            (SELECT COUNT(*) FROM notas_finais WHERE aprovado=1) AS aprovados,
            (SELECT COUNT(*) FROM notas_finais WHERE aprovado=0) AS reprovados
        "),
        'top5'  => q("SELECT f.nome, ROUND(AVG(nf.nota_final),1) AS media
                      FROM notas_finais nf JOIN formandos f ON f.id=nf.formando_id
                      GROUP BY f.id ORDER BY media DESC LIMIT 5"),
        'estat' => q("SELECT * FROM vw_estatisticas_disciplina ORDER BY media DESC"),
        'recentes' => q("SELECT c.data_avaliacao, f.nome AS formando, d.nome AS disciplina,
                         t.titulo, c.nota
                         FROM classificacoes c
                         JOIN formandos f ON f.id=c.formando_id
                         JOIN testes t ON t.id=c.teste_id
                         JOIN disciplinas d ON d.id=t.disciplina_id
                         ORDER BY c.data_avaliacao DESC, c.id DESC LIMIT 8"),
    ];
}

function get_formandos(string $s): array {
    $like = "%$s%";
    return q("SELECT f.id, f.codigo, f.nome, f.email, f.data_nascimento, f.telefone,
               COUNT(DISTINCT nf.disciplina_id) AS disc_count,
               ROUND(AVG(nf.nota_final),1) AS media_geral
               FROM formandos f
               LEFT JOIN notas_finais nf ON nf.formando_id=f.id
               WHERE f.nome LIKE ? OR f.codigo LIKE ?
               GROUP BY f.id ORDER BY f.codigo",
              [$like, $like]);
}

function get_formando(int $id): array {
    $f  = q1("SELECT * FROM formandos WHERE id=?", [$id]);
    $nf = q("SELECT nf.*, d.nome AS disciplina, d.codigo,
              IF(nf.aprovado,'Aprovado','Reprovado') AS resultado
              FROM notas_finais nf JOIN disciplinas d ON d.id=nf.disciplina_id
              WHERE nf.formando_id=? ORDER BY d.nome", [$id]);
    $cl = q("SELECT c.nota, c.data_avaliacao, t.titulo, t.tipo, d.nome AS disciplina
              FROM classificacoes c
              JOIN testes t ON t.id=c.teste_id
              JOIN disciplinas d ON d.id=t.disciplina_id
              WHERE c.formando_id=? ORDER BY c.data_avaliacao", [$id]);
    return ['formando'=>$f, 'notas'=>$nf, 'classificacoes'=>$cl];
}

function get_disciplinas(): array {
    return q("SELECT d.*, COUNT(DISTINCT t.id) AS num_testes,
               COUNT(DISTINCT a.id) AS num_aulas,
               ROUND(AVG(nf.nota_final),1) AS media,
               SUM(nf.aprovado) AS aprovados,
               COUNT(nf.id)-SUM(nf.aprovado) AS reprovados
               FROM disciplinas d
               LEFT JOIN testes t ON t.disciplina_id=d.id
               LEFT JOIN aulas a ON a.disciplina_id=d.id
               LEFT JOIN notas_finais nf ON nf.disciplina_id=d.id
               GROUP BY d.id ORDER BY d.codigo");
}

function get_disciplina(int $id): array {
    $d = q1("SELECT * FROM disciplinas WHERE id=?", [$id]);
    $aulas = q("SELECT a.*, s.conteudo, s.recursos, fo.nome AS formador
                FROM aulas a
                JOIN formadores fo ON fo.id=a.formador_id
                LEFT JOIN sumarios s ON s.aula_id=a.id
                WHERE a.disciplina_id=? ORDER BY a.numero_aula", [$id]);
    $testes = q("SELECT t.*, e.introducao, e.instrucoes,
                  COUNT(p.id) AS num_perguntas
                  FROM testes t
                  LEFT JOIN enunciados_testes e ON e.teste_id=t.id
                  LEFT JOIN perguntas_teste p ON p.enunciado_id=e.id
                  WHERE t.disciplina_id=? GROUP BY t.id ORDER BY t.data_realizacao", [$id]);
    $pauta = q("SELECT f.codigo, f.nome, nf.nota_final,
                 IF(nf.aprovado,'Aprovado','Reprovado') AS resultado
                 FROM notas_finais nf JOIN formandos f ON f.id=nf.formando_id
                 WHERE nf.disciplina_id=? ORDER BY nf.nota_final DESC", [$id]);
    return ['disciplina'=>$d,'aulas'=>$aulas,'testes'=>$testes,'pauta'=>$pauta];
}

function get_sumarios(string $s): array {
    $like = "%$s%";
    return q("SELECT s.id, s.conteudo, s.recursos, s.criado_em,
               a.numero_aula, a.data_aula, a.sala, a.hora_inicio, a.hora_fim,
               d.nome AS disciplina, d.codigo,
               fo.nome AS formador
               FROM sumarios s
               JOIN aulas a ON a.id=s.aula_id
               JOIN disciplinas d ON d.id=a.disciplina_id
               JOIN formadores fo ON fo.id=a.formador_id
               WHERE s.conteudo LIKE ? OR d.nome LIKE ? OR fo.nome LIKE ?
               ORDER BY a.data_aula DESC", [$like,$like,$like]);
}

function get_testes(string $s): array {
    $like = "%$s%";
    return q("SELECT t.*, d.nome AS disciplina, d.codigo,
               fo.nome AS formador,
               e.instrucoes, e.introducao,
               COUNT(DISTINCT p.id) AS num_perguntas,
               COUNT(DISTINCT c.formando_id) AS num_realizados,
               ROUND(AVG(c.nota),1) AS media_nota
               FROM testes t
               JOIN disciplinas d ON d.id=t.disciplina_id
               JOIN formadores fo ON fo.id=t.formador_id
               LEFT JOIN enunciados_testes e ON e.teste_id=t.id
               LEFT JOIN perguntas_teste p ON p.enunciado_id=e.id
               LEFT JOIN classificacoes c ON c.teste_id=t.id
               WHERE t.titulo LIKE ? OR d.nome LIKE ? OR fo.nome LIKE ?
               GROUP BY t.id ORDER BY t.data_realizacao", [$like,$like,$like]);
}

function get_teste(int $id): array {
    $t = q1("SELECT t.*, d.nome AS disciplina, fo.nome AS formador,
              e.introducao, e.instrucoes
              FROM testes t JOIN disciplinas d ON d.id=t.disciplina_id
              JOIN formadores fo ON fo.id=t.formador_id
              LEFT JOIN enunciados_testes e ON e.teste_id=t.id
              WHERE t.id=?", [$id]);
    $pergs = q("SELECT p.* FROM perguntas_teste p
                JOIN enunciados_testes e ON e.id=p.enunciado_id
                WHERE e.teste_id=? ORDER BY p.numero", [$id]);
    $class = q("SELECT f.codigo, f.nome, c.nota, c.data_avaliacao, c.observacoes
                FROM classificacoes c JOIN formandos f ON f.id=c.formando_id
                WHERE c.teste_id=? ORDER BY c.nota DESC", [$id]);
    return ['teste'=>$t,'perguntas'=>$pergs,'classificacoes'=>$class];
}

function get_pauta(): array {
    return q("SELECT * FROM vw_pauta ORDER BY disciplina, nota_final DESC");
}

// ============================================================
//  HELPERS HTML
// ============================================================
function nota_badge(float $n): string {
    if ($n >= 17) $cls = 'badge-excellent';
    elseif ($n >= 14) $cls = 'badge-good';
    elseif ($n >= 9.5) $cls = 'badge-pass';
    else $cls = 'badge-fail';
    return "<span class=\"badge $cls\">".number_format($n,1)."</span>";
}

function tipo_badge(string $t): string {
    $map = [
        'ficha'=>'Ficha','teste_escrito'=>'Teste Escrito',
        'projeto'=>'Projeto','oral'=>'Oral','pratico'=>'Prático'
    ];
    return "<span class=\"tipo-badge tipo-$t\">".(($map[$t])??$t)."</span>";
}

function render_error(string $msg): string {
    return "<!DOCTYPE html><html><body style='font-family:monospace;padding:2rem;color:#c00'>
    <h2>Erro</h2><p>$msg</p></body></html>";
}

function h(mixed $v): string { return htmlspecialchars((string)$v, ENT_QUOTES); }
function url(string $p, ?int $id=null, string $s=''): string {
    $u = "?p=$p";
    if ($id) $u .= "&id=$id";
    if ($s)  $u .= "&s=".urlencode($s);
    return $u;
}

// ============================================================
//  RENDER PAGES
// ============================================================
function render_dashboard(): string {
    $d = get_dashboard();
    $s = $d['stats'];
    ob_start(); ?>
    <div class="page-header">
        <h1>Dashboard</h1>
        <p class="subtitle">Turma TIC-2024-A &mdash; Formação em Informática</p>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon">👤</div>
            <div class="stat-value"><?=h($s['formandos'])?></div>
            <div class="stat-label">Formandos</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">📚</div>
            <div class="stat-value"><?=h($s['disciplinas'])?></div>
            <div class="stat-label">Disciplinas</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">🎓</div>
            <div class="stat-value"><?=h($s['aulas'])?></div>
            <div class="stat-label">Aulas</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">📝</div>
            <div class="stat-value"><?=h($s['testes'])?></div>
            <div class="stat-label">Avaliações</div>
        </div>
        <div class="stat-card accent">
            <div class="stat-icon">⭐</div>
            <div class="stat-value"><?=h($s['media_geral'])?></div>
            <div class="stat-label">Média Geral</div>
        </div>
        <div class="stat-card green">
            <div class="stat-icon">✅</div>
            <div class="stat-value"><?=h($s['aprovados'])?></div>
            <div class="stat-label">Aprovações</div>
        </div>
        <div class="stat-card red">
            <div class="stat-icon">❌</div>
            <div class="stat-value"><?=h($s['reprovados'])?></div>
            <div class="stat-label">Reprovações</div>
        </div>
        <div class="stat-card muted">
            <div class="stat-icon">📊</div>
            <div class="stat-value"><?=h($s['classificacoes'])?></div>
            <div class="stat-label">Notas Lançadas</div>
        </div>
    </div>

    <div class="two-col">
        <div class="card">
            <h2 class="card-title">📈 Estatísticas por Disciplina</h2>
            <table class="data-table">
                <thead><tr>
                    <th>Disciplina</th><th>Formandos</th>
                    <th>Média</th><th>✅ Apr.</th><th>❌ Rep.</th>
                    <th>Máx</th><th>Mín</th>
                </tr></thead>
                <tbody>
                <?php foreach($d['estat'] as $e): ?>
                <tr>
                    <td><strong><?=h($e['disciplina'])?></strong></td>
                    <td class="center"><?=h($e['total_formandos'])?></td>
                    <td class="center"><?=nota_badge((float)$e['media'])?></td>
                    <td class="center green-num"><?=h($e['aprovados'])?></td>
                    <td class="center red-num"><?=h($e['reprovados'])?></td>
                    <td class="center"><?=h($e['nota_maxima'])?></td>
                    <td class="center"><?=h($e['nota_minima'])?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="side-cards">
            <div class="card">
                <h2 class="card-title">🏆 Top 5 Formandos</h2>
                <ol class="top-list">
                <?php foreach($d['top5'] as $i=>$r): ?>
                    <li class="top-item rank-<?=$i+1?>">
                        <span class="rank">#<?=$i+1?></span>
                        <span class="top-name"><?=h($r['nome'])?></span>
                        <span class="top-media"><?=nota_badge((float)$r['media'])?></span>
                    </li>
                <?php endforeach; ?>
                </ol>
            </div>
            <div class="card">
                <h2 class="card-title">🕒 Últimas Classificações</h2>
                <div class="feed">
                <?php foreach($d['recentes'] as $r): ?>
                    <div class="feed-item">
                        <div class="feed-main">
                            <span class="feed-name"><?=h($r['formando'])?></span>
                            <span class="feed-disc"><?=h($r['disciplina'])?></span>
                        </div>
                        <div class="feed-sub"><?=h($r['data_avaliacao'])?></div>
                        <?=nota_badge((float)$r['nota'])?>
                    </div>
                <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    <?php return ob_get_clean();
}

function render_formandos(string $s): string {
    $rows = get_formandos($s);
    ob_start(); ?>
    <div class="page-header">
        <h1>Formandos</h1>
        <?=render_search('formandos', $s, 'Pesquisar por nome ou código…')?>
    </div>
    <div class="card">
        <table class="data-table">
            <thead><tr>
                <th>Código</th><th>Nome</th><th>Email</th>
                <th>Nascimento</th><th>Telefone</th>
                <th>Disciplinas</th><th>Média Geral</th><th></th>
            </tr></thead>
            <tbody>
            <?php foreach($rows as $r): ?>
            <tr>
                <td><code><?=h($r['codigo'])?></code></td>
                <td><strong><?=h($r['nome'])?></strong></td>
                <td class="muted"><?=h($r['email'])?></td>
                <td><?=h($r['data_nascimento'])?></td>
                <td><?=h($r['telefone'])?></td>
                <td class="center"><?=h($r['disc_count'])?></td>
                <td class="center"><?=$r['media_geral']?nota_badge((float)$r['media_geral']):'—'?></td>
                <td><a href="<?=url('formando',$r['id'])?>" class="btn-link">Ver →</a></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php return ob_get_clean();
}

function render_formando(int $id): string {
    $d = get_formando($id);
    $f = $d['formando'];
    if (!$f) return '<p class="error">Formando não encontrado.</p>';
    $media = !empty($d['notas']) ? array_sum(array_column($d['notas'],'nota_final'))/count($d['notas']) : null;
    ob_start(); ?>
    <div class="page-header">
        <a href="<?=url('formandos')?>" class="back-link">← Formandos</a>
        <h1><?=h($f['nome'])?></h1>
        <p class="subtitle"><code><?=h($f['codigo'])?></code> &nbsp;·&nbsp; <?=h($f['email'])?></p>
    </div>

    <div class="profile-grid">
        <div class="card profile-info">
            <h2 class="card-title">Dados Pessoais</h2>
            <dl class="def-list">
                <dt>Nome completo</dt><dd><?=h($f['nome'])?></dd>
                <dt>Código</dt><dd><code><?=h($f['codigo'])?></code></dd>
                <dt>Email</dt><dd><?=h($f['email'])?></dd>
                <dt>Data de Nascimento</dt><dd><?=h($f['data_nascimento'])?></dd>
                <dt>Telefone</dt><dd><?=h($f['telefone'])?></dd>
                <dt>Média Geral</dt><dd><?=$media?nota_badge($media):'—'?></dd>
            </dl>
        </div>

        <div class="card">
            <h2 class="card-title">Notas Finais por Disciplina</h2>
            <table class="data-table">
                <thead><tr>
                    <th>Cód.</th><th>Disciplina</th>
                    <th>Nota Final</th><th>Resultado</th>
                </tr></thead>
                <tbody>
                <?php foreach($d['notas'] as $n): ?>
                <tr>
                    <td><code><?=h($n['codigo'])?></code></td>
                    <td><?=h($n['disciplina'])?></td>
                    <td class="center"><?=nota_badge((float)$n['nota_final'])?></td>
                    <td class="center">
                        <span class="result-badge <?=$n['aprovado']?'res-aprov':'res-reprov'?>">
                            <?=$n['aprovado']?'✅ Aprovado':'❌ Reprovado'?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card mt">
        <h2 class="card-title">Histórico de Avaliações</h2>
        <table class="data-table">
            <thead><tr>
                <th>Data</th><th>Disciplina</th>
                <th>Avaliação</th><th>Tipo</th><th>Nota</th>
            </tr></thead>
            <tbody>
            <?php foreach($d['classificacoes'] as $c): ?>
            <tr>
                <td><?=h($c['data_avaliacao'])?></td>
                <td><?=h($c['disciplina'])?></td>
                <td><?=h($c['titulo'])?></td>
                <td><?=tipo_badge($c['tipo'])?></td>
                <td class="center"><?=nota_badge((float)$c['nota'])?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php return ob_get_clean();
}

function render_disciplinas(): string {
    $rows = get_disciplinas();
    ob_start(); ?>
    <div class="page-header">
        <h1>Disciplinas</h1>
        <p class="subtitle"><?=count($rows)?> disciplinas do plano curricular</p>
    </div>
    <div class="disc-grid">
    <?php foreach($rows as $r): ?>
        <a href="<?=url('disciplina',$r['id'])?>" class="disc-card">
            <div class="disc-codigo"><?=h($r['codigo'])?></div>
            <div class="disc-nome"><?=h($r['nome'])?></div>
            <div class="disc-desc"><?=h(mb_strimwidth($r['descricao']??'',0,80,'…'))?></div>
            <div class="disc-meta">
                <span>⏱ <?=h($r['carga_horaria'])?>h</span>
                <span>📖 <?=h($r['num_aulas'])?> aulas</span>
                <span>📝 <?=h($r['num_testes'])?> testes</span>
                <?php if($r['media']): ?>
                <span>⭐ <?=h($r['media'])?></span>
                <?php endif; ?>
            </div>
        </a>
    <?php endforeach; ?>
    </div>
    <?php return ob_get_clean();
}

function render_disciplina(int $id): string {
    $d = get_disciplina($id);
    $disc = $d['disciplina'];
    if (!$disc) return '<p class="error">Disciplina não encontrada.</p>';
    $aprovados = count(array_filter($d['pauta'], fn($r)=>$r['resultado']==='Aprovado'));
    ob_start(); ?>
    <div class="page-header">
        <a href="<?=url('disciplinas')?>" class="back-link">← Disciplinas</a>
        <h1><?=h($disc['nome'])?></h1>
        <p class="subtitle"><code><?=h($disc['codigo'])?></code> &nbsp;·&nbsp;
        <?=h($disc['carga_horaria'])?> horas &nbsp;·&nbsp;
        <?=$aprovados?>/<?=count($d['pauta'])?> aprovados</p>
    </div>
    <p class="disc-descricao"><?=h($disc['descricao'])?></p>

    <div class="tabs-container" x-data="tabs()">
        <div class="tabs-nav">
            <button class="tab-btn active" data-tab="sumarios">📋 Sumários (<?=count($d['aulas'])?>)</button>
            <button class="tab-btn" data-tab="testes">📝 Testes (<?=count($d['testes'])?>)</button>
            <button class="tab-btn" data-tab="pauta">🏆 Pauta (<?=count($d['pauta'])?>)</button>
        </div>

        <div class="tab-panel active" id="tab-sumarios">
        <?php if(empty($d['aulas'])): ?>
            <p class="empty">Sem aulas registadas.</p>
        <?php else: foreach($d['aulas'] as $a): ?>
            <div class="sumario-card">
                <div class="sumario-header">
                    <span class="aula-num">Aula <?=h($a['numero_aula'])?></span>
                    <span class="aula-data"><?=h($a['data_aula'])?></span>
                    <span class="aula-hora"><?=h($a['hora_inicio'])?> – <?=h($a['hora_fim'])?></span>
                    <span class="aula-sala">🏛 <?=h($a['sala'])?></span>
                    <span class="aula-formador">👤 <?=h($a['formador'])?></span>
                </div>
                <?php if($a['conteudo']): ?>
                <div class="sumario-body">
                    <p><?=nl2br(h($a['conteudo']))?></p>
                    <?php if($a['recursos']): ?>
                    <div class="recursos">
                        <strong>Recursos:</strong> <?=h($a['recursos'])?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php else: ?>
                <div class="sumario-body empty-sum">Sumário não registado.</div>
                <?php endif; ?>
            </div>
        <?php endforeach; endif; ?>
        </div>

        <div class="tab-panel" id="tab-testes">
        <?php if(empty($d['testes'])): ?>
            <p class="empty">Sem testes registados.</p>
        <?php else: foreach($d['testes'] as $t): ?>
            <div class="teste-card-detail">
                <div class="teste-head">
                    <div>
                        <span class="teste-titulo"><?=h($t['titulo'])?></span>
                        <?=tipo_badge($t['tipo'])?>
                    </div>
                    <div class="teste-meta-right">
                        <span>📅 <?=h($t['data_realizacao']??'—')?></span>
                        <?php if($t['duracao_min']): ?>
                        <span>⏱ <?=h($t['duracao_min'])?> min</span>
                        <?php endif; ?>
                        <span>⚖️ <?=h($t['cotacao_total'])?> val.</span>
                        <a href="<?=url('teste',$t['id'])?>" class="btn-link">Ver enunciado →</a>
                    </div>
                </div>
                <?php if($t['introducao']): ?>
                <p class="teste-intro"><?=h($t['introducao'])?></p>
                <?php endif; ?>
            </div>
        <?php endforeach; endif; ?>
        </div>

        <div class="tab-panel" id="tab-pauta">
        <?php if(empty($d['pauta'])): ?>
            <p class="empty">Sem notas lançadas.</p>
        <?php else: ?>
            <table class="data-table">
                <thead><tr>
                    <th>#</th><th>Código</th><th>Formando</th>
                    <th>Nota Final</th><th>Resultado</th>
                </tr></thead>
                <tbody>
                <?php foreach($d['pauta'] as $i=>$r): ?>
                <tr class="<?=$r['resultado']==='Aprovado'?'row-pass':'row-fail'?>">
                    <td class="center rank-cell"><?=$i+1?></td>
                    <td><code><?=h($r['codigo'])?></code></td>
                    <td><a href="<?=url('formando')?>&id=<?=/*need id*/''?>"><?=h($r['nome'])?></a></td>
                    <td class="center"><?=nota_badge((float)$r['nota_final'])?></td>
                    <td class="center">
                        <span class="result-badge <?=$r['resultado']==='Aprovado'?'res-aprov':'res-reprov'?>">
                            <?=$r['resultado']==='Aprovado'?'✅ Aprovado':'❌ Reprovado'?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        </div>
    </div>
    <?php return ob_get_clean();
}

function render_sumarios(string $s): string {
    $rows = get_sumarios($s);
    ob_start(); ?>
    <div class="page-header">
        <h1>Sumários</h1>
        <?=render_search('sumarios', $s, 'Pesquisar conteúdo, disciplina ou formador…')?>
    </div>
    <?php foreach($rows as $r): ?>
    <div class="sumario-card">
        <div class="sumario-header">
            <span class="disc-pill"><?=h($r['codigo'])?></span>
            <strong><?=h($r['disciplina'])?></strong>
            <span class="aula-num">Aula <?=h($r['numero_aula'])?></span>
            <span class="aula-data"><?=h($r['data_aula'])?></span>
            <span class="aula-hora"><?=h($r['hora_inicio'])?> – <?=h($r['hora_fim'])?></span>
            <span class="aula-sala">🏛 <?=h($r['sala'])?></span>
            <span class="aula-formador">👤 <?=h($r['formador'])?></span>
        </div>
        <div class="sumario-body">
            <p><?=nl2br(h($r['conteudo']))?></p>
            <?php if($r['recursos']): ?>
            <div class="recursos"><strong>Recursos:</strong> <?=h($r['recursos'])?></div>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach;
    if(empty($rows)) echo '<div class="card"><p class="empty">Nenhum sumário encontrado.</p></div>';
    return ob_get_clean();
}

function render_testes(string $s): string {
    $rows = get_testes($s);
    ob_start(); ?>
    <div class="page-header">
        <h1>Enunciados &amp; Testes</h1>
        <?=render_search('testes', $s, 'Pesquisar título, disciplina ou formador…')?>
    </div>
    <div class="testes-list">
    <?php foreach($rows as $r): ?>
        <div class="card teste-card">
            <div class="teste-head">
                <div class="teste-left">
                    <?=tipo_badge($r['tipo'])?>
                    <span class="teste-titulo"><?=h($r['titulo'])?></span>
                    <span class="muted"><?=h($r['disciplina'])?></span>
                </div>
                <div class="teste-right">
                    <span>📅 <?=h($r['data_realizacao']??'—')?></span>
                    <?php if($r['duracao_min']): ?><span>⏱ <?=h($r['duracao_min'])?> min</span><?php endif;?>
                    <span>⚖️ <?=h($r['cotacao_total'])?> val.</span>
                    <span>❓ <?=h($r['num_perguntas'])?> pergs.</span>
                    <?php if($r['media_nota']): ?><span><?=nota_badge((float)$r['media_nota'])?> média</span><?php endif;?>
                    <a href="<?=url('teste',$r['id'])?>" class="btn-link">Enunciado →</a>
                </div>
            </div>
            <?php if($r['introducao']): ?>
            <p class="teste-intro"><?=h(mb_strimwidth($r['introducao'],0,180,'…'))?></p>
            <?php endif; ?>
        </div>
    <?php endforeach;
    if(empty($rows)) echo '<div class="card"><p class="empty">Nenhum teste encontrado.</p></div>';
    ?>
    </div>
    <?php return ob_get_clean();
}

function render_teste(int $id): string {
    $d = get_teste($id);
    $t = $d['teste'];
    if (!$t) return '<p class="error">Teste não encontrado.</p>';
    $media = !empty($d['classificacoes']) ? array_sum(array_column($d['classificacoes'],'nota'))/count($d['classificacoes']) : null;
    ob_start(); ?>
    <div class="page-header">
        <a href="<?=url('testes')?>" class="back-link">← Testes</a>
        <h1><?=h($t['titulo'])?></h1>
        <p class="subtitle">
            <?=tipo_badge($t['tipo'])?>
            <?=h($t['disciplina'])?> &nbsp;·&nbsp;
            <?=h($t['formador'])?> &nbsp;·&nbsp;
            <?=h($t['data_realizacao']??'—')?>
            <?php if($t['duracao_min']): ?>&nbsp;·&nbsp; ⏱ <?=h($t['duracao_min'])?> min<?php endif;?>
            &nbsp;·&nbsp; ⚖️ <?=h($t['cotacao_total'])?> valores
        </p>
    </div>

    <div class="two-col-asym">
        <div>
            <?php if($t['introducao']||$t['instrucoes']): ?>
            <div class="card mb">
                <h2 class="card-title">📄 Enunciado</h2>
                <?php if($t['introducao']): ?>
                <div class="enunciado-intro"><?=h($t['introducao'])?></div>
                <?php endif; ?>
                <?php if($t['instrucoes']): ?>
                <div class="instrucoes-box">
                    <strong>Instruções:</strong><br><?=nl2br(h($t['instrucoes']))?>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <?php if(!empty($d['perguntas'])): ?>
            <div class="card">
                <h2 class="card-title">❓ Perguntas (<?=count($d['perguntas'])?>)</h2>
                <ol class="perguntas-list">
                <?php foreach($d['perguntas'] as $p): ?>
                    <li class="pergunta-item">
                        <div class="perg-head">
                            <span class="perg-tipo"><?=tipo_badge($p['tipo'])?></span>
                            <span class="perg-cot">⚖️ <?=h($p['cotacao'])?> val.</span>
                        </div>
                        <p class="perg-texto"><?=nl2br(h($p['texto_pergunta']))?></p>
                    </li>
                <?php endforeach; ?>
                </ol>
            </div>
            <?php endif; ?>
        </div>

        <div>
            <div class="card">
                <h2 class="card-title">📊 Classificações</h2>
                <?php if($media): ?>
                <div class="media-box">
                    <span>Média da turma</span>
                    <?=nota_badge($media)?>
                </div>
                <?php endif; ?>
                <table class="data-table">
                    <thead><tr><th>#</th><th>Formando</th><th>Nota</th></tr></thead>
                    <tbody>
                    <?php foreach($d['classificacoes'] as $i=>$c): ?>
                    <tr>
                        <td class="center muted"><?=$i+1?></td>
                        <td><?=h($c['nome'])?></td>
                        <td class="center"><?=nota_badge((float)$c['nota'])?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if(empty($d['classificacoes'])): ?>
                    <tr><td colspan="3" class="empty">Sem classificações.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php return ob_get_clean();
}

function render_pauta(): string {
    $rows = get_pauta();
    $by_disc = [];
    foreach($rows as $r) $by_disc[$r['disciplina']][] = $r;
    ob_start(); ?>
    <div class="page-header">
        <h1>Pauta Geral</h1>
        <p class="subtitle">Resultados finais por disciplina</p>
    </div>
    <?php foreach($by_disc as $disc => $alunos):
        $aprov = count(array_filter($alunos, fn($a)=>$a['resultado']==='Aprovado'));
        $media = round(array_sum(array_column($alunos,'nota_final'))/count($alunos),1);
    ?>
    <div class="card mb">
        <div class="pauta-disc-header">
            <h2 class="pauta-disc-title"><?=h($disc)?></h2>
            <div class="pauta-disc-stats">
                <span><?=nota_badge($media)?> média</span>
                <span class="green-num">✅ <?=$aprov?> apr.</span>
                <span class="red-num">❌ <?=count($alunos)-$aprov?> rep.</span>
            </div>
        </div>
        <table class="data-table">
            <thead><tr>
                <th>#</th><th>Código</th><th>Formando</th>
                <th>Nota Final</th><th>Resultado</th>
            </tr></thead>
            <tbody>
            <?php foreach($alunos as $i=>$a): ?>
            <tr class="<?=$a['resultado']==='Aprovado'?'row-pass':'row-fail'?>">
                <td class="center muted"><?=$i+1?></td>
                <td><code><?=h($a['formando'])?></code></td>
                <td><?=h($a['nome_formando'])?></td>
                <td class="center"><?=nota_badge((float)$a['nota_final'])?></td>
                <td class="center">
                    <span class="result-badge <?=$a['resultado']==='Aprovado'?'res-aprov':'res-reprov'?>">
                        <?=$a['resultado']==='Aprovado'?'✅ Aprovado':'❌ Reprovado'?>
                    </span>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endforeach;
    return ob_get_clean();
}

function render_search(string $page, string $val, string $placeholder): string {
    return '<form method="get" class="search-form">
        <input type="hidden" name="p" value="'.h($page).'">
        <input type="text" name="s" value="'.h($val).'" placeholder="'.h($placeholder).'" class="search-input">
        <button type="submit" class="search-btn">🔍</button>
    </form>';
}

// ============================================================
//  DISPATCH
// ============================================================
$content = match(true) {
    $page === 'dashboard'   => render_dashboard(),
    $page === 'formandos'   => render_formandos($search),
    $page === 'formando'    => render_formando($id ?? 0),
    $page === 'disciplinas' => render_disciplinas(),
    $page === 'disciplina'  => render_disciplina($id ?? 0),
    $page === 'sumarios'    => render_sumarios($search),
    $page === 'testes'      => render_testes($search),
    $page === 'teste'       => render_teste($id ?? 0),
    $page === 'pauta'       => render_pauta(),
    default => render_dashboard(),
};

$nav_items = [
    'dashboard'  => ['icon'=>'⊞',   'label'=>'Dashboard'],
    'formandos'  => ['icon'=>'👤',  'label'=>'Formandos'],
    'disciplinas'=> ['icon'=>'📚',  'label'=>'Disciplinas'],
    'sumarios'   => ['icon'=>'📋',  'label'=>'Sumários'],
    'testes'     => ['icon'=>'📝',  'label'=>'Testes'],
    'pauta'      => ['icon'=>'🏆',  'label'=>'Pauta'],
];
?>
<!DOCTYPE html>
<html lang="pt">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>FormaTIC – Gestão de Formação</title>
<style>
/* ---- RESET & BASE ---- */
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html{font-size:15px;-webkit-font-smoothing:antialiased}
body{font-family:'Courier New',Courier,monospace;background:#0d0f11;color:#c8cfd8;min-height:100vh;display:flex}

/* ---- SIDEBAR ---- */
.sidebar{width:220px;min-height:100vh;background:#111418;border-right:1px solid #1e2329;display:flex;flex-direction:column;position:sticky;top:0;height:100vh;overflow-y:auto;flex-shrink:0}
.sidebar-brand{padding:1.4rem 1.2rem 1rem;border-bottom:1px solid #1e2329}
.brand-name{font-size:1.3rem;font-weight:700;color:#e2e8f0;letter-spacing:-0.5px}
.brand-sub{font-size:.7rem;color:#4a5568;text-transform:uppercase;letter-spacing:.1em;margin-top:2px}
.nav{padding:.6rem 0;flex:1}
.nav-item{display:flex;align-items:center;gap:.65rem;padding:.55rem 1.2rem;color:#6b7280;text-decoration:none;font-size:.82rem;font-weight:500;letter-spacing:.02em;transition:all .15s;border-left:3px solid transparent}
.nav-item:hover{color:#c8cfd8;background:#161b22}
.nav-item.active{color:#60a5fa;background:#1a2235;border-left-color:#60a5fa}
.nav-icon{font-size:.95rem;width:1.2rem;text-align:center}
.sidebar-footer{padding:.8rem 1.2rem;border-top:1px solid #1e2329;font-size:.7rem;color:#374151}

/* ---- MAIN ---- */
.main{flex:1;overflow-x:hidden;min-width:0}
.main-inner{max-width:1100px;margin:0 auto;padding:2rem 1.8rem}

/* ---- PAGE HEADER ---- */
.page-header{margin-bottom:1.8rem}
.back-link{font-size:.78rem;color:#60a5fa;text-decoration:none;display:inline-block;margin-bottom:.6rem}
.back-link:hover{text-decoration:underline}
.page-header h1{font-size:1.7rem;font-weight:700;color:#e2e8f0;letter-spacing:-0.5px}
.subtitle{margin-top:.4rem;color:#6b7280;font-size:.82rem}
.disc-descricao{color:#9ca3af;font-size:.88rem;margin-bottom:1.5rem;max-width:700px;line-height:1.6}

/* ---- CARDS ---- */
.card{background:#111418;border:1px solid #1e2329;border-radius:8px;padding:1.2rem 1.4rem;margin-bottom:1.2rem}
.card-title{font-size:.88rem;font-weight:600;color:#9ca3af;text-transform:uppercase;letter-spacing:.08em;margin-bottom:1rem;padding-bottom:.6rem;border-bottom:1px solid #1e2329}
.mt{margin-top:1.2rem}
.mb{margin-bottom:1.2rem}

/* ---- STAT GRID ---- */
.stats-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:.9rem;margin-bottom:1.5rem}
.stat-card{background:#111418;border:1px solid #1e2329;border-radius:8px;padding:1rem 1.1rem;text-align:center;transition:border-color .2s}
.stat-card:hover{border-color:#374151}
.stat-card.accent{border-color:#1e3a5f;background:#0d1e35}
.stat-card.green{border-color:#14532d;background:#071a10}
.stat-card.red{border-color:#450a0a;background:#1a0606}
.stat-card.muted{background:#0d0f11}
.stat-icon{font-size:1.3rem;margin-bottom:.4rem}
.stat-value{font-size:1.8rem;font-weight:700;color:#e2e8f0;line-height:1}
.stat-label{font-size:.72rem;color:#6b7280;text-transform:uppercase;letter-spacing:.06em;margin-top:.3rem}

/* ---- TWO COL ---- */
.two-col{display:grid;grid-template-columns:1fr 360px;gap:1.2rem;align-items:start}
.two-col-asym{display:grid;grid-template-columns:1fr 340px;gap:1.2rem;align-items:start}
.side-cards{display:flex;flex-direction:column;gap:1.2rem}

/* ---- TABLES ---- */
.data-table{width:100%;border-collapse:collapse;font-size:.82rem}
.data-table th{text-align:left;padding:.5rem .7rem;color:#6b7280;font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;border-bottom:1px solid #1e2329;font-weight:600}
.data-table td{padding:.55rem .7rem;border-bottom:1px solid #0d0f11;color:#c8cfd8;vertical-align:middle}
.data-table tr:last-child td{border-bottom:none}
.data-table tr:hover td{background:#161b22}
.row-pass td{background:rgba(20,83,45,.08)}
.row-fail td{background:rgba(69,10,10,.08)}
.rank-cell{color:#6b7280;font-size:.75rem}
.center{text-align:center}
.muted{color:#6b7280}
a{color:#60a5fa;text-decoration:none}
a:hover{text-decoration:underline}

/* ---- BADGES ---- */
.badge{display:inline-block;padding:.18rem .55rem;border-radius:4px;font-size:.8rem;font-weight:700;font-family:'Courier New',monospace}
.badge-excellent{background:#064e3b;color:#6ee7b7;border:1px solid #065f46}
.badge-good{background:#1e3a5f;color:#93c5fd;border:1px solid #1e40af}
.badge-pass{background:#1a2e05;color:#86efac;border:1px solid #166534}
.badge-fail{background:#450a0a;color:#fca5a5;border:1px solid #7f1d1d}
.result-badge{font-size:.75rem;padding:.15rem .45rem;border-radius:3px}
.res-aprov{background:#14532d;color:#86efac}
.res-reprov{background:#450a0a;color:#fca5a5}
.tipo-badge{font-size:.7rem;padding:.15rem .45rem;border-radius:3px;text-transform:uppercase;letter-spacing:.04em;margin-right:.4rem}
.tipo-ficha{background:#1e3a5f;color:#93c5fd}
.tipo-teste_escrito{background:#3b1d5f;color:#c4b5fd}
.tipo-projeto{background:#1a2e05;color:#86efac}
.tipo-pratico{background:#1a2505;color:#a3e635}
.tipo-oral{background:#2d1505;color:#fdba74}
.tipo-escolha_multipla{background:#1e3a5f;color:#93c5fd}
.tipo-verdadeiro_falso{background:#3b1d5f;color:#c4b5fd}
.tipo-desenvolvimento{background:#062520;color:#5eead4}
.tipo-pratica{background:#1a2505;color:#a3e635}

/* ---- SEARCH ---- */
.search-form{display:flex;gap:.5rem;margin-top:.8rem;max-width:480px}
.search-input{flex:1;background:#111418;border:1px solid #1e2329;border-radius:5px;padding:.45rem .8rem;color:#c8cfd8;font-family:inherit;font-size:.85rem;outline:none}
.search-input:focus{border-color:#374151}
.search-btn{background:#1e2329;border:1px solid #374151;border-radius:5px;padding:.45rem .8rem;cursor:pointer;font-size:.85rem;color:#c8cfd8}
.search-btn:hover{background:#374151}
.btn-link{font-size:.75rem;color:#60a5fa;white-space:nowrap;padding:.2rem .5rem;border:1px solid #1e3a5f;border-radius:4px;background:#0d1e35}
.btn-link:hover{background:#1e3a5f;text-decoration:none}

/* ---- TOP LIST ---- */
.top-list{list-style:none}
.top-item{display:flex;align-items:center;gap:.6rem;padding:.4rem 0;border-bottom:1px solid #0d0f11;font-size:.82rem}
.top-item:last-child{border-bottom:none}
.rank{font-size:.7rem;font-weight:700;color:#6b7280;min-width:1.5rem}
.rank-1 .rank{color:#f59e0b}
.rank-2 .rank{color:#9ca3af}
.rank-3 .rank{color:#92400e}
.top-name{flex:1}
.top-media{}

/* ---- FEED ---- */
.feed{}
.feed-item{display:flex;align-items:center;gap:.5rem;padding:.45rem 0;border-bottom:1px solid #0d0f11;font-size:.78rem}
.feed-item:last-child{border-bottom:none}
.feed-main{flex:1;display:flex;flex-direction:column;gap:.1rem}
.feed-name{color:#c8cfd8;font-weight:600}
.feed-disc{color:#6b7280}
.feed-sub{color:#4a5568;font-size:.7rem;white-space:nowrap}

/* ---- SUMÁRIOS ---- */
.sumario-card{background:#111418;border:1px solid #1e2329;border-radius:7px;margin-bottom:.9rem;overflow:hidden}
.sumario-header{display:flex;flex-wrap:wrap;gap:.6rem;align-items:center;padding:.65rem 1rem;background:#161b22;border-bottom:1px solid #1e2329;font-size:.78rem}
.aula-num{font-weight:700;color:#60a5fa}
.aula-data{color:#9ca3af}
.aula-hora{color:#6b7280}
.aula-sala{color:#6b7280}
.aula-formador{color:#9ca3af}
.disc-pill{background:#1e3a5f;color:#93c5fd;padding:.1rem .45rem;border-radius:3px;font-size:.72rem;font-weight:700}
.sumario-body{padding:.8rem 1rem;font-size:.84rem;line-height:1.7;color:#c8cfd8}
.empty-sum{color:#4a5568;font-style:italic}
.recursos{margin-top:.6rem;padding:.4rem .7rem;background:#0d0f11;border-radius:4px;font-size:.77rem;color:#6b7280}

/* ---- DISCIPLINAS GRID ---- */
.disc-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:1rem}
.disc-card{background:#111418;border:1px solid #1e2329;border-radius:8px;padding:1.1rem 1.2rem;text-decoration:none;color:inherit;transition:border-color .2s,background .2s;display:block}
.disc-card:hover{border-color:#374151;background:#161b22;text-decoration:none}
.disc-codigo{font-size:.72rem;font-weight:700;color:#60a5fa;letter-spacing:.08em;text-transform:uppercase;margin-bottom:.4rem}
.disc-nome{font-size:.98rem;font-weight:700;color:#e2e8f0;margin-bottom:.4rem}
.disc-desc{font-size:.78rem;color:#6b7280;margin-bottom:.7rem;line-height:1.5}
.disc-meta{display:flex;flex-wrap:wrap;gap:.5rem;font-size:.73rem;color:#4a5568}

/* ---- TABS ---- */
.tabs-container{}
.tabs-nav{display:flex;gap:.3rem;margin-bottom:1rem;border-bottom:1px solid #1e2329;padding-bottom:.3rem}
.tab-btn{background:none;border:1px solid transparent;border-radius:5px 5px 0 0;padding:.45rem .9rem;cursor:pointer;color:#6b7280;font-family:inherit;font-size:.8rem;font-weight:600}
.tab-btn:hover{color:#c8cfd8}
.tab-btn.active{color:#60a5fa;border-color:#1e2329;border-bottom-color:#111418;background:#111418}
.tab-panel{display:none}.tab-panel.active{display:block}

/* ---- TESTES ---- */
.testes-list{}
.teste-card{margin-bottom:.9rem}
.teste-card-detail{background:#161b22;border:1px solid #1e2329;border-radius:6px;margin-bottom:.8rem;padding:.8rem 1rem}
.teste-head{display:flex;justify-content:space-between;align-items:flex-start;gap:1rem;flex-wrap:wrap}
.teste-left,.teste-right{display:flex;flex-wrap:wrap;gap:.5rem;align-items:center;font-size:.78rem}
.teste-titulo{font-weight:700;color:#e2e8f0;font-size:.88rem}
.teste-meta-right{display:flex;flex-wrap:wrap;gap:.5rem;align-items:center;font-size:.78rem;color:#6b7280}
.teste-intro{margin-top:.6rem;font-size:.8rem;color:#9ca3af;line-height:1.5}
.enunciado-intro{font-size:.88rem;color:#c8cfd8;line-height:1.7;margin-bottom:.8rem}
.instrucoes-box{background:#0d0f11;border-left:3px solid #374151;padding:.6rem .9rem;font-size:.82rem;color:#9ca3af;line-height:1.6;border-radius:0 4px 4px 0}
.perguntas-list{list-style:none;counter-reset:perg}
.pergunta-item{padding:.9rem 0;border-bottom:1px solid #1e2329}
.pergunta-item:last-child{border-bottom:none}
.perg-head{display:flex;gap:.5rem;align-items:center;margin-bottom:.4rem}
.perg-cot{font-size:.72rem;color:#6b7280;margin-left:auto}
.perg-texto{font-size:.85rem;color:#c8cfd8;line-height:1.6}

/* ---- PAUTA ---- */
.pauta-disc-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:.8rem}
.pauta-disc-title{font-size:1rem;font-weight:700;color:#e2e8f0}
.pauta-disc-stats{display:flex;gap:.8rem;font-size:.8rem;align-items:center}
.green-num{color:#6ee7b7}
.red-num{color:#fca5a5}

/* ---- PROFILE ---- */
.profile-grid{display:grid;grid-template-columns:280px 1fr;gap:1.2rem;margin-bottom:1.2rem}
.profile-info{}
.def-list{font-size:.82rem}
.def-list dt{color:#6b7280;font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;margin-top:.7rem;margin-bottom:.15rem}
.def-list dt:first-child{margin-top:0}
.def-list dd{color:#c8cfd8}
.media-box{display:flex;justify-content:space-between;align-items:center;padding:.5rem .7rem;background:#0d0f11;border-radius:5px;margin-bottom:.8rem;font-size:.82rem;color:#9ca3af}

/* ---- MISC ---- */
code{background:#1e2329;padding:.1rem .4rem;border-radius:3px;font-size:.82rem;color:#a5f3fc}
.empty{color:#4a5568;font-style:italic;text-align:center;padding:1.5rem}
.error{color:#fca5a5;padding:1rem}

/* ---- RESPONSIVE ---- */
@media(max-width:900px){
  .stats-grid{grid-template-columns:repeat(2,1fr)}
  .two-col,.two-col-asym,.profile-grid{grid-template-columns:1fr}
  .sidebar{width:56px}.nav-item span.nav-label{display:none}
  .brand-sub,.brand-sub{display:none}.brand-name{font-size:.9rem}
}
</style>
</head>
<body>

<nav class="sidebar">
    <div class="sidebar-brand">
        <div class="brand-name">FormaTIC</div>
        <div class="brand-sub">Gestão de Formação</div>
    </div>
    <div class="nav">
    <?php foreach($nav_items as $key => $item): ?>
        <a href="<?=url($key)?>"
           class="nav-item <?=($page===$key||($page==='formando'&&$key==='formandos')||($page==='disciplina'&&$key==='disciplinas')||($page==='teste'&&$key==='testes'))?'active':''?>">
            <span class="nav-icon"><?=$item['icon']?></span>
            <span class="nav-label"><?=$item['label']?></span>
        </a>
    <?php endforeach; ?>
    </div>
    <div class="sidebar-footer">
        <div style="margin-bottom:.5rem;color:#6b7280">👤 <?=htmlspecialchars($_SESSION['user'])?></div>
        <form method="post">
            <button name="logout" value="1" style="background:none;border:1px solid #374151;border-radius:4px;padding:.25rem .6rem;color:#6b7280;font-family:inherit;font-size:.7rem;cursor:pointer;width:100%">Sair →</button>
        </form>
    </div>
</nav>

<main class="main">
    <div class="main-inner">
        <?=$content?>
    </div>
</main>

<script>
// Tabs
document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        const container = btn.closest('.tabs-container');
        container.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        container.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
        btn.classList.add('active');
        const target = container.querySelector('#tab-' + btn.dataset.tab);
        if (target) target.classList.add('active');
    });
});
</script>
</body>
</html>
