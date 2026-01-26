<?php
require_once 'db.php';
require_once 'layout.php';

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_section') {
        $key = $_POST['section_key'];
        $title = $_POST['title'];
        $subtitle = $_POST['subtitle'] ?? '';
        $content = $_POST['content'] ?? '';
        
        // Handle Image Upload
        $imageData = null;
        if (isset($_FILES['image']) && $_FILES['image']['error'] === 0) {
            $data = file_get_contents($_FILES['image']['tmp_name']);
            $type = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
            $imageData = 'data:image/' . $type . ';base64,' . base64_encode($data);
        }
        
        if ($key === 'footer') {
            // Especial handling for footer to save multiple fields as JSON
            $footerData = [
                'description' => $_POST['footer_description'] ?? '',
                'instagram' => $_POST['footer_instagram'] ?? '',
                'facebook' => $_POST['footer_facebook'] ?? '',
                'whatsapp' => $_POST['footer_whatsapp'] ?? '',
                'email' => $_POST['footer_email'] ?? '',
                'address' => $_POST['footer_address'] ?? '',
                'hours_mon_fri' => $_POST['footer_hours_mon_fri'] ?? '',
                'hours_sat' => $_POST['footer_hours_sat'] ?? '',
                'hours_sun' => $_POST['footer_hours_sun'] ?? ''
            ];
            $content = json_encode($footerData, JSON_UNESCAPED_UNICODE);
        }

        // Compatible UPSERT logic for both SQLite and MySQL
        $check = $pdo->prepare("SELECT id FROM landing_sections WHERE section_key = ?");
        $check->execute([$key]);
        $exists = $check->fetch();

        if ($exists) {
            if ($imageData) {
                $pdo->prepare("UPDATE landing_sections SET title=?, subtitle=?, content=?, image_data=?, updated_at=CURRENT_TIMESTAMP WHERE section_key=?")
                    ->execute([$title, $subtitle, $content, $imageData, $key]);
            } else {
                $pdo->prepare("UPDATE landing_sections SET title=?, subtitle=?, content=?, updated_at=CURRENT_TIMESTAMP WHERE section_key=?")
                    ->execute([$title, $subtitle, $content, $key]);
            }
        } else {
            if ($imageData) {
                $pdo->prepare("INSERT INTO landing_sections (section_key, title, subtitle, content, image_data) VALUES (?, ?, ?, ?, ?)")
                    ->execute([$key, $title, $subtitle, $content, $imageData]);
            } else {
                $pdo->prepare("INSERT INTO landing_sections (section_key, title, subtitle, content) VALUES (?, ?, ?, ?)")
                    ->execute([$key, $title, $subtitle, $content]);
            }
        }
            
    } elseif ($action === 'add_item') {
        $section_key = $_POST['section_key'];
        $title = $_POST['title'];
        $content = $_POST['content'];
        // Handle Image Upload (Simplificado para Base64)
        $imageData = null;
        if (isset($_FILES['image']) && $_FILES['image']['error'] === 0) {
            $data = file_get_contents($_FILES['image']['tmp_name']);
            $type = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
            $imageData = 'data:image/' . $type . ';base64,' . base64_encode($data);
        }
        
        $pdo->prepare("INSERT INTO landing_items (section_key, title, content, image_data) VALUES (?, ?, ?, ?)")
            ->execute([$section_key, $title, $content, $imageData]);
            
    } elseif ($action === 'delete_item') {
        $id = $_POST['item_id'];
        $pdo->prepare("DELETE FROM landing_items WHERE id = ?")->execute([$id]);
    } elseif ($action === 'add_section') {
        $title = $_POST['title'];
        $key = 'custom_' . strtolower(preg_replace('/[^a-zA-Z0-9]/', '_', $title));
        $pdo->prepare("INSERT INTO landing_sections (section_key, title, content) VALUES (?, ?, ?)")
            ->execute([$key, $title, $_POST['content']]);
    } elseif ($action === 'delete_section') {
        $key = $_POST['section_key'];
        // Não permitir deletar seções padrão
        $defaults = ['hero', 'about', 'how_it_works'];
        if (!in_array($key, $defaults)) {
            $pdo->prepare("DELETE FROM landing_sections WHERE section_key = ?")->execute([$key]);
        }
    }
    
    header("Location: admin_landing.php");
    exit;
}

