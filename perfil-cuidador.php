<?php
require_once 'config.php';
require_once 'auth.php';

// Requer login como cuidador
requireCuidador();

$db = getDB();
$cuidadorId = getUserId();

// Buscar pacientes atribuídos ao cuidador
$stmt = $db->prepare("
    SELECT p.*
    FROM pacientes p
    INNER JOIN vinculos_cuidador vc ON p.id = vc.paciente_id
    WHERE vc.cuidador_id = ? AND vc.status = 'ativo'
    LIMIT 1
");
$stmt->execute([$cuidadorId]);
$paciente = $stmt->fetch();

if (!$paciente) {
    die("Nenhum paciente atribuído a você.");
}

// Buscar últimos sinais vitais
$stmt = $db->prepare("
    SELECT * FROM sinais_vitais
    WHERE paciente_id = ?
    ORDER BY data_medicao DESC
    LIMIT 1
");
$stmt->execute([$paciente['id']]);
$sinaisVitais = $stmt->fetch();

// Buscar agenda de hoje
$stmt = $db->prepare("
    SELECT * FROM agendamentos
    WHERE paciente_id = ? AND DATE(data_agendamento) = CURDATE()
    ORDER BY horario
");
$stmt->execute([$paciente['id']]);
$agendaHoje = $stmt->fetchAll();

// Buscar medicações ativas
$stmt = $db->prepare("
    SELECT m.*,
    (SELECT COUNT(*) FROM registro_medicamentos rm 
     WHERE rm.medicacao_id = m.id AND DATE(rm.data_administracao) = CURDATE()) as administrado_hoje
    FROM medicacoes m
    WHERE m.paciente_id = ? AND m.status = 'ativo'
    ORDER BY m.horario_administracao
");
$stmt->execute([$paciente['id']]);
$medicacoes = $stmt->fetchAll();

// Buscar orientações ativas
$stmt = $db->prepare("
    SELECT * FROM orientacoes
    WHERE paciente_id = ? AND status = 'ativo'
    ORDER BY tipo, id
");
$stmt->execute([$paciente['id']]);
$orientacoes = $stmt->fetchAll();

// Processar registro de sinais vitais
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['registrar_sinais'])) {
    try {
        $stmt = $db->prepare("
            INSERT INTO sinais_vitais 
            (paciente_id, pressao_arterial, frequencia_cardiaca, temperatura, glicemia, saturacao_oxigenio, registrado_por)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $paciente['id'],
            sanitize($_POST['pressao']),
            (int)$_POST['frequencia'],
            (float)$_POST['temperatura'],
            (int)$_POST['glicemia'],
            (int)$_POST['saturacao'],
            $cuidadorId
        ]);
        
        header('Location: perfil-cuidador.php?success=sinais');
        exit;
    } catch (Exception $e) {
        $erro = 'Erro ao registrar sinais vitais: ' . $e->getMessage();
    }
}

// Processar registro de medicação
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['registrar_medicacao'])) {
    try {
        $stmt = $db->prepare("
            INSERT INTO registro_medicamentos (medicacao_id, administrado_por, observacoes)
            VALUES (?, ?, ?)
        ");
        
        $stmt->execute([
            (int)$_POST['medicacao_id'],
            $cuidadorId,
            sanitize($_POST['observacoes'] ?? '')
        ]);
        
        header('Location: perfil-cuidador.php?success=medicacao');
        exit;
    } catch (Exception $e) {
        $erro = 'Erro ao registrar medicação: ' . $e->getMessage();
    }
}

// Processar nova evolução
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nova_evolucao'])) {
    try {
        $stmt = $db->prepare("
            INSERT INTO evolucoes (paciente_id, descricao, tipo, registrado_por)
            VALUES (?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $paciente['id'],
            sanitize($_POST['descricao']),
            sanitize($_POST['tipo']),
            $cuidadorId
        ]);
        
        header('Location: perfil-cuidador.php?success=evolucao');
        exit;
    } catch (Exception $e) {
        $erro = 'Erro ao registrar evolução: ' . $e->getMessage();
    }
}

