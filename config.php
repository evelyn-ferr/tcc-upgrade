<?php

define('DB_HOST', 'localhost:3307');
define('DB_NAME', 'cuidarbem');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Configurações do sistema
define('SITE_URL', 'http://localhost/cuidarbem');
define('SITE_NAME', 'CuidarBem');
define('UPLOAD_DIR', __DIR__ . '/uploads/');
define('MAX_FILE_SIZE', 10485760); // 10MB em bytes

// Fuso horário
date_default_timezone_set('America/Sao_Paulo');

/**
 * Classe de Conexão com o Banco de Dados
 */
class Database {
    private static $instance = null;
    private $conn;
    
    private function __construct() {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET
            ];
            
            $this->conn = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            die("Erro na conexão com o banco de dados: " . $e->getMessage());
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->conn;
    }
    
    // Prevenir clonagem
    private function __clone() {}
    
    // Prevenir unserialize
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }
}

/**
 * Função auxiliar para obter a conexão
 */
function getDB() {
    return Database::getInstance()->getConnection();
}

/**
 * Função para sanitizar entrada de dados
 */
function sanitize($data) {
    if (is_array($data)) {
        return array_map('sanitize', $data);
    }
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

/**
 * Função para validar email
 */
function validarEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

/**
 * Função para validar CPF
 */
function validarCPF($cpf) {
    $cpf = preg_replace('/[^0-9]/', '', $cpf);
    
    if (strlen($cpf) != 11) {
        return false;
    }
    
    // Verifica se todos os dígitos são iguais
    if (preg_match('/(\d)\1{10}/', $cpf)) {
        return false;
    }
    
    // Valida primeiro dígito verificador
    for ($t = 9; $t < 11; $t++) {
        for ($d = 0, $c = 0; $c < $t; $c++) {
            $d += $cpf[$c] * (($t + 1) - $c);
        }
        $d = ((10 * $d) % 11) % 10;
        if ($cpf[$c] != $d) {
            return false;
        }
    }
    
    return true;
}

/**
 * Função para formatar CPF
 */
function formatarCPF($cpf) {
    $cpf = preg_replace('/[^0-9]/', '', $cpf);
    return preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $cpf);
}

/**
 * Função para formatar telefone
 */
function formatarTelefone($telefone) {
    $telefone = preg_replace('/[^0-9]/', '', $telefone);
    if (strlen($telefone) == 11) {
        return preg_replace('/(\d{2})(\d{5})(\d{4})/', '($1) $2-$3', $telefone);
    } elseif (strlen($telefone) == 10) {
        return preg_replace('/(\d{2})(\d{4})(\d{4})/', '($1) $2-$3', $telefone);
    }
    return $telefone;
}

/**
 * Função para formatar data do MySQL para BR
 */
function formatarDataBR($data) {
    if (empty($data)) return '';
    $timestamp = strtotime($data);
    return date('d/m/Y', $timestamp);
}

/**
 * Função para formatar data e hora do MySQL para BR
 */
function formatarDataHoraBR($dataHora) {
    if (empty($dataHora)) return '';
    $timestamp = strtotime($dataHora);
    return date('d/m/Y H:i', $timestamp);
}

/**
 * Função para upload de arquivos
 */
function uploadArquivo($file, $pasta = 'geral') {
    $uploadDir = UPLOAD_DIR . $pasta . '/';
    
    // Cria o diretório se não existir
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    // Validações
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'Erro no upload do arquivo'];
    }
    
    if ($file['size'] > MAX_FILE_SIZE) {
        return ['success' => false, 'message' => 'Arquivo muito grande. Máximo: 10MB'];
    }
    
    // Extensões permitidas
    $extensoesPermitidas = ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx'];
    $extensao = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if (!in_array($extensao, $extensoesPermitidas)) {
        return ['success' => false, 'message' => 'Tipo de arquivo não permitido'];
    }
    
    // Nome único para o arquivo
    $nomeArquivo = uniqid() . '_' . time() . '.' . $extensao;
    $caminhoCompleto = $uploadDir . $nomeArquivo;
    
    if (move_uploaded_file($file['tmp_name'], $caminhoCompleto)) {
        return [
            'success' => true, 
            'filename' => $nomeArquivo,
            'path' => $pasta . '/' . $nomeArquivo
        ];
    }
    
    return ['success' => false, 'message' => 'Erro ao mover arquivo'];
}

/**
 * Função para exibir mensagens de alerta
 */
function exibirMensagem($tipo, $mensagem) {
    $class = 'alert-' . $tipo;
    $icone = [
        'success' => '✓',
        'error' => '✗',
        'warning' => '⚠',
        'info' => 'ℹ'
    ][$tipo] ?? 'ℹ';
    
    return "<div class='alert {$class}'>{$icone} {$mensagem}</div>";
}
?>
