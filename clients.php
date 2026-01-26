<?php
require_once 'db.php';
require_once 'layout.php';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create') {
        $stmt = $pdo->prepare("INSERT INTO clients (name, phone, notes) VALUES (?, ?, ?)");
        $stmt->execute([$_POST['name'], $_POST['phone'], $_POST['notes'] ?? '']);
        header("Location: clients.php");
        exit;
    }
    
    if ($action === 'update') {
        $stmt = $pdo->prepare("UPDATE clients SET name = ?, phone = ?, notes = ? WHERE id = ?");
        $stmt->execute([$_POST['name'], $_POST['phone'], $_POST['notes'] ?? '', $_POST['id']]);
        header("Location: clients.php");
        exit;
    }
    
    if ($action === 'delete') {
        $pdo->prepare("DELETE FROM clients WHERE id = ?")->execute([$_POST['id']]);
        header("Location: clients.php");
        exit;
    }
    
    if ($action === 'save_anamnese') {
        // Save anamnese data in notes field as JSON
        $anamnese = json_encode([
            'alergias' => $_POST['alergias'] ?? '',
            'medicamentos' => $_POST['medicamentos'] ?? '',
            'problemas_pele' => $_POST['problemas_pele'] ?? '',
            'tratamentos_anteriores' => $_POST['tratamentos_anteriores'] ?? '',
            'observacoes' => $_POST['observacoes'] ?? '',
            'updated_at' => date('Y-m-d H:i:s')
        ]);
        $stmt = $pdo->prepare("UPDATE clients SET notes = ? WHERE id = ?");
        $stmt->execute([$anamnese, $_POST['client_id']]);
        header("Location: clients.php?ficha=" . $_POST['client_id']);
        exit;
    }
    
    if ($action === 'upload_image') {
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $imageData = base64_encode(file_get_contents($_FILES['image']['tmp_name']));
            $mimeType = $_FILES['image']['type'];
            $fullData = "data:{$mimeType};base64,{$imageData}";
            
            $stmt = $pdo->prepare("INSERT INTO client_images (client_id, image_data, tag, notes) VALUES (?, ?, ?, ?)");
            $stmt->execute([$_POST['client_id'], $fullData, $_POST['tag'], $_POST['image_notes'] ?? '']);
        }
        header("Location: clients.php?galeria=" . $_POST['client_id']);
        exit;
    }
    
    if ($action === 'delete_image') {
        $pdo->prepare("DELETE FROM client_images WHERE id = ?")->execute([$_POST['image_id']]);
        header("Location: clients.php?galeria=" . $_POST['client_id']);
        exit;
    }
}

// Fetch clients
$search = $_GET['search'] ?? '';
$sql = "SELECT * FROM clients WHERE name LIKE ? ORDER BY name ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute(["%$search%"]);
$clients = $stmt->fetchAll();

// Check if viewing ficha or galeria
$viewingFicha = isset($_GET['ficha']) ? (int)$_GET['ficha'] : null;
$viewingGaleria = isset($_GET['galeria']) ? (int)$_GET['galeria'] : null;

// Fetch client data if viewing ficha/galeria
$selectedClient = null;
$clientImages = [];
if ($viewingFicha || $viewingGaleria) {
    $clientId = $viewingFicha ?: $viewingGaleria;
    $selectedClient = $pdo->query("SELECT * FROM clients WHERE id = $clientId")->fetch();
    
    if ($viewingGaleria) {
        $stmt = $pdo->prepare("SELECT * FROM client_images WHERE client_id = ? ORDER BY created_at DESC");
        $stmt->execute([$clientId]);
        $clientImages = $stmt->fetchAll();
    }
}

renderHeader("Clientes - Essenza");
renderSidebar('Clientes');
?>

