<?php
require_once 'db.php';
require_once 'auth.php';

// Se já está logado, redireciona para dashboard
if (isAuthenticated()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';

// Processar login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (login($pdo, $username, $password)) {
        header('Location: dashboard.php');
        exit;
    } else {
        $error = 'Usuário ou senha incorretos';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Essenza Glow</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&family=Playfair+Display:ital,wght@0,400;0,600;1,400&display=swap" rel="stylesheet">
    <script>
      tailwind.config = {
        theme: {
          extend: {
            colors: {
              ivory: { DEFAULT: '#F8F4EA', dark: '#F5EFE3' },
              sand: { DEFAULT: '#EEE9DC', dark: '#E3DECF' },
              sage: { DEFAULT: '#5B7355', dark: '#4A5E44', light: '#E8F0E6' },
              charcoal: { DEFAULT: '#433C30', light: '#5C5446' },
              gold: { DEFAULT: '#DAC38F', dark: '#B49C73' },
            },
            fontFamily: { serif: ['"Playfair Display"', 'serif'], sans: ['"Inter"', 'sans-serif'] }
          }
        }
      }
    </script>
    <style>
        body { font-family: 'Inter', sans-serif; }
        .animate-fade-in { animation: fadeIn 0.5s ease-out; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
    </style>
</head>
<body class="min-h-screen bg-gradient-to-br from-ivory via-sand to-ivory flex items-center justify-center p-4">
    <div class="w-full max-w-md animate-fade-in">
        <!-- Logo -->
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-16 h-16 bg-white rounded-2xl shadow-lg mb-4">
                <i data-lucide="sparkles" class="w-8 h-8 text-gold"></i>
            </div>
            <h1 class="font-serif text-3xl text-charcoal">Essenza Glow</h1>
            <p class="text-charcoal-light text-sm mt-1">Sistema de Gestão</p>
        </div>
        
        <!-- Card de Login -->
        <div class="bg-white rounded-2xl shadow-xl border border-sand overflow-hidden">
            <div class="p-8">
                <h2 class="font-serif text-2xl text-charcoal text-center mb-6">Entrar</h2>
                
                <?php if ($error): ?>
                <div class="mb-4 p-4 bg-red-50 border border-red-200 rounded-xl text-red-700 text-sm flex items-center gap-2">
                    <i data-lucide="alert-circle" class="w-4 h-4"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
                <?php endif; ?>
                
                <form method="POST" class="space-y-5">
                    <div>
                        <label class="block text-xs uppercase tracking-wider text-charcoal-light mb-2 font-semibold">Usuário</label>
                        <div class="relative">
                            <i data-lucide="user" class="absolute left-4 top-3.5 w-5 h-5 text-charcoal-light/50"></i>
                            <input type="text" name="username" required autofocus
                                   class="w-full pl-12 pr-4 py-3 bg-ivory border border-sand rounded-xl text-charcoal placeholder-charcoal-light/50 focus:ring-2 focus:ring-sage focus:border-sage transition-all"
                                   placeholder="Digite seu usuário">
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-xs uppercase tracking-wider text-charcoal-light mb-2 font-semibold">Senha</label>
                        <div class="relative">
                            <i data-lucide="lock" class="absolute left-4 top-3.5 w-5 h-5 text-charcoal-light/50"></i>
                            <input type="password" name="password" required
                                   class="w-full pl-12 pr-4 py-3 bg-ivory border border-sand rounded-xl text-charcoal placeholder-charcoal-light/50 focus:ring-2 focus:ring-sage focus:border-sage transition-all"
                                   placeholder="Digite sua senha">
                        </div>
                    </div>
                    
                    <button type="submit" class="w-full py-3.5 bg-charcoal text-white rounded-xl hover:bg-charcoal/90 transition-all font-medium flex items-center justify-center gap-2 shadow-lg hover:shadow-xl">
                        <i data-lucide="log-in" class="w-4 h-4"></i> Entrar
                    </button>
                </form>
            </div>
            
            <div class="px-8 py-4 bg-ivory/50 border-t border-sand text-center">
                <a href="index.php" class="text-sm text-charcoal-light hover:text-gold transition-colors inline-flex items-center gap-2">
                    <i data-lucide="arrow-left" class="w-4 h-4"></i> Voltar ao site
                </a>
            </div>
        
        <!-- Link público -->
        <div class="text-center mt-6">
            <a href="book.php" class="text-sm text-charcoal-light hover:text-sage transition-colors inline-flex items-center gap-1">
                <i data-lucide="external-link" class="w-4 h-4"></i> Acessar agendamento público
            </a>
        </div>
    </div>
    
    <script>lucide.createIcons();</script>
</body>
</html>