$sucesso = '';
if (isset($_GET['success'])) {
    $mensagens = [
        'sinais' => 'Sinais vitais registrados com sucesso!',
        'medicacao' => 'Medicação registrada com sucesso!',
        'evolucao' => 'Evolução registrada com sucesso!'
    ];
    $sucesso = $mensagens[$_GET['success']] ?? '';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CuidarBem - Perfil do Cuidador</title>
    <link rel="stylesheet" href="style-cuidador.css">
</head>
<body>
    <header>
        <nav class="container">
            <div class="logo">🏥 CuidarBem</div>
            <ul class="nav-links">
                <li><a href="index.php">Início</a></li>
                <li><a href="#agenda">Agenda</a></li>
                <li><a href="#orientacoes">Orientações</a></li>
                <li><a href="logout.php">Sair</a></li>
            </ul>
            <div class="user-info">
                <span>👨‍⚕️</span>
                <div>
                    <div style="font-weight: bold;"><?= sanitize(getUserName()) ?></div>
                    <div style="font-size: 0.8rem; opacity: 0.8;">Cuidador Responsável</div>
                </div>
            </div>
        </nav>
    </header>

    <main class="main-content">
        <div class="container">
            <?php if ($sucesso): ?>
            <div class="alert alert-info" style="max-width: 850px; margin: 20px auto;">
                <strong>✓ <?= $sucesso ?></strong>
            </div>
            <?php endif; ?>
            
            <?php if (isset($erro)): ?>
            <div class="alert alert-warning" style="max-width: 850px; margin: 20px auto;">
                <strong>❌ <?= $erro ?></strong>
            </div>
            <?php endif; ?>
            
            <div class="dashboard-header">
                <h1 style="color: #2c3e50; margin-bottom: 1rem;">Painel do Cuidador</h1>
                <p style="color: #7f8c8d;">Acompanhe todas as informações e cuidados do paciente em tempo real</p>
                
                <div class="alert alert-info" style="margin-top: 1rem;">
                    <strong>🔋 Última atualização:</strong> <?= date('d/m/Y H:i') ?> - 
                    <?php if ($sinaisVitais): ?>
                        Todos os sinais vitais normais
                    <?php else: ?>
                        Aguardando registro de sinais vitais
                    <?php endif; ?>
                </div>
            </div>

            <div class="dashboard-grid">
                <div class="card">
                    <div class="card-header">
                        <div class="card-icon">👩🏾</div>
                        <div>
                            <h3>Informações do Paciente</h3>
                            <p style="color: #7f8c8d;"><?= sanitize($paciente['nome']) ?>, <?= $paciente['idade'] ?> anos</p>
                        </div>
                    </div>
                    
                    <div class="patient-info">
                        <div class="info-item">
                            <div class="info-label">Status Atual</div>
                            <div class="info-value">
                                <span class="status-badge status-<?= $paciente['status_saude'] ?>">
                                    <?= ucfirst($paciente['status_saude']) ?>
                                </span>
                            </div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Diagnóstico</div>
                            <div class="info-value"><?= sanitize($paciente['diagnostico'] ?? 'Não informado') ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Médico Responsável</div>
                            <div class="info-value"><?= sanitize($paciente['medico_responsavel'] ?? 'Não informado') ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Telefone</div>
                            <div class="info-value"><?= formatarTelefone($paciente['telefone']) ?></div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <div class="card-icon">💗</div>
                        <div>
                            <h3>Sinais Vitais</h3>
                            <p style="color: #7f8c8d;">
                                <?php if ($sinaisVitais): ?>
                                    Última medição: <?= formatarDataHoraBR($sinaisVitais['data_medicao']) ?>
                                <?php else: ?>
                                    Nenhum registro ainda
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                    
                    <div class="patient-info">
                        <?php if ($sinaisVitais): ?>
                        <div class="info-item">
                            <div class="info-label">Pressão Arterial</div>
                            <div class="info-value"><?= sanitize($sinaisVitais['pressao_arterial']) ?> mmHg</div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Frequência Cardíaca</div>
                            <div class="info-value"><?= $sinaisVitais['frequencia_cardiaca'] ?> bpm</div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Temperatura</div>
                            <div class="info-value"><?= $sinaisVitais['temperatura'] ?>°C</div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Glicemia</div>
                            <div class="info-value"><?= $sinaisVitais['glicemia'] ?> mg/dL</div>
                        </div>
                        <?php else: ?>
                        <p style="text-align: center; padding: 1rem; color: #7f8c8d;">
                            Nenhuma medição registrada hoje
                        </p>
                        <?php endif; ?>
                    </div>

                    <button class="btn btn-primary" style="width: 100%; margin-top: 1rem;" onclick="document.getElementById('modalSinais').style.display='block'">
                        📊 Registrar Novos Sinais
                    </button>
                </div>
            </div>

            <div class="dashboard-grid">
                <div class="card" id="agenda">
                    <div class="card-header">
                        <div class="card-icon">📅</div>
                        <div>
                            <h3>Agenda de Hoje</h3>
                            <p style="color: #7f8c8d;"><?= date('d \d\e F \d\e Y') ?></p>
                        </div>
                    </div>

                    <?php if (empty($agendaHoje)): ?>
                        <p style="text-align: center; padding: 2rem; color: #7f8c8d;">
                            Nenhum agendamento para hoje
                        </p>
                    <?php else: ?>
                        <?php foreach ($agendaHoje as $ag): ?>
                        <div class="agenda-item <?= $ag['urgencia'] === 'urgente' ? 'agenda-urgent' : '' ?>">
                            <div class="agenda-time"><?= date('H:i', strtotime($ag['horario'])) ?></div>
                            <div>
                                <strong><?= ucfirst(str_replace('-', ' ', $ag['tipo_servico'])) ?></strong>
                                <?php if ($ag['observacoes']): ?>
                                <div style="color: #7f8c8d; font-size: 0.9rem;">
                                    <?= sanitize($ag['observacoes']) ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <div class="card">
                    <div class="card-header">
                        <div class="card-icon">💊</div>
                        <div>
                            <h3>Controle de Medicações</h3>
                            <p style="color: #7f8c8d;">Administração de hoje</p>
                        </div>
                    </div>

                    <?php if (empty($medicacoes)): ?>
                        <p style="text-align: center; padding: 2rem; color: #7f8c8d;">
                            Nenhuma medicação ativa
                        </p>
                    <?php else: ?>
                        <?php foreach ($medicacoes as $med): ?>
                        <div class="medication-item">
                            <div>
                                <div class="medication-name"><?= sanitize($med['nome_medicamento']) ?></div>
                                <div class="medication-dose">
                                    <?= sanitize($med['dosagem']) ?> - <?= date('H:i', strtotime($med['horario_administracao'])) ?>
                                </div>
                            </div>
                            <span class="medication-status <?= $med['administrado_hoje'] > 0 ? 'status-done' : 'status-pending' ?>">
                                <?= $med['administrado_hoje'] > 0 ? '✅ Administrado' : '⏰ Pendente' ?>
                            </span>
                        </div>
                        <?php endforeach; ?>
                        
                        <button class="btn btn-secondary" style="width: 100%; margin-top: 1rem;" onclick="document.getElementById('modalMedicacao').style.display='block'">
                            ➕ Registrar Administração
                        </button>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card" id="orientacoes">
                <div class="card-header">
                    <div class="card-icon">📋</div>
                    <div>
                        <h3>Orientações e Cuidados Especiais</h3>
                        <p style="color: #7f8c8d;">Instruções da equipe médica</p>
                    </div>
                </div>

                <?php if (empty($orientacoes)): ?>
                    <p style="text-align: center; padding: 2rem; color: #7f8c8d;">
                        Nenhuma orientação registrada
                    </p>
                <?php else: ?>
                    <?php foreach ($orientacoes as $or): ?>
                    <div class="orientation-item">
                        <div class="orientation-title">
                            <?php
                            $icones = [
                                'cuidado' => '🩺',
                                'dieta' => '🍽️',
                                'medicacao' => '💊',
                                'alerta' => '⚠️',
                                'procedimento' => '🏥'
                            ];
                            echo $icones[$or['tipo']] ?? '📋';
                            ?>
                            <?= sanitize($or['titulo']) ?>
                        </div>
                        <div class="orientation-text">
                            <?= nl2br(sanitize($or['descricao'])) ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="quick-actions">
                <button class="btn btn-primary" onclick="document.getElementById('modalEvolucao').style.display='block'">📝 Nova Evolução</button>
                <button class="btn btn-primary" onclick="alert('Em breve: contato direto com médico')">📞 Contatar Médico</button>
                <button class="btn btn-secondary" onclick="alert('Em breve: geração de relatório PDF')">📊 Gerar Relatório</button>
                <button class="btn btn-secondary" onclick="window.location.href='historico.php'">📋 Ver Histórico</button>
            </div>
        </div>
    </main>

    <!-- Modal Sinais Vitais -->
    <div id="modalSinais" class="modal">
        <div class="modal-content" style="max-width: 600px;">
            <span class="modal-close" onclick="document.getElementById('modalSinais').style.display='none'">&times;</span>
            <h2 style="margin-bottom: 1.5rem; position: relative; z-index: 2;">Registrar Sinais Vitais</h2>
            <form method="POST">
                <div class="form-group">
                    <label>Pressão Arterial (mmHg)</label>
                    <input type="text" name="pressao" placeholder="120/80" required>
                </div>
                <div class="form-group">
                    <label>Frequência Cardíaca (bpm)</label>
                    <input type="number" name="frequencia" placeholder="72" required>
                </div>
                <div class="form-group">
                    <label>Temperatura (°C)</label>
                    <input type="number" step="0.1" name="temperatura" placeholder="36.5" required>
                </div>
                <div class="form-group">
                    <label>Glicemia (mg/dL)</label>
                    <input type="number" name="glicemia" placeholder="110" required>
                </div>
                <div class="form-group">
                    <label>Saturação de O2 (%)</label>
                    <input type="number" name="saturacao" placeholder="98">
                </div>
                <button type="submit" name="registrar_sinais" class="btn btn-primary" style="width: 100%;">
                    Registrar Sinais
                </button>
            </form>
        </div>
    </div>

    <!-- Modal Medicação -->
    <div id="modalMedicacao" class="modal">
        <div class="modal-content" style="max-width: 600px;">
            <span class="modal-close" onclick="document.getElementById('modalMedicacao').style.display='none'">&times;</span>
            <h2 style="margin-bottom: 1.5rem; position: relative; z-index: 2;">Registrar Administração de Medicação</h2>
            <form method="POST">
                <div class="form-group">
                    <label>Medicação</label>
                    <select name="medicacao_id" required>
                        <option value="">Selecione...</option>
                        <?php foreach ($medicacoes as $med): ?>
                        <option value="<?= $med['id'] ?>">
                            <?= sanitize($med['nome_medicamento']) ?> - <?= sanitize($med['dosagem']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Observações (opcional)</label>
                    <textarea name="observacoes" rows="3" placeholder="Ex: Paciente aceitou bem a medicação"></textarea>
                </div>
                <button type="submit" name="registrar_medicacao" class="btn btn-primary" style="width: 100%;">
                    Registrar Administração
                </button>
            </form>
        </div>
    </div>

    <!-- Modal Evolução -->
    <div id="modalEvolucao" class="modal">
        <div class="modal-content" style="max-width: 600px;">
            <span class="modal-close" onclick="document.getElementById('modalEvolucao').style.display='none'">&times;</span>
            <h2 style="margin-bottom: 1.5rem; position: relative; z-index: 2;">Registrar Nova Evolução</h2>
            <form method="POST">
                <div class="form-group">
                    <label>Tipo</label>
                    <select name="tipo" required>
                        <option value="evolucao">Evolução</option>
                        <option value="intercorrencia">Intercorrência</option>
                        <option value="procedimento">Procedimento</option>
                        <option value="observacao">Observação</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Descrição</label>
                    <textarea name="descricao" rows="5" required 
                              placeholder="Descreva o estado atual do paciente, procedimentos realizados, observações importantes..."></textarea>
                </div>
                <button type="submit" name="nova_evolucao" class="btn btn-primary" style="width: 100%;">
                    Registrar Evolução
                </button>
            </form>
        </div>
    </div>

    <footer style="background: #2c3e50; color: white; text-align: center; padding: 2rem 0; margin-top: 3rem;">
        <div class="container">
            <p>&copy; <?= date('Y') ?> CuidarBem - Atendimento Domiciliar. Todos os direitos reservados.</p>
            <p style="margin-top: 0.5rem; font-size: 0.9rem;">Emergências 24h: (17) 9914-08891</p>
        </div>
    </footer>

    <script>
        // Fechar modais ao clicar fora
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        }
    </script>
</body>
</html>
