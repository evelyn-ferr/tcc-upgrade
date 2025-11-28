<?php
require_once 'config.php';
require_once 'auth.php';

$erro = '';
$sucesso = '';

// Se já estiver logado, redirecionar
if (isLogged()) {
    if (isFamiliar()) {
        header('Location: perfil-familiar.php');
    } elseif (isCuidador()) {
        header('Location: perfil-cuidador.php');
    } else {
        header('Location: dashboard.php');
    }
    exit;
}

// Processar login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $email = sanitize($_POST['email']);
    $senha = $_POST['senha'];
    $tipo = sanitize($_POST['tipo']);
    
    if (empty($email) || empty($senha)) {
        $erro = 'Preencha todos os campos';
    } else {
        $resultado = Auth::login($email, $senha);
        
        if ($resultado['success']) {
            // Verificar se o tipo está correto
            if ($resultado['tipo'] === $tipo) {
                // Redirecionar para a página apropriada
                if ($tipo === 'familiar') {
                    header('Location: perfil-familiar.php');
                } elseif ($tipo === 'cuidador') {
                    header('Location: perfil-cuidador.php');
                } else {
                    header('Location: dashboard.php');
                }
                exit;
            } else {
                Auth::logout();
                $erro = 'Tipo de usuário incorreto. Você está tentando acessar como ' . 
                        ($tipo === 'familiar' ? 'Familiar' : 'Cuidador') . 
                        ' mas seu cadastro é como ' . 
                        ($resultado['tipo'] === 'familiar' ? 'Familiar' : 'Cuidador');
            }
        } else {
            $erro = $resultado['message'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CuidarBem - Área para Cuidadores e Familiares</title>
    <link rel="stylesheet" href="style-familiar.css">
</head>
<body>
    <header>
        <nav class="container">
            <div class="logo">🏥 CuidarBem</div>
            <ul class="nav-links">
                <li><a href="index.php">Voltar ao Site</a></li>
                <li><a href="#beneficios">Benefícios</a></li>
                <li><a href="#contato">Contato</a></li>
            </ul>
        </nav>
    </header>

    <section class="hero">
        <div class="container">
            <h1>Área para Cuidadores e Familiares</h1>
            <p>Acompanhe o tratamento, agenda e receba orientações sobre o paciente</p>
        </div>
    </section>

    <div class="container">
        <?php if ($erro): ?>
            <div style="background: #ffebee; color: #c62828; padding: 1rem; border-radius: 8px; margin: 20px auto; max-width: 850px; text-align: center;">
                <strong>❌ <?= $erro ?></strong>
            </div>
        <?php endif; ?>
        
        <?php if ($sucesso): ?>
            <div style="background: #e8f5e9; color: #2e7d32; padding: 1rem; border-radius: 8px; margin: 20px auto; max-width: 850px; text-align: center;">
                <strong>✓ <?= $sucesso ?></strong>
            </div>
        <?php endif; ?>
        
        <div class="login-container">
            <div class="login-section">
                <h2>Acesso para Familiares</h2>
                <p>Faça login para acompanhar o tratamento do seu ente querido, visualizar a agenda de consultas e receber orientações da equipe médica.</p>
                
                <form method="POST" action="">
                    <input type="hidden" name="tipo" value="familiar">
                    
                    <div class="form-group">
                        <label for="emailFamiliar">Email</label>
                        <input type="email" id="emailFamiliar" name="email" required placeholder="seu@email.com">
                    </div>
                    
                    <div class="form-group">
                        <label for="senhaFamiliar">Senha</label>
                        <input type="password" id="senhaFamiliar" name="senha" required placeholder="Sua senha">
                    </div>
                    
                    <button type="submit" name="login" class="login-btn">Acessar como Familiar</button>
                    
                    <div style="margin-top: 1rem; text-align: center; font-size: 0.9rem;">
                        <p>Não tem conta? <a href="cadastro.php" style="color: #A9C166; font-weight: bold;">Cadastre-se</a></p>
                        <p style="color: #999; margin-top: 0.5rem;">Email de teste: maria.familiar@email.com<br>Senha: 123456</p>
                    </div>
                </form>
            </div>
            
            <div class="login-section">
                <h2>Acesso para Cuidadores</h2>
                <p>Profissionais cadastrados podem acessar os prontuários, registrar evoluções e receber orientações da equipe médica.</p>
                
                <form method="POST" action="">
                    <input type="hidden" name="tipo" value="cuidador">
                    
                    <div class="form-group">
                        <label for="emailCuidador">Email Profissional</label>
                        <input type="email" id="emailCuidador" name="email" required placeholder="profissional@email.com">
                    </div>
                    
                    <div class="form-group">
                        <label for="senhaCuidador">Senha</label>
                        <input type="password" id="senhaCuidador" name="senha" required placeholder="Sua senha">
                    </div>
                    
                    <button type="submit" name="login" class="login-btn" style="background: linear-gradient(45deg, #d7285f, #e88dc5);">Acessar como Cuidador</button>
                    
                    <div style="margin-top: 1rem; text-align: center; font-size: 0.9rem;">
                        <p style="color: #999;">Email de teste: joao.silva@email.com<br>Senha: 123456</p>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <section id="beneficios" class="features">
        <div class="container">
            <h2 style="text-align: center; font-size: 2.5rem; margin-bottom: 1rem;">Benefícios da Área Exclusiva</h2>
            <div class="features-grid">
                <div class="feature-card">
                    <div class="feature-icon">📅</div>
                    <h3>Agenda Compartilhada</h3>
                    <p>Acompanhe todas as consultas e visitas agendadas em tempo real, com lembretes automáticos.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">📋</div>
                    <h3>Prontuário Digital</h3>
                    <p>Acesse o histórico médico completo, incluindo receitas, exames e evoluções.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">💬</div>
                    <h3>Comunicação Direta</h3>
                    <p>Tire dúvidas e receba orientações diretamente da equipe médica responsável.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">📱</div>
                    <h3>Acesso Mobile</h3>
                    <p>Disponível 24h por dia em qualquer dispositivo com internet.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">🔒</div>
                    <h3>Segurança de Dados</h3>
                    <p>Todas as informações são criptografadas e protegidas conforme as normas de privacidade.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">👨‍👩‍👧‍👦</div>
                    <h3>Perfil Familiar</h3>
                    <p>Vários familiares podem ter acesso ao mesmo paciente, com diferentes níveis de permissão.</p>
                </div>
            </div>
        </div>
    </section>

    <section id="contato" style="padding: 60px 0; background: linear-gradient(rgba(164, 10, 59, 0.8), rgba(255, 211, 243, 0.8)); color: white; text-align: center;">
        <div class="container">
            <h2>Precisa de Ajuda?</h2>
            <p style="font-size: 1.2rem; margin: 1rem 0;">📱 (17) 9914-08891 | 📧 suporte@cuidarbem.com.br</p>
            <p>Nosso time de suporte está disponível de segunda a sexta, das 8h às 18h</p>
        </div>
    </section>

    <footer>
        <div class="container">
            <p>&copy; <?= date('Y') ?> CuidarBem - Atendimento Domiciliar. Todos os direitos reservados.</p>
        </div>
    </footer>
</body>
</html>
