CREATE DATABASE IF NOT EXISTS cuidarbem CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE cuidarbem;

-- Tabela de Usuários
CREATE TABLE IF NOT EXISTS usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    senha VARCHAR(255) NOT NULL,
    tipo_usuario ENUM('familiar', 'cuidador', 'admin') NOT NULL,
    telefone VARCHAR(20),
    cpf VARCHAR(14) UNIQUE,
    data_cadastro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('ativo', 'inativo') DEFAULT 'ativo',
    INDEX idx_email (email),
    INDEX idx_tipo (tipo_usuario)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de Pacientes
CREATE TABLE IF NOT EXISTS pacientes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    cpf VARCHAR(14) UNIQUE NOT NULL,
    idade INT NOT NULL,
    sexo ENUM('masculino', 'feminino') NOT NULL,
    telefone VARCHAR(20),
    email VARCHAR(100),
    endereco TEXT NOT NULL,
    diagnostico VARCHAR(255),
    status_saude ENUM('estavel', 'atencao', 'critico') DEFAULT 'estavel',
    medico_responsavel VARCHAR(100),
    data_cadastro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    foto_identidade VARCHAR(255),
    INDEX idx_cpf (cpf),
    INDEX idx_status (status_saude)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de Vínculos Familiar-Paciente
CREATE TABLE IF NOT EXISTS vinculos_familiar (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    paciente_id INT NOT NULL,
    grau_parentesco VARCHAR(50),
    data_vinculo TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (paciente_id) REFERENCES pacientes(id) ON DELETE CASCADE,
    UNIQUE KEY unique_vinculo (usuario_id, paciente_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de Vínculos Cuidador-Paciente
CREATE TABLE IF NOT EXISTS vinculos_cuidador (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cuidador_id INT NOT NULL,
    paciente_id INT NOT NULL,
    data_inicio DATE NOT NULL,
    data_fim DATE,
    status ENUM('ativo', 'inativo') DEFAULT 'ativo',
    FOREIGN KEY (cuidador_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (paciente_id) REFERENCES pacientes(id) ON DELETE CASCADE,
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de Agendamentos/Consultas
CREATE TABLE IF NOT EXISTS agendamentos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    paciente_id INT NOT NULL,
    tipo_servico ENUM('consulta-medica', 'fisioterapia', 'exames', 'emergencia', 'enfermagem') NOT NULL,
    data_agendamento DATE NOT NULL,
    periodo ENUM('manha', 'tarde', 'noite') NOT NULL,
    horario TIME,
    urgencia ENUM('rotina', 'urgente', 'emergencia') DEFAULT 'rotina',
    observacoes TEXT,
    status ENUM('pendente', 'confirmado', 'realizado', 'cancelado') DEFAULT 'pendente',
    data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (paciente_id) REFERENCES pacientes(id) ON DELETE CASCADE,
    INDEX idx_data (data_agendamento),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de Sinais Vitais
CREATE TABLE IF NOT EXISTS sinais_vitais (
    id INT AUTO_INCREMENT PRIMARY KEY,
    paciente_id INT NOT NULL,
    pressao_arterial VARCHAR(20),
    frequencia_cardiaca INT,
    temperatura DECIMAL(4,1),
    glicemia INT,
    saturacao_oxigenio INT,
    data_medicao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    registrado_por INT,
    FOREIGN KEY (paciente_id) REFERENCES pacientes(id) ON DELETE CASCADE,
    FOREIGN KEY (registrado_por) REFERENCES usuarios(id) ON DELETE SET NULL,
    INDEX idx_paciente_data (paciente_id, data_medicao)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de Medicações
CREATE TABLE IF NOT EXISTS medicacoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    paciente_id INT NOT NULL,
    nome_medicamento VARCHAR(100) NOT NULL,
    dosagem VARCHAR(50) NOT NULL,
    frequencia VARCHAR(100) NOT NULL,
    horario_administracao TIME NOT NULL,
    data_inicio DATE NOT NULL,
    data_fim DATE,
    observacoes TEXT,
    status ENUM('ativo', 'suspenso', 'concluido') DEFAULT 'ativo',
    FOREIGN KEY (paciente_id) REFERENCES pacientes(id) ON DELETE CASCADE,
    INDEX idx_paciente_status (paciente_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de Registro de Administração de Medicamentos
CREATE TABLE IF NOT EXISTS registro_medicamentos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    medicacao_id INT NOT NULL,
    data_administracao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    administrado_por INT NOT NULL,
    observacoes TEXT,
    FOREIGN KEY (medicacao_id) REFERENCES medicacoes(id) ON DELETE CASCADE,
    FOREIGN KEY (administrado_por) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_medicacao_data (medicacao_id, data_administracao)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de Evoluções/Prontuário
CREATE TABLE IF NOT EXISTS evolucoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    paciente_id INT NOT NULL,
    descricao TEXT NOT NULL,
    tipo ENUM('evolucao', 'intercorrencia', 'procedimento', 'observacao') NOT NULL,
    registrado_por INT NOT NULL,
    data_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (paciente_id) REFERENCES pacientes(id) ON DELETE CASCADE,
    FOREIGN KEY (registrado_por) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_paciente_data (paciente_id, data_registro)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de Documentos/Exames
CREATE TABLE IF NOT EXISTS documentos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    paciente_id INT NOT NULL,
    tipo_documento ENUM('exame', 'laudo', 'receita', 'relatorio', 'identidade', 'outro') NOT NULL,
    nome_arquivo VARCHAR(255) NOT NULL,
    caminho_arquivo VARCHAR(500) NOT NULL,
    descricao TEXT,
    data_upload TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    enviado_por INT,
    FOREIGN KEY (paciente_id) REFERENCES pacientes(id) ON DELETE CASCADE,
    FOREIGN KEY (enviado_por) REFERENCES usuarios(id) ON DELETE SET NULL,
    INDEX idx_paciente_tipo (paciente_id, tipo_documento)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de Orientações Médicas
CREATE TABLE IF NOT EXISTS orientacoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    paciente_id INT NOT NULL,
    titulo VARCHAR(255) NOT NULL,
    descricao TEXT NOT NULL,
    tipo ENUM('cuidado', 'dieta', 'medicacao', 'alerta', 'procedimento') NOT NULL,
    criado_por INT NOT NULL,
    data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('ativo', 'inativo') DEFAULT 'ativo',
    FOREIGN KEY (paciente_id) REFERENCES pacientes(id) ON DELETE CASCADE,
    FOREIGN KEY (criado_por) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_paciente_status (paciente_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de Notificações
CREATE TABLE IF NOT EXISTS notificacoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    titulo VARCHAR(255) NOT NULL,
    mensagem TEXT NOT NULL,
    tipo ENUM('info', 'alerta', 'urgente', 'sucesso') DEFAULT 'info',
    lida BOOLEAN DEFAULT FALSE,
    data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_usuario_lida (usuario_id, lida)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ====================================
-- DADOS INICIAIS PARA TESTES
-- ====================================

-- Senha padrão para todos: 123456 (hash bcrypt)
-- Hash gerado com password_hash('123456', PASSWORD_DEFAULT)

-- Usuários de exemplo
INSERT INTO usuarios (nome, email, senha, tipo_usuario, telefone, cpf) VALUES
('João Silva', 'joao.silva@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'cuidador', '(17) 99256-5680', '123.456.789-00'),
('Maria Santos Familiar', 'maria.familiar@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'familiar', '(17) 99914-0889', '987.654.321-00'),
('Admin Sistema', 'admin@cuidarbem.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', '(17) 99999-9999', '111.222.333-44');

-- Paciente de exemplo
INSERT INTO pacientes (nome, cpf, idade, sexo, telefone, email, endereco, diagnostico, status_saude, medico_responsavel) VALUES
('Maria Santos', '555.666.777-88', 78, 'feminino', '(17) 98888-7777', 'maria.santos@email.com', 
 'Rua das Flores, 123, Centro, Votuporanga/SP, CEP: 15500-000', 
 'Diabetes Tipo 2', 'estavel', 'Dr. Carlos Lima');

-- Vínculos
INSERT INTO vinculos_familiar (usuario_id, paciente_id, grau_parentesco) VALUES (2, 1, 'Filha');
INSERT INTO vinculos_cuidador (cuidador_id, paciente_id, data_inicio, status) VALUES (1, 1, '2025-01-01', 'ativo');

-- Medicações
INSERT INTO medicacoes (paciente_id, nome_medicamento, dosagem, frequencia, horario_administracao, data_inicio, status) VALUES
(1, 'Metformina', '500mg', 'A cada 12 horas', '08:00:00', '2025-01-01', 'ativo'),
(1, 'Insulina NPH', '10 UI', '1x ao dia', '08:00:00', '2025-01-01', 'ativo'),
(1, 'Omeprazol', '20mg', '1x ao dia', '20:00:00', '2025-01-01', 'ativo');

-- Sinais Vitais
INSERT INTO sinais_vitais (paciente_id, pressao_arterial, frequencia_cardiaca, temperatura, glicemia, saturacao_oxigenio, registrado_por) VALUES
(1, '120/80', 72, 36.5, 110, 98, 1);

-- Agendamentos
INSERT INTO agendamentos (paciente_id, tipo_servico, data_agendamento, periodo, horario, urgencia, status) VALUES
(1, 'consulta-medica', '2025-08-15', 'manha', '10:00:00', 'rotina', 'confirmado'),
(1, 'fisioterapia', '2025-08-19', 'tarde', '18:00:00', 'rotina', 'pendente');

-- Orientações
INSERT INTO orientacoes (paciente_id, titulo, descricao, tipo, criado_por, status) VALUES
(1, 'Cuidados com Diabetes', 
 '• Verificar glicemia capilar 4x ao dia (jejum, pré-almoço, pré-jantar, antes de dormir)\n• Aplicar insulina conforme prescrição médica\n• Observar sinais de hipoglicemia: sudorese, tremores, confusão mental\n• Manter dieta adequada conforme orientação nutricional', 
 'cuidado', 1, 'ativo'),
(1, 'Cuidados com os Pés', 
 '• Inspeção diária dos pés em busca de feridas ou alterações\n• Troca de curativo do pé direito diariamente às 15:00\n• Aplicar pomada prescrita (Sulfadiazina de prata)\n• Manter pés secos e usar calçados adequados', 
 'cuidado', 1, 'ativo');

-- Evoluções
INSERT INTO evolucoes (paciente_id, descricao, tipo, registrado_por) VALUES
(1, 'Paciente estável, sinais vitais dentro da normalidade. Glicemia controlada. Boa aceitação alimentar.', 'evolucao', 1),
(1, 'Curativo do pé direito realizado. Ferida em processo de cicatrização, sem sinais de infecção.', 'procedimento', 1);

-- ====================================
-- VIEWS ÚTEIS
-- ====================================

-- View de Pacientes com Responsáveis
CREATE OR REPLACE VIEW v_pacientes_completo AS
SELECT 
    p.*,
    GROUP_CONCAT(DISTINCT CONCAT(uf.nome, ' (', vf.grau_parentesco, ')') SEPARATOR ', ') as familiares,
    GROUP_CONCAT(DISTINCT uc.nome SEPARATOR ', ') as cuidadores
FROM pacientes p
LEFT JOIN vinculos_familiar vf ON p.id = vf.paciente_id
LEFT JOIN usuarios uf ON vf.usuario_id = uf.id
LEFT JOIN vinculos_cuidador vc ON p.id = vc.paciente_id AND vc.status = 'ativo'
LEFT JOIN usuarios uc ON vc.cuidador_id = uc.id
GROUP BY p.id;

-- View de Agenda do Dia
CREATE OR REPLACE VIEW v_agenda_hoje AS
SELECT 
    a.*,
    p.nome as paciente_nome,
    p.telefone as paciente_telefone
FROM agendamentos a
INNER JOIN pacientes p ON a.paciente_id = p.id
WHERE DATE(a.data_agendamento) = CURDATE()
AND a.status != 'cancelado'
ORDER BY a.horario;

-- View de Medicações Pendentes
CREATE OR REPLACE VIEW v_medicacoes_pendentes AS
SELECT 
    m.*,
    p.nome as paciente_nome,
    CASE 
        WHEN NOT EXISTS (
            SELECT 1 FROM registro_medicamentos rm 
            WHERE rm.medicacao_id = m.id 
            AND DATE(rm.data_administracao) = CURDATE()
        ) THEN 'pendente'
        ELSE 'administrado'
    END as status_hoje
FROM medicacoes m
INNER JOIN pacientes p ON m.paciente_id = p.id
WHERE m.status = 'ativo';

-- ====================================
-- PROCEDURES ÚTEIS
-- ====================================

DELIMITER //

-- Procedure para registrar sinais vitais
CREATE PROCEDURE sp_registrar_sinais_vitais(
    IN p_paciente_id INT,
    IN p_pressao VARCHAR(20),
    IN p_freq_cardiaca INT,
    IN p_temperatura DECIMAL(4,1),
    IN p_glicemia INT,
    IN p_saturacao INT,
    IN p_registrado_por INT
)
BEGIN
    INSERT INTO sinais_vitais (
        paciente_id, pressao_arterial, frequencia_cardiaca, 
        temperatura, glicemia, saturacao_oxigenio, registrado_por
    ) VALUES (
        p_paciente_id, p_pressao, p_freq_cardiaca, 
        p_temperatura, p_glicemia, p_saturacao, p_registrado_por
    );
    
    -- Criar notificação se houver valores críticos
    IF p_glicemia > 250 OR p_glicemia < 70 OR p_temperatura > 37.8 THEN
        INSERT INTO notificacoes (usuario_id, titulo, mensagem, tipo)
        SELECT 
            vf.usuario_id,
            'Alerta de Saúde',
            CONCAT('Sinais vitais fora do padrão registrados para ', p.nome),
            'alerta'
        FROM vinculos_familiar vf
        INNER JOIN pacientes p ON vf.paciente_id = p.id
        WHERE vf.paciente_id = p_paciente_id;
    END IF;
END //

-- Procedure para registrar medicação
CREATE PROCEDURE sp_registrar_medicacao(
    IN p_medicacao_id INT,
    IN p_administrado_por INT,
    IN p_observacoes TEXT
)
BEGIN
    INSERT INTO registro_medicamentos (
        medicacao_id, administrado_por, observacoes
    ) VALUES (
        p_medicacao_id, p_administrado_por, p_observacoes
    );
END //

DELIMITER ;

-- ====================================
-- TRIGGERS
-- ====================================

DELIMITER //

-- Trigger para criar notificação ao criar novo agendamento
CREATE TRIGGER tr_notificar_novo_agendamento
AFTER INSERT ON agendamentos
FOR EACH ROW
BEGIN
    INSERT INTO notificacoes (usuario_id, titulo, mensagem, tipo)
    SELECT 
        vf.usuario_id,
        'Novo Agendamento',
        CONCAT('Novo agendamento de ', NEW.tipo_servico, ' criado para ', 
               DATE_FORMAT(NEW.data_agendamento, '%d/%m/%Y')),
        'info'
    FROM vinculos_familiar vf
    WHERE vf.paciente_id = NEW.paciente_id;
END //

DELIMITER ;

-- ====================================
-- ÍNDICES ADICIONAIS PARA PERFORMANCE
-- ====================================

CREATE INDEX idx_sinais_vitais_recentes ON sinais_vitais(paciente_id, data_medicao DESC);
CREATE INDEX idx_evolucoes_recentes ON evolucoes(paciente_id, data_registro DESC);
CREATE INDEX idx_notificacoes_nao_lidas ON notificacoes(usuario_id, lida, data_criacao DESC);

-- ====================================
-- FIM DO SCRIPT
-- ====================================