<div class="max-w-6xl mx-auto space-y-6 animate-fade-in">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:justify-between md:items-end gap-4">
        <div>
            <h2 class="font-serif text-2xl md:text-3xl text-charcoal mb-1">Clientes</h2>
            <p class="text-charcoal-light text-sm"><?php echo count($clients); ?> clientes cadastrados</p>
        </div>
        <button onclick="openModal('modalClient')" class="bg-charcoal text-white px-5 py-3 rounded-xl shadow-lg hover:shadow-xl flex items-center justify-center gap-2 transition-all">
            <i data-lucide="user-plus" class="w-4 h-4"></i> Novo Cliente
        </button>
    </div>

    <!-- Search -->
    <form class="relative">
        <i data-lucide="search" class="absolute left-4 top-3.5 text-charcoal-light w-5 h-5"></i>
        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Buscar por nome..." 
               class="w-full pl-12 pr-4 py-3 border border-sand rounded-xl bg-white focus:ring-2 focus:ring-sage focus:border-sage transition-all">
    </form>

    <!-- Clients Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        <?php foreach($clients as $c): 
            $initials = strtoupper(substr($c['name'], 0, 1));
            $anamnese = json_decode($c['notes'] ?? '{}', true);
            $hasAnamnese = is_array($anamnese) && !empty($anamnese['alergias'] ?? $anamnese['medicamentos'] ?? $anamnese['problemas_pele'] ?? '');
        ?>
        <div class="bg-white p-5 rounded-2xl border border-sand hover:border-sage hover:shadow-md transition-all group relative">
            <!-- Delete Button -->
            <form method="POST" onsubmit="return confirm('Excluir este cliente?')" class="absolute top-4 right-4 opacity-0 group-hover:opacity-100 transition-opacity">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?php echo $c['id']; ?>">
                <button type="submit" class="p-2 rounded-lg hover:bg-red-50 text-charcoal-light hover:text-red-500 transition-colors">
                    <i data-lucide="trash-2" class="w-4 h-4"></i>
                </button>
            </form>
            
            <!-- Client Info -->
            <div class="flex items-center gap-4 mb-4">
                <div class="w-14 h-14 rounded-full bg-gradient-to-br from-sage/30 to-gold/30 border-2 border-white shadow-md flex items-center justify-center font-serif text-xl text-charcoal">
                    <?php echo $initials; ?>
                </div>
                <div class="flex-1">
                    <h3 class="font-serif text-lg text-charcoal"><?php echo htmlspecialchars($c['name']); ?></h3>
                    <a href="tel:<?php echo $c['phone']; ?>" class="text-sm text-charcoal-light flex items-center gap-1 hover:text-sage transition-colors">
                        <i data-lucide="phone" class="w-3 h-3"></i> <?php echo htmlspecialchars($c['phone']); ?>
                    </a>
                </div>
                <?php if ($hasAnamnese): ?>
                <span class="px-2 py-1 bg-sage/20 text-sage text-[10px] font-bold rounded-full uppercase">Ficha</span>
                <?php endif; ?>
            </div>
            
            <!-- Action Buttons -->
            <div class="grid grid-cols-2 gap-2 pt-4 border-t border-sand">
                <a href="?ficha=<?php echo $c['id']; ?>" class="bg-ivory hover:bg-sand text-charcoal text-xs py-3 rounded-xl flex items-center justify-center gap-2 transition-all">
                    <i data-lucide="clipboard-list" class="w-4 h-4"></i> Ficha
                </a>
                <a href="?galeria=<?php echo $c['id']; ?>" class="bg-gold/10 hover:bg-gold text-gold-dark hover:text-white text-xs py-3 rounded-xl flex items-center justify-center gap-2 transition-all">
                    <i data-lucide="images" class="w-4 h-4"></i> Galeria
                </a>
            </div>
        </div>
        <?php endforeach; ?>
        
        <?php if (empty($clients)): ?>
        <div class="col-span-full bg-white rounded-2xl border border-sand p-12 text-center">
            <div class="w-16 h-16 bg-sand rounded-full flex items-center justify-center mx-auto mb-4">
                <i data-lucide="users" class="w-8 h-8 text-charcoal-light"></i>
            </div>
            <h3 class="font-serif text-xl text-charcoal mb-2">Nenhum cliente encontrado</h3>
            <p class="text-charcoal-light text-sm mb-4">Comece adicionando seu primeiro cliente</p>
            <button onclick="openModal('modalClient')" class="bg-charcoal text-white px-5 py-2 rounded-lg inline-flex items-center gap-2">
                <i data-lucide="plus" class="w-4 h-4"></i> Adicionar Cliente
            </button>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal: Novo Cliente -->
