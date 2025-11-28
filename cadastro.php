<?php
require_once 'config.php';

$sucesso = '';
$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['agendar'])) {
    try {
        $db = getDB();
        
        // Validar CPF
        if (!validarCPF($_POST['cpf'])) {
            throw new Exception('CPF inválido');
        }
        
        // Validar campos obrigatórios
        $camposObrigatorios = ['nome', 'cpf', 'idade', 'sexo', 'telefone', 'endereco', 'servico', 'data', 'periodo'];
        foreach ($camposObrigatorios as $campo) {
            if (empty($_POST[$campo])) {
                throw new Exception('Preencha todos os campos obrigatórios');
            }
        }
        
        // Validar data (não pode ser no passado)
        if (strtotime($_POST['data']) < strtotime('today')) {
            throw new Exception('Data de agendamento não pode ser no passado');
        }
        
        // Upload da identidade
        $fotoIdentidade = '';
        if (isset($_FILES['identidade']) && $_FILES['identidade']['error'] === UPLOAD_ERR_OK) {
            $upload = uploadArquivo($_FILES['identidade'], 'identidades');
            if ($upload['success']) {
                $fotoIdentidade = $upload['path'];
            } else {
                throw new Exception($upload['message']);
            }
        }
        
        $db->beginTransaction();
        
        $cpfLimpo = preg_replace('/[^0-9]/', '', $_POST['cpf']);
        
        // Verificar se paciente já existe
        $stmt = $db->prepare("SELECT id FROM pacientes WHERE cpf = ?");
        $stmt->execute([$cpfLimpo]);
        $paciente = $stmt->fetch();
        
        if ($paciente) {
            $pacienteId = $paciente['id'];
        } else {
            // Inserir novo paciente
            $stmt = $db->prepare("
                INSERT INTO pacientes (nome, cpf, idade, sexo, telefone, email, endereco, foto_identidade)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                sanitize($_POST['nome']),
                $cpfLimpo,
                (int)$_POST['idade'],
                sanitize($_POST['sexo']),
                preg_replace('/[^0-9]/', '', $_POST['telefone']),
                sanitize($_POST['email'] ?? ''),
                sanitize($_POST['endereco']),
                $fotoIdentidade
            ]);
            
            $pacienteId = $db->lastInsertId();
        }
        
        // Inserir agendamento
        $stmt = $db->prepare("
            INSERT INTO agendamentos 
            (paciente_id, tipo_servico, data_agendamento, periodo, urgencia, observacoes, status)
            VALUES (?, ?, ?, ?, ?, ?, 'pendente')
        ");
        
        $stmt->execute([
            $pacienteId,
            sanitize($_POST['servico']),
            sanitize($_POST['data']),
            sanitize($_POST['periodo']),
            sanitize($_POST['urgencia'] ?? 'rotina'),
            sanitize($_POST['observacoes'] ?? '')
        ]);
        
        $db->commit();
        
        $sucesso = 'Agendamento solicitado com sucesso! Nossa equipe entrará em contato em até 2 horas para confirmar o horário.';
        
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        $erro = 'Erro ao processar agendamento: ' . $e->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['enviar_exame'])) {
    try {
        if (!isset($_FILES['examesUpload']) || $_FILES['examesUpload']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('Nenhum arquivo selecionado');
        }
        
        $upload = uploadArquivo($_FILES['examesUpload'], 'exames');
        if ($upload['success']) {
            $sucesso = 'Documento enviado com sucesso! Nosso time médico irá analisá-lo.';
        } else {
            throw new Exception($upload['message']);
        }
        
    } catch (Exception $e) {
        $erro = 'Erro ao enviar documento: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CuidarBem - Atendimento Domiciliar para Idosos</title>
    <link rel="stylesheet" href="style-cadastro.css">
</head>
<body>
    <header>
        <nav class="container">
            <div class="logo">🏥 CuidarBem</div>
            <ul class="nav-links">
                <li><a href="index.php">Início</a></li>
                <li><a href="#servicos">Serviços</a></li>
                <li><a href="#sobre">Sobre</a></li>
                <li><a href="#contato">Contato</a></li>
            </ul>
        </nav>
    </header>

    <section id="home" class="hero">
        <div class="container">
            <h1>Cuidado Médico na Sua Casa</h1>
            <p>Atendimento domiciliar especializado para idosos com conforto, segurança e carinho. Nossa equipe vai até você!</p>
            <button class="cta-button" onclick="openModal()">Marcar Consulta</button>
            <a href="login.php" class="cta-button">Área do Cliente</a>
        </div>
    </section>

    <?php if ($sucesso): ?>
        <div class="container">
            <div style="background: #e8f5e9; color: #2e7d32; padding: 1.5rem; border-radius: 12px; margin: 20px auto; max-width: 800px; text-align: center; box-shadow: 0 4px 12px rgba(0,0,0,0.1);">
                <strong>✓ <?= htmlspecialchars($sucesso) ?></strong>
            </div>
        </div>
    <?php endif; ?>
    
    <?php if ($erro): ?>
        <div class="container">
            <div style="background: #ffebee; color: #c62828; padding: 1.5rem; border-radius: 12px; margin: 20px auto; max-width: 800px; text-align: center; box-shadow: 0 4px 12px rgba(0,0,0,0.1);">
                <strong>❌ <?= htmlspecialchars($erro) ?></strong>
            </div>
        </div>
    <?php endif; ?>

    <section class="features">
        <div class="container">
            <h2 style="text-align: center; font-size: 2.5rem; margin-bottom: 1rem;">Por que escolher a CuidarBem?</h2>
            <div class="features-grid">
                <div class="feature-card">
                    <div class="feature-icon">🏠</div>
                    <h3>Atendimento Domiciliar</h3>
                    <p>Consultas no conforto da sua casa, eliminando o estresse do deslocamento e filas de espera.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">👨‍⚕️</div>
                    <h3>Equipe Especializada</h3>
                    <p>Médicos e enfermeiros especializados no cuidado de idosos, com experiência e dedicação.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">⏰</div>
                    <h3>Horários Flexíveis</h3>
                    <p>Agendamentos conforme sua disponibilidade, incluindo fins de semana e emergências.</p>
                </div>
            </div>
        </div>
    </section>

    <section id="servicos" class="services">
        <div class="container">
            <h2 style="text-align: center; font-size: 2.5rem; margin-bottom: 1rem;">Nossos Serviços</h2>
            <div class="services-grid">
                <div class="service-item">
                    <h4>Consultas Médicas</h4>
                    <p>Avaliação médica completa, diagnósticos e prescrições no domicílio.</p>
                </div>
                <div class="service-item">
                    <h4>Enfermagem</h4>
                    <p>Curativos, aplicação de medicamentos e cuidados especializados.</p>
                </div>
                <div class="service-item">
                    <h4>Fisioterapia</h4>
                    <p>Reabilitação e exercícios terapêuticos em casa.</p>
                </div>
                <div class="service-item">
                    <h4>Exames Domiciliares</h4>
                    <p>Coleta de sangue, ECG e outros exames sem sair de casa.</p>
                </div>
                <div class="service-item">
                    <h4>Acompanhamento</h4>
                    <p>Monitoramento contínuo da saúde e bem-estar.</p>
                </div>
                <div class="service-item">
                    <h4>Emergências</h4>
                    <p>Atendimento de urgência 24 horas para situações críticas.</p>
                </div>
            </div>
        </div>
    </section>

    <section id="envio-exames" style="padding: 80px 0; background: #f8f9fa;">
        <div class="container" style="text-align: center;">
            <h2 style="font-size: 2.5rem; margin-bottom: 1rem;">Envio de Documentos e Exames</h2>
            <p style="max-width: 700px; margin: 0 auto 2rem auto; font-size: 1.2rem;">
                Agora você pode enviar exames, laudos e documentos médicos diretamente para nossa equipe antes da consulta. 
                Assim, o médico poderá analisar seu caso com mais agilidade.
            </p>
            <form method="POST" enctype="multipart/form-data" style="max-width: 500px; margin: 0 auto;">
                <div class="file-upload" style="margin-bottom: 1rem;">
                    <input type="file" id="examesUpload" name="examesUpload" accept=".pdf,.jpg,.png,.jpeg" required>
                    <label for="examesUpload" class="file-upload-label">
                        📄 Clique para enviar exames ou laudos<br>
                        <small>Formatos aceitos: PDF, JPG, PNG (máx. 10MB)</small>
                    </label>
                </div>
                <button type="submit" name="enviar_exame" class="submit-btn">Enviar Documento</button>
            </form>
        </div>
    </section>

    <section id="contato" class="contact-info">
        <div class="container">
            <h2>Entre em Contato</h2>
            <p style="font-size: 1.2rem; margin: 1rem 0;">📱 (17) 9914-08891 | 📧 clinica@cuidarbem.gmail.com</p>
        </div>
    </section>

    <footer>
        <div class="container">
            <p>&copy; <?= date('Y') ?> CuidarBem - Atendimento Domiciliar. Todos os direitos reservados.</p>
        </div>
    </footer>

    <!-- Modal de Agendamento -->
    <div id="agendamentoModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Agendar Consulta Domiciliar</h2>
                <button class="close" onclick="closeModal()">&times;</button>
            </div>
            <div class="form-container">
                <form method="POST" enctype="multipart/form-data" onsubmit="return validarFormulario()">
                    <h3 style="margin-bottom: 1rem; color: #2ea02c;">Dados do Paciente</h3>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="nome">Nome Completo *</label>
                            <input type="text" id="nome" name="nome" required minlength="3">
                        </div>
                        <div class="form-group">
                            <label for="cpf">CPF *</label>
                            <input type="text" id="cpf" name="cpf" placeholder="000.000.000-00" required maxlength="14">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="idade">Idade *</label>
                            <input type="number" id="idade" name="idade" min="1" max="120" required>
                        </div>
                        <div class="form-group">
                            <label for="sexo">Sexo *</label>
                            <select id="sexo" name="sexo" required>
                                <option value="">Selecione</option>
                                <option value="masculino">Masculino</option>
                                <option value="feminino">Feminino</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="telefone">Telefone *</label>
                            <input type="tel" id="telefone" name="telefone" placeholder="(11) 99999-9999" required maxlength="15">
                        </div>
                        <div class="form-group">
                            <label for="email">Email</label>
                            <input type="email" id="email" name="email">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="endereco">Endereço Completo *</label>
                        <textarea id="endereco" name="endereco" rows="3" placeholder="Rua, número, bairro, cidade, CEP" required minlength="10"></textarea>
                    </div>

                    <h3 style="margin: 2rem 0 1rem 0; color: #3cc94f;">Informações da Consulta</h3>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="servico">Tipo de Serviço *</label>
                            <select id="servico" name="servico" required>
                                <option value="">Selecione o serviço</option>
                                <option value="consulta-medica">Consulta Médica</option>
                                <option value="fisioterapia">Fisioterapia</option>
                                <option value="exames">Exames Domiciliares</option>
                                <option value="emergencia">Emergência</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="data">Data Preferencial *</label>
                            <input type="date" id="data" name="data" min="<?= date('Y-m-d') ?>" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="periodo">Período *</label>
                            <select id="periodo" name="periodo" required>
                                <option value="">Selecione</option>
                                <option value="manha">Manhã (8h-12h)</option>
                                <option value="tarde">Tarde (13h-17h)</option>
                                <option value="noite">Noite (18h-21h)</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="urgencia">Nível de Urgência</label>
                            <select id="urgencia" name="urgencia">
                                <option value="rotina">Rotina</option>
                                <option value="urgente">Urgente (24-48h)</option>
                                <option value="emergencia">Emergência (ASAP)</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="observacoes">Observações ou Sintomas</label>
                        <textarea id="observacoes" name="observacoes" rows="3" placeholder="Descreva os sintomas, medicações em uso ou qualquer informação relevante..."></textarea>
                    </div>

                    <h3 style="margin: 2rem 0 1rem 0; color: #2ca04d;">Documentação</h3>
                    
                    <div class="form-group">
                        <label for="identidade">Foto da Identidade (RG ou CNH) *</label>
                        <div class="file-upload">
                            <input type="file" id="identidade" name="identidade" accept="image/*,.pdf" required>
                            <label for="identidade" class="file-upload-label">
                                📷 Clique para enviar foto da identidade
                                <br><small>Formatos aceitos: JPG, PNG, PDF (máx. 5MB)</small>
                            </label>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>
                            <input type="checkbox" required style="width: auto; margin-right: 0.5rem;">
                            Concordo com os termos de serviço e autorizo o atendimento domiciliar *
                        </label>
                    </div>

                    <button type="submit" name="agendar" class="submit-btn">Solicitar Agendamento</button>
                    
                    <p style="margin-top: 1rem; font-size: 0.9rem; color: #df2e2e; text-align: center;">
                        * Campos obrigatórios. Nossa equipe entrará em contato em até 2 horas para confirmar o agendamento.
                    </p>
                </form>
            </div>
        </div>
    </div>

    <script>
        function openModal() {
            document.getElementById('agendamentoModal').style.display = 'block';
            document.body.style.overflow = 'hidden';
        }

        function closeModal() {
            document.getElementById('agendamentoModal').style.display = 'none';
            document.body.style.overflow = 'auto';
        }

        window.onclick = function(event) {
            const modal = document.getElementById('agendamentoModal');
            if (event.target === modal) {
                closeModal();
            }
        }

        // Máscara de CPF
        document.getElementById('cpf').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length > 11) value = value.slice(0, 11);
            value = value.replace(/(\d{3})(\d)/, '$1.$2');
            value = value.replace(/(\d{3})(\d)/, '$1.$2');
            value = value.replace(/(\d{3})(\d{1,2})$/, '$1-$2');
            e.target.value = value;
        });

        // Máscara de telefone
        document.getElementById('telefone').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length > 11) value = value.slice(0, 11);
            if (value.length <= 10) {
                value = value.replace(/(\d{2})(\d)/, '($1) $2');
                value = value.replace(/(\d{4})(\d)/, '$1-$2');
            } else {
                value = value.replace(/(\d{2})(\d)/, '($1) $2');
                value = value.replace(/(\d{5})(\d)/, '$1-$2');
            }
            e.target.value = value;
        });

        // Feedback visual no upload
        document.getElementById('identidade').addEventListener('change', function(e) {
            const label = e.target.nextElementSibling;
            const fileName = e.target.files[0]?.name;
            if (fileName) {
                label.innerHTML = `✅ ${fileName}<br><small>Arquivo selecionado com sucesso!</small>`;
                label.style.background = '#e8f5e8';
                label.style.borderColor = '#28a745';
            }
        });
        
        document.getElementById('examesUpload').addEventListener('change', function(e) {
            const label = e.target.nextElementSibling;
            const fileName = e.target.files[0]?.name;
            if (fileName) {
                label.innerHTML = `✅ ${fileName}<br><small>Arquivo selecionado com sucesso!</small>`;
                label.style.background = '#e8f5e8';
                label.style.borderColor = '#28a745';
            }
        });

        // Validação do formulário
        function validarFormulario() {
            const cpf = document.getElementById('cpf').value.replace(/\D/g, '');
            if (cpf.length !== 11) {
                alert('CPF inválido! Digite um CPF com 11 dígitos.');
                return false;
            }
            
            const data = document.getElementById('data').value;
            if (new Date(data) < new Date().setHours(0,0,0,0)) {
                alert('A data não pode ser no passado!');
                return false;
            }
            
            return true;
        }
    </script>
</body>
</html>