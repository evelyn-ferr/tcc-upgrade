<?php
/**
 * CuidarBem - Sistema de Autenticação
 * 
 * Funções para gerenciar login, logout e sessões
 */

require_once 'config.php';

// Iniciar sessão se ainda não foi iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Classe de Autenticação
 */
class Auth {
    
    /**
     * Realizar login do usuário
     */
    public static function login($email, $senha) {
        try {
            $db = getDB();
            
            $stmt = $db->prepare("
                SELECT id, nome, email, senha, tipo_usuario, telefone, cpf, status 
                FROM usuarios 
                WHERE email = ? AND status = 'ativo'
            ");
            
            $stmt->execute([$email]);
            $usuario = $stmt->fetch();
            
            if ($usuario && password_verify($senha, $usuario['senha'])) {
                // Login bem-sucedido
                $_SESSION['user_id'] = $usuario['id'];
                $_SESSION['user_name'] = $usuario['nome'];
                $_SESSION['user_email'] = $usuario['email'];
                $_SESSION['user_type'] = $usuario['tipo_usuario'];
                $_SESSION['logged_in'] = true;
                
                // Registrar último acesso
                $stmt = $db->prepare("
                    UPDATE usuarios 
                    SET data_ultimo_acesso = NOW() 
                    WHERE id = ?
                ");
                $stmt->execute([$usuario['id']]);
                
                return [
                    'success' => true, 
                    'tipo' => $usuario['tipo_usuario'],
                    'nome' => $usuario['nome']
                ];
            }
            
            return ['success' => false, 'message' => 'Email ou senha incorretos'];
            
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Erro ao fazer login: ' . $e->getMessage()];
        }
    }
    
    /**
     * Realizar logout do usuário
     */
    public static function logout() {
        session_destroy();
        header('Location: index.php');
        exit;
    }
    
    /**
     * Verificar se o usuário está logado
     */
    public static function check() {
        return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
    }
    
    /**
     * Verificar se o usuário é de determinado tipo
     */
    public static function checkTipo($tipo) {
        if (!self::check()) {
            return false;
        }
        
        if (is_array($tipo)) {
            return in_array($_SESSION['user_type'], $tipo);
        }
        
        return $_SESSION['user_type'] === $tipo;
    }
    
    /**
     * Redirecionar se não estiver logado
     */
    public static function requireLogin() {
        if (!self::check()) {
            header('Location: login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
            exit;
        }
    }
    
    /**
     * Redirecionar se não for do tipo especificado
     */
    public static function requireTipo($tipo) {
        self::requireLogin();
        
        if (!self::checkTipo($tipo)) {
            header('Location: sem-permissao.php');
            exit;
        }
    }
    
    /**
     * Obter ID do usuário logado
     */
    public static function getUserId() {
        return $_SESSION['user_id'] ?? null;
    }
    
    /**
     * Obter nome do usuário logado
     */
    public static function getUserName() {
        return $_SESSION['user_name'] ?? '';
    }
    
    /**
     * Obter tipo do usuário logado
     */
    public static function getUserType() {
        return $_SESSION['user_type'] ?? '';
    }
    
    /**
     * Registrar novo usuário
     */
    public static function registrar($dados) {
        try {
            $db = getDB();
            
            // Validações
            if (!validarEmail($dados['email'])) {
                return ['success' => false, 'message' => 'Email inválido'];
            }
            
            if (!validarCPF($dados['cpf'])) {
                return ['success' => false, 'message' => 'CPF inválido'];
            }
            
            if (strlen($dados['senha']) < 6) {
                return ['success' => false, 'message' => 'Senha deve ter no mínimo 6 caracteres'];
            }
            
            // Verificar se email já existe
            $stmt = $db->prepare("SELECT id FROM usuarios WHERE email = ?");
            $stmt->execute([$dados['email']]);
            if ($stmt->fetch()) {
                return ['success' => false, 'message' => 'Email já cadastrado'];
            }
            
            // Verificar se CPF já existe
            $stmt = $db->prepare("SELECT id FROM usuarios WHERE cpf = ?");
            $stmt->execute([$dados['cpf']]);
            if ($stmt->fetch()) {
                return ['success' => false, 'message' => 'CPF já cadastrado'];
            }
            
            // Hash da senha
            $senhaHash = password_hash($dados['senha'], PASSWORD_DEFAULT);
            
            // Inserir usuário
            $stmt = $db->prepare("
                INSERT INTO usuarios (nome, email, senha, tipo_usuario, telefone, cpf) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $dados['nome'],
                $dados['email'],
                $senhaHash,
                $dados['tipo_usuario'],
                $dados['telefone'] ?? null,
                $dados['cpf']
            ]);
            
            return ['success' => true, 'message' => 'Usuário cadastrado com sucesso'];
            
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Erro ao cadastrar: ' . $e->getMessage()];
        }
    }
    
    /**
     * Alterar senha do usuário
     */
    public static function alterarSenha($usuarioId, $senhaAtual, $senhaNova) {
        try {
            $db = getDB();
            
            // Verificar senha atual
            $stmt = $db->prepare("SELECT senha FROM usuarios WHERE id = ?");
            $stmt->execute([$usuarioId]);
            $usuario = $stmt->fetch();
            
            if (!$usuario || !password_verify($senhaAtual, $usuario['senha'])) {
                return ['success' => false, 'message' => 'Senha atual incorreta'];
            }
            
            if (strlen($senhaNova) < 6) {
                return ['success' => false, 'message' => 'Nova senha deve ter no mínimo 6 caracteres'];
            }
            
            // Atualizar senha
            $senhaHash = password_hash($senhaNova, PASSWORD_DEFAULT);
            $stmt = $db->prepare("UPDATE usuarios SET senha = ? WHERE id = ?");
            $stmt->execute([$senhaHash, $usuarioId]);
            
            return ['success' => true, 'message' => 'Senha alterada com sucesso'];
            
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Erro ao alterar senha: ' . $e->getMessage()];
        }
    }
    
    /**
     * Recuperar senha (enviar email com token)
     */
    public static function recuperarSenha($email) {
        try {
            $db = getDB();
            
            $stmt = $db->prepare("SELECT id, nome FROM usuarios WHERE email = ?");
            $stmt->execute([$email]);
            $usuario = $stmt->fetch();
            
            if (!$usuario) {
                return ['success' => false, 'message' => 'Email não encontrado'];
            }
            
            // Gerar token
            $token = bin2hex(random_bytes(32));
            $expira = date('Y-m-d H:i:s', strtotime('+1 hour'));
            
            // Salvar token no banco (você precisará criar uma tabela para isso)
            // Por enquanto, retornar sucesso
            
            // TODO: Implementar envio de email
            
            return [
                'success' => true, 
                'message' => 'Email de recuperação enviado (funcionalidade em desenvolvimento)',
                'token' => $token // Remover em produção
            ];
            
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Erro ao recuperar senha: ' . $e->getMessage()];
        }
    }
}

/**
 * Funções auxiliares de sessão
 */

function isLogged() {
    return Auth::check();
}

function isFamiliar() {
    return Auth::checkTipo('familiar');
}

function isCuidador() {
    return Auth::checkTipo('cuidador');
}

function isAdmin() {
    return Auth::checkTipo('admin');
}

function getUserId() {
    return Auth::getUserId();
}

function getUserName() {
    return Auth::getUserName();
}

function getUserType() {
    return Auth::getUserType();
}

/**
 * Middleware de proteção de página
 */
function requireLogin() {
    Auth::requireLogin();
}

function requireFamiliar() {
    Auth::requireTipo('familiar');
}

function requireCuidador() {
    Auth::requireTipo('cuidador');
}

function requireAdmin() {
    Auth::requireTipo('admin');
}
?>