$stmt = $pdo->query("SELECT * FROM landing_sections");
while ($row = $stmt->fetch()) {
    $sections[$row['section_key']] = $row;
}
// Defaults to avoid warnings
$defSections = ['hero', 'about', 'how_it_works', 'footer', 'logo'];
foreach ($defSections as $k) {
if (!isset($sections[$k])) $sections[$k] = ['title'=>'', 'subtitle'=>'', 'content'=>'', 'image_data'=>''];
}
if(empty($sections['logo']['title'])) $sections['logo']['title'] = 'Essenza Glow';
if(empty($sections['logo']['subtitle'])) $sections['logo']['subtitle'] = '40'; // Default logo height in px

// Decode footer data
$footer = json_decode($sections['footer']['content'] ?? '{}', true);
$footerDefaults = [
    'description' => 'Redefinindo os padrões de beleza através da estética avançada e do acolhimento humano.',
    'instagram' => '#', 'facebook' => '#', 'whatsapp' => '(11) 99999-9999',
    'email' => 'contato@essenzaglow.com.br', 'address' => 'São Paulo, SP',
    'hours_mon_fri' => '09:00 - 19:00', 'hours_sat' => '09:00 - 14:00', 'hours_sun' => 'Fechado'
];
foreach($footerDefaults as $k => $v) if(empty($footer[$k])) $footer[$k] = $v;

$testimonials = $pdo->query("SELECT * FROM landing_items WHERE section_key = 'testimonials' ORDER BY created_at DESC")->fetchAll();
$faqs = $pdo->query("SELECT * FROM landing_items WHERE section_key = 'faq' ORDER BY display_order ASC")->fetchAll();

// Separar seções padrão de customizadas
$standardKeys = ['hero', 'about', 'how_it_works', 'footer', 'logo'];
$customSections = [];
foreach ($sections as $key => $sec) {
    if (!in_array($key, $standardKeys)) {
        $customSections[$key] = $sec;
    }
}

renderHeader("Gerenciar Site");
renderSidebar("Site");
?>

