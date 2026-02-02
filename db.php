<?php
// db.php
date_default_timezone_set('America/Sao_Paulo');

// --- CONFIGURAÇÃO DE AMBIENTE ---
$httpHost = $_SERVER['HTTP_HOST'] ?? '';
$hostname = strtolower(explode(':', $httpHost)[0]);
$localhosts = ['localhost', '127.0.0.1', '0.0.0.0'];
$is_local = php_sapi_name() === 'cli-server' || in_array($hostname, $localhosts, true);

try {
    if ($is_local) {
        // Local: SQLite
        $pdo = new PDO('sqlite:essenza.db');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pk_type = "INTEGER PRIMARY KEY AUTOINCREMENT";
    } else {
        // Produção (Hostinger): MySQL
        $host = 'localhost'; // Geralmente localhost na Hostinger
        $dbname = 'u854567422_essezaglow';
        $user = 'u854567422_glow';
        $pass = 'Escher007.';
        
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pk_type = "INT AUTO_INCREMENT PRIMARY KEY";
        
        // Garantir que o MySQL use o modo silencioso para erros de alteração se necessário, 
        // ou ignorar erros específicos de duplicidade de coluna se rodar o schema todo.
    }

    // --- CRIAÇÃO DAS TABELAS (Schema) ---
    // Nota: Usamos variáveis para os tipos que mudam entre SQLite e MySQL
    
    // Tabela de Clientes
    $pdo->exec("CREATE TABLE IF NOT EXISTS clients (
        id $pk_type,
        name VARCHAR(255) NOT NULL,
        phone VARCHAR(50),
        notes TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // Tabela de Serviços
    $pdo->exec("CREATE TABLE IF NOT EXISTS services (
        id $pk_type,
        name VARCHAR(255) NOT NULL,
        category VARCHAR(50), -- BODY, FACE
        duration_minutes INTEGER,
        price DECIMAL(10,2),
        description TEXT,
        is_featured INTEGER DEFAULT 0,
        discount_price DECIMAL(10,2) DEFAULT NULL
    )");

    // Tabela de Combos (Pacotes Promocionais)
    $pdo->exec("CREATE TABLE IF NOT EXISTS combos (
        id $pk_type,
        name VARCHAR(255) NOT NULL,
        category VARCHAR(50),
        duration_minutes INTEGER,
        original_price DECIMAL(10,2),
        promotional_price DECIMAL(10,2),
        description TEXT,
        is_featured INTEGER DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // Tabela de Agendamentos
    $pdo->exec("CREATE TABLE IF NOT EXISTS appointments (
        id $pk_type,
        client_id INTEGER,
        service_id INTEGER,
        combo_id INTEGER,
        start_at DATETIME,
        end_at DATETIME,
        status VARCHAR(50), 
        price DECIMAL(10,2),
        payment_status VARCHAR(50), 
        title VARCHAR(255),
        type VARCHAR(50) DEFAULT 'APPOINTMENT'
    )");

    // Tabela de Imagens (Galeria Antes/Depois)
    $pdo->exec("CREATE TABLE IF NOT EXISTS client_images (
        id $pk_type,
        client_id INTEGER,
        image_data LONGTEXT, -- Base64
        tag VARCHAR(50), 
        notes TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // Tabela de Estoque (Inventory)
    $pdo->exec("CREATE TABLE IF NOT EXISTS inventory (
        id $pk_type,
        name VARCHAR(255) NOT NULL,
        category VARCHAR(50),
        quantity INTEGER DEFAULT 0,
        min_quantity INTEGER DEFAULT 5,
        price DECIMAL(10,2),
        unit VARCHAR(20) DEFAULT 'un'
    )");

    // Tabela de Relacionamento Combo-Serviços (Many-to-Many)
    $pdo->exec("CREATE TABLE IF NOT EXISTS combo_services (
        id $pk_type,
        combo_id INTEGER,
        service_id INTEGER
    )");

    // Tabela de Horários Disponíveis
    $pdo->exec("CREATE TABLE IF NOT EXISTS available_slots (
        id $pk_type,
        date DATE NOT NULL,
        time_start VARCHAR(10) NOT NULL,
        time_end VARCHAR(10),
        is_booked INTEGER DEFAULT 0,
        appointment_id INTEGER,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // Tabela de Configurações
    $pdo->exec("CREATE TABLE IF NOT EXISTS settings (
        id $pk_type,
        setting_key VARCHAR(100) UNIQUE NOT NULL,
        setting_value TEXT,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // Tabela de Usuários
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id $pk_type,
        username VARCHAR(100) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        name VARCHAR(255) NOT NULL,
        role VARCHAR(50) DEFAULT 'admin',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // Tabela de Despesas
    $pdo->exec("CREATE TABLE IF NOT EXISTS expenses (
        id $pk_type,
        description VARCHAR(255) NOT NULL,
        category VARCHAR(100),
        amount DECIMAL(10,2) NOT NULL,
        expense_date DATE NOT NULL,
        notes TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // Tabela de Seções da Landing Page
    $pdo->exec("CREATE TABLE IF NOT EXISTS landing_sections (
        id $pk_type,
        section_key VARCHAR(100) UNIQUE NOT NULL,
        title VARCHAR(255),
        subtitle VARCHAR(255),
        content TEXT,
        image_data LONGTEXT,
        is_active INTEGER DEFAULT 1,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // Tabela de Itens da Landing Page (Testemunhos, FAQs, Destaques)
    $pdo->exec("CREATE TABLE IF NOT EXISTS landing_items (
        id $pk_type,
        section_key VARCHAR(100) NOT NULL,
        title VARCHAR(255),
        content TEXT,
        image_data LONGTEXT,
        display_order INTEGER DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // --- SEEDS E ATUALIZAÇÕES ---
    
    // Seed de Usuários
    $stmt = $pdo->query("SELECT COUNT(*) FROM users");
    if ($stmt->fetchColumn() == 0) {
        $hashedPassword = password_hash('yohanngostoso', PASSWORD_DEFAULT);
        $pdo->prepare("INSERT INTO users (username, password, name, role) VALUES (?, ?, ?, ?)")
            ->execute(['bialindona', $hashedPassword, 'Administrador', 'admin']);
    } else {
        // Garantir que se o usuário 'admin' existir localmente, ele seja atualizado
        if ($is_local) {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = 'admin'");
            $stmt->execute();
            if ($user = $stmt->fetch()) {
                $hashedPassword = password_hash('yohanngostoso', PASSWORD_DEFAULT);
                $pdo->prepare("UPDATE users SET username = 'bialindona', password = ? WHERE id = ?")
                    ->execute([$hashedPassword, $user['id']]);
            }
        }
    }

    // Seed de Configurações
    $stmt = $pdo->query("SELECT COUNT(*) FROM settings");
    if ($stmt->fetchColumn() == 0) {
        $pdo->exec("INSERT INTO settings (setting_key, setting_value) VALUES 
            ('whatsapp_number', '5511999999999'),
            ('business_name', 'Essenza Glow'),
            ('business_address', ''),
            ('appointment_reminder_template', 'Olá {nome}! 👋\\n\\nConfirmando seu agendamento na Essenza Glow:\\n\\n📅 Data: {data}\\n⏰ Horário: {horario}\\n💆 Serviço: {servico}\\n💰 Valor: R$ {preco}\\n\\nAguardamos você! ✨')
        ");
    }

    // Seed Landing Page
    $stmt = $pdo->query("SELECT COUNT(*) FROM landing_sections");
    if ($stmt->fetchColumn() == 0) {
        $pdo->exec("INSERT INTO landing_sections (section_key, title, subtitle, content) VALUES 
            ('hero', 'Sua Jornada de Beleza Começa Aqui', 'Tratamentos exclusivos para realçar sua beleza natural.', NULL),
            ('about', 'Quem Sou Eu', 'Especialista em estética avançada com anos de experiência.', 'Sou apaixonada por transformar vidas através da estética...'),
            ('how_it_works', 'Como Funciona', 'Do agendamento ao resultado final.', NULL),
            ('logo', 'Essenza Glow', '', NULL)
        ");
    }

} catch (PDOException $e) {
    die("Erro no banco de dados: " . $e->getMessage());
}
?>