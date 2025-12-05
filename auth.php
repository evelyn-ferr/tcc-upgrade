<?php
/**
 * CuidarBem - Sistema de Autenticação
 * VERSÃO CORRIGIDA
 */

// Configurar charset
header('Content-Type: text/html; charset=UTF-8');
mb_internal_encoding('UTF-8');

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
            
            // Limpar email
            $email = trim(strtolower($email));
            
            $stmt = $db->prepare("
                SELECT id, nome, email, senha, tipo_usuario, telefone, cpf, status 
                FROM usuarios 
                WHERE LOWER(email) = ? AND status = 'ativo'
            ");
            
            $stmt->execute([$email]);
            $usuario = $stmt->fetch();
            
            if (!$usuario) {
                return ['success' => false, 'message' => 'Email não encontrado ou conta inativa'];
            }
            
            if (!password_verify($senha, $usuario['senha'])) {
                return ['success' => false, 'message' => 'Senha incorreta'];
            }
            
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
            
        } catch (PDOException $e) {
            error_log("Erro no login: " . $e->getMessage());
            return ['success' => false, 'message' => 'Erro ao processar login. Tente novamente.'];
        }
    }
    
    /**
     * Realizar logout do usuário
     */
    public static function logout() {
        $_SESSION = array();
        if (isset($_COOKIE[session_name()])) {
            setcookie(session_name(), '', time()-42000, '/');
        }
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
            
            // Limpar e validar dados
            $email = trim(strtolower($dados['email']));
            $cpf = preg_replace('/[^0-9]/', '', $dados['cpf']);
            $telefone = preg_replace('/[^0-9]/', '', $dados['telefone'] ?? '');
            $nome = trim($dados['nome']);
            $senha = $dados['senha'];
            $tipo = $dados['tipo_usuario'];
            
            // Validações
            if (empty($nome) || empty($email) || empty($senha) || empty($tipo) || empty($cpf)) {
                return ['success' => false, 'message' => 'Preencha todos os campos obrigatórios'];
            }
            
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return ['success' => false, 'message' => 'Email inválido'];
            }
            
            if (!validarCPF($cpf)) {
                return ['success' => false, 'message' => 'CPF inválido'];
            }
            
            if (strlen($senha) < 6) {
                return ['success' => false, 'message' => 'Senha deve ter no mínimo 6 caracteres'];
            }
            
            if (!in_array($tipo, ['familiar', 'cuidador'])) {
                return ['success' => false, 'message' => 'Tipo de usuário inválido'];
            }
            
            // Verificar se email já existe
            $stmt = $db->prepare("SELECT COUNT(*) FROM usuarios WHERE LOWER(email) = ?");
            $stmt->execute([$email]);
            if ($stmt->fetchColumn() > 0) {
                return ['success' => false, 'message' => 'Este email já está cadastrado'];
            }
            
            // Verificar se CPF já existe
            $stmt = $db->prepare("SELECT COUNT(*) FROM usuarios WHERE cpf = ?");
            $stmt->execute([$cpf]);
            if ($stmt->fetchColumn() > 0) {
                return ['success' => false, 'message' => 'Este CPF já está cadastrado'];
            }
            
            // Hash da senha
            $senhaHash = password_hash($senha, PASSWORD_DEFAULT);
            
            // Inserir usuário
            $stmt = $db->prepare("
                INSERT INTO usuarios (nome, email, senha, tipo_usuario, telefone, cpf, status) 
                VALUES (?, ?, ?, ?, ?, ?, 'ativo')
            ");
            
            $resultado = $stmt->execute([
                $nome,
                $email,
                $senhaHash,
                $tipo,
                $telefone,
                $cpf
            ]);
            
            if ($resultado) {
                return ['success' => true, 'message' => 'Cadastro realizado com sucesso!'];
            } else {
                return ['success' => false, 'message' => 'Erro ao salvar cadastro'];
            }
            
        } catch (PDOException $e) {
            error_log("Erro no cadastro: " . $e->getMessage());
            return ['success' => false, 'message' => 'Erro ao processar cadastro. Tente novamente.'];
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
            error_log("Erro ao alterar senha: " . $e->getMessage());
            return ['success' => false, 'message' => 'Erro ao alterar senha. Tente novamente.'];
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