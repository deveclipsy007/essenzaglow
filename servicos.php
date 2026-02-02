<?php
require_once 'db.php';
require_once 'layout.php';

// --- HANDLE ACTIONS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $is_featured = isset($_POST['is_featured']) ? 1 : 0;
        $discount_price = !empty($_POST['discount_price']) ? $_POST['discount_price'] : null;
        $stmt = $pdo->prepare("INSERT INTO services (name, category, duration_minutes, price, description, is_featured, discount_price) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$_POST['name'], $_POST['category'], $_POST['duration'], $_POST['price'], $_POST['description'], $is_featured, $discount_price]);
    } elseif ($action === 'edit') {
        $is_featured = isset($_POST['is_featured']) ? 1 : 0;
        $discount_price = !empty($_POST['discount_price']) ? $_POST['discount_price'] : null;
        $stmt = $pdo->prepare("UPDATE services SET name=?, category=?, duration_minutes=?, price=?, description=?, is_featured=?, discount_price=? WHERE id=?");
        $stmt->execute([$_POST['name'], $_POST['category'], $_POST['duration'], $_POST['price'], $_POST['description'], $is_featured, $discount_price, $_POST['id']]);
    } elseif ($action === 'delete') {
        $stmt = $pdo->prepare("DELETE FROM services WHERE id=?");
        $stmt->execute([$_POST['id']]);
    } elseif ($action === 'add_combo') {
        $is_featured = isset($_POST['is_featured']) ? 1 : 0;
        // Criar combo
        $stmt = $pdo->prepare("INSERT INTO combos (name, category, duration_minutes, original_price, promotional_price, description, is_featured) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $_POST['combo_name'], 
            $_POST['combo_category'], 
            $_POST['combo_duration'], 
            $_POST['original_price'],
            $_POST['combo_price'], 
            $_POST['combo_description'],
            $is_featured
        ]);
        
        $comboId = $pdo->lastInsertId();
        
        // Adicionar serviços ao combo
        if (!empty($_POST['combo_services'])) {
            $stmt = $pdo->prepare("INSERT INTO combo_services (combo_id, service_id) VALUES (?, ?)");
            foreach ($_POST['combo_services'] as $serviceId) {
                $stmt->execute([$comboId, $serviceId]);
            }
        }
    } elseif ($action === 'delete_combo') {
        $stmt = $pdo->prepare("DELETE FROM combos WHERE id=?");
        $stmt->execute([$_POST['id']]);
    } elseif ($action === 'edit_combo') {
        $id = $_POST['id'];
        $is_featured = isset($_POST['is_featured']) ? 1 : 0;
        
        $stmt = $pdo->prepare("UPDATE combos SET name=?, category=?, duration_minutes=?, original_price=?, promotional_price=?, description=?, is_featured=? WHERE id=?");
        $stmt->execute([
            $_POST['combo_name'], 
            $_POST['combo_category'], 
            $_POST['combo_duration'], 
            $_POST['original_price'],
            $_POST['combo_price'], 
            $_POST['combo_description'],
            $is_featured,
            $id
        ]);
        
        // Sincronizar serviços do combo
        $pdo->prepare("DELETE FROM combo_services WHERE combo_id = ?")->execute([$id]);
        if (!empty($_POST['combo_services'])) {
            $stmt = $pdo->prepare("INSERT INTO combo_services (combo_id, service_id) VALUES (?, ?)");
            foreach ($_POST['combo_services'] as $serviceId) {
                $stmt->execute([$id, $serviceId]);
            }
        }
    } elseif ($action === 'add_package') {
        $is_featured = isset($_POST['is_featured']) ? 1 : 0;
        // Criar pacote
        $stmt = $pdo->prepare("INSERT INTO packages (name, service_id, session_count, price, description, is_featured) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $_POST['package_name'], 
            $_POST['package_service_id'], 
            $_POST['package_sessions'], 
            $_POST['package_price'], 
            $_POST['package_description'],
            $is_featured
        ]);
    } elseif ($action === 'edit_package') {
        $is_featured = isset($_POST['is_featured']) ? 1 : 0;
        $stmt = $pdo->prepare("UPDATE packages SET name=?, service_id=?, session_count=?, price=?, description=?, is_featured=? WHERE id=?");
        $stmt->execute([
            $_POST['package_name'], 
            $_POST['package_service_id'], 
            $_POST['package_sessions'], 
            $_POST['package_price'], 
            $_POST['package_description'],
            $is_featured,
            $_POST['id']
        ]);
    } elseif ($action === 'delete_package') {
        $stmt = $pdo->prepare("DELETE FROM packages WHERE id=?");
        $stmt->execute([$_POST['id']]);
    }
    
    header("Location: servicos.php");
    exit;
}

// --- LOGIC ---
$stmt = $pdo->query("SELECT * FROM services ORDER BY category, name");
$services = $stmt->fetchAll();

// Buscar combos
$stmt = $pdo->query("SELECT * FROM combos ORDER BY created_at DESC");
$combos = $stmt->fetchAll();

