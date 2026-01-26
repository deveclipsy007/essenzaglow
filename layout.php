<?php
require_once 'auth.php';

function renderHeader($title = "Essenza Glow") {
    // Verificar autenticação em todas as páginas protegidas
    requireAuth();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $title; ?></title>
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
        body { font-family: 'Inter', sans-serif; background-color: #F8F4EA; color: #433C30; }
        .mobile-menu-open { overflow: hidden; }
        .sidebar-overlay { opacity: 0; visibility: hidden; transition: all 0.3s ease; }
        .sidebar-overlay.active { opacity: 1; visibility: visible; }
        .sidebar-mobile { transform: translateX(-100%); transition: transform 0.3s ease; }
        .sidebar-mobile.active { transform: translateX(0); }
    </style>
</head>
<body class="flex min-h-screen">
<?php
}

function renderSidebar($active = '') {
    $menu = [
        ['url' => 'dashboard.php', 'icon' => 'layout-dashboard', 'label' => 'Dashboard'],
        ['url' => 'agenda.php', 'icon' => 'calendar-days', 'label' => 'Agenda'],
        ['url' => 'servicos.php', 'icon' => 'sparkles', 'label' => 'Serviços'],
        ['url' => 'clients.php', 'icon' => 'users', 'label' => 'Clientes'],
        ['url' => 'financeiro.php', 'icon' => 'credit-card', 'label' => 'Financeiro'],
        ['url' => 'estoque.php', 'icon' => 'package', 'label' => 'Estoque'],
        ['url' => 'admin_landing.php', 'icon' => 'monitor', 'label' => 'Site'],
        ['url' => 'config.php', 'icon' => 'settings', 'label' => 'Configurações'],
    ];
?>
    <!-- Overlay para mobile -->
    <div id="sidebarOverlay" onclick="closeMobileMenu()" class="sidebar-overlay fixed inset-0 bg-black/50 z-30 md:hidden"></div>
    
    <!-- Header Mobile -->
    <header class="md:hidden fixed top-0 left-0 right-0 bg-white/95 backdrop-blur-xl border-b border-sand z-20 px-4 py-3 flex justify-between items-center">
        <h1 class="font-serif text-xl text-charcoal flex items-center gap-2">
            <i data-lucide="sparkles" class="text-gold fill-gold w-5 h-5"></i> Essenza
        </h1>
        <button onclick="toggleMobileMenu()" class="p-2 hover:bg-sand rounded-lg transition-colors">
            <i data-lucide="menu" class="w-6 h-6 text-charcoal"></i>
        </button>
    </header>
    
    <!-- Sidebar -->
    <aside id="sidebar" class="sidebar-mobile md:translate-x-0 w-64 bg-white/95 backdrop-blur-xl border-r border-sand-dark/50 fixed inset-y-0 z-40 flex flex-col">
        <div class="p-6 pb-4 flex justify-between items-center">
            <h1 class="font-serif text-2xl text-charcoal flex items-center gap-2">
                <i data-lucide="sparkles" class="text-gold fill-gold"></i> Essenza
            </h1>
            <button onclick="closeMobileMenu()" class="md:hidden p-2 hover:bg-sand rounded-lg">
                <i data-lucide="x" class="w-5 h-5 text-charcoal"></i>
            </button>
        </div>
        <nav class="flex-1 px-4 space-y-2 mt-4 overflow-y-auto">
            <?php foreach($menu as $item): 
                $isActive = $active == $item['label'];
            ?>
            <a href="<?php echo $item['url']; ?>" class="flex items-center gap-3 px-4 py-3 rounded-lg transition-all <?php echo $isActive ? 'bg-sage text-white shadow-md' : 'text-charcoal-light hover:bg-sand/50'; ?>">
                <i data-lucide="<?php echo $item['icon']; ?>"></i>
                <span class="font-medium"><?php echo $item['label']; ?></span>
            </a>
            <?php endforeach; ?>
        </nav>
        <div class="p-4 border-t border-sand space-y-3">
            <a href="book.php" target="_blank" class="flex items-center gap-2 text-xs text-charcoal hover:text-sage transition-colors">
                <i data-lucide="external-link" class="w-4 h-4"></i> Link Público (Agendamento)
            </a>
            <div class="flex items-center justify-between pt-2 border-t border-sand/50">
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 rounded-full bg-sage/20 flex items-center justify-center">
                        <i data-lucide="user" class="w-4 h-4 text-sage"></i>
                    </div>
                    <span class="text-xs font-medium text-charcoal truncate"><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Admin'); ?></span>
                </div>
                <a href="logout.php" class="p-2 rounded-lg hover:bg-red-50 text-charcoal-light hover:text-red-500 transition-colors" title="Sair">
                    <i data-lucide="log-out" class="w-4 h-4"></i>
                </a>
            </div>
        </div>
    </aside>
    
    <!-- Main Content -->
    <main class="flex-1 md:ml-64 p-4 md:p-8 pt-20 md:pt-8 overflow-y-auto max-w-7xl mx-auto w-full">
<?php
}

function renderFooter() {
?>
    </main>
    <script>
        lucide.createIcons();
        function openModal(id) { document.getElementById(id).showModal(); }
        function closeModal(id) { document.getElementById(id).close(); }
        
        function toggleMobileMenu() {
            document.getElementById('sidebar').classList.toggle('active');
            document.getElementById('sidebarOverlay').classList.toggle('active');
            document.body.classList.toggle('mobile-menu-open');
        }
        
        function closeMobileMenu() {
            document.getElementById('sidebar').classList.remove('active');
            document.getElementById('sidebarOverlay').classList.remove('active');
            document.body.classList.remove('mobile-menu-open');
        }
    </script>
</body>
</html>
<?php
}
?>