<?php
require_once 'config.php';
require_once 'auth.php';

// Se já estiver logado, redirecionar para a página apropriada
if (isLogged()) {
    if (isFamiliar()) {
        header('Location: perfil-familiar.php');
    } elseif (isCuidador()) {
        header('Location: perfil-cuidador.php');
    } else {
        header('Location: index.php');
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clínica Médica - Bem-vindo(a)</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: #E8D5D0;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        header {
            background: #D4A89C;
            backdrop-filter: blur(10px);
            padding: 2.5rem 0;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            animation: slideDown 0.6s ease-out;
        }

        @keyframes slideDown {
            from {
                transform: translateY(-100%);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
            text-align: center;
        }

        header h1 {
            font-size: 2.5rem;
            color: #3B3A1C;
            margin-bottom: 0.5rem;
            font-weight: 600;
            text-shadow: 2px 2px 4px rgba(59, 58, 28, 0.3);
        }

        header p {
            color: #6B4E3D;
            font-size: 1.1rem;
        }

        .main-content {
            background: #FDFCFB;
            border-radius: 30px;
            padding: 3rem;
            margin: 3rem auto;
            max-width: 800px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
            animation: fadeInUp 0.8s ease-out 0.2s both;
            border: 3px solid #A9C166;
        }

        @keyframes fadeInUp {
            from {
                transform: translateY(30px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .main-content img {
            width: 300px;
            height: 300px;
            object-fit: cover;
            border-radius: 50%;
            margin-bottom: 2rem;
            box-shadow: 0 10px 30px rgba(169, 193, 102, 0.3);
            transition: transform 0.3s ease;
        }

        .main-content img:hover {
            transform: scale(1.05);
        }

        .main-content h2 {
            font-size: 2rem;
            color: #6B4E3D;
            margin-bottom: 1rem;
            font-weight: 600;
        }

        .main-content p {
            color: #6B4E3D;
            font-size: 1.1rem;
            margin-bottom: 2rem;
            line-height: 1.6;
        }

        .btn-cadastro {
            display: inline-block;
            background: #A9C166;
            color: white;
            padding: 1.2rem 3rem;
            text-decoration: none;
            border-radius: 50px;
            font-weight: 600;
            font-size: 1.1rem;
            box-shadow: 0 10px 30px rgba(169, 193, 102, 0.4);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            margin: 10px;
        }

        .btn-cadastro::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: #8AA654;
            transition: left 0.3s ease;
            z-index: -1;
        }

        .btn-cadastro:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 40px rgba(169, 193, 102, 0.5);
        }

        .btn-cadastro:hover::before {
            left: 0;
        }

        footer {
            background: #D4A89C;
            backdrop-filter: blur(10px);
            padding: 2rem 0;
            margin-top: auto;
            box-shadow: 0 -4px 20px rgba(0, 0, 0, 0.1);
        }

        footer p {
            color: #6B4E3D;
            margin: 0.3rem 0;
            font-size: 0.95rem;
        }

        @media (max-width: 768px) {
            header h1 {
                font-size: 1.8rem;
            }

            .main-content {
                padding: 2rem;
                margin: 2rem 1rem;
            }

            .main-content h2 {
                font-size: 1.5rem;
            }

            .btn-cadastro {
                padding: 1rem 2rem;
                font-size: 1rem;
            }
        }
    </style>
</head>
<body>
    <header>
        <div class="container">
            <h1>🏥 Clínica Cuidar Bem</h1>
            <p>Cuidando sempre com excelência</p>
        </div>
    </header>
    
    <div class="container">
        <div class="main-content">
            <div style="width: 300px; height: 300px; margin: 0 auto 2rem; background: linear-gradient(135deg, #A9C166, #D4A89C); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 120px; box-shadow: 0 10px 30px rgba(169, 193, 102, 0.3);">
                🏥
            </div>
            
            <h2>Agende sua consulta agora mesmo</h2>
            <p>Nossa clínica oferece os melhores especialistas para cuidar da sua saúde</p>
            
            <a href="cadastro.php" class="btn-cadastro">Cadastro e Agendamento</a>
            <a href="login.php" class="btn-cadastro" style="background: linear-gradient(45deg, #D4A89C, #E8D5D0);">Área do Cliente</a>
        </div>
    </div>
    
    <footer>
        <div class="container">
            <p>© <?= date('Y') ?> Clínica Cuidar Bem - Todos os direitos reservados</p>
            <p>Telefone: (17) 9914-08891  |  Email: clinica@cuidarbem.com.br</p>
        </div>
    </footer>
</body>
</html>