<dialog id="modalClient" class="p-0 rounded-2xl shadow-2xl w-full max-w-md backdrop:bg-black/30 border-0">
    <div class="w-full bg-white rounded-2xl overflow-hidden">
        <div class="px-6 py-5 bg-gradient-to-r from-sage/20 to-ivory flex justify-between items-center border-b border-sand">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-sage/30 rounded-xl flex items-center justify-center">
                    <i data-lucide="user-plus" class="w-5 h-5 text-sage-dark"></i>
                </div>
                <h2 class="font-serif text-xl text-charcoal">Novo Cliente</h2>
            </div>
            <button onclick="closeModal('modalClient')" class="p-2 hover:bg-white/50 rounded-lg transition-colors">
                <i data-lucide="x" class="w-5 h-5 text-charcoal-light"></i>
            </button>
        </div>
        <form method="POST" class="p-6 space-y-5">
            <input type="hidden" name="action" value="create">
            
            <div>
                <label class="block text-xs uppercase tracking-wider text-charcoal-light mb-2 font-semibold">Nome Completo</label>
                <div class="relative">
                    <i data-lucide="user" class="absolute left-3 top-3 w-5 h-5 text-charcoal-light/50"></i>
                    <input type="text" name="name" required 
                           class="w-full pl-11 pr-4 py-3 bg-ivory border border-sand rounded-xl text-charcoal placeholder-charcoal-light/50 focus:ring-2 focus:ring-sage focus:border-sage transition-all"
                           placeholder="Nome do cliente">
                </div>
            </div>
            
            <div>
                <label class="block text-xs uppercase tracking-wider text-charcoal-light mb-2 font-semibold">Telefone</label>
                <div class="relative">
                    <i data-lucide="phone" class="absolute left-3 top-3 w-5 h-5 text-charcoal-light/50"></i>
                    <input type="tel" name="phone" required 
                           class="w-full pl-11 pr-4 py-3 bg-ivory border border-sand rounded-xl text-charcoal placeholder-charcoal-light/50 focus:ring-2 focus:ring-sage focus:border-sage transition-all"
                           placeholder="(11) 99999-9999">
                </div>
            </div>
            
            <div class="flex gap-3 pt-2">
                <button type="button" onclick="closeModal('modalClient')" class="flex-1 py-3 rounded-xl border border-sand text-charcoal hover:bg-sand transition-all font-medium">
                    Cancelar
                </button>
                <button type="submit" class="flex-1 py-3 rounded-xl bg-charcoal text-white hover:bg-charcoal/90 transition-all font-medium flex items-center justify-center gap-2">
                    <i data-lucide="check" class="w-4 h-4"></i> Cadastrar
                </button>
            </div>
        </form>
    </div>
</dialog>

<!-- Modal: Ficha de Anamnese -->
<?php if ($viewingFicha && $selectedClient): 
    $anamnese = json_decode($selectedClient['notes'] ?? '{}', true);
    if (!is_array($anamnese)) $anamnese = [];
