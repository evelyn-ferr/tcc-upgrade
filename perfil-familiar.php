<?php
require_once 'config.php';
require_once 'auth.php';

// Requer login como familiar
requireFamiliar();

$db = getDB();
$usuarioId = getUserId();

// Buscar pacientes vinculados ao familiar
$stmt = $db->prepare("
    SELECT p.*, vf.grau_parentesco
    FROM pacientes p
    INNER JOIN vinculos_familiar vf ON p.id = vf.paciente_id
    WHERE vf.usuario_id = ?
    LIMIT 1
");
$stmt->execute([$usuarioId]);
$paciente = $stmt->fetch();

if (!$paciente) {
    die("Nenhum paciente vinculado a esta conta.");
}

// Buscar próximos agendamentos
$stmt = $db->prepare("
    SELECT * FROM agendamentos
    WHERE paciente_id = ? AND data_agendamento >= CURDATE()
    ORDER BY data_agendamento, horario
    LIMIT 5
");
$stmt->execute([$paciente['id']]);
$agendamentos = $stmt->fetchAll();

// Buscar últimos sinais vitais
$stmt = $db->prepare("
    SELECT * FROM sinais_vitais
    WHERE paciente_id = ?
    ORDER BY data_medicao DESC
    LIMIT 1
");
$stmt->execute([$paciente['id']]);
$sinaisVitais = $stmt->fetch();

// Buscar evoluções recentes
$stmt = $db->prepare("
    SELECT e.*, u.nome as registrado_por_nome
    FROM evolucoes e
    LEFT JOIN usuarios u ON e.registrado_por = u.id
    WHERE e.paciente_id = ?
    ORDER BY e.data_registro DESC
    LIMIT 5
");
$stmt->execute([$paciente['id']]);
$evolucoes = $stmt->fetchAll();

// Buscar notificações não lidas
$stmt = $db->prepare("
    SELECT COUNT(*) as total FROM notificacoes
    WHERE usuario_id = ? AND lida = FALSE
");
$stmt->execute([$usuarioId]);
$notificacoesNaoLidas = $stmt->fetch()['total'];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CuidarBem - Perfil Familiar</title>
    <link rel="stylesheet" href="style-perfilfamiliar.css">
</head>
<body>
    <header>
        <nav class="container">
            <div class="logo">🏥 CuidarBem</div>
            <ul class="nav-links">
                <li><a href="index.php">Início</a></li>
                <li><a href="#agenda">Agenda</a></li>
                <li><a href="#evolucoes">Evoluções</a></li>
                <li><a href="logout.php">Sair</a></li>
            </ul>
            <div class="user-info">
                <span>👨</span>
                <div>
                    <div style="font-weight: bold;"><?= sanitize(getUserName()) ?></div>
                    <div style="font-size: 0.8rem; opacity: 0.8;">Familiar</div>
                </div>
            </div>
        </nav>
    </header>

    <main class="main-content">
        <div class="container">
            <div class="welcome-section">
                <h1 class="welcome-title">Bem-vindo(a), <?= sanitize(explode(' ', getUserName())[0]) ?>!</h1>
                <p style="font-size: 1.2rem; color: #7f8c8d; position: relative; z-index: 2;">
                    Acompanhe todas as informações de saúde de <?= sanitize($paciente['nome']) ?>
                </p>
                
                <?php if ($notificacoesNaoLidas > 0): ?>
                <div class="alert alert-info" style="margin-top: 1rem; position: relative; z-index: 2;">
                    <strong>🔔 Você tem <?= $notificacoesNaoLidas ?> notificações não lidas</strong>
                </div>
                <?php endif; ?>
            </div>

            <div class="patient-info">
                <div class="info-card">
                    <div class="card-icon">👤</div>
                    <div class="card-content">
                        <h3>Informações do Paciente</h3>
                        <p><strong>Nome:</strong> <?= sanitize($paciente['nome']) ?></p>
                        <p><strong>Idade:</strong> <?= $paciente['idade'] ?> anos</p>
                        <p><strong>Sexo:</strong> <?= ucfirst($paciente['sexo']) ?></p>
                        <p><strong>Relação:</strong> <?= sanitize($paciente['grau_parentesco']) ?></p>
                    </div>
                </div>

                <div class="info-card">
                    <div class="card-icon">❤️</div>
                    <div class="card-content">
                        <h3>Status de Saúde</h3>
                        <p style="font-size: 1.5rem; font-weight: bold; margin: 1rem 0;">
                            <span class="status-badge status-<?= $paciente['status_saude'] ?>">
                                <?= ucfirst($paciente['status_saude']) ?>
                            </span>
                        </p>
                        <?php if ($paciente['diagnostico']): ?>
                        <p><strong>Diagnóstico:</strong><br><?= sanitize($paciente['diagnostico']) ?></p>
                        <?php endif; ?>
                        <?php if ($paciente['medico_responsavel']): ?>
                        <p><strong>Médico:</strong><br><?= sanitize($paciente['medico_responsavel']) ?></p>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($sinaisVitais): ?>
                <div class="info-card">
                    <div class="card-icon">💓</div>
                    <div class="card-content">
                        <h3>Últimos Sinais Vitais</h3>
                        <p><strong>Data:</strong> <?= formatarDataHoraBR($sinaisVitais['data_medicao']) ?></p>
                        <?php if ($sinaisVitais['pressao_arterial']): ?>
                        <p>Pressão: <?= sanitize($sinaisVitais['pressao_arterial']) ?> mmHg</p>
                        <?php endif; ?>
                        <?php if ($sinaisVitais['frequencia_cardiaca']): ?>
                        <p>Frequência: <?= $sinaisVitais['frequencia_cardiaca'] ?> bpm</p>
                        <?php endif; ?>
                        <?php if ($sinaisVitais['temperatura']): ?>
                        <p>Temperatura: <?= $sinaisVitais['temperatura'] ?>°C</p>
                        <?php endif; ?>
                        <?php if ($sinaisVitais['glicemia']): ?>
                        <p>Glicemia: <?= $sinaisVitais['glicemia'] ?> mg/dL</p>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <div class="quick-actions">
                <a href="historico.php" class="action-btn">📋 Ver Histórico</a>
                <a href="documentos.php" class="action-btn">📄 Documentos</a>
                <a href="contato-equipe.php" class="action-btn">💬 Contatar Equipe</a>
            </div>

            <div class="timeline" id="agenda">
                <h3>Próximos Agendamentos</h3>
                
                <?php if (empty($agendamentos)): ?>
                    <p style="text-align: center; color: #7f8c8d; padding: 2rem;">
                        Nenhum agendamento próximo
                    </p>
                <?php else: ?>
                    <?php foreach ($agendamentos as $agend): ?>
                    <div class="timeline-item">
                        <div class="timeline-icon">📅</div>
                        <div class="timeline-content">
                            <h4><?= ucfirst(str_replace('-', ' ', $agend['tipo_servico'])) ?></h4>
                            <p class="timeline-date">
                                <?= formatarDataBR($agend['data_agendamento']) ?> - 
                                <?= ucfirst($agend['periodo']) ?>
                                <?php if ($agend['horario']): ?>
                                    às <?= date('H:i', strtotime($agend['horario'])) ?>
                                <?php endif; ?>
                            </p>
                            <p>
                                <span class="status-badge status-<?= $agend['status'] ?>">
                                    <?= ucfirst($agend['status']) ?>
                                </span>
                            </p>
                            <?php if ($agend['observacoes']): ?>
                            <p style="margin-top: 0.5rem; font-size: 0.9rem;">
                                <?= nl2br(sanitize($agend['observacoes'])) ?>
                            </p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="timeline" id="evolucoes">
                <h3>Evoluções Recentes</h3>
                
                <?php if (empty($evolucoes)): ?>
                    <p style="text-align: center; color: #7f8c8d; padding: 2rem;">
                        Nenhuma evolução registrada
                    </p>
                <?php else: ?>
                    <?php foreach ($evolucoes as $ev): ?>
                    <div class="timeline-item">
                        <div class="timeline-icon">
                            <?php
                            $icones = [
                                'evolucao' => '📝',
                                'intercorrencia' => '⚠️',
                                'procedimento' => '🩺',
                                'observacao' => '💭'
                            ];
                            echo $icones[$ev['tipo']] ?? '📋';
                            ?>
                        </div>
                        <div class="timeline-content">
                            <h4><?= ucfirst($ev['tipo']) ?></h4>
                            <p class="timeline-date">
                                <?= formatarDataHoraBR($ev['data_registro']) ?>
                                <?php if ($ev['registrado_por_nome']): ?>
                                    - Por: <?= sanitize($ev['registrado_por_nome']) ?>
                                <?php endif; ?>
                            </p>
                            <p><?= nl2br(sanitize($ev['descricao'])) ?></p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="emergency-contact">
                <h3>Contato de Emergência</h3>
                <p style="font-size: 1.2rem; margin: 1rem 0; position: relative; z-index: 2;">
                    Em caso de emergência, entre em contato imediatamente:
                </p>
                <button class="emergency-btn" onclick="window.location.href='tel:+5517991408891'">
                    📞 Ligar Agora: (17) 9914-08891
                </button>
                <p style="margin-top: 1rem; font-size: 0.9rem; color: #7f8c8d; position: relative; z-index: 2;">
                    Disponível 24h por dia, 7 dias por semana
                </p>
            </div>
        </div>
    </main>

    <footer style="background: #2c3e50; color: white; text-align: center; padding: 2rem 0; margin-top: 3rem;">
        <div class="container">
            <p>&copy; <?= date('Y') ?> CuidarBem - Atendimento Domiciliar. Todos os direitos reservados.</p>
        </div>
    </footer>
</body>
</html>
