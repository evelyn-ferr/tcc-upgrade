<?php
require_once 'config.php';
require_once 'auth.php';

$sucesso = '';
$erro = '';

// Processar cadastro de usuário (familiar ou cuidador)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cadastrar_usuario'])) {
    try {
        $db = getDB();
        
        // Validações
        $nome = sanitize($_POST['nome']);
        $email = sanitize($_POST['email']);
        $senha = $_POST['senha'];
        $confirmar_senha = $_POST['confirmar_senha'];
        $tipo = sanitize($_POST['tipo_usuario']);
        $telefone = preg_replace('/[^0-9]/', '', $_POST['telefone']);
        $cpf = preg_replace('/[^0-9]/', '', $_POST['cpf']);
        
        // Validar campos obrigatórios
        if (empty($nome) || empty($email) || empty($senha) || empty($tipo) || empty($cpf)) {
            throw new Exception('Preencha todos os campos obrigatórios');
        }
        
        // Validar email
        if (!validarEmail($email)) {
            throw new Exception('Email inválido');
        }
        
        // Validar CPF
        if (!validarCPF($cpf)) {
            throw new Exception('CPF inválido');
        }
        
        // Validar senha
        if (strlen($senha) < 6) {
            throw new Exception('A senha deve ter no mínimo 6 caracteres');
        }
        
        if ($senha !== $confirmar_senha) {
            throw new Exception('As senhas não coincidem');
        }
        
        // Verificar se email já existe
        $stmt = $db->prepare("SELECT id FROM usuarios WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            throw new Exception('Este email já está cadastrado');
        }
        
        // Verificar se CPF já existe
        $stmt = $db->prepare("SELECT id FROM usuarios WHERE cpf = ?");
        $stmt->execute([$cpf]);
        if ($stmt->fetch()) {
            throw new Exception('Este CPF já está cadastrado');
        }
        
        // Criar hash da senha
        $senhaHash = password_hash($senha, PASSWORD_DEFAULT);
        
        // Inserir usuário
        $stmt = $db->prepare("
            INSERT INTO usuarios (nome, email, senha, tipo_usuario, telefone, cpf)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([$nome, $email, $senhaHash, $tipo, $telefone, $cpf]);
        
        $sucesso = 'Cadastro realizado com sucesso! Você já pode fazer login.';
        
    } catch (Exception $e) {
        $erro = $e->getMessage();
    }
}

