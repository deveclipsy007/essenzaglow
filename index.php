<?php
require_once 'db.php';

// Fetch Landing Page Content
$sections = [];
$stmt = $pdo->query("SELECT * FROM landing_sections ORDER BY id ASC");
while ($row = $stmt->fetch()) {
    $sections[$row['section_key']] = $row;
}
// Defaults
$defaults = [
    'hero' => ['title' => 'Sua Jornada de Beleza Começa Aqui', 'subtitle' => 'Tratamentos exclusivos para realçar sua beleza natural', 'content' => '', 'image_data' => ''],
    'about' => ['title' => 'Quem Sou Eu', 'subtitle' => '', 'content' => 'Texto sobre a profissional...'],
    'how_it_works' => ['title' => 'Como Funciona', 'subtitle' => 'Do agendamento ao resultado', 'content' => ''],
    'footer' => ['title' => 'Rodapé', 'subtitle' => '', 'content' => '{}'],
    'logo' => ['title' => 'Essenza Glow', 'subtitle' => '', 'content' => '', 'image_data' => '']
];
foreach($defaults as $k => $v) {
    if(!isset($sections[$k])) $sections[$k] = $v;
}

// Decode Footer Data
$footer = json_decode($sections['footer']['content'] ?? '{}', true);
$footerDefaults = [
    'description' => 'Redefinindo os padrões de beleza através da estética avançada e do acolhimento humano.',
    'instagram' => '#', 'facebook' => '#', 'whatsapp' => '(11) 99999-9999',
    'email' => 'contato@essenzaglow.com.br', 'address' => 'São Paulo, SP',
    'hours_mon_fri' => '09:00 - 19:00', 'hours_sat' => '09:00 - 14:00', 'hours_sun' => 'Fechado'
];
foreach($footerDefaults as $k => $v) if(empty($footer[$k])) $footer[$k] = $v;

// Fetch Testimonials
$testimonials = $pdo->query("SELECT * FROM landing_items WHERE section_key = 'testimonials' ORDER BY created_at DESC")->fetchAll();

// Fetch Featured Services
$services = $pdo->query("SELECT * FROM services WHERE is_featured = 1 ORDER BY id DESC")->fetchAll();