?>
<dialog id="modalFicha" class="p-0 rounded-2xl shadow-2xl w-full max-w-2xl backdrop:bg-black/30 border-0" open>
    <div class="w-full bg-white rounded-2xl overflow-hidden max-h-[90vh] flex flex-col">
        <div class="px-6 py-5 bg-gradient-to-r from-blue-50 to-ivory flex justify-between items-center border-b border-sand flex-shrink-0">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-blue-100 rounded-xl flex items-center justify-center">
                    <i data-lucide="clipboard-list" class="w-5 h-5 text-blue-600"></i>
                </div>
                <div>
                    <h2 class="font-serif text-xl text-charcoal">Ficha de Anamnese</h2>
                    <p class="text-sm text-charcoal-light"><?php echo htmlspecialchars($selectedClient['name']); ?></p>
                </div>
            </div>
            <a href="clients.php" class="p-2 hover:bg-white/50 rounded-lg transition-colors">
                <i data-lucide="x" class="w-5 h-5 text-charcoal-light"></i>
            </a>
        </div>
        <form method="POST" class="p-6 space-y-5 overflow-y-auto">
            <input type="hidden" name="action" value="save_anamnese">
            <input type="hidden" name="client_id" value="<?php echo $selectedClient['id']; ?>">
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div>
                    <label class="block text-xs uppercase tracking-wider text-charcoal-light mb-2 font-semibold">Alergias</label>
                    <textarea name="alergias" rows="3" 
                              class="w-full px-4 py-3 bg-ivory border border-sand rounded-xl text-charcoal placeholder-charcoal-light/50 focus:ring-2 focus:ring-blue-300 focus:border-blue-300 transition-all"
                              placeholder="Liste alergias conhecidas..."><?php echo htmlspecialchars($anamnese['alergias'] ?? ''); ?></textarea>
                </div>
                <div>
                    <label class="block text-xs uppercase tracking-wider text-charcoal-light mb-2 font-semibold">Medicamentos em Uso</label>
                    <textarea name="medicamentos" rows="3" 
                              class="w-full px-4 py-3 bg-ivory border border-sand rounded-xl text-charcoal placeholder-charcoal-light/50 focus:ring-2 focus:ring-blue-300 focus:border-blue-300 transition-all"
                              placeholder="Medicamentos que está tomando..."><?php echo htmlspecialchars($anamnese['medicamentos'] ?? ''); ?></textarea>
                </div>
            </div>
            
            <div>
                <label class="block text-xs uppercase tracking-wider text-charcoal-light mb-2 font-semibold">Problemas de Pele / Condições</label>
                <textarea name="problemas_pele" rows="3" 
                          class="w-full px-4 py-3 bg-ivory border border-sand rounded-xl text-charcoal placeholder-charcoal-light/50 focus:ring-2 focus:ring-blue-300 focus:border-blue-300 transition-all"
                          placeholder="Acne, rosácea, manchas, sensibilidade..."><?php echo htmlspecialchars($anamnese['problemas_pele'] ?? ''); ?></textarea>
            </div>
            
            <div>
                <label class="block text-xs uppercase tracking-wider text-charcoal-light mb-2 font-semibold">Tratamentos Anteriores</label>
                <textarea name="tratamentos_anteriores" rows="3" 
                          class="w-full px-4 py-3 bg-ivory border border-sand rounded-xl text-charcoal placeholder-charcoal-light/50 focus:ring-2 focus:ring-blue-300 focus:border-blue-300 transition-all"
                          placeholder="Tratamentos estéticos realizados anteriormente..."><?php echo htmlspecialchars($anamnese['tratamentos_anteriores'] ?? ''); ?></textarea>
            </div>
            
            <div>
                <label class="block text-xs uppercase tracking-wider text-charcoal-light mb-2 font-semibold">Observações Gerais</label>
                <textarea name="observacoes" rows="3" 
                          class="w-full px-4 py-3 bg-ivory border border-sand rounded-xl text-charcoal placeholder-charcoal-light/50 focus:ring-2 focus:ring-blue-300 focus:border-blue-300 transition-all"
                          placeholder="Outras informações importantes..."><?php echo htmlspecialchars($anamnese['observacoes'] ?? ''); ?></textarea>
            </div>
            
            <?php if (!empty($anamnese['updated_at'])): ?>
            <p class="text-xs text-charcoal-light text-right">
                <i data-lucide="clock" class="w-3 h-3 inline"></i> Última atualização: <?php echo date('d/m/Y H:i', strtotime($anamnese['updated_at'])); ?>
            </p>
            <?php endif; ?>
            
            <div class="flex gap-3 pt-2">
                <a href="clients.php" class="flex-1 py-3 rounded-xl border border-sand text-charcoal hover:bg-sand transition-all font-medium text-center">
                    Voltar
                </a>
                <button type="submit" class="flex-1 py-3 rounded-xl bg-blue-600 text-white hover:bg-blue-700 transition-all font-medium flex items-center justify-center gap-2">
                    <i data-lucide="save" class="w-4 h-4"></i> Salvar Ficha
                </button>
            </div>
        </form>
    </div>
</dialog>
<?php endif; ?>