// Processar agendamento de consulta
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
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CuidarBem - Cadastro e Agendamento</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #E8D5D0 0%, #A9C166 100%);
            min-height: 100vh;
            padding-bottom: 2rem;
        }

        header {
            background: rgba(255, 255, 255, 0.95);
            padding: 1.5rem 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }

        nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            font-size: 1.5rem;
            font-weight: bold;
            color: #A9C166;
        }

        .nav-links {
            display: flex;
            gap: 2rem;
            list-style: none;
        }

        .nav-links a {
            text-decoration: none;
            color: #333;
            font-weight: 500;
            transition: color 0.3s;
        }

        .nav-links a:hover {
            color: #A9C166;
        }

        .hero {
            text-align: center;
            padding: 3rem 0;
            color: #333;
        }

        .hero h1 {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            color: #3B3A1C;
        }

        .hero p {
            font-size: 1.2rem;
            margin-bottom: 2rem;
            color: #6B4E3D;
        }

        .cta-button {
            display: inline-block;
            background: #A9C166;
            color: white;
            padding: 1rem 2.5rem;
            text-decoration: none;
            border-radius: 50px;
            font-weight: 600;
            font-size: 1.1rem;
            border: none;
            cursor: pointer;
            margin: 0.5rem;
            transition: all 0.3s;
        }

        .cta-button:hover {
            background: #8AA654;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }

        .alert {
            padding: 1.5rem;
            border-radius: 12px;
            margin: 20px auto;
            max-width: 800px;
            text-align: center;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        .alert-success {
            background: #e8f5e9;
            color: #2e7d32;
        }

        .alert-error {
            background: #ffebee;
            color: #c62828;
        }

        .tabs {
            display: flex;
            justify-content: center;
            gap: 1rem;
            margin: 2rem 0;
        }

        .tab-button {
            padding: 1rem 2rem;
            background: white;
            border: 2px solid #A9C166;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
        }

        .tab-button.active {
            background: #A9C166;
            color: white;
        }

        .tab-content {
            display: none;
            background: white;
            padding: 2rem;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            max-width: 800px;
            margin: 0 auto;
        }

        .tab-content.active {
            display: block;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #333;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 0.8rem;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 1rem;
            font-family: inherit;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #A9C166;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        .submit-btn {
            width: 100%;
            padding: 1rem;
            background: linear-gradient(45deg, #A9C166, #8AA654);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(169, 193, 102, 0.4);
        }

        .file-upload {
            position: relative;
        }

        .file-upload input[type="file"] {
            position: absolute;
            opacity: 0;
            width: 0;
            height: 0;
        }

        .file-upload-label {
            display: block;
            padding: 1rem;
            background: #f5f5f5;
            border: 2px dashed #A9C166;
            border-radius: 8px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
        }

        .file-upload-label:hover {
            background: #e8f5e9;
        }

        footer {
            background: rgba(255, 255, 255, 0.95);
            padding: 2rem 0;
            margin-top: 3rem;
            text-align: center;
            box-shadow: 0 -2px 10px rgba(0,0,0,0.1);
        }

        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }

            .nav-links {
                gap: 1rem;
            }

            .hero h1 {
                font-size: 1.8rem;
            }
        }
    </style>
</head>
<body>
    <header>
        <nav class="container">
            <div class="logo">🏥 CuidarBem</div>
            <ul class="nav-links">
                <li><a href="index.php">Início</a></li>
                <li><a href="login.php">Login</a></li>
            </ul>
        </nav>
    </header>

    <section class="hero">
        <div class="container">
            <h1>Cadastro e Agendamento</h1>
            <p>Crie sua conta ou agende uma consulta domiciliar</p>
        </div>
    </section>

    <?php if ($sucesso): ?>
        <div class="container">
            <div class="alert alert-success">
                <strong>✓ <?= htmlspecialchars($sucesso) ?></strong>
            </div>
        </div>
    <?php endif; ?>
    
    <?php if ($erro): ?>
        <div class="container">
            <div class="alert alert-error">
                <strong>❌ <?= htmlspecialchars($erro) ?></strong>
            </div>
        </div>
    <?php endif; ?>

    <div class="container">
        <div class="tabs">
            <button class="tab-button active" onclick="switchTab('cadastro')">
                👤 Cadastrar como Familiar/Cuidador
            </button>
            <button class="tab-button" onclick="switchTab('agendamento')">
                📅 Agendar Consulta
            </button>
        </div>

        <!-- Tab Cadastro -->
        <div id="cadastro" class="tab-content active">
            <h2 style="margin-bottom: 1.5rem; color: #3B3A1C;">Criar Nova Conta</h2>
            <form method="POST">
                <div class="form-group">
                    <label for="tipo_usuario">Tipo de Cadastro *</label>
                    <select id="tipo_usuario" name="tipo_usuario" required>
                        <option value="">Selecione...</option>
                        <option value="familiar">Familiar (Acompanhar paciente)</option>
                        <option value="cuidador">Cuidador Profissional</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="nome">Nome Completo *</label>
                    <input type="text" id="nome" name="nome" required>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="cpf_cadastro">CPF *</label>
                        <input type="text" id="cpf_cadastro" name="cpf" required maxlength="14" placeholder="000.000.000-00">
                    </div>
                    <div class="form-group">
                        <label for="telefone_cadastro">Telefone *</label>
                        <input type="tel" id="telefone_cadastro" name="telefone" required maxlength="15" placeholder="(00) 00000-0000">
                    </div>
                </div>

                <div class="form-group">
                    <label for="email_cadastro">Email *</label>
                    <input type="email" id="email_cadastro" name="email" required>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="senha">Senha *</label>
                        <input type="password" id="senha" name="senha" required minlength="6" placeholder="Mínimo 6 caracteres">
                    </div>
                    <div class="form-group">
                        <label for="confirmar_senha">Confirmar Senha *</label>
                        <input type="password" id="confirmar_senha" name="confirmar_senha" required minlength="6">
                    </div>
                </div>

                <div class="form-group">
                    <label>
                        <input type="checkbox" required style="width: auto; margin-right: 0.5rem;">
                        Concordo com os termos de uso e política de privacidade *
                    </label>
                </div>

                <button type="submit" name="cadastrar_usuario" class="submit-btn">Criar Conta</button>

                <p style="text-align: center; margin-top: 1rem;">
                    Já tem uma conta? <a href="login.php" style="color: #A9C166; font-weight: bold;">Faça login aqui</a>
                </p>
            </form>
        </div>

        <!-- Tab Agendamento -->
        <div id="agendamento" class="tab-content">
            <h2 style="margin-bottom: 1.5rem; color: #3B3A1C;">Agendar Consulta Domiciliar</h2>
            <form method="POST" enctype="multipart/form-data">
                <h3 style="margin-bottom: 1rem; color: #A9C166;">Dados do Paciente</h3>

                <div class="form-group">
                    <label for="nome_paciente">Nome Completo *</label>
                    <input type="text" id="nome_paciente" name="nome" required>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="cpf">CPF *</label>
                        <input type="text" id="cpf" name="cpf" required maxlength="14" placeholder="000.000.000-00">
                    </div>
                    <div class="form-group">
                        <label for="idade">Idade *</label>
                        <input type="number" id="idade" name="idade" min="1" max="120" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="sexo">Sexo *</label>
                        <select id="sexo" name="sexo" required>
                            <option value="">Selecione</option>
                            <option value="masculino">Masculino</option>
                            <option value="feminino">Feminino</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="telefone">Telefone *</label>
                        <input type="tel" id="telefone" name="telefone" required maxlength="15" placeholder="(00) 00000-0000">
                    </div>
                </div>

                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email">
                </div>

                <div class="form-group">
                    <label for="endereco">Endereço Completo *</label>
                    <textarea id="endereco" name="endereco" rows="3" required placeholder="Rua, número, bairro, cidade, CEP"></textarea>
                </div>

                <h3 style="margin: 2rem 0 1rem; color: #A9C166;">Informações da Consulta</h3>

                <div class="form-row">
                    <div class="form-group">
                        <label for="servico">Tipo de Serviço *</label>
                        <select id="servico" name="servico" required>
                            <option value="">Selecione...</option>
                            <option value="consulta-medica">Consulta Médica</option>
                            <option value="fisioterapia">Fisioterapia</option>
                            <option value="exames">Exames Domiciliares</option>
                            <option value="enfermagem">Enfermagem</option>
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
                        <label for="urgencia">Urgência</label>
                        <select id="urgencia" name="urgencia">
                            <option value="rotina">Rotina</option>
                            <option value="urgente">Urgente (24-48h)</option>
                            <option value="emergencia">Emergência (ASAP)</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label for="observacoes">Observações</label>
                    <textarea id="observacoes" name="observacoes" rows="3" placeholder="Descreva sintomas, medicações em uso, etc..."></textarea>
                </div>

                <div class="form-group">
                    <label for="identidade">Foto da Identidade (RG ou CNH)</label>
                    <div class="file-upload">
                        <input type="file" id="identidade" name="identidade" accept="image/*,.pdf">
                        <label for="identidade" class="file-upload-label">
                            📷 Clique para enviar foto da identidade
                            <br><small>Formatos: JPG, PNG, PDF (máx. 10MB)</small>
                        </label>
                    </div>
                </div>

                <button type="submit" name="agendar" class="submit-btn">Solicitar Agendamento</button>
            </form>
        </div>
    </div>

    <footer>
        <div class="container">
            <p>&copy; <?= date('Y') ?> CuidarBem - Atendimento Domiciliar</p>
            <p>📞 (17) 9914-08891 | 📧 contato@cuidarbem.com.br</p>
        </div>
    </footer>

    <script>
        function switchTab(tabName) {
            // Esconder todos os tabs
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            document.querySelectorAll('.tab-button').forEach(btn => {
                btn.classList.remove('active');
            });

            // Mostrar tab selecionado
            document.getElementById(tabName).classList.add('active');
            event.target.classList.add('active');
        }

        // Máscara CPF
        function mascaraCPF(input) {
            input.addEventListener('input', function(e) {
                let value = e.target.value.replace(/\D/g, '');
                if (value.length > 11) value = value.slice(0, 11);
                value = value.replace(/(\d{3})(\d)/, '$1.$2');
                value = value.replace(/(\d{3})(\d)/, '$1.$2');
                value = value.replace(/(\d{3})(\d{1,2})$/, '$1-$2');
                e.target.value = value;
            });
        }

        mascaraCPF(document.getElementById('cpf'));
        mascaraCPF(document.getElementById('cpf_cadastro'));

        // Máscara Telefone
        function mascaraTelefone(input) {
            input.addEventListener('input', function(e) {
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
        }

        mascaraTelefone(document.getElementById('telefone'));
        mascaraTelefone(document.getElementById('telefone_cadastro'));

        // Feedback upload
        document.getElementById('identidade').addEventListener('change', function(e) {
            const label = e.target.nextElementSibling;
            const fileName = e.target.files[0]?.name;
            if (fileName) {
                label.innerHTML = `✅ ${fileName}<br><small>Arquivo selecionado!</small>`;
                label.style.background = '#e8f5e9';
                label.style.borderColor = '#28a745';
            }
        });
    </script>
</body>
</html>