<div class="space-y-8 animate-fade-in max-w-5xl mx-auto pb-12">
    <div>
        <h2 class="font-serif text-3xl text-charcoal mb-2">Gerenciar Landing Page</h2>
        <p class="text-charcoal-light">Edite o conteúdo que aparece na página pública.</p>
    </div>

    <!-- IDENTIDADE VISUAL -->
    <div class="bg-white p-6 md:p-8 rounded-2xl border border-sand shadow-sm">
        <div class="flex items-center gap-3 mb-6">
            <div class="w-10 h-10 rounded-full bg-gold/20 flex items-center justify-center text-gold-dark"><i data-lucide="sparkles"></i></div>
            <h3 class="font-serif text-xl text-charcoal">Identidade Visual</h3>
        </div>
        <form method="POST" enctype="multipart/form-data" class="space-y-4">
            <input type="hidden" name="action" value="update_section">
            <input type="hidden" name="section_key" value="logo">
            
            <div class="grid md:grid-cols-3 gap-6">
                <!-- Nome da Marca -->
                <div class="md:col-span-2 space-y-4">
                    <div class="space-y-2">
                        <label class="text-sm font-medium text-charcoal">Nome da Marca (Texto da Logo)</label>
                        <input type="text" name="title" value="<?php echo htmlspecialchars($sections['logo']['title']); ?>" class="w-full px-4 py-2 rounded-lg border border-sand focus:border-gold outline-none">
                        <p class="text-xs text-charcoal-light italic">Será exibido caso você não envie uma imagem de logo.</p>
                    </div>
                    
                    <div class="space-y-2">
                        <label class="text-sm font-medium text-charcoal flex justify-between">
                            Tamanho da Logo (Altura)
                            <span id="logoSizeDisplay" class="text-gold-dark font-bold"><?php echo $sections['logo']['subtitle']; ?>px</span>
                        </label>
                        <input type="range" name="subtitle" min="20" max="200" value="<?php echo $sections['logo']['subtitle']; ?>" 
                               class="w-full h-2 bg-sand rounded-lg appearance-none cursor-pointer accent-gold"
                               oninput="document.getElementById('logoSizeDisplay').innerText = this.value + 'px'; document.getElementById('previewLogo').style.height = this.value + 'px';">
                        <p class="text-[10px] text-charcoal-light">Ajuste entre 20px e 200px para encontrar o tamanho ideal.</p>
                    </div>
                </div>

                <!-- Logo Image -->
                <div class="md:col-span-1 space-y-2">
                    <label class="text-sm font-medium text-charcoal block">Logo (Imagem)</label>
                    <div class="aspect-video rounded-xl bg-sand overflow-hidden relative border border-sand group cursor-pointer flex items-center justify-center">
                        <?php if(!empty($sections['logo']['image_data'])): ?>
                            <img id="previewLogo" src="<?php echo $sections['logo']['image_data']; ?>" style="height: <?php echo $sections['logo']['subtitle']; ?>px" class="w-auto object-contain transition-all">
                        <?php else: ?>
                            <div class="absolute inset-0 flex items-center justify-center text-charcoal-light opacity-50">
                                <i data-lucide="image" class="w-8 h-8"></i>
                            </div>
                        <?php endif; ?>
                        <div class="absolute inset-0 bg-black/40 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity">
                            <span class="text-white text-[10px] font-medium">Trocar Logo</span>
                        </div>
                        <input type="file" name="image" accept="image/*" class="absolute inset-0 opacity-0 cursor-pointer">
                    </div>
                </div>
            </div>
            
            <div class="flex justify-end pt-2">
                <button type="submit" class="bg-charcoal text-white px-6 py-2 rounded-lg hover:bg-charcoal-light transition-colors">Salvar Identidade</button>
            </div>
        </form>
    </div>

    <!-- HERO SECTION -->
    <div class="bg-white p-6 md:p-8 rounded-2xl border border-sand shadow-sm">
        <div class="flex items-center gap-3 mb-6">
            <div class="w-10 h-10 rounded-full bg-gold/20 flex items-center justify-center text-gold-dark"><i data-lucide="layout-template"></i></div>
            <h3 class="font-serif text-xl text-charcoal">Capa (Hero)</h3>
        </div>
        <form method="POST" enctype="multipart/form-data" class="space-y-4">
            <input type="hidden" name="action" value="update_section">
            <input type="hidden" name="section_key" value="hero">
            
            <div class="grid md:grid-cols-3 gap-6">
                <!-- Coluna de Texto -->
                <div class="md:col-span-2 space-y-4">
                    <div class="grid md:grid-cols-2 gap-4">
                        <div class="space-y-2">
                            <label class="text-sm font-medium text-charcoal">Título Principal</label>
                            <input type="text" name="title" value="<?php echo htmlspecialchars($sections['hero']['title'] ?? ''); ?>" class="w-full px-4 py-2 rounded-lg border border-sand focus:border-gold focus:ring-1 focus:ring-gold outline-none transition-all">
                        </div>
                        <div class="space-y-2">
                            <label class="text-sm font-medium text-charcoal">Subtítulo</label>
                            <input type="text" name="subtitle" value="<?php echo htmlspecialchars($sections['hero']['subtitle'] ?? ''); ?>" class="w-full px-4 py-2 rounded-lg border border-sand focus:border-gold focus:ring-1 focus:ring-gold outline-none transition-all">
                        </div>
                    </div>
                </div>

                <!-- Coluna da Imagem -->
                <div class="md:col-span-1 space-y-2">
                    <label class="text-sm font-medium text-charcoal block">Imagem de Capa (Opcional)</label>
                    <div class="aspect-video md:aspect-square rounded-xl bg-sand overflow-hidden relative border border-sand group cursor-pointer">
                        <?php if(!empty($sections['hero']['image_data'])): ?>
                            <img src="<?php echo $sections['hero']['image_data']; ?>" class="w-full h-full object-cover">
                        <?php else: ?>
                            <div class="absolute inset-0 flex items-center justify-center text-charcoal-light opacity-50">
                                <i data-lucide="image" class="w-8 h-8"></i>
                            </div>
                        <?php endif; ?>
                        <div class="absolute inset-0 bg-black/40 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity">
                            <span class="text-white text-[10px] font-medium">Trocar Capa</span>
                        </div>
                        <input type="file" name="image" accept="image/*" class="absolute inset-0 opacity-0 cursor-pointer">
                    </div>
                </div>
            </div>
            
            <div class="flex justify-end pt-2">
                <button type="submit" class="bg-charcoal text-white px-6 py-2 rounded-lg hover:bg-charcoal-light transition-colors">Salvar Alterações</button>
            </div>
        </form>
    </div>

    <!-- ABOUT SECTION -->
    <div class="bg-white p-6 md:p-8 rounded-2xl border border-sand shadow-sm">
        <div class="flex items-center gap-3 mb-6">
            <div class="w-10 h-10 rounded-full bg-sage/20 flex items-center justify-center text-sage"><i data-lucide="user"></i></div>
            <h3 class="font-serif text-xl text-charcoal">Quem Sou Eu</h3>
        </div>
        <form method="POST" enctype="multipart/form-data" class="space-y-4">
            <input type="hidden" name="action" value="update_section">
            <input type="hidden" name="section_key" value="about">
            
            <div class="grid md:grid-cols-3 gap-6">
                <!-- Coluna da Imagem -->
                <div class="md:col-span-1 space-y-4">
                    <label class="text-sm font-medium text-charcoal block">Foto Profissional</label>
                    <div class="aspect-[4/5] rounded-2xl bg-sand overflow-hidden relative border border-sand group cursor-pointer">
                        <?php if(!empty($sections['about']['image_data'])): ?>
                            <img src="<?php echo $sections['about']['image_data']; ?>" class="w-full h-full object-cover">
                        <?php else: ?>
                            <div class="absolute inset-0 flex items-center justify-center text-charcoal-light opacity-50">
                                <i data-lucide="image" class="w-12 h-12"></i>
                            </div>
                        <?php endif; ?>
                        <div class="absolute inset-0 bg-black/40 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity">
                            <span class="text-white text-xs font-medium">Trocar Imagem</span>
                        </div>
                        <input type="file" name="image" accept="image/*" class="absolute inset-0 opacity-0 cursor-pointer">
                    </div>
                    <p class="text-[10px] text-charcoal-light italic text-center">Recomendado: Proporção 4:5</p>
                </div>

                <!-- Coluna de Texto -->
                <div class="md:col-span-2 space-y-4">
                    <div class="space-y-2">
                        <label class="text-sm font-medium text-charcoal">Título</label>
                        <input type="text" name="title" value="<?php echo htmlspecialchars($sections['about']['title'] ?? ''); ?>" class="w-full px-4 py-2 rounded-lg border border-sand focus:border-gold outline-none">
                    </div>
                    <div class="space-y-2">
                        <label class="text-sm font-medium text-charcoal">Texto Descritivo</label>
                        <textarea name="content" rows="8" class="w-full px-4 py-2 rounded-lg border border-sand focus:border-gold outline-none"><?php echo htmlspecialchars($sections['about']['content'] ?? ''); ?></textarea>
                    </div>
                </div>
            </div>

            <div class="flex justify-end pt-2">
                <button type="submit" class="bg-charcoal text-white px-6 py-2 rounded-lg hover:bg-charcoal-light transition-colors">Salvar Alterações</button>
            </div>
        </form>
    </div>

    <!-- TESTIMONIALS -->
    <div class="bg-white p-6 md:p-8 rounded-2xl border border-sand shadow-sm">
        <div class="flex items-center justify-between mb-6">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-full bg-orange-100 flex items-center justify-center text-orange-600"><i data-lucide="message-circle"></i></div>
                <h3 class="font-serif text-xl text-charcoal">Depoimentos</h3>
            </div>
            <button onclick="openModal('newTestimonial')" class="text-sm bg-sand hover:bg-sand-dark px-3 py-2 rounded-lg transition-colors">+ Adicionar</button>
        </div>

        <div class="grid gap-4">
            <?php foreach ($testimonials as $item): ?>
            <div class="flex items-center gap-4 p-4 border border-sand rounded-xl bg-ivory/50">
                <?php if($item['image_data']): ?>
                    <img src="<?php echo $item['image_data']; ?>" class="w-12 h-12 rounded-full object-cover">
                <?php else: ?>
                    <div class="w-12 h-12 rounded-full bg-sand flex items-center justify-center"><span class="font-serif text-lg"><?php echo substr($item['title'], 0, 1); ?></span></div>
                <?php endif; ?>
                <div class="flex-1">
                    <h4 class="font-medium text-charcoal"><?php echo htmlspecialchars($item['title']); ?></h4>
                    <p class="text-sm text-charcoal-light line-clamp-2"><?php echo htmlspecialchars($item['content']); ?></p>
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="delete_item">
                    <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                    <button type="submit" class="text-red-400 hover:text-red-600 p-2"><i data-lucide="trash-2" class="w-4 h-4"></i></button>
                </form>
            </div>
            <?php endforeach; ?>
            <?php if(empty($testimonials)): ?>
                <p class="text-center text-charcoal-light text-sm italic py-4">Nenhum depoimento cadastrado.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- FOOTER SECTION -->
    <div class="bg-white p-6 md:p-8 rounded-2xl border border-sand shadow-sm">
        <div class="flex items-center gap-3 mb-6">
            <div class="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center text-blue-600"><i data-lucide="layout"></i></div>
            <h3 class="font-serif text-xl text-charcoal">Rodapé</h3>
        </div>
        <form method="POST" class="space-y-6">
            <input type="hidden" name="action" value="update_section">
            <input type="hidden" name="section_key" value="footer">
            <input type="hidden" name="title" value="Rodapé">

            <div class="grid md:grid-cols-2 gap-6">
                <!-- Informações Gerais -->
                <div class="space-y-4">
                    <h4 class="font-medium text-charcoal text-sm border-b border-sand pb-1 uppercase tracking-wider">Informações Gerais</h4>
                    <div class="space-y-2">
                        <label class="text-xs font-medium text-charcoal-light">Descrição (Sobre a Essenza)</label>
                        <textarea name="footer_description" rows="3" class="w-full px-4 py-2 rounded-lg border border-sand focus:border-gold outline-none text-sm"><?php echo htmlspecialchars($footer['description']); ?></textarea>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-2">
                            <label class="text-xs font-medium text-charcoal-light">WhatsApp/Telefone</label>
                            <input type="text" name="footer_whatsapp" value="<?php echo htmlspecialchars($footer['whatsapp']); ?>" class="w-full px-4 py-2 rounded-lg border border-sand focus:border-gold outline-none text-sm">
                        </div>
                        <div class="space-y-2">
                            <label class="text-xs font-medium text-charcoal-light">E-mail</label>
                            <input type="email" name="footer_email" value="<?php echo htmlspecialchars($footer['email']); ?>" class="w-full px-4 py-2 rounded-lg border border-sand focus:border-gold outline-none text-sm">
                        </div>
                    </div>
                    <div class="space-y-2">
                        <label class="text-xs font-medium text-charcoal-light">Endereço</label>
                        <input type="text" name="footer_address" value="<?php echo htmlspecialchars($footer['address']); ?>" class="w-full px-4 py-2 rounded-lg border border-sand focus:border-gold outline-none text-sm">
                    </div>
                </div>

                <!-- Redes Sociais e Horários -->
                <div class="space-y-4">
                    <h4 class="font-medium text-charcoal text-sm border-b border-sand pb-1 uppercase tracking-wider">Social e Horários</h4>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-2">
                            <label class="text-xs font-medium text-charcoal-light">Instagram (URL)</label>
                            <input type="text" name="footer_instagram" value="<?php echo htmlspecialchars($footer['instagram']); ?>" class="w-full px-4 py-2 rounded-lg border border-sand focus:border-gold outline-none text-sm">
                        </div>
                        <div class="space-y-2">
                            <label class="text-xs font-medium text-charcoal-light">Facebook (URL)</label>
                            <input type="text" name="footer_facebook" value="<?php echo htmlspecialchars($footer['facebook']); ?>" class="w-full px-4 py-2 rounded-lg border border-sand focus:border-gold outline-none text-sm">
                        </div>
                    </div>
                    <div class="space-y-2">
                        <label class="text-xs font-medium text-charcoal-light">Horário Seg-Sex</label>
                        <input type="text" name="footer_hours_mon_fri" value="<?php echo htmlspecialchars($footer['hours_mon_fri']); ?>" class="w-full px-4 py-2 rounded-lg border border-sand focus:border-gold outline-none text-sm">
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-2">
                            <label class="text-xs font-medium text-charcoal-light">Horário Sábado</label>
                            <input type="text" name="footer_hours_sat" value="<?php echo htmlspecialchars($footer['hours_sat']); ?>" class="w-full px-4 py-2 rounded-lg border border-sand focus:border-gold outline-none text-sm">
                        </div>
                        <div class="space-y-2">
                            <label class="text-xs font-medium text-charcoal-light">Horário Domingo</label>
                            <input type="text" name="footer_hours_sun" value="<?php echo htmlspecialchars($footer['hours_sun']); ?>" class="w-full px-4 py-2 rounded-lg border border-sand focus:border-gold outline-none text-sm">
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex justify-end pt-2 border-t border-sand">
                <button type="submit" class="bg-charcoal text-white px-8 py-2.5 rounded-lg hover:bg-charcoal-light transition-colors font-medium">Salvar Rodapé</button>
            </div>
        </form>
    </div>

    <!-- DYNAMIC SECTIONS -->
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <h3 class="font-serif text-2xl text-charcoal">Seções Adicionais</h3>
            <button onclick="openModal('newSection')" class="bg-charcoal text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-charcoal-light transition-colors">+ Nova Seção</button>
        </div>

        <?php foreach ($customSections as $key => $sec): ?>
        <div class="bg-white p-6 md:p-8 rounded-2xl border border-sand shadow-sm relative group">
            <div class="absolute top-4 right-4 flex gap-2">
                <form method="POST" onsubmit="return confirm('Deseja excluir esta seção?')">
                    <input type="hidden" name="action" value="delete_section">
                    <input type="hidden" name="section_key" value="<?php echo $key; ?>">
                    <button type="submit" class="text-red-400 hover:text-red-600 p-2"><i data-lucide="trash-2" class="w-4 h-4"></i></button>
                </form>
            </div>

            <div class="flex items-center gap-3 mb-6">
                <div class="w-10 h-10 rounded-full bg-sand flex items-center justify-center text-charcoal"><i data-lucide="layers"></i></div>
                <h3 class="font-serif text-xl text-charcoal"><?php echo htmlspecialchars($sec['title']); ?></h3>
            </div>

            <form method="POST" class="space-y-4">
                <input type="hidden" name="action" value="update_section">
                <input type="hidden" name="section_key" value="<?php echo $key; ?>">
                
                <div class="space-y-2">
                    <label class="text-sm font-medium text-charcoal">Título</label>
                    <input type="text" name="title" value="<?php echo htmlspecialchars($sec['title']); ?>" class="w-full px-4 py-2 rounded-lg border border-sand focus:border-gold outline-none">
                </div>
                <div class="space-y-2">
                    <label class="text-sm font-medium text-charcoal">Conteúdo</label>
                    <textarea name="content" rows="4" class="w-full px-4 py-2 rounded-lg border border-sand focus:border-gold outline-none"><?php echo htmlspecialchars($sec['content']); ?></textarea>
                </div>
                <div class="flex justify-end pt-2">
                    <button type="submit" class="bg-charcoal text-white px-6 py-2 rounded-lg hover:bg-charcoal-light transition-colors">Salvar Alterações</button>
                </div>
            </form>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Modal Nova Seção -->
