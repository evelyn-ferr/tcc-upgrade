<?php
require_once 'config.php';
require_once 'auth.php';

// Requer login
requireLogin();

$db = getDB();
$usuarioId = getUserId();

// Determinar qual paciente exibir baseado no tipo de usuário
if (isFamiliar()) {
    $stmt = $db->prepare("
        SELECT p.id FROM pacientes p
        INNER JOIN vinculos_familiar vf ON p.id = vf.paciente_id
        WHERE vf.usuario_id = ?
        LIMIT 1
    ");
    $stmt->execute([$usuarioId]);
} elseif (isCuidador()) {
    $stmt = $db->prepare("
        SELECT p.id FROM pacientes p
        INNER JOIN vinculos_cuidador vc ON p.id = vc.paciente_id
        WHERE vc.cuidador_id = ? AND vc.status = 'ativo'
        LIMIT 1
    ");
    $stmt->execute([$usuarioId]);
} else {
    die("Tipo de usuário não autorizado");
}

$result = $stmt->fetch();
if (!$result) {
    die("Nenhum paciente encontrado");
}

$pacienteId = $result['id'];

// Buscar informações do paciente
$stmt = $db->prepare("SELECT * FROM pacientes WHERE id = ?");
$stmt->execute([$pacienteId]);
$paciente = $stmt->fetch();

// Buscar estatísticas
$stmt = $db->prepare("SELECT COUNT(*) as total FROM sinais_vitais WHERE paciente_id = ?");
$stmt->execute([$pacienteId]);
$totalSinais = $stmt->fetch()['total'];

$stmt = $db->prepare("SELECT COUNT(*) as total FROM agendamentos WHERE paciente_id = ? AND status = 'realizado'");
$stmt->execute([$pacienteId]);
$totalConsultas = $stmt->fetch()['total'];

$stmt = $db->prepare("SELECT COUNT(*) as total FROM evolucoes WHERE paciente_id = ?");
$stmt->execute([$pacienteId]);
$totalEvolucoes = $stmt->fetch()['total'];

// Filtros
$periodo = $_GET['periodo'] ?? '30';
$categoria = $_GET['categoria'] ?? 'todos';

// Buscar histórico com filtros
$whereClause = "WHERE paciente_id = ?";
$params = [$pacienteId];

if ($periodo !== 'todos') {
    $whereClause .= " AND data_registro >= DATE_SUB(NOW(), INTERVAL ? DAY)";
    $params[] = (int)$periodo;
}

if ($categoria !== 'todos') {
    $whereClause .= " AND tipo = ?";
    $params[] = $categoria;
}

$stmt = $db->prepare("
    SELECT * FROM evolucoes
    $whereClause
    ORDER BY data_registro DESC
    LIMIT 50
");
$stmt->execute($params);
$historico = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CuidarBem - Histórico Médico</title>
    <link rel="stylesheet" href="style-historico.css">
</head>
<body>
    <header>
        <nav class="container">
            <div class="logo">🏥 CuidarBem</div>
            <ul class="nav-links">
                <li><a href="<?= isFamiliar() ? 'perfil-familiar.php' : 'perfil-cuidador.php' ?>">Voltar</a></li>
                <li><a href="logout.php">Sair</a></li>
            </ul>
            <div class="user-info">
                <span><?= isFamiliar() ? '👨' : '👨‍⚕️' ?></span>
                <div>
                    <div style="font-weight: bold;"><?= sanitize(getUserName()) ?></div>
                    <div style="font-size: 0.8rem; opacity: 0.8;">
                        <?= isFamiliar() ? 'Familiar' : 'Cuidador' ?>
                    </div>
                </div>
            </div>
        </nav>
    </header>

    <main class="main-content">
        <div class="container">
            <div class="page-header">
                <div>
                    <h1 class="page-title">Histórico Médico</h1>
                    <p style="color: #7f8c8d; margin-top: 0.5rem; position: relative; z-index: 2;">
                        <?= sanitize($paciente['nome']) ?>
                    </p>
                </div>
                <a href="<?= isFamiliar() ? 'perfil-familiar.php' : 'perfil-cuidador.php' ?>" class="back-btn">
                    ← Voltar
                </a>
            </div>

            <div class="stats-grid">
                <div class="stat-card">
                    <span class="stat-number"><?= $totalSinais ?></span>
                    <span class="stat-label">Sinais Vitais Registrados</span>
                </div>
                <div class="stat-card">
                    <span class="stat-number"><?= $totalConsultas ?></span>
                    <span class="stat-label">Consultas Realizadas</span>
                </div>
                <div class="stat-card">
                    <span class="stat-number"><?= $totalEvolucoes ?></span>
                    <span class="stat-label">Evoluções Registradas</span>
                </div>
            </div>

            <div class="filters-section">
                <h3 style="margin-bottom: 1.5rem; color: var(--texto-escuro); position: relative; z-index: 2;">
                    Filtros de Pesquisa
                </h3>
                <form method="GET" class="filters">
                    <div class="filter-group">
                        <label>📅 Período</label>
                        <select name="periodo">
                            <option value="7" <?= $periodo == '7' ? 'selected' : '' ?>>Última semana</option>
                            <option value="30" <?= $periodo == '30' ? 'selected' : '' ?>>Último mês</option>
                            <option value="90" <?= $periodo == '90' ? 'selected' : '' ?>>Últimos 3 meses</option>
                            <option value="todos" <?= $periodo == 'todos' ? 'selected' : '' ?>>Todo o período</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label>📋 Categoria</label>
                        <select name="categoria">
                            <option value="todos" <?= $categoria == 'todos' ? 'selected' : '' ?>>Todas</option>
                            <option value="evolucao" <?= $categoria == 'evolucao' ? 'selected' : '' ?>>Evoluções</option>
                            <option value="intercorrencia" <?= $categoria == 'intercorrencia' ? 'selected' : '' ?>>Intercorrências</option>
                            <option value="procedimento" <?= $categoria == 'procedimento' ? 'selected' : '' ?>>Procedimentos</option>
                            <option value="observacao" <?= $categoria == 'observacao' ? 'selected' : '' ?>>Observações</option>
                        </select>
                    </div>
                </form>
                
                <div class="filter-buttons">
                    <button type="submit" class="btn btn-primary" onclick="document.querySelector('form').submit()">
                        🔍 Aplicar Filtros
                    </button>
                    <a href="historico.php" class="btn btn-secondary">
                        🔄 Limpar Filtros
                    </a>
                </div>
            </div>

            <?php if (empty($historico)): ?>
            <div class="alert">
                <div class="alert-icon">ℹ️</div>
                <div class="alert-content">
                    <div class="alert-title">Nenhum registro encontrado</div>
                    <div class="alert-text">
                        Não há registros para o período e filtros selecionados.
                        Tente ajustar os filtros ou selecione "Todo o período".
                    </div>
                </div>
            </div>
            <?php else: ?>
            <div class="timeline">
                <h3 class="timeline-title">Linha do Tempo</h3>
                
                <?php foreach ($historico as $item): ?>
                <div class="timeline-item">
                    <div class="timeline-icon">
                        <?php
                        $icones = [
                            'evolucao' => '📝',
                            'intercorrencia' => '⚠️',
                            'procedimento' => '🩺',
                            'observacao' => '💭'
                        ];
                        echo $icones[$item['tipo']] ?? '📋';
                        ?>
                    </div>
                    <div class="timeline-content">
                        <div class="timeline-header">
                            <div class="timeline-item-title"><?= ucfirst($item['tipo']) ?></div>
                            <div class="timeline-time"><?= formatarDataHoraBR($item['data_registro']) ?></div>
                        </div>
                        <div class="timeline-body">
                            <?= nl2br(sanitize($item['descricao'])) ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </main>

    <footer style="background: #2c3e50; color: white; text-align: center; padding: 2rem 0; margin-top: 3rem;">
        <div class="container">
            <p>&copy; <?= date('Y') ?> CuidarBem - Atendimento Domiciliar. Todos os direitos reservados.</p>
        </div>
    </footer>
</body>
</html>