// Para cada combo, buscar os serviços inclusos
foreach ($combos as &$combo) {
    $stmt = $pdo->prepare("
        SELECT s.* FROM services s
        INNER JOIN combo_services cs ON s.id = cs.service_id
        WHERE cs.combo_id = ?
    ");
    $stmt->execute([$combo['id']]);
    $combo['included_services'] = $stmt->fetchAll();
}
unset($combo);

// Buscar pacotes
$packages = [];
try {
    $stmt = $pdo->query("SELECT p.*, s.name as service_name, s.price as single_price FROM packages p LEFT JOIN services s ON p.service_id = s.id ORDER BY p.name");
    $packages = $stmt->fetchAll();
} catch (PDOException $e) {
    // Tabela packages ainda não existe ou erro na query.
    // Ignorar para não quebrar a página, o usuário precisa rodar a migração.
    // Opcionalmente poderíamos logar o erro: error_log($e->getMessage());
}

// --- VIEW ---
renderHeader("Serviços - Essenza");
renderSidebar('Serviços');
?>

<div class="max-w-6xl mx-auto space-y-8 animate-fade-in">
    <div class="flex justify-between items-center">
        <div>
            <h2 class="font-serif text-3xl text-charcoal">Menu de Serviços</h2>
            <p class="text-charcoal-light text-sm">Gerencie procedimentos e combos</p>
        </div>
        <button onclick="resetAddForms(); openModal('modalAdd')" class="bg-[#4A4238] hover:bg-[#3d362e] text-white px-4 py-2 rounded-lg font-medium transition-all">
            + Novo Item
        </button>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <!-- PACOTES START -->
        <?php foreach ($packages as $pkg): ?>
        <?php 
            // Calcular economia
            $originalTotal = $pkg['single_price'] * $pkg['session_count'];
            $savings = $originalTotal - $pkg['price'];
            $savingsPercent = $originalTotal > 0 ? ($savings / $originalTotal) * 100 : 0;
            $pricePerSession = $pkg['session_count'] > 0 ? $pkg['price'] / $pkg['session_count'] : 0;
        ?>
        <!-- Card de Pacote - Azul Sereno e Bege -->
        <div class="relative rounded-2xl overflow-hidden shadow-md hover:shadow-xl transition-all duration-300 group bg-gradient-to-br from-[#F0F4F8] via-[#E6EEF5] to-[#DDE7F0] border border-[#CCDDEE]">
            <div class="p-6">
                <!-- Header -->
                <div class="flex justify-between items-start mb-4">
                    <div class="flex flex-col gap-2">
                        <div class="inline-flex items-center gap-2 bg-[#5B85AA] text-white px-3 py-1.5 rounded-full shadow-md">
                            <span class="text-sm">📦</span>
                            <span class="text-[10px] uppercase tracking-wider font-bold">Pacote de Sessões</span>
                        </div>
                        <span class="inline-flex items-center text-[9px] uppercase tracking-[0.15em] text-[#5B85AA] font-semibold bg-white/80 px-2.5 py-1 rounded-full border border-[#CCDDEE] w-fit">
                            <?php echo $pkg['session_count']; ?>x SESSÕES
                        </span>
                    </div>
                    <!-- Badge Economia -->
                    <?php if ($savings > 0): ?>
                    <div class="bg-[#5B85AA] text-white px-3 py-1.5 rounded-full shadow-md">
                        <span class="text-xs font-bold">ECONOMIA <?php echo number_format($savingsPercent, 0); ?>%</span>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Nome do Pacote -->
                <h3 class="font-serif text-2xl text-[#2C3E50] mb-1 tracking-tight"><?php echo htmlspecialchars($pkg['name']); ?></h3>
                <p class="text-sm text-[#5B85AA] mb-3">
                    <?php echo htmlspecialchars($pkg['service_name']); ?>
                </p>

                <!-- Preços -->
                <div class="bg-white/70 rounded-xl p-4 mb-4 border border-[#CCDDEE]">
                    <?php if ($savings > 0): ?>
                    <div class="flex items-baseline justify-between mb-1">
                        <span class="text-xs text-[#5B85AA] uppercase font-bold">Total Original</span>
                        <span class="text-sm text-[#5B85AA] line-through">R$ <?php echo number_format($originalTotal, 2, ',', '.'); ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <div class="flex items-baseline justify-between">
                         <div class="flex flex-col">
                            <span class="text-[10px] text-[#5B85AA] uppercase font-bold">Por Sessão</span>
                            <span class="font-bold text-[#2C3E50]">R$ <?php echo number_format($pricePerSession, 2, ',', '.'); ?></span>
                         </div>
                         <div class="text-right">
                             <span class="block text-[10px] text-[#5B85AA] uppercase font-bold">Total do Pacote</span>
                             <span class="font-serif text-3xl text-[#5B85AA] font-bold">R$ <?php echo number_format($pkg['price'], 2, ',', '.'); ?></span>
                         </div>
                    </div>
                </div>

                <!-- Descrição -->
                <?php if(!empty($pkg['description'])): ?>
                <div class="bg-[#E6EEF5] rounded-xl p-3 mb-4 border border-[#CCDDEE]">
                    <p class="text-xs text-[#506B85] italic">"<?php echo htmlspecialchars($pkg['description']); ?>"</p>
                </div>
                <?php endif; ?>

                <!-- Ações -->
                <div class="flex gap-2">
                    <button onclick='fillEditPackageModal(<?php echo json_encode($pkg); ?>)' class="flex-1 py-2 rounded-lg bg-white/70 hover:bg-white text-[#506B85] transition-all font-medium text-xs border border-[#CCDDEE] flex items-center justify-center gap-2">
                        <i data-lucide="edit-3" class="w-3 h-3"></i> Editar
                    </button>
                    <form method="POST" onsubmit="return confirm('Excluir este pacote?');" class="flex-none">
                        <input type="hidden" name="action" value="delete_package">
                        <input type="hidden" name="id" value="<?php echo $pkg['id']; ?>">
                        <button type="submit" class="px-3 py-2 rounded-lg bg-red-50 hover:bg-red-100 text-red-600 border border-red-200 transaction-colors">
                            <i data-lucide="trash-2" class="w-3 h-3"></i>
                        </button>
                    </form>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        <!-- PACOTES END -->

        <?php foreach ($combos as $combo): ?>
        <?php 
            $savings = $combo['original_price'] - $combo['promotional_price'];
            $savingsPercent = $combo['original_price'] > 0 ? ($savings / $combo['original_price']) * 100 : 0;
        ?>
        <!-- Card de Combo - Verde e Bege Elegante -->
        <div class="relative rounded-2xl overflow-hidden shadow-md hover:shadow-xl transition-all duration-300 group bg-gradient-to-br from-[#F5F2ED] via-[#EAE5DA] to-[#E0DDD4] border border-[#D4CFC4]">
            <!-- Conteúdo do card -->
            <div class="p-6">
                <!-- Header com badges -->
                <div class="flex justify-between items-start mb-4">
                    <div class="flex flex-col gap-2">
                        <!-- Badge principal COMBO -->
                        <div class="inline-flex items-center gap-2 bg-[#5B7355] text-white px-3 py-1.5 rounded-full shadow-md">
                            <span class="text-sm">✨</span>
                            <span class="text-[10px] uppercase tracking-wider font-bold">Combo Promocional</span>
                        </div>
                        
                        <!-- Tag de categoria -->
                        <span class="inline-flex items-center text-[9px] uppercase tracking-[0.15em] text-[#5B7355] font-semibold bg-white/80 px-2.5 py-1 rounded-full border border-[#D4CFC4] w-fit">
                            <?php echo htmlspecialchars($combo['category']); ?>
                        </span>
                    </div>
                    
                    <!-- Badge de economia -->
                    <div class="flex flex-col items-end gap-1">
                        <div class="bg-[#5B7355] text-white px-3 py-1.5 rounded-full shadow-md">
                            <span class="text-xs font-bold">ECONOMIZE <?php echo number_format($savingsPercent, 0); ?>%</span>
                        </div>
                        <div class="flex items-center gap-1.5 text-sm text-[#5B7355]">
                            <i data-lucide="clock" class="w-4 h-4"></i>
                            <span class="font-semibold"><?php echo $combo['duration_minutes']; ?> min</span>
                        </div>
                    </div>
                </div>
                
                <!-- Nome do combo -->
                <h3 class="font-serif text-3xl text-[#4A4238] mb-3 tracking-tight"><?php echo htmlspecialchars($combo['name']); ?></h3>
                
                <!-- Preços e desconto -->
                <div class="bg-white/70 rounded-xl p-4 mb-4 border border-[#D4CFC4]">
                    <div class="flex items-baseline justify-between mb-2">
                        <div class="flex items-baseline gap-2">
                            <span class="text-sm text-[#8B7355]">De</span>
                            <span class="text-lg text-[#8B7355] line-through font-medium">
                                R$ <?php echo number_format($combo['original_price'], 2, ',', '.'); ?>
                            </span>
                        </div>
                        <div class="flex items-center gap-1.5 bg-[#E8F0E6] text-[#5B7355] px-2.5 py-1 rounded-lg">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path>
                            </svg>
                            <span class="text-xs font-bold">-R$ <?php echo number_format($savings, 2, ',', '.'); ?></span>
                        </div>
                    </div>
                    <div class="flex items-baseline gap-2">
                        <span class="text-sm text-[#8B7355]">Por apenas</span>
                        <span class="font-serif text-4xl text-[#5B7355] font-bold">
                            R$ <?php echo number_format($combo['promotional_price'], 2, ',', '.'); ?>
                        </span>
                    </div>
                </div>
                
                <!-- Serviços Inclusos -->
                <div class="bg-[#E8F0E6] rounded-xl p-4 mb-4 border border-[#C8D9C4]">
                    <div class="flex items-center gap-2 mb-3">
                        <div class="bg-[#5B7355] p-1.5 rounded-lg">
                            <i data-lucide="package" class="w-4 h-4 text-white"></i>
                        </div>
                        <span class="text-xs font-bold uppercase tracking-wider text-[#4A4238]">O que está incluso:</span>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <?php foreach ($combo['included_services'] as $includedService): ?>
                        <span class="inline-flex items-center gap-1.5 bg-white/80 text-[#5B7355] px-3 py-1.5 rounded-full text-xs font-medium border border-[#C8D9C4] shadow-sm">
                            <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                            </svg>
                            <?php echo htmlspecialchars($includedService['name'] ?? ''); ?>
                        </span>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- Botões de Ação -->
                <div class="flex gap-2">
                    <button onclick='fillEditComboModal(<?php echo json_encode($combo); ?>)' class="flex-1 py-2.5 rounded-lg bg-white/70 hover:bg-white text-[#4A4238] transition-all font-medium text-sm border border-[#D4CFC4] flex items-center justify-center gap-2">
                        <i data-lucide="edit-3" class="w-4 h-4"></i>
                        <span>Editar</span>
                    </button>
                    <form method="POST" onsubmit="return confirm('Excluir este combo?');" class="flex-1">
                        <input type="hidden" name="action" value="delete_combo">
                        <input type="hidden" name="id" value="<?php echo $combo['id']; ?>">
                        <button type="submit" class="w-full py-2.5 rounded-lg bg-red-50 hover:bg-red-100 text-red-600 hover:text-red-700 transition-all font-medium text-sm border border-red-200 flex items-center justify-center gap-2">
                            <i data-lucide="trash-2" class="w-4 h-4"></i>
                            <span>Excluir</span>
                        </button>
                    </form>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        
        <?php foreach ($services as $service): ?>
        <!-- Card de Serviço Individual -->
        <div class="bg-white rounded-xl p-6 shadow-sm border border-[#E8E3DA] hover:shadow-md transition-all group">
            <div class="flex justify-between items-start mb-4">
                <span class="text-[10px] uppercase tracking-[0.15em] text-[#8B7355] font-semibold bg-[#F5F2ED] px-3 py-1.5 rounded-md">
                    <?php echo htmlspecialchars($service['category'] ?? ''); ?>
                </span>
                <div class="flex items-center gap-1.5 text-sm text-[#8B7355]">
                    <i data-lucide="clock" class="w-4 h-4"></i>
                    <span><?php echo $service['duration_minutes']; ?> min</span>
                </div>
            </div>
            
            <h3 class="font-serif text-2xl text-[#4A4238] mb-2"><?php echo htmlspecialchars($service['name'] ?? ''); ?></h3>
            <p class="text-[#8B7355] text-sm mb-6"><?php echo htmlspecialchars($service['description'] ?? ''); ?></p>
            
            <div class="flex justify-end">
                <p class="font-serif text-3xl text-[#4A4238]">R$ <?php echo number_format($service['price'], 2, ',', '.'); ?></p>
            </div>
            
            <div class="mt-4 pt-4 border-t border-[#E8E3DA] flex gap-2">
                <button onclick='fillEditModal(<?php echo json_encode($service); ?>)' class="flex-1 py-2 rounded-lg bg-[#F5F2ED] text-[#4A4238] text-xs font-medium hover:bg-[#E8E3DA] transition-colors flex items-center justify-center gap-2">
                    <i data-lucide="edit-3" class="w-3 h-3"></i> Editar
                </button>
                <form method="POST" onsubmit="return confirm('Excluir este serviço?');" class="flex-none">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?php echo $service['id']; ?>">
                    <button type="submit" class="px-3 py-2 rounded-lg bg-red-50 text-red-600 hover:bg-red-100 hover:text-red-700 transition-colors border border-red-200">
                        <i data-lucide="trash-2" class="w-4 h-4"></i>
                    </button>
                </form>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Modal: Adicionar -->
<dialog id="modalAdd" class="p-0 rounded-2xl shadow-2xl w-full max-w-md backdrop:bg-[#4A4238]/20">
    <div class="w-full">
        <div class="bg-[#F5F2ED] px-6 py-4 flex justify-between items-center border-b border-[#E8E3DA]">
            <h2 class="font-serif text-xl text-[#4A4238]">Adicionar Item</h2>
            <button onclick="closeModal('modalAdd')" class="p-1 hover:bg-[#E8E3DA] rounded-full transition-colors">
                <i data-lucide="x" class="w-5 h-5 text-[#8B7355]"></i>
            </button>
        </div>
        
        <!-- Abas -->
        <div class="bg-white border-b border-[#E8E3DA] px-6 flex gap-6">
            <button id="tabProcedimento" onclick="switchTab('procedimento')" class="pb-3 pt-4 text-sm font-medium text-[#4A4238] border-b-2 border-[#4A4238]">
                Procedimento Único
            </button>
            <button id="tabCombo" onclick="switchTab('combo')" class="pb-3 pt-4 text-sm font-medium text-[#8B7355] border-b-2 border-transparent hover:text-[#4A4238] transition-colors">
                <i data-lucide="layers" class="w-4 h-4 inline mr-1"></i> Criar Combo
            </button>
            <button id="tabPacote" onclick="switchTab('pacote')" class="pb-3 pt-4 text-sm font-medium text-[#8B7355] border-b-2 border-transparent hover:text-[#4A4238] transition-colors">
                <i data-lucide="package" class="w-4 h-4 inline mr-1"></i> Criar Pacote
            </button>
        </div>
        
        <!-- Formulário: Procedimento Único -->
        <form method="POST" id="formProcedimento" class="p-6 space-y-4 bg-white">
            <input type="hidden" name="action" value="add">
            <div class="grid grid-cols-1 gap-4">
                <div>
                    <label class="block text-[10px] uppercase tracking-[0.1em] text-[#8B7355] mb-2 font-semibold">NOME DO PROCEDIMENTO</label>
                    <input type="text" name="name" required class="w-full px-4 py-3 rounded-lg bg-[#FBF9F6] border border-[#E8E3DA] text-[#4A4238] placeholder-[#B5A594] focus:outline-none focus:ring-2 focus:ring-[#C9B896] transition-all" placeholder="Ex: Massagem Relaxante">
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-[10px] uppercase tracking-[0.1em] text-[#8B7355] mb-2 font-semibold">CATEGORIA</label>
                        <select name="category" class="w-full px-4 py-3 rounded-lg bg-[#FBF9F6] border border-[#E8E3DA] text-[#4A4238] focus:outline-none focus:ring-2 focus:ring-[#C9B896] transition-all">
                            <option value="CORPO">Corporal</option>
                            <option value="FACE">Facial</option>
                            <option value="OUTRO">Outro</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[10px] uppercase tracking-[0.1em] text-[#8B7355] mb-2 font-semibold">DURAÇÃO (MIN)</label>
                        <div class="relative">
                            <i data-lucide="clock" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-[#C9B896]"></i>
                            <input type="number" name="duration" required class="w-full pl-10 pr-4 py-3 rounded-lg bg-[#FBF9F6] border border-[#E8E3DA] text-[#4A4238] placeholder-[#B5A594] focus:outline-none focus:ring-2 focus:ring-[#C9B896] transition-all" placeholder="60">
                        </div>
                    </div>
                </div>
                <div>
                    <label class="block text-[10px] uppercase tracking-[0.1em] text-[#8B7355] mb-2 font-semibold">VALOR (R$)</label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-[#C9B896]">R$</span>
                        <input type="number" step="0.01" name="price" required class="w-full pl-10 pr-4 py-3 rounded-lg bg-[#FBF9F6] border border-[#E8E3DA] text-[#4A4238] placeholder-[#B5A594] focus:outline-none focus:ring-2 focus:ring-[#C9B896] transition-all" placeholder="0">
                    </div>
                </div>
                <div>
                    <label class="block text-[10px] uppercase tracking-[0.1em] text-[#8B7355] mb-2 font-semibold">DESCRIÇÃO</label>
                    <div class="relative">
                        <i data-lucide="align-left" class="w-4 h-4 absolute left-3 top-3 text-[#C9B896]"></i>
                        <textarea name="description" rows="4" class="w-full pl-10 pr-4 py-3 rounded-lg bg-[#FBF9F6] border border-[#E8E3DA] text-[#4A4238] placeholder-[#B5A594] focus:outline-none focus:ring-2 focus:ring-[#C9B896] transition-all resize-none" placeholder="Detalhes sobre o procedimento..."></textarea>
                    </div>
                </div>

                <div class="flex items-center gap-4 py-2">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="is_featured" value="1" class="w-4 h-4 text-[#C9B896] border-[#E8E3DA] rounded">
                        <span class="text-sm text-[#4A4238]">Aparecer na Landing Page</span>
                    </label>
                </div>

                <div>
                    <label class="block text-[10px] uppercase tracking-[0.1em] text-[#8B7355] mb-2 font-semibold">VALOR PROMOCIONAL (SITE)</label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-[#C9B896]">R$</span>
                        <input type="number" step="0.01" name="discount_price" class="w-full pl-10 pr-4 py-3 rounded-lg bg-[#FBF9F6] border border-[#E8E3DA] text-[#4A4238] placeholder-[#B5A594] focus:outline-none focus:ring-2 focus:ring-[#C9B896] transition-all" placeholder="Deixe vazio para preço normal">
                    </div>
                </div>
            </div>
            <div class="pt-2">
                <button type="submit" class="w-full py-3.5 rounded-lg bg-[#4A4238] text-white hover:bg-[#3d362e] transition-all font-medium">Salvar Serviço</button>
            </div>
        </form>
        
        <!-- Formulário: Criar Combo -->
        <form method="POST" id="formCombo" class="p-6 space-y-4 bg-white hidden">
            <input type="hidden" name="action" value="add_combo">
            <input type="hidden" name="original_price" id="original_price_input" value="0">
            <div class="grid grid-cols-1 gap-4">
                <div>
                    <label for="add_combo_name" class="block text-[10px] uppercase tracking-[0.1em] text-[#8B7355] mb-2 font-semibold">NOME DO COMBO</label>
                    <input type="text" name="combo_name" id="add_combo_name" required class="w-full px-4 py-3 rounded-lg bg-[#FBF9F6] border border-[#E8E3DA] text-[#4A4238] placeholder-[#B5A594] focus:outline-none focus:ring-2 focus:ring-[#C9B896] transition-all" placeholder="Ex: Day Spa Relax">
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-[10px] uppercase tracking-[0.1em] text-[#8B7355] mb-2 font-semibold">CATEGORIA</label>
                        <select name="combo_category" class="w-full px-4 py-3 rounded-lg bg-[#FBF9F6] border border-[#E8E3DA] text-[#4A4238] focus:outline-none focus:ring-2 focus:ring-[#C9B896] transition-all">
                            <option value="CORPO">Corporal</option>
                            <option value="FACE">Facial</option>
                            <option value="OUTRO">Outro</option>
                        </select>
                    </div>
                    <div>
                        <label for="add_combo_duration" class="block text-[10px] uppercase tracking-[0.1em] text-[#8B7355] mb-2 font-semibold">DURAÇÃO (MIN)</label>
                        <div class="relative">
                            <i data-lucide="clock" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-[#C9B896]"></i>
                            <input type="number" name="combo_duration" id="add_combo_duration" required class="w-full pl-10 pr-4 py-3 rounded-lg bg-[#FBF9F6] border border-[#E8E3DA] text-[#4A4238] placeholder-[#B5A594] focus:outline-none focus:ring-2 focus:ring-[#C9B896] transition-all" placeholder="0">
                        </div>
                    </div>
                </div>
                
                <!-- Seleção de Serviços -->
                <div>
                    <label class="block text-[10px] uppercase tracking-[0.1em] text-[#8B7355] mb-2 font-semibold">SELECIONE OS SERVIÇOS INCLUSOS:</label>
                    <div class="bg-[#FBF9F6] border border-[#E8E3DA] rounded-lg p-4 max-h-40 overflow-y-auto">
                        <?php if (empty($services)): ?>
                            <p class="text-sm text-[#8B7355] italic">Nenhum outro serviço simples cadastrado.</p>
                        <?php else: ?>
                            <?php foreach ($services as $svc): ?>
                            <label class="flex items-center gap-2 py-2 hover:bg-white/50 px-2 rounded cursor-pointer">
                                <input type="checkbox" name="combo_services[]" value="<?php echo $svc['id']; ?>" 
                                       data-price="<?php echo $svc['price']; ?>"
                                       data-duration="<?php echo $svc['duration_minutes']; ?>"
                                       onchange="updateComboCalculations()"
                                       class="w-4 h-4 text-[#C9B896] border-[#E8E3DA] rounded focus:ring-[#C9B896]">
                                <span class="text-sm text-[#4A4238]"><?php echo htmlspecialchars($svc['name']); ?> (R$ <?php echo number_format($svc['price'], 2, ',', '.'); ?>)</span>
                            </label>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <div class="mt-2 flex justify-end">
                        <span class="text-sm text-[#8B7355]">Valor Original: <span id="add_original_price_display" class="font-semibold text-[#4A4238]">R$ 0,00</span></span>
                    </div>
                </div>
                
                <div>
                    <label class="block text-[10px] uppercase tracking-[0.1em] text-[#8B7355] mb-2 font-semibold">VALOR DO COMBO (PROMOCIONAL)</label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-[#C9B896]">R$</span>
                        <input type="number" step="0.01" name="combo_price" id="add_combo_price" required class="w-full pl-10 pr-4 py-3 rounded-lg bg-[#FBF9F6] border border-[#E8E3DA] text-[#4A4238] placeholder-[#B5A594] focus:outline-none focus:ring-2 focus:ring-[#C9B896] transition-all" placeholder="0">
                    </div>
                </div>
                <div>
                    <label class="block text-[10px] uppercase tracking-[0.1em] text-[#8B7355] mb-2 font-semibold">DESCRIÇÃO (OPCIONAL)</label>
                    <div class="relative">
                        <i data-lucide="align-left" class="w-4 h-4 absolute left-3 top-3 text-[#C9B896]"></i>
                        <textarea name="combo_description" rows="3" class="w-full pl-10 pr-4 py-3 rounded-lg bg-[#FBF9F6] border border-[#E8E3DA] text-[#4A4238] placeholder-[#B5A594] focus:outline-none focus:ring-2 focus:ring-[#C9B896] transition-all resize-none" placeholder="Detalhes do pacote..."></textarea>
                    </div>
                </div>

                <div class="flex items-center gap-4 py-2">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="is_featured" value="1" class="w-4 h-4 text-[#C9B896] border-[#E8E3DA] rounded">
                        <span class="text-sm text-[#4A4238]">Destacar na Landing Page</span>
                    </label>
                </div>
            </div>
            <div class="pt-2">
                <button type="submit" class="w-full py-3.5 rounded-lg bg-[#4A4238] text-white hover:bg-[#3d362e] transition-all font-medium">Salvar Combo</button>
            </div>
        </form>
        <!-- Formulário: Criar Pacote -->
        <form method="POST" id="formPackage" class="p-6 space-y-4 bg-white hidden">
            <input type="hidden" name="action" value="add_package">
            <input type="hidden" name="package_price" id="add_package_price_input" value="0">
            
            <div class="grid grid-cols-1 gap-4">
                <div>
                    <label class="block text-[10px] uppercase tracking-[0.1em] text-[#8B7355] mb-2 font-semibold">NOME DO PACOTE</label>
                    <input type="text" name="package_name" required class="w-full px-4 py-3 rounded-lg bg-[#FBF9F6] border border-[#E8E3DA] text-[#4A4238] placeholder-[#B5A594] focus:outline-none focus:ring-2 focus:ring-[#C9B896] transition-all" placeholder="Ex: Pacote 10 Sessões Massagem">
                </div>
                
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-[10px] uppercase tracking-[0.1em] text-[#8B7355] mb-2 font-semibold">SERVIÇO BASE</label>
                        <select name="package_service_id" id="add_package_service" onchange="updatePackageCalculations()" required class="w-full px-4 py-3 rounded-lg bg-[#FBF9F6] border border-[#E8E3DA] text-[#4A4238] focus:outline-none focus:ring-2 focus:ring-[#C9B896] transition-all">
                            <option value="">Selecione...</option>
                            <?php foreach ($services as $svc): ?>
                            <option value="<?php echo $svc['id']; ?>" data-price="<?php echo $svc['price']; ?>"><?php echo htmlspecialchars($svc['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[10px] uppercase tracking-[0.1em] text-[#8B7355] mb-2 font-semibold">QTD SESSÕES</label>
                        <input type="number" name="package_sessions" id="add_package_sessions" oninput="updatePackageCalculations()" required min="2" value="5" class="w-full px-4 py-3 rounded-lg bg-[#FBF9F6] border border-[#E8E3DA] text-[#4A4238] placeholder-[#B5A594] focus:outline-none focus:ring-2 focus:ring-[#C9B896] transition-all">
                    </div>
                </div>
                
                <div>
                     <div class="flex justify-between items-center mb-2">
                        <label class="block text-[10px] uppercase tracking-[0.1em] text-[#8B7355] font-semibold">PREÇO DO PACOTE</label>
                        <span class="text-xs text-[#8B7355]">Preço Unit. com Desconto: <span id="add_package_unit_price" class="font-bold">R$ 0,00</span></span>
                    </div>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-[#C9B896]">R$</span>
                        <input type="number" step="0.01" name="package_price" id="add_package_price" required class="w-full pl-10 pr-4 py-3 rounded-lg bg-[#FBF9F6] border border-[#E8E3DA] text-[#4A4238] placeholder-[#B5A594] focus:outline-none focus:ring-2 focus:ring-[#C9B896] transition-all">
                    </div>
                     <div class="mt-1 flex justify-between text-xs">
                        <span class="text-[#8B7355]">Total Original: <span id="add_package_original_total" class="line-through">R$ 0,00</span></span>
                        <span class="text-[#5B7355] font-bold" id="add_package_savings">Economia: 0%</span>
                    </div>
                </div>

                <div>
                    <label class="block text-[10px] uppercase tracking-[0.1em] text-[#8B7355] mb-2 font-semibold">DESCRIÇÃO (OPCIONAL)</label>
                    <textarea name="package_description" rows="3" class="w-full px-4 py-3 rounded-lg bg-[#FBF9F6] border border-[#E8E3DA] text-[#4A4238] placeholder-[#B5A594] focus:outline-none focus:ring-2 focus:ring-[#C9B896] transition-all resize-none"></textarea>
                </div>
                
                <div class="flex items-center gap-4 py-2">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="is_featured" value="1" class="w-4 h-4 text-[#C9B896] border-[#E8E3DA] rounded">
                        <span class="text-sm text-[#4A4238]">Destacar na Landing Page</span>
                    </label>
                </div>
            </div>
            <div class="pt-2">
                <button type="submit" class="w-full py-3.5 rounded-lg bg-[#4A4238] text-white hover:bg-[#3d362e] transition-all font-medium">Salvar Pacote</button>
            </div>
        </form>
    </div>
</dialog>

<!-- Modal: Editar -->
<dialog id="modalEdit" class="p-0 rounded-2xl shadow-2xl w-full max-w-md backdrop:bg-[#4A4238]/20">
    <div class="w-full">
        <div class="bg-[#F5F2ED] px-6 py-4 flex justify-between items-center border-b border-[#E8E3DA]">
            <h2 class="font-serif text-xl text-[#4A4238]">Editar Serviço</h2>
            <button onclick="closeModal('modalEdit')" class="p-1 hover:bg-[#E8E3DA] rounded-full transition-colors">
                <i data-lucide="x" class="w-5 h-5 text-[#8B7355]"></i>
            </button>
        </div>
        <form method="POST" id="formEdit" class="p-6 space-y-4 bg-white">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" id="edit_id">
            <div class="grid grid-cols-1 gap-4">
                <div>
                    <label class="block text-[10px] uppercase tracking-[0.1em] text-[#8B7355] mb-2 font-semibold">NOME DO PROCEDIMENTO</label>
                    <input type="text" name="name" id="edit_name" required class="w-full px-4 py-3 rounded-lg bg-[#FBF9F6] border border-[#E8E3DA] text-[#4A4238] focus:outline-none focus:ring-2 focus:ring-[#C9B896] transition-all">
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-[10px] uppercase tracking-[0.1em] text-[#8B7355] mb-2 font-semibold">CATEGORIA</label>
                        <select name="category" id="edit_category" class="w-full px-4 py-3 rounded-lg bg-[#FBF9F6] border border-[#E8E3DA] text-[#4A4238] focus:outline-none focus:ring-2 focus:ring-[#C9B896] transition-all">
                            <option value="CORPO">Corporal</option>
                            <option value="FACE">Facial</option>
                            <option value="OUTRO">Outro</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[10px] uppercase tracking-[0.1em] text-[#8B7355] mb-2 font-semibold">DURAÇÃO (MIN)</label>
                        <input type="number" name="duration" id="edit_duration" required class="w-full px-4 py-3 rounded-lg bg-[#FBF9F6] border border-[#E8E3DA] text-[#4A4238] focus:outline-none focus:ring-2 focus:ring-[#C9B896] transition-all">
                    </div>
                </div>
                <div>
                    <label class="block text-[10px] uppercase tracking-[0.1em] text-[#8B7355] mb-2 font-semibold">VALOR (R$)</label>
                    <input type="number" step="0.01" name="price" id="edit_price" required class="w-full px-4 py-3 rounded-lg bg-[#FBF9F6] border border-[#E8E3DA] text-[#4A4238] focus:outline-none focus:ring-2 focus:ring-[#C9B896] transition-all">
                </div>
                <div>
                    <label class="block text-[10px] uppercase tracking-[0.1em] text-[#8B7355] mb-2 font-semibold">DESCRIÇÃO</label>
                    <textarea name="description" id="edit_description" rows="3" class="w-full px-4 py-3 rounded-lg bg-[#FBF9F6] border border-[#E8E3DA] text-[#4A4238] focus:outline-none focus:ring-2 focus:ring-[#C9B896] transition-all resize-none"></textarea>
                </div>

                <div class="flex items-center gap-4 py-2">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="is_featured" id="edit_is_featured" value="1" class="w-4 h-4 text-[#C9B896] border-[#E8E3DA] rounded">
                        <span class="text-sm text-[#4A4238]">Aparecer na Landing Page</span>
                    </label>
                </div>

                <div>
                    <label class="block text-[10px] uppercase tracking-[0.1em] text-[#8B7355] mb-2 font-semibold">VALOR PROMOCIONAL (SITE)</label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-[#C9B896]">R$</span>
                        <input type="number" step="0.01" name="discount_price" id="edit_discount_price" class="w-full pl-10 pr-4 py-3 rounded-lg bg-[#FBF9F6] border border-[#E8E3DA] text-[#4A4238] focus:outline-none focus:ring-2 focus:ring-[#C9B896] transition-all">
                    </div>
                </div>
            </div>
            <div class="pt-4 flex gap-2">
                <button type="button" onclick="closeModal('modalEdit')" class="flex-1 py-3 rounded-lg bg-[#F5F2ED] text-[#4A4238] hover:bg-[#E8E3DA] transition-all font-medium">Cancelar</button>
                <button type="submit" class="flex-1 py-3 rounded-lg bg-[#4A4238] text-white hover:bg-[#3d362e] transition-all font-medium">Atualizar</button>
            </div>
        </form>
    </div>
</dialog>

<script>
    function resetAddForms() {
        // Reset Procedure Form
        document.getElementById('formProcedimento').reset();
        
        // Reset Combo Form
        document.getElementById('formCombo').reset();
        
        // Explicitly clear dynamic fields that reset() might miss if they were modified by JS
        document.getElementById('add_combo_name').value = '';
        document.getElementById('add_combo_duration').value = '';
        document.getElementById('add_combo_price').value = '';
        document.querySelector('#formCombo textarea[name="combo_description"]').value = '';
        document.getElementById('original_price_input').value = '0';
        
        // Uncheck all services
        const checkboxes = document.querySelectorAll('#formCombo input[name="combo_services[]"]');
        checkboxes.forEach(cb => cb.checked = false);
        
        // Reset calculations
        const display = document.getElementById('add_original_price_display');
        if (display) display.textContent = 'R$ 0,00';

        // Reset Package Form
        const formPackage = document.getElementById('formPackage');
        if (formPackage) {
            formPackage.reset();
            const serviceSelect = document.querySelector('#formPackage select[name="package_service_id"]');
            if(serviceSelect) serviceSelect.value = '';
            
            const unitPrice = document.getElementById('add_package_unit_price');
            if(unitPrice) unitPrice.innerText = 'R$ 0,00';
            
            const origTotal = document.getElementById('add_package_original_total');
            if(origTotal) origTotal.innerText = 'R$ 0,00';
            
            const savings = document.getElementById('add_package_savings');
            if(savings) savings.innerText = 'Economia: 0%';
        }
    }

    function fillEditModal(service) {
        document.getElementById('edit_id').value = service.id;
        document.getElementById('edit_name').value = service.name;
        document.getElementById('edit_category').value = service.category;
        document.getElementById('edit_duration').value = service.duration_minutes;
        document.getElementById('edit_price').value = service.price;
        document.getElementById('edit_description').value = service.description;
        document.getElementById('edit_is_featured').checked = service.is_featured == 1;
        document.getElementById('edit_discount_price').value = service.discount_price;
        openModal('modalEdit');
    }
    
    function fillEditComboModal(combo) {
        document.getElementById('edit_combo_id').value = combo.id;
        document.getElementById('edit_combo_name').value = combo.name;
        document.getElementById('edit_combo_category').value = combo.category;
        document.getElementById('edit_combo_duration').value = combo.duration_minutes;
        document.getElementById('edit_combo_price').value = combo.promotional_price;
        document.getElementById('edit_combo_description').value = combo.description;
        document.getElementById('edit_combo_is_featured').checked = combo.is_featured == 1;
        
        // Reset and check included services
        const checkboxes = document.querySelectorAll('#formEditCombo input[name="combo_services[]"]');
        checkboxes.forEach(cb => cb.checked = false);
        
        if (combo.included_services) {
            combo.included_services.forEach(svc => {
                const cb = document.getElementById('edit_combo_svc_' + svc.id);
                if (cb) cb.checked = true;
            });
        }
        
        
        updateComboCalculations('edit');
        openModal('modalEditCombo');
    }
    
    function switchTab(tab) {
        const tabProcedimento = document.getElementById('tabProcedimento');
        const tabCombo = document.getElementById('tabCombo');
        const tabPacote = document.getElementById('tabPacote');
        
        const formProcedimento = document.getElementById('formProcedimento');
        const formCombo = document.getElementById('formCombo');
        const formPackage = document.getElementById('formPackage');
        
        // Logic implemented above in the first few lines of this replacement block is actually incorrect because I was thinking inline but I am replacing the function body.
        // Let's rewrite the whole function properly.
        
        // Reset styles first
        const inactiveStyle = 'pb-3 pt-4 text-sm font-medium text-[#8B7355] border-b-2 border-transparent hover:text-[#4A4238] transition-colors';
        const activeStyle = 'pb-3 pt-4 text-sm font-medium text-[#4A4238] border-b-2 border-[#4A4238]';
        
        if (tab === 'procedimento') {
            tabProcedimento.className = activeStyle;
            tabCombo.className = inactiveStyle;
            tabPacote.className = inactiveStyle;
            formProcedimento.classList.remove('hidden');
            formCombo.classList.add('hidden');
            formPackage.classList.add('hidden');
        } else if (tab === 'combo') {
            tabProcedimento.className = inactiveStyle;
            tabCombo.className = activeStyle;
            tabPacote.className = inactiveStyle;
            formProcedimento.classList.add('hidden');
            formCombo.classList.remove('hidden');
            formPackage.classList.add('hidden');
        } else if (tab === 'pacote') {
            tabProcedimento.className = inactiveStyle;
            tabCombo.className = inactiveStyle;
            tabPacote.className = activeStyle;
            formProcedimento.classList.add('hidden');
            formCombo.classList.add('hidden');
            formPackage.classList.remove('hidden');
        }
    }
    
    function updatePackageCalculations(mode = 'add') {
         const suffix = mode === 'edit' ? 'edit_package_' : 'add_package_';
         const serviceSelect = document.getElementById(suffix + (mode === 'edit' ? 'service_id' : 'service'));
         const sessionsInput = document.getElementById(suffix + 'sessions');
         const priceInput = document.getElementById(suffix + 'price');
         
         const unitPriceDisplay = document.getElementById(suffix + 'unit_price');
         const originalTotalDisplay = document.getElementById(suffix + 'original_total');
         const savingsDisplay = document.getElementById(suffix + 'savings');
         
         if (!serviceSelect || !sessionsInput || !priceInput) return;
         
         const selectedOption = serviceSelect.options[serviceSelect.selectedIndex];
         const servicePrice = parseFloat(selectedOption.getAttribute('data-price') || 0);
         const sessions = parseInt(sessionsInput.value || 0);
         const packagePrice = parseFloat(priceInput.value || 0);
         
         const originalTotal = servicePrice * sessions;
         const unitPrice = sessions > 0 ? packagePrice / sessions : 0;
         const savings = originalTotal - packagePrice;
         const savingsPercent = originalTotal > 0 ? (savings / originalTotal) * 100 : 0;
         
         if (originalTotalDisplay) originalTotalDisplay.innerText = 'R$ ' + originalTotal.toFixed(2).replace('.', ',');
         if (unitPriceDisplay) unitPriceDisplay.innerText = 'R$ ' + unitPrice.toFixed(2).replace('.', ',');
         

    }

    function fillEditPackageModal(pkg) {
         document.getElementById('edit_package_id').value = pkg.id;
         document.getElementById('edit_package_name').value = pkg.name;
         document.getElementById('edit_package_service_id').value = pkg.service_id;
         document.getElementById('edit_package_sessions').value = pkg.session_count;
         document.getElementById('edit_package_price').value = pkg.price;
         document.getElementById('edit_package_description').value = pkg.description;
         const featured = document.getElementById('edit_package_is_featured');
         if(featured) featured.checked = pkg.is_featured == 1;
         
         // Trigger calculations
         updatePackageCalculations('edit');
         
         openModal('modalEditPackage');
    }

    
    // Add event listener to price updates
    document.addEventListener('DOMContentLoaded', () => {
        document.getElementById('add_package_price').addEventListener('input', () => updatePackageCalculations('add'));
        // document.getElementById('edit_package_price').addEventListener('input', () => updatePackageCalculations('edit')); // Will enable when edit modal exists
    });


    function updateComboCalculations(mode = 'add') {
        const suffix = mode === 'edit' ? 'edit_combo_' : '';
        const displayId = mode === 'edit' ? 'edit_combo_original_price_display' : 'add_original_price_display';
        const checkboxes = document.querySelectorAll('#form' + (mode === 'edit' ? 'EditCombo' : 'Combo') + ' input[name="combo_services[]"]:checked');
        let totalPrice = 0;
        let totalDuration = 0;
        
        checkboxes.forEach(cb => {
            totalPrice += parseFloat(cb.dataset.price || 0);
            totalDuration += parseInt(cb.dataset.duration || 0);
        });
        
        const display = document.getElementById(displayId);
        const input = document.getElementById(suffix + 'original_price_input');
        
        if (display) display.textContent = 'R$ ' + totalPrice.toFixed(2).replace('.', ',');
        if (input) input.value = totalPrice.toFixed(2);
        
        // Sugerir duração apenas se estiver zerado ou for criação nova
        if (mode === 'add') {
            const durationInput = document.querySelector('#formCombo input[name="combo_duration"]');
            if (durationInput && totalDuration > 0) durationInput.value = totalDuration;
        }
    }
</script>

<!-- Modal: Editar Pacote -->
<dialog id="modalEditPackage" class="p-0 rounded-2xl shadow-2xl w-full max-w-md backdrop:bg-[#4A4238]/20">
    <div class="w-full">
        <div class="bg-[#F5F2ED] px-6 py-4 flex justify-between items-center border-b border-[#E8E3DA]">
            <h2 class="font-serif text-xl text-[#4A4238]">Editar Pacote</h2>
            <button onclick="closeModal('modalEditPackage')" class="p-1 hover:bg-[#E8E3DA] rounded-full transition-colors">
                <i data-lucide="x" class="w-5 h-5 text-[#8B7355]"></i>
            </button>
        </div>
        
        <form method="POST" id="formEditPackage" class="p-6 space-y-4 bg-white">
            <input type="hidden" name="action" value="edit_package">
            <input type="hidden" name="id" id="edit_package_id">
            
            <div class="grid grid-cols-1 gap-4">
                <div>
                    <label class="block text-[10px] uppercase tracking-[0.1em] text-[#8B7355] mb-2 font-semibold">NOME DO PACOTE</label>
                    <input type="text" name="package_name" id="edit_package_name" required class="w-full px-4 py-3 rounded-lg bg-[#FBF9F6] border border-[#E8E3DA] text-[#4A4238]">
                </div>
                
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-[10px] uppercase tracking-[0.1em] text-[#8B7355] mb-2 font-semibold">SERVIÇO BASE</label>
                        <select name="package_service_id" id="edit_package_service_id" onchange="updatePackageCalculations('edit')" required class="w-full px-4 py-3 rounded-lg bg-[#FBF9F6] border border-[#E8E3DA] text-[#4A4238]">
                            <?php foreach ($services as $svc): ?>
                            <option value="<?php echo $svc['id']; ?>" data-price="<?php echo $svc['price']; ?>"><?php echo htmlspecialchars($svc['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[10px] uppercase tracking-[0.1em] text-[#8B7355] mb-2 font-semibold">QTD SESSÕES</label>
                        <input type="number" name="package_sessions" id="edit_package_sessions" oninput="updatePackageCalculations('edit')" required min="2" class="w-full px-4 py-3 rounded-lg bg-[#FBF9F6] border border-[#E8E3DA] text-[#4A4238]">
                    </div>
                </div>
                
                <div>
                     <div class="flex justify-between items-center mb-2">
                        <label class="block text-[10px] uppercase tracking-[0.1em] text-[#8B7355] font-semibold">PREÇO DO PACOTE</label>
                        <span class="text-xs text-[#8B7355]">Preço Unit.: <span id="edit_package_unit_price" class="font-bold">R$ 0,00</span></span>
                    </div>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-[#C9B896]">R$</span>
                        <input type="number" step="0.01" name="package_price" id="edit_package_price" oninput="updatePackageCalculations('edit')" required class="w-full pl-10 pr-4 py-3 rounded-lg bg-[#FBF9F6] border border-[#E8E3DA] text-[#4A4238]">
                    </div>
                     <div class="mt-1 flex justify-between text-xs">
                        <span class="text-[#8B7355]">Total Original: <span id="edit_package_original_total" class="line-through">R$ 0,00</span></span>
                        <span class="text-[#5B7355] font-bold" id="edit_package_savings">Economia: 0%</span>
                    </div>
                </div>

                <div>
                    <label class="block text-[10px] uppercase tracking-[0.1em] text-[#8B7355] mb-2 font-semibold">DESCRIÇÃO</label>
                    <textarea name="package_description" id="edit_package_description" rows="3" class="w-full px-4 py-3 rounded-lg bg-[#FBF9F6] border border-[#E8E3DA] text-[#4A4238] resize-none"></textarea>
                </div>
                
                <div class="flex items-center gap-4 py-2">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="is_featured" id="edit_package_is_featured" value="1" class="w-4 h-4 text-[#C9B896] border-[#E8E3DA] rounded">
                        <span class="text-sm text-[#4A4238]">Destacar na Landing Page</span>
                    </label>
                </div>
            </div>
            
            <div class="pt-4 flex gap-2">
                <button type="button" onclick="closeModal('modalEditPackage')" class="flex-1 py-3 rounded-lg bg-[#F5F2ED] text-[#4A4238] hover:bg-[#E8E3DA] font-medium transition-all">Cancelar</button>
                <button type="submit" class="flex-1 py-3 rounded-lg bg-[#4A4238] text-white hover:bg-[#3d362e] font-medium transition-all">Atualizar</button>
            </div>
        </form>
    </div>
</dialog>

<!-- Modal: Editar Combo -->
<dialog id="modalEditCombo" class="p-0 rounded-2xl shadow-2xl w-full max-w-md backdrop:bg-[#4A4238]/20">
    <div class="w-full">
        <div class="bg-[#F5F2ED] px-6 py-4 flex justify-between items-center border-b border-[#E8E3DA]">
            <h2 class="font-serif text-xl text-[#4A4238]">Editar Combo</h2>
            <button onclick="closeModal('modalEditCombo')" class="p-1 hover:bg-[#E8E3DA] rounded-full transition-colors">
                <i data-lucide="x" class="w-5 h-5 text-[#8B7355]"></i>
            </button>
        </div>
        
        <form method="POST" id="formEditCombo" class="p-6 space-y-4 bg-white">
            <input type="hidden" name="action" value="edit_combo">
            <input type="hidden" name="id" id="edit_combo_id">
            <input type="hidden" name="original_price" id="edit_combo_original_price_input" value="0">
            
            <div class="grid grid-cols-1 gap-4">
                <div>
                    <label class="block text-[10px] uppercase tracking-[0.1em] text-[#8B7355] mb-2 font-semibold">NOME DO COMBO</label>
                    <input type="text" name="combo_name" id="edit_combo_name" required class="w-full px-4 py-3 rounded-lg bg-[#FBF9F6] border border-[#E8E3DA] text-[#4A4238]">
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-[10px] uppercase tracking-[0.1em] text-[#8B7355] mb-2 font-semibold">CATEGORIA</label>
                        <select name="combo_category" id="edit_combo_category" class="w-full px-4 py-3 rounded-lg bg-[#FBF9F6] border border-[#E8E3DA] text-[#4A4238]">
                            <option value="CORPO">Corporal</option>
                            <option value="FACE">Facial</option>
                            <option value="OUTRO">Outro</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[10px] uppercase tracking-[0.1em] text-[#8B7355] mb-2 font-semibold">DURAÇÃO (MIN)</label>
                        <input type="number" name="combo_duration" id="edit_combo_duration" required class="w-full px-4 py-3 rounded-lg bg-[#FBF9F6] border border-[#E8E3DA] text-[#4A4238]">
                    </div>
                </div>
                
                <div>
                    <label class="block text-[10px] uppercase tracking-[0.1em] text-[#8B7355] mb-2 font-semibold">SERVIÇOS INCLUSOS:</label>
                    <div class="bg-[#FBF9F6] border border-[#E8E3DA] rounded-lg p-4 max-h-40 overflow-y-auto">
                        <?php foreach ($services as $svc): ?>
                        <label class="flex items-center gap-2 py-2 hover:bg-white/50 px-2 rounded cursor-pointer">
                            <input type="checkbox" name="combo_services[]" value="<?php echo $svc['id']; ?>" 
                                   data-price="<?php echo $svc['price']; ?>"
                                   id="edit_combo_svc_<?php echo $svc['id']; ?>"
                                   onchange="updateComboCalculations('edit')"
                                   class="w-4 h-4 text-[#C9B896] border-[#E8E3DA] rounded">
                            <span class="text-sm text-[#4A4238]"><?php echo htmlspecialchars($svc['name']); ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                    <div class="mt-2 flex justify-end">
                        <span class="text-sm text-[#8B7355]">Valor Original: <span id="edit_combo_original_price_display" class="font-semibold text-[#4A4238]">R$ 0,00</span></span>
                    </div>
                </div>
                
                <div>
                    <label class="block text-[10px] uppercase tracking-[0.1em] text-[#8B7355] mb-2 font-semibold">VALOR PROMOCIONAL</label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-[#C9B896]">R$</span>
                        <input type="number" step="0.01" name="combo_price" id="edit_combo_price" required class="w-full pl-10 pr-4 py-3 rounded-lg bg-[#FBF9F6] border border-[#E8E3DA] text-[#4A4238]">
                    </div>
                </div>
                <div>
                    <label class="block text-[10px] uppercase tracking-[0.1em] text-[#8B7355] mb-2 font-semibold">DESCRIÇÃO</label>
                    <textarea name="combo_description" id="edit_combo_description" rows="3" class="w-full px-4 py-3 rounded-lg bg-[#FBF9F6] border border-[#E8E3DA] text-[#4A4238] resize-none"></textarea>
                </div>
                <div class="flex items-center gap-4 py-2">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="is_featured" id="edit_combo_is_featured" value="1" class="w-4 h-4 text-[#C9B896] border-[#E8E3DA] rounded">
                        <span class="text-sm text-[#4A4238]">Destacar na Landing Page</span>
                    </label>
                </div>
            </div>
            
            <div class="pt-4 flex gap-2">
                <button type="button" onclick="closeModal('modalEditCombo')" class="flex-1 py-3 rounded-lg bg-[#F5F2ED] text-[#4A4238] hover:bg-[#E8E3DA] font-medium transition-all">Cancelar</button>
                <button type="submit" class="flex-1 py-3 rounded-lg bg-[#4A4238] text-white hover:bg-[#3d362e] font-medium transition-all">Atualizar</button>
            </div>
        </form>
    </div>
</dialog>

<?php renderFooter(); ?>