// Fetch Featured Combos
$combos = $pdo->query("SELECT * FROM combos WHERE is_featured = 1 ORDER BY created_at DESC")->fetchAll();
foreach ($combos as &$combo) {
    $stmt = $pdo->prepare("
        SELECT s.* FROM services s
        INNER JOIN combo_services cs ON s.id = cs.service_id
        WHERE cs.combo_id = ?
    ");
    $stmt->execute([$combo['id']]);
    $combo['included_services'] = $stmt->fetchAll();
}

?>
<!DOCTYPE html>
<html lang="pt-BR" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Essenza Glow - Estética Avançada</title>
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
            fontFamily: { serif: ['"Playfair Display"', 'serif'], sans: ['"Inter"', 'sans-serif'] },
            animation: {
                'fade-in': 'fadeIn 1s ease-out',
                'slide-up': 'slideUp 0.8s ease-out',
            },
            keyframes: {
                fadeIn: { '0%': { opacity: '0' }, '100%': { opacity: '1' } },
                slideUp: { '0%': { transform: 'translateY(20px)', opacity: '0' }, '100%': { transform: 'translateY(0)', opacity: '1' } }
            }
          }
        }
      }
    </script>
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #F8F4EA; color: #433C30; }
        .hero-pattern { background-image: radial-gradient(#DAC38F 1px, transparent 1px); background-size: 20px 20px; opacity: 0.1; }
        .glass { background: rgba(255, 255, 255, 0.7); backdrop-filter: blur(10px); border: 1px solid rgba(255, 255, 255, 0.2); }
        
        /* Dynamic Primary Color */
        :root { --primary-color: <?php echo $sections['logo']['content'] ?: '#433C30'; ?>; }
        .btn-primary { background-color: var(--primary-color) !important; color: white !important; }
        .btn-primary:hover { opacity: 0.9; transform: translateY(-1px); }
        .text-primary { color: var(--primary-color) !important; }
        .border-primary { border-color: var(--primary-color) !important; }
    </style>
</head>
<body class="antialiased">

    <!-- Navigation -->
    <nav class="fixed w-full z-50 bg-white/80 backdrop-blur-md border-b border-sand transition-all duration-300" id="navbar">
        <div class="max-w-7xl mx-auto px-6 py-4 flex justify-between items-center">
            <a href="index.php" class="font-serif text-2xl text-charcoal flex items-center gap-2 group">
                <?php if(!empty($sections['logo']['image_data'])): ?>
                    <img src="<?php echo $sections['logo']['image_data']; ?>" 
                         style="height: <?php echo $sections['logo']['subtitle']; ?>px" 
                         class="w-auto object-contain" alt="Logo">
                <?php else: ?>
                    <i data-lucide="sparkles" class="w-6 h-6 text-gold group-hover:rotate-12 transition-transform"></i>
                    <?php echo htmlspecialchars($sections['logo']['title']); ?>
                <?php endif; ?>
            </a>
            
            <div class="hidden md:flex items-center gap-8 text-sm font-medium tracking-wide text-charcoal-light">
                <a href="#inicio" class="hover:text-gold transition-colors">Início</a>
                <?php if(!empty($combos)): ?><a href="#combos" class="hover:text-gold transition-colors">Combos</a><?php endif; ?>
                <a href="#servicos" class="hover:text-gold transition-colors">Serviços</a>
                <a href="#sobre" class="hover:text-gold transition-colors">Sobre</a>
                <a href="#depoimentos" class="hover:text-gold transition-colors">Depoimentos</a>
            </div>

            <div class="flex items-center gap-4">
                <a href="login.php" class="text-sm font-medium hover:text-gold hidden md:block">Login</a>
                <a href="book.php" class="btn-primary px-6 py-2.5 rounded-full text-sm font-medium hover:shadow-lg transition-all transform hover:-translate-y-0.5">
                    Agendar Horário
                </a>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <header id="inicio" class="relative pt-32 pb-20 md:pt-48 md:pb-32 overflow-hidden">
        <div class="absolute inset-0 hero-pattern pointer-events-none"></div>
        <div class="absolute top-0 right-0 -mt-20 -mr-20 w-96 h-96 bg-gold/10 rounded-full blur-3xl"></div>
        <div class="absolute bottom-0 left-0 -mb-20 -ml-20 w-80 h-80 bg-sage/10 rounded-full blur-3xl"></div>

        <div class="max-w-7xl mx-auto px-6 relative z-10">
            <div class="grid md:grid-cols-2 gap-12 items-center">
                <div class="animate-slide-up">
                    <span class="inline-block py-1 px-3 rounded-full bg-sand/50 text-charcoal-light text-xs font-semibold tracking-wider uppercase mb-6 border border-sand">
                        Estética Avançada & Bem-estar
                    </span>
                    <h1 class="font-serif text-5xl md:text-7xl text-charcoal leading-tight mb-6">
                        <?php echo nl2br(htmlspecialchars($sections['hero']['title'])); ?>
                    </h1>
                    <p class="text-lg md:text-xl text-charcoal-light mb-10 leading-relaxed max-w-2xl">
                        <?php echo nl2br(htmlspecialchars($sections['hero']['subtitle'])); ?>
                    </p>
                    <div class="flex flex-col sm:flex-row gap-4">
                        <a href="book.php" class="btn-primary px-8 py-4 rounded-full text-center hover:bg-charcoal-light transition-all shadow-xl hover:shadow-2xl text-lg font-medium">
                            Agendar Consulta
                        </a>
                        <a href="#sobre" class="bg-white border border-sand text-charcoal px-8 py-4 rounded-full text-center hover:bg-ivory transition-all text-lg font-medium">
                            Conhecer mais
                        </a>
                    </div>
                </div>

                <?php if(!empty($sections['hero']['image_data'])): ?>
                <div class="relative animate-fade-in mt-12 md:mt-0">
                    <div class="aspect-square rounded-[3rem] bg-sand overflow-hidden shadow-2xl rotate-2 hover:rotate-0 transition-transform duration-700">
                        <img src="<?php echo $sections['hero']['image_data']; ?>" class="w-full h-full object-cover">
                    </div>
                    <div class="absolute -bottom-6 -right-6 w-32 h-32 bg-gold/20 rounded-full blur-2xl"></div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <!-- PROMOTIONAL COMBOS (Featured) -->
    <?php if(!empty($combos)): ?>
    <section id="combos" class="py-24 bg-gradient-to-b from-white to-ivory relative">
        <div class="max-w-7xl mx-auto px-6">
            <div class="flex flex-col md:flex-row md:items-end justify-between mb-16 gap-4">
                <div class="max-w-2xl">
                    <span class="text-gold font-bold uppercase tracking-widest text-xs mb-2 block">Ofertas Exclusivas</span>
                    <h2 class="font-serif text-4xl md:text-5xl text-charcoal">Pacotes Imperdíveis</h2>
                </div>
                <p class="text-charcoal-light max-w-sm">Combinações exclusivas pensadas para o seu máximo bem-estar com valores especiais.</p>
            </div>

            <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-8">
                <?php foreach($combos as $combo): 
                    $savings = $combo['original_price'] - $combo['promotional_price'];
                ?>
                <div class="group relative bg-[#F5F2ED] rounded-[32px] p-8 border border-sand hover:border-gold/50 transition-all duration-500 hover:shadow-2xl overflow-hidden">
                    <!-- Background Design -->
                    <div class="absolute -top-12 -right-12 w-40 h-40 bg-gold/20 rounded-full blur-2xl group-hover:bg-gold/30 transition-colors"></div>
                    
                    <div class="relative z-10 flex flex-col h-full">
                        <div class="mb-6 flex justify-between items-start">
                            <span class="bg-sage text-white text-[10px] font-bold uppercase tracking-wider py-1.5 px-4 rounded-full shadow-lg">Combo Especial</span>
                            <div class="text-right">
                                <p class="text-xs text-charcoal-light line-through">R$ <?php echo number_format($combo['original_price'], 2, ',', '.'); ?></p>
                                <p class="text-2xl font-serif text-sage font-bold">R$ <?php echo number_format($combo['promotional_price'], 2, ',', '.'); ?></p>
                            </div>
                        </div>

                        <h3 class="font-serif text-3xl text-charcoal mb-4 group-hover:text-gold transition-colors"><?php echo htmlspecialchars($combo['name']); ?></h3>
                        
                        <div class="space-y-3 mb-8 flex-1">
                            <?php foreach ($combo['included_services'] as $svc): ?>
                            <div class="flex items-center gap-3 text-sm text-charcoal-light">
                                <div class="w-1.5 h-1.5 rounded-full bg-gold"></div>
                                <span><?php echo htmlspecialchars($svc['name']); ?></span>
                            </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="pt-6 border-t border-sand/50 mt-auto">
                            <div class="flex items-center justify-between mb-6">
                                <span class="text-xs font-medium text-charcoal-light flex items-center gap-1">
                                    <i data-lucide="clock" class="w-3.5 h-3.5"></i> <?php echo $combo['duration_minutes']; ?> min
                                </span>
                                <span class="text-xs font-bold text-sage">Economia de R$ <?php echo number_format($savings, 2, ',', '.'); ?></span>
                            </div>
                            <a href="book.php" class="block w-full text-center py-4 btn-primary text-white rounded-2xl font-medium hover:bg-gold transition-all shadow-lg group-hover:shadow-gold/20">
                                Reservar Este Combo
                            </a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- Services Highlight -->
    <section id="servicos" class="py-24 bg-white">
        <div class="max-w-7xl mx-auto px-6">
            <div class="text-center mb-16 max-w-2xl mx-auto">
                <span class="text-gold font-bold uppercase tracking-widest text-xs mb-2 block">Bem-estar Diário</span>
                <h2 class="font-serif text-4xl text-charcoal mb-4">Nossos Tratamentos</h2>
                <div class="w-16 h-1 bg-gold mx-auto rounded-full mb-4"></div>
                <p class="text-charcoal-light">Experiências únicas desenhadas para realçar sua beleza e promover relaxamento profundo.</p>
            </div>

            <div class="grid md:grid-cols-3 gap-8">
                <?php foreach($services as $svc): ?>
                <div class="group p-8 rounded-3xl bg-ivory border border-sand hover:border-gold/50 transition-all hover:shadow-xl hover:-translate-y-1">
                    <div class="w-14 h-14 rounded-2xl bg-white flex items-center justify-center mb-6 text-gold group-hover:bg-gold group-hover:text-white transition-colors shadow-sm">
                        <i data-lucide="sparkles" class="w-7 h-7"></i>
                    </div>
                    <h3 class="font-serif text-2xl text-charcoal mb-3"><?php echo htmlspecialchars($svc['name']); ?></h3>
                    <p class="text-charcoal-light mb-6 line-clamp-3 text-sm leading-relaxed">
                        <?php echo htmlspecialchars($svc['description'] ?: 'Tratamento especializado para revigorar e cuidar de você.'); ?>
                    </p>
                    <div class="flex items-center justify-between mt-auto pt-6 border-t border-sand/50">
                        <div>
                            <?php if ($svc['discount_price']): ?>
                                <p class="text-[10px] text-charcoal-light line-through">R$ <?php echo number_format($svc['price'], 2, ',', '.'); ?></p>
                                <p class="text-sage font-bold">R$ <?php echo number_format($svc['discount_price'], 2, ',', '.'); ?></p>
                            <?php else: ?>
                                <span class="text-sage font-medium">R$ <?php echo number_format($svc['price'], 2, ',', '.'); ?></span>
                            <?php endif; ?>
                        </div>
                        <a href="book.php" class="text-charcoal text-sm font-medium hover:text-gold flex items-center gap-1 group-hover:gap-2 transition-all">
                            Agendar <i data-lucide="arrow-right" class="w-4 h-4"></i>
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <div class="text-center mt-12">
                <a href="book.php" class="inline-flex items-center gap-2 text-charcoal hover:text-gold transition-colors border-b border-charcoal hover:border-gold pb-0.5">
                    Ver menu completo de serviços
                </a>
            </div>
        </div>
    </section>

    <!-- About Section -->
    <section id="sobre" class="py-24 relative overflow-hidden bg-ivory/30">
        <div class="max-w-7xl mx-auto px-6">
            <div class="grid md:grid-cols-2 gap-16 items-center">
                <div class="relative order-2 md:order-1">
                    <div class="aspect-[4/5] rounded-[3rem] bg-sand overflow-hidden relative shadow-2xl">
                        <?php if(!empty($sections['about']['image_data'])): ?>
                            <img src="<?php echo $sections['about']['image_data']; ?>" class="w-full h-full object-cover">
                        <?php else: ?>
                            <div class="absolute inset-0 bg-neutral-200 flex items-center justify-center text-neutral-400">
                                <i data-lucide="image" class="w-12 h-12"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                    <!-- Micro-badge -->
                    <div class="absolute -bottom-10 -left-10 bg-white p-8 rounded-[2.5rem] shadow-2xl animate-fade-in hidden lg:block">
                        <p class="font-serif text-2xl text-charcoal mb-1">Especialista</p>
                        <p class="text-xs text-gold uppercase tracking-widest font-bold">Estética Avançada</p>
                    </div>
                </div>
                
                <div class="order-1 md:order-2">
                    <span class="text-gold font-bold uppercase tracking-widest text-xs mb-4 block">A Mente por Trás</span>
                    <h2 class="font-serif text-4xl md:text-5xl text-charcoal mb-8 leading-tight"><?php echo htmlspecialchars($sections['about']['title']); ?></h2>
                    <div class="prose prose-stone text-charcoal-light mb-10 text-lg leading-relaxed">
                        <?php echo nl2br(htmlspecialchars($sections['about']['content'])); ?>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-8 mb-10">
                        <div>
                            <h4 class="font-serif text-4xl text-gold mb-1">500+</h4>
                            <p class="text-xs text-charcoal uppercase tracking-widest font-bold opacity-60">Resultados Reais</p>
                        </div>
                        <div>
                            <h4 class="font-serif text-4xl text-gold mb-1">5★</h4>
                            <p class="text-xs text-charcoal uppercase tracking-widest font-bold opacity-60">Excelência</p>
                        </div>
                    </div>
                    
                    <a href="book.php" class="inline-flex items-center gap-3 btn-primary text-white px-8 py-4 rounded-full hover:bg-gold transition-all shadow-xl">
                        Agendar minha avaliação <i data-lucide="chevron-right" class="w-5 h-5"></i>
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- Dynamic Sections -->
    <?php 
    $standardKeys = ['hero', 'about', 'how_it_works', 'footer', 'logo'];
    foreach ($sections as $key => $sec): 
        if (in_array($key, $standardKeys)) continue;
    ?>
    <section class="py-24 bg-white border-t border-sand/30">
        <div class="max-w-7xl mx-auto px-6">
            <div class="text-center mb-12">
                <h2 class="font-serif text-4xl text-charcoal mb-4"><?php echo htmlspecialchars($sec['title']); ?></h2>
                <div class="w-12 h-1 bg-gold mx-auto rounded-full"></div>
            </div>
            <div class="prose prose-stone max-w-4xl mx-auto text-charcoal-light text-center text-lg leading-relaxed">
                <?php echo nl2br(htmlspecialchars($sec['content'])); ?>
            </div>
        </div>
    </section>
    <?php endforeach; ?>

    <!-- Testimonials -->
    <?php if(!empty($testimonials)): ?>
    <section id="depoimentos" class="py-24 bg-charcoal text-ivory relative overflow-hidden">
        <div class="absolute inset-0 opacity-5 pointer-events-none">
            <svg class="h-full w-full" viewBox="0 0 100 100" preserveAspectRatio="none">
                <defs><pattern id="grid" width="10" height="10" patternUnits="userSpaceOnUse"><path d="M 10 0 L 0 0 0 10" fill="none" stroke="currentColor" stroke-width="0.5"/></pattern></defs>
                <rect width="100" height="100" fill="url(#grid)" />
            </svg>
        </div>
        
        <div class="max-w-7xl mx-auto px-6 relative z-10">
            <div class="text-center mb-20">
                <span class="text-gold font-bold uppercase tracking-widest text-xs mb-2 block">Experiências</span>
                <h2 class="font-serif text-4xl md:text-5xl mb-4">Relatos de Transformação</h2>
                <p class="text-white/40">O carinho e satisfação de nossas clientes em cada detalhe.</p>
            </div>

            <div class="grid md:grid-cols-3 gap-8">
                <?php foreach($testimonials as $t): ?>
                <div class="bg-white/5 backdrop-blur-md p-10 rounded-[2.5rem] border border-white/10 hover:bg-white/10 transition-all duration-500 hover:-translate-y-2">
                    <div class="flex items-center gap-1 text-gold mb-6">
                        <?php for($i=0;$i<5;$i++): ?><i data-lucide="star" class="w-4 h-4 fill-current"></i><?php endfor; ?>
                    </div>
                    <p class="text-white/80 mb-8 italic text-lg leading-relaxed">"<?php echo htmlspecialchars($t['content']); ?>"</p>
                    <div class="flex items-center gap-4">
                        <?php if($t['image_data']): ?>
                            <img src="<?php echo $t['image_data']; ?>" class="w-12 h-12 rounded-full object-cover ring-2 ring-gold/30">
                        <?php else: ?>
                            <div class="w-12 h-12 rounded-full bg-white/10 flex items-center justify-center text-lg font-bold text-gold"><?php echo substr($t['title'], 0, 1); ?></div>
                        <?php endif; ?>
                        <div>
                            <h4 class="font-medium text-white"><?php echo htmlspecialchars($t['title']); ?></h4>
                            <span class="text-xs text-white/30 uppercase tracking-widest font-bold">Cliente Verificada</span>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- Footer -->
    <footer class="bg-white border-t border-sand pt-20 pb-10">
        <div class="max-w-7xl mx-auto px-6">
            <div class="grid md:grid-cols-4 gap-12 mb-20">
                <div class="col-span-1 md:col-span-1">
                    <a href="index.php" class="font-serif text-3xl text-charcoal flex items-center gap-2 mb-6">
                        <?php if(!empty($sections['logo']['image_data'])): ?>
                            <img src="<?php echo $sections['logo']['image_data']; ?>" 
                                 style="height: <?php echo (intval($sections['logo']['subtitle']) * 1.2); ?>px" 
                                 class="w-auto object-contain" alt="Logo">
                        <?php else: ?>
                            <i data-lucide="sparkles" class="w-6 h-6 text-gold"></i> <?php echo htmlspecialchars($sections['logo']['title']); ?>
                        <?php endif; ?>
                    </a>
                    <p class="text-charcoal-light text-base leading-relaxed"><?php echo htmlspecialchars($footer['description']); ?></p>
                    <div class="flex gap-4 mt-6">
                        <a href="<?php echo htmlspecialchars($footer['instagram']); ?>" target="_blank" class="w-10 h-10 rounded-full bg-sand flex items-center justify-center text-charcoal hover:bg-gold hover:text-white transition-all"><i data-lucide="instagram" class="w-5 h-5"></i></a>
                        <a href="<?php echo htmlspecialchars($footer['facebook']); ?>" target="_blank" class="w-10 h-10 rounded-full bg-sand flex items-center justify-center text-charcoal hover:bg-gold hover:text-white transition-all"><i data-lucide="facebook" class="w-5 h-5"></i></a>
                    </div>
                </div>
                
                <div>
                    <h4 class="font-bold text-charcoal mb-6 uppercase tracking-widest text-xs">Acesso Rápido</h4>
                    <ul class="space-y-4 text-sm text-charcoal-light font-medium">
                        <li><a href="#inicio" class="hover:text-gold transition-colors">Página Inicial</a></li>
                        <li><a href="#servicos" class="hover:text-gold transition-colors">Menu de Serviços</a></li>
                        <li><a href="#sobre" class="hover:text-gold transition-colors">Sobre mim</a></li>
                        <li><a href="book.php" class="hover:text-gold transition-colors">Fazer Agendamento</a></li>
                    </ul>
                </div>

                <div>
                    <h4 class="font-bold text-charcoal mb-6 uppercase tracking-widest text-xs">Atendimento</h4>
                    <ul class="space-y-4 text-sm text-charcoal-light">
                        <li class="flex items-center gap-3"><i data-lucide="phone" class="w-4 h-4 text-gold"></i> <?php echo htmlspecialchars($footer['whatsapp']); ?></li>
                        <li class="flex items-center gap-3"><i data-lucide="mail" class="w-4 h-4 text-gold"></i> <?php echo htmlspecialchars($footer['email']); ?></li>
                        <li class="flex items-center gap-3"><i data-lucide="map-pin" class="w-4 h-4 text-gold"></i> <?php echo htmlspecialchars($footer['address']); ?></li>
                    </ul>
                </div>

                <div>
                    <h4 class="font-bold text-charcoal mb-6 uppercase tracking-widest text-xs">Horário de Funcionamento</h4>
                    <ul class="space-y-3 text-sm text-charcoal-light">
                        <li class="flex justify-between"><span>Segunda - Sexta</span> <span><?php echo htmlspecialchars($footer['hours_mon_fri']); ?></span></li>
                        <li class="flex justify-between"><span>Sábado</span> <span><?php echo htmlspecialchars($footer['hours_sat']); ?></span></li>
                        <li class="flex justify-between <?php echo $footer['hours_sun'] === 'Fechado' ? 'text-charcoal/30' : ''; ?>"><span>Domingo</span> <span><?php echo htmlspecialchars($footer['hours_sun']); ?></span></li>
                    </ul>
                </div>
            </div>
            
            <div class="pt-8 border-t border-sand flex flex-col md:flex-row justify-between items-center gap-4 text-xs text-charcoal-light font-medium">
                <p>&copy; <?php echo date('Y'); ?> Essenza Glow &bull; Estética Avançada.</p>
            </div>
        </div>
    </footer>

    <script>
        lucide.createIcons();
    </script>
</body>
</html>