<!-- Modal: Galeria de Imagens -->
<?php if ($viewingGaleria && $selectedClient): ?>
<dialog id="modalGaleria" class="p-0 rounded-2xl shadow-2xl w-full max-w-4xl backdrop:bg-black/30 border-0" open>
    <div class="w-full bg-white rounded-2xl overflow-hidden max-h-[90vh] flex flex-col">
        <div class="px-6 py-5 bg-gradient-to-r from-gold/20 to-ivory flex justify-between items-center border-b border-sand flex-shrink-0">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-gold/30 rounded-xl flex items-center justify-center">
                    <i data-lucide="images" class="w-5 h-5 text-gold-dark"></i>
                </div>
                <div>
                    <h2 class="font-serif text-xl text-charcoal">Galeria Antes & Depois</h2>
                    <p class="text-sm text-charcoal-light"><?php echo htmlspecialchars($selectedClient['name']); ?></p>
                </div>
            </div>
            <a href="clients.php" class="p-2 hover:bg-white/50 rounded-lg transition-colors">
                <i data-lucide="x" class="w-5 h-5 text-charcoal-light"></i>
            </a>
        </div>
        
        <div class="p-6 overflow-y-auto">
            <!-- Upload Form -->
            <form method="POST" enctype="multipart/form-data" class="bg-ivory rounded-xl p-4 mb-6 border border-sand">
                <input type="hidden" name="action" value="upload_image">
                <input type="hidden" name="client_id" value="<?php echo $selectedClient['id']; ?>">
                
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                    <div class="md:col-span-2">
                        <label class="block text-xs uppercase tracking-wider text-charcoal-light mb-2 font-semibold">Selecionar Imagem</label>
                        <input type="file" name="image" accept="image/*" required
                               class="w-full text-sm text-charcoal file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-white file:text-charcoal hover:file:bg-sand cursor-pointer">
                    </div>
                    <div>
                        <label class="block text-xs uppercase tracking-wider text-charcoal-light mb-2 font-semibold">Tipo</label>
                        <select name="tag" required class="w-full px-4 py-2.5 bg-white border border-sand rounded-xl text-charcoal focus:ring-2 focus:ring-gold focus:border-gold transition-all">
                            <option value="Antes">Antes</option>
                            <option value="Depois">Depois</option>
                            <option value="Progresso">Progresso</option>
                        </select>
                    </div>
                    <button type="submit" class="py-2.5 px-4 rounded-xl bg-gold text-white hover:bg-gold-dark transition-all font-medium flex items-center justify-center gap-2">
                        <i data-lucide="upload" class="w-4 h-4"></i> Enviar
                    </button>
                </div>
            </form>
            
            <!-- Images Grid -->
            <?php if (empty($clientImages)): ?>
            <div class="text-center py-12">
                <div class="w-16 h-16 bg-sand rounded-full flex items-center justify-center mx-auto mb-4">
                    <i data-lucide="image-off" class="w-8 h-8 text-charcoal-light"></i>
                </div>
                <h3 class="font-serif text-lg text-charcoal mb-2">Nenhuma imagem ainda</h3>
                <p class="text-charcoal-light text-sm">Envie a primeira foto para começar o histórico visual</p>
            </div>
            <?php else: ?>
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                <?php foreach ($clientImages as $img): ?>
                <div class="relative group rounded-xl overflow-hidden border border-sand bg-ivory">
                    <img src="<?php echo $img['image_data']; ?>" alt="<?php echo $img['tag']; ?>" class="w-full h-40 object-cover">
                    <div class="absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                        <form method="POST" class="inline" onsubmit="return confirm('Excluir esta imagem?')">
                            <input type="hidden" name="action" value="delete_image">
                            <input type="hidden" name="image_id" value="<?php echo $img['id']; ?>">
                            <input type="hidden" name="client_id" value="<?php echo $selectedClient['id']; ?>">
                            <button type="submit" class="p-2 bg-red-500 text-white rounded-lg hover:bg-red-600 transition-colors">
                                <i data-lucide="trash-2" class="w-4 h-4"></i>
                            </button>
                        </form>
                    </div>
                    <div class="p-2">
                        <span class="inline-block px-2 py-0.5 rounded-full text-[10px] font-bold uppercase
                            <?php 
                                if ($img['tag'] === 'Antes') echo 'bg-amber-100 text-amber-700';
                                elseif ($img['tag'] === 'Depois') echo 'bg-emerald-100 text-emerald-700';
                                else echo 'bg-blue-100 text-blue-700';
                            ?>">
                            <?php echo $img['tag']; ?>
                        </span>
                        <p class="text-[10px] text-charcoal-light mt-1"><?php echo date('d/m/Y', strtotime($img['created_at'])); ?></p>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        
        <div class="px-6 py-4 border-t border-sand flex-shrink-0">
            <a href="clients.php" class="w-full py-3 rounded-xl border border-sand text-charcoal hover:bg-sand transition-all font-medium text-center block">
                Voltar para Clientes
            </a>
        </div>
    </div>
</dialog>
<?php endif; ?>

<?php renderFooter(); ?>