<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CuidarBem - Instalação</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #E8D5D0 0%, #A9C166 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .container {
            background: white;
            padding: 3rem;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-width: 600px;
            width: 100%;
        }
        h1 {
            color: #A9C166;
            margin-bottom: 0.5rem;
            text-align: center;
        }
        p { color: #666; margin-bottom: 2rem; text-align: center; }
        .form-group {
            margin-bottom: 1.5rem;
        }
        label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #333;
        }
        input, select {
            width: 100%;
            padding: 0.8rem;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 1rem;
        }
        input:focus, select:focus {
            outline: none;
            border-color: #A9C166;
        }
        button {
            width: 100%;
            padding: 1rem;
            background: linear-gradient(45deg, #A9C166, #8AA654);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.3s;
        }
        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(169, 193, 102, 0.4);
        }
        .success {
            background: #d4edda;
            color: #155724;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
        }
        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
        }
        .check-item {
            padding: 0.5rem;
            margin: 0.5rem 0;
            border-left: 3px solid #ccc;
            padding-left: 1rem;
        }
        .check-item.ok {
            border-color: #28a745;
            background: #d4edda;
        }
        .check-item.fail {
            border-color: #dc3545;
            background: #f8d7da;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🏥 CuidarBem</h1>
        <p>Instalador Automático do Sistema</p>
        
        <?php
        $mensagem = '';
        $tipo = '';
        
        // Verificações do sistema
        $checks = [
            'PHP >= 7.4' => version_compare(PHP_VERSION, '7.4.0', '>='),
            'Extensão PDO' => extension_loaded('pdo'),
            'Extensão PDO MySQL' => extension_loaded('pdo_mysql'),
            'Extensão mbstring' => extension_loaded('mbstring'),
            'Pasta uploads/ gravável' => is_writable(__DIR__ . '/uploads') || @mkdir(__DIR__ . '/uploads', 0755, true)
        ];
        
        $todosOk = !in_array(false, $checks);
        
        // Processar instalação
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['instalar'])) {
            try {
                // Conectar ao banco
                $dsn = "mysql:host={$_POST['db_host']};charset=utf8mb4";
                $pdo = new PDO($dsn, $_POST['db_user'], $_POST['db_pass']);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                
                // Criar banco de dados
                $pdo->exec("CREATE DATABASE IF NOT EXISTS {$_POST['db_name']} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                $pdo->exec("USE {$_POST['db_name']}");
                
                // Ler e executar SQL
                $sql = file_get_contents(__DIR__ . '/database.sql');
                
                // Remover comentários e linhas vazias
                $sql = preg_replace('/^--.*$/m', '', $sql);
                $sql = preg_replace('/^\s*$/m', '', $sql);
                
                // Executar comandos SQL
                $statements = explode(';', $sql);
                foreach ($statements as $statement) {
                    $statement = trim($statement);
                    if (!empty($statement)) {
                        try {
                            $pdo->exec($statement);
                        } catch (PDOException $e) {
                            // Ignorar erros de views e procedures que já existem
                            if (strpos($e->getMessage(), 'already exists') === false) {
                                throw $e;
                            }
                        }
                    }
                }
                
                // Atualizar config.php
                $configContent = file_get_contents(__DIR__ . '/config.php');
                $configContent = preg_replace(
                    "/define\('DB_HOST', '.*?'\);/",
                    "define('DB_HOST', '{$_POST['db_host']}');",
                    $configContent
                );
                $configContent = preg_replace(
                    "/define\('DB_NAME', '.*?'\);/",
                    "define('DB_NAME', '{$_POST['db_name']}');",
                    $configContent
                );
                $configContent = preg_replace(
                    "/define\('DB_USER', '.*?'\);/",
                    "define('DB_USER', '{$_POST['db_user']}');",
                    $configContent
                );
                $configContent = preg_replace(
                    "/define\('DB_PASS', '.*?'\);/",
                    "define('DB_PASS', '{$_POST['db_pass']}');",
                    $configContent
                );
                
                file_put_contents(__DIR__ . '/config.php', $configContent);
                
                // Criar pastas de upload
                @mkdir(__DIR__ . '/uploads/identidades', 0755, true);
                @mkdir(__DIR__ . '/uploads/exames', 0755, true);
                @mkdir(__DIR__ . '/uploads/geral', 0755, true);
                
                $mensagem = 'Instalação concluída com sucesso! Você já pode usar o sistema.';
                $tipo = 'success';
                
            } catch (Exception $e) {
                $mensagem = 'Erro na instalação: ' . $e->getMessage();
                $tipo = 'error';
            }
        }
        ?>
        
        <?php if ($mensagem): ?>
            <div class="<?= $tipo ?>">
                <?= htmlspecialchars($mensagem) ?>
                <?php if ($tipo === 'success'): ?>
                    <br><br>
                    <strong>Credenciais de teste:</strong><br>
                    Familiar: maria.familiar@email.com / 123456<br>
                    Cuidador: joao.silva@email.com / 123456<br>
                    <br>
                    <a href="index.php" style="color: #155724; text-decoration: underline;">
                        Ir para a página inicial →
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <h3 style="margin: 1.5rem 0 1rem 0; color: #333;">Verificações do Sistema</h3>
        <?php foreach ($checks as $nome => $ok): ?>
            <div class="check-item <?= $ok ? 'ok' : 'fail' ?>">
                <?= $ok ? '✓' : '✗' ?> <?= $nome ?>
            </div>
        <?php endforeach; ?>
        
        <?php if ($todosOk): ?>
            <h3 style="margin: 2rem 0 1rem 0; color: #333;">Configuração do Banco de Dados</h3>
            <form method="POST">
                <div class="form-group">
                    <label>Host do Banco de Dados</label>
                    <input type="text" name="db_host" value="localhost" required>
                </div>
                
                <div class="form-group">
                    <label>Nome do Banco de Dados</label>
                    <input type="text" name="db_name" value="cuidarbem" required>
                </div>
                
                <div class="form-group">
                    <label>Usuário do Banco</label>
                    <input type="text" name="db_user" value="root" required>
                </div>
                
                <div class="form-group">
                    <label>Senha do Banco</label>
                    <input type="password" name="db_pass">
                </div>
                
                <button type="submit" name="instalar">Instalar Sistema</button>
            </form>
        <?php else: ?>
            <div class="error" style="margin-top: 1rem;">
                <strong>⚠️ Atenção!</strong><br>
                Seu servidor não atende todos os requisitos. Por favor, corrija os itens marcados com ✗ antes de continuar.
            </div>
        <?php endif; ?>
        
        <p style="margin-top: 2rem; font-size: 0.9rem;">
            <strong>Nota:</strong> Este instalador configura automaticamente o banco de dados e as pastas necessárias.
            Após a instalação bem-sucedida, você pode remover este arquivo (install.php) por segurança.
        </p>
    </div>
</body>
</html>