<dialog id="newSection" class="p-0 rounded-2xl backdrop:bg-black/50 w-full max-w-lg shadow-2xl">
    <div class="p-6">
        <h3 class="font-serif text-xl mb-4">Criar Nova Seção</h3>
        <form method="POST" class="space-y-4">
            <input type="hidden" name="action" value="add_section">
            
            <div class="space-y-2">
                <label class="text-sm font-medium">Nome da Seção (Ex: Blog, FAQ, etc)</label>
                <input type="text" name="title" required class="w-full px-4 py-2 rounded-lg border border-sand">
            </div>
            <div class="space-y-2">
                <label class="text-sm font-medium">Conteúdo Inicial</label>
                <textarea name="content" rows="4" class="w-full px-4 py-2 rounded-lg border border-sand"></textarea>
            </div>
            
            <div class="flex justify-end gap-2 pt-4">
                <button type="button" onclick="closeModal('newSection')" class="px-4 py-2 text-charcoal hover:bg-sand rounded-lg">Cancelar</button>
                <button type="submit" class="px-4 py-2 bg-sage text-white rounded-lg hover:bg-sage-dark">Criar Seção</button>
            </div>
        </form>
    </div>
</dialog>

<!-- Modal Novo Depoimento -->
<dialog id="newTestimonial" class="p-0 rounded-2xl backdrop:bg-black/50 w-full max-w-lg shadow-2xl">
    <div class="p-6">
        <h3 class="font-serif text-xl mb-4">Novo Depoimento</h3>
        <form method="POST" enctype="multipart/form-data" class="space-y-4">
            <input type="hidden" name="action" value="add_item">
            <input type="hidden" name="section_key" value="testimonials">
            
            <div class="space-y-2">
                <label class="text-sm font-medium">Nome do Cliente</label>
                <input type="text" name="title" required class="w-full px-4 py-2 rounded-lg border border-sand">
            </div>
            <div class="space-y-2">
                <label class="text-sm font-medium">Depoimento</label>
                <textarea name="content" required rows="3" class="w-full px-4 py-2 rounded-lg border border-sand"></textarea>
            </div>
            <div class="space-y-2">
                <label class="text-sm font-medium">Foto (Opcional)</label>
                <input type="file" name="image" accept="image/*" class="w-full text-sm">
            </div>
            
            <div class="flex justify-end gap-2 pt-4">
                <button type="button" onclick="closeModal('newTestimonial')" class="px-4 py-2 text-charcoal hover:bg-sand rounded-lg">Cancelar</button>
                <button type="submit" class="px-4 py-2 bg-sage text-white rounded-lg hover:bg-sage-dark">Adicionar</button>
            </div>
        </form>
    </div>
</dialog>

<?php renderFooter(); ?>
