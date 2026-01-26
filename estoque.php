<?php
require_once 'db.php';
require_once 'layout.php';

// --- HANDLE ACTIONS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $stmt = $pdo->prepare("INSERT INTO inventory (name, category, quantity, min_quantity, price, unit) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$_POST['name'], $_POST['category'], $_POST['quantity'], $_POST['min_quantity'], $_POST['price'], $_POST['unit']]);
    } elseif ($action === 'edit') {
        $stmt = $pdo->prepare("UPDATE inventory SET name=?, category=?, quantity=?, min_quantity=?, price=?, unit=? WHERE id=?");
        $stmt->execute([$_POST['name'], $_POST['category'], $_POST['quantity'], $_POST['min_quantity'], $_POST['price'], $_POST['unit'], $_POST['id']]);
    } elseif ($action === 'delete') {
        $stmt = $pdo->prepare("DELETE FROM inventory WHERE id=?");
        $stmt->execute([$_POST['id']]);
    } elseif ($action === 'update_qty') {
        $stmt = $pdo->prepare("UPDATE inventory SET quantity = quantity + ? WHERE id = ?");
        $stmt->execute([$_POST['delta'], $_POST['id']]);
        echo json_encode(['success' => true]);
        exit;
    }
    
    header("Location: estoque.php");
    exit;
}

// --- LOGIC ---
$stmt = $pdo->query("SELECT * FROM inventory ORDER BY (quantity <= min_quantity) DESC, name ASC");
$items = $stmt->fetchAll();

// Stats
$totalItems = count($items);
$lowStockItems = count(array_filter($items, fn($i) => $i['quantity'] <= $i['min_quantity']));
$totalValue = array_sum(array_map(fn($i) => $i['quantity'] * $i['price'], $items));

// Group by category
$categories = [];
foreach ($items as $item) {
    $cat = $item['category'] ?: 'Sem Categoria';
    $categories[$cat][] = $item;
}

// --- VIEW ---
renderHeader("Estoque - Essenza");
renderSidebar('Estoque');
?>

<div class="max-w-7xl mx-auto space-y-6 animate-fade-in">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:justify-between md:items-end gap-4">
        <div>
            <h2 class="font-serif text-2xl md:text-3xl text-charcoal mb-1">Gestão de Estoque</h2>
            <p class="text-charcoal-light text-sm">Controle seus produtos e insumos</p>
        </div>
        <button onclick="openModal('modalAdd')" class="bg-charcoal text-white px-5 py-3 rounded-xl shadow-lg hover:shadow-xl flex items-center justify-center gap-2 transition-all">
            <i data-lucide="plus" class="w-4 h-4"></i> Novo Produto
        </button>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="bg-white p-5 rounded-2xl border border-sand shadow-sm">
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 bg-sage/20 rounded-xl flex items-center justify-center">
                    <i data-lucide="package" class="w-6 h-6 text-sage"></i>
                </div>
                <div>
                    <p class="text-xs text-charcoal-light uppercase tracking-wider font-medium">Total de Itens</p>
                    <p class="text-2xl font-serif text-charcoal"><?php echo $totalItems; ?></p>
                </div>
            </div>
        </div>
        <div class="bg-white p-5 rounded-2xl border border-sand shadow-sm">
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 bg-red-100 rounded-xl flex items-center justify-center">
                    <i data-lucide="alert-triangle" class="w-6 h-6 text-red-500"></i>
                </div>
                <div>
                    <p class="text-xs text-charcoal-light uppercase tracking-wider font-medium">Estoque Baixo</p>
                    <p class="text-2xl font-serif <?php echo $lowStockItems > 0 ? 'text-red-500' : 'text-charcoal'; ?>"><?php echo $lowStockItems; ?></p>
                </div>
            </div>
        </div>
        <div class="bg-white p-5 rounded-2xl border border-sand shadow-sm">
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 bg-gold/20 rounded-xl flex items-center justify-center">
                    <i data-lucide="coins" class="w-6 h-6 text-gold-dark"></i>
                </div>
                <div>
                    <p class="text-xs text-charcoal-light uppercase tracking-wider font-medium">Valor em Estoque</p>
                    <p class="text-2xl font-serif text-charcoal">R$ <?php echo number_format($totalValue, 2, ',', '.'); ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Items by Category -->
    <?php foreach ($categories as $catName => $catItems): ?>
    <div class="bg-white rounded-2xl border border-sand shadow-sm overflow-hidden">
        <div class="px-6 py-4 bg-gradient-to-r from-ivory to-white border-b border-sand flex items-center gap-3">
            <div class="w-8 h-8 bg-sage/20 rounded-lg flex items-center justify-center">
                <i data-lucide="folder" class="w-4 h-4 text-sage"></i>
            </div>
            <h3 class="font-serif text-lg text-charcoal"><?php echo htmlspecialchars($catName); ?></h3>
            <span class="text-xs bg-sand px-2 py-1 rounded-full text-charcoal-light"><?php echo count($catItems); ?> itens</span>
        </div>
        
        <div class="divide-y divide-sand/50">
            <?php foreach ($catItems as $item): 
                $lowStock = $item['quantity'] <= $item['min_quantity'];
                $percentStock = $item['min_quantity'] > 0 ? min(100, ($item['quantity'] / ($item['min_quantity'] * 2)) * 100) : 100;
            ?>
            <div class="px-6 py-4 flex flex-col md:flex-row md:items-center justify-between gap-4 hover:bg-ivory/50 transition-colors group">
                <!-- Item Info -->
                <div class="flex items-center gap-4 flex-1">
                    <div class="w-12 h-12 rounded-xl flex items-center justify-center <?php echo $lowStock ? 'bg-red-100' : 'bg-sage/10'; ?>">
                        <i data-lucide="<?php echo $lowStock ? 'alert-circle' : 'box'; ?>" class="w-6 h-6 <?php echo $lowStock ? 'text-red-500' : 'text-sage'; ?>"></i>
                    </div>
                    <div class="flex-1">
                        <div class="flex items-center gap-2">
                            <h4 class="font-medium text-charcoal"><?php echo htmlspecialchars($item['name']); ?></h4>
                            <?php if ($lowStock): ?>
                            <span class="px-2 py-0.5 bg-red-100 text-red-600 text-[10px] font-bold uppercase rounded-full animate-pulse">Repor!</span>
                            <?php endif; ?>
                        </div>
                        <div class="flex items-center gap-4 mt-1">
                            <span class="text-xs text-charcoal-light">R$ <?php echo number_format($item['price'] ?? 0, 2, ',', '.'); ?>/<?php echo $item['unit']; ?></span>
                            <!-- Progress bar -->
                            <div class="hidden md:flex items-center gap-2 flex-1 max-w-[200px]">
                                <div class="flex-1 h-2 bg-sand rounded-full overflow-hidden">
                                    <div class="h-full rounded-full transition-all <?php echo $lowStock ? 'bg-red-400' : 'bg-sage'; ?>" style="width: <?php echo $percentStock; ?>%"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Quantity Controls -->
                <div class="flex items-center gap-4">
                    <div class="flex items-center gap-2 bg-ivory rounded-xl px-3 py-2">
                        <button onclick="updateQty(<?php echo $item['id']; ?>, -1)" class="w-8 h-8 rounded-lg bg-white border border-sand hover:border-red-300 hover:bg-red-50 transition-all flex items-center justify-center shadow-sm">
                            <i data-lucide="minus" class="w-4 h-4 text-charcoal"></i>
                        </button>
                        <div class="text-center min-w-[60px]">
                            <span id="qty-<?php echo $item['id']; ?>" class="text-xl font-serif <?php echo $lowStock ? 'text-red-600' : 'text-charcoal'; ?>"><?php echo $item['quantity']; ?></span>
                            <span class="text-xs text-charcoal-light ml-1"><?php echo $item['unit']; ?></span>
                        </div>
                        <button onclick="updateQty(<?php echo $item['id']; ?>, 1)" class="w-8 h-8 rounded-lg bg-white border border-sand hover:border-sage hover:bg-sage/10 transition-all flex items-center justify-center shadow-sm">
                            <i data-lucide="plus" class="w-4 h-4 text-charcoal"></i>
                        </button>
                    </div>
                    
                    <div class="text-xs text-charcoal-light text-center hidden sm:block">
                        <span class="block">Mín</span>
                        <span class="font-medium"><?php echo $item['min_quantity']; ?></span>
                    </div>
                    
                    <!-- Actions -->
                    <div class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                        <button onclick='fillEditModal(<?php echo json_encode($item); ?>)' class="p-2 rounded-lg hover:bg-sand text-charcoal-light hover:text-charcoal transition-colors">
                            <i data-lucide="edit-3" class="w-4 h-4"></i>
                        </button>
                        <form method="POST" class="inline" onsubmit="return confirm('Excluir este item?');">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?php echo $item['id']; ?>">
                            <button type="submit" class="p-2 rounded-lg hover:bg-red-50 text-charcoal-light hover:text-red-500 transition-colors">
                                <i data-lucide="trash-2" class="w-4 h-4"></i>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endforeach; ?>
    
    <?php if (empty($items)): ?>
    <div class="bg-white rounded-2xl border border-sand p-12 text-center">
        <div class="w-16 h-16 bg-sand rounded-full flex items-center justify-center mx-auto mb-4">
            <i data-lucide="package-open" class="w-8 h-8 text-charcoal-light"></i>
        </div>
        <h3 class="font-serif text-xl text-charcoal mb-2">Nenhum item cadastrado</h3>
        <p class="text-charcoal-light text-sm mb-4">Comece adicionando produtos ao seu estoque</p>
        <button onclick="openModal('modalAdd')" class="bg-charcoal text-white px-5 py-2 rounded-lg inline-flex items-center gap-2">
            <i data-lucide="plus" class="w-4 h-4"></i> Adicionar Primeiro Item
        </button>
    </div>
    <?php endif; ?>
</div>

<!-- Modal: Adicionar -->
<dialog id="modalAdd" class="p-0 rounded-2xl shadow-2xl w-full max-w-md backdrop:bg-black/30 border-0">
    <div class="w-full bg-white rounded-2xl overflow-hidden">
        <div class="px-6 py-5 bg-gradient-to-r from-sage/20 to-ivory flex justify-between items-center border-b border-sand">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-sage/30 rounded-xl flex items-center justify-center">
                    <i data-lucide="package-plus" class="w-5 h-5 text-sage-dark"></i>
                </div>
                <h2 class="font-serif text-xl text-charcoal">Novo Produto</h2>
            </div>
            <button onclick="closeModal('modalAdd')" class="p-2 hover:bg-white/50 rounded-lg transition-colors">
                <i data-lucide="x" class="w-5 h-5 text-charcoal-light"></i>
            </button>
        </div>
        <form method="POST" class="p-6 space-y-5">
            <input type="hidden" name="action" value="add">
            
            <div>
                <label class="block text-xs uppercase tracking-wider text-charcoal-light mb-2 font-semibold">Nome do Produto</label>
                <input type="text" name="name" required 
                       class="w-full px-4 py-3 bg-ivory border border-sand rounded-xl text-charcoal placeholder-charcoal-light/50 focus:ring-2 focus:ring-sage focus:border-sage transition-all"
                       placeholder="Ex: Cera Depilatória">
            </div>
            
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs uppercase tracking-wider text-charcoal-light mb-2 font-semibold">Categoria</label>
                    <select name="category" class="w-full px-4 py-3 bg-ivory border border-sand rounded-xl text-charcoal focus:ring-2 focus:ring-sage focus:border-sage transition-all">
                        <option value="Insumos">Insumos</option>
                        <option value="Cosméticos">Cosméticos</option>
                        <option value="Equipamentos">Equipamentos</option>
                        <option value="Descartáveis">Descartáveis</option>
                        <option value="Outros">Outros</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs uppercase tracking-wider text-charcoal-light mb-2 font-semibold">Unidade</label>
                    <select name="unit" class="w-full px-4 py-3 bg-ivory border border-sand rounded-xl text-charcoal focus:ring-2 focus:ring-sage focus:border-sage transition-all">
                        <option value="un">Unidade (un)</option>
                        <option value="kg">Quilograma (kg)</option>
                        <option value="g">Grama (g)</option>
                        <option value="ml">Mililitro (ml)</option>
                        <option value="L">Litro (L)</option>
                        <option value="cx">Caixa (cx)</option>
                    </select>
                </div>
            </div>
            
            <div class="grid grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs uppercase tracking-wider text-charcoal-light mb-2 font-semibold">Quantidade</label>
                    <input type="number" name="quantity" required value="0" min="0"
                           class="w-full px-4 py-3 bg-ivory border border-sand rounded-xl text-charcoal text-center focus:ring-2 focus:ring-sage focus:border-sage transition-all">
                </div>
                <div>
                    <label class="block text-xs uppercase tracking-wider text-charcoal-light mb-2 font-semibold">Qtd Mínima</label>
                    <input type="number" name="min_quantity" required value="5" min="0"
                           class="w-full px-4 py-3 bg-ivory border border-sand rounded-xl text-charcoal text-center focus:ring-2 focus:ring-sage focus:border-sage transition-all">
                </div>
                <div>
                    <label class="block text-xs uppercase tracking-wider text-charcoal-light mb-2 font-semibold">Preço (R$)</label>
                    <input type="number" step="0.01" name="price" value="0.00" min="0"
                           class="w-full px-4 py-3 bg-ivory border border-sand rounded-xl text-charcoal text-center focus:ring-2 focus:ring-sage focus:border-sage transition-all">
                </div>
            </div>
            
            <div class="flex gap-3 pt-2">
                <button type="button" onclick="closeModal('modalAdd')" class="flex-1 py-3 rounded-xl border border-sand text-charcoal hover:bg-sand transition-all font-medium">
                    Cancelar
                </button>
                <button type="submit" class="flex-1 py-3 rounded-xl bg-charcoal text-white hover:bg-charcoal/90 transition-all font-medium flex items-center justify-center gap-2">
                    <i data-lucide="check" class="w-4 h-4"></i> Cadastrar
                </button>
            </div>
        </form>
    </div>
</dialog>

<!-- Modal: Editar -->
<dialog id="modalEdit" class="p-0 rounded-2xl shadow-2xl w-full max-w-md backdrop:bg-black/30 border-0">
    <div class="w-full bg-white rounded-2xl overflow-hidden">
        <div class="px-6 py-5 bg-gradient-to-r from-gold/20 to-ivory flex justify-between items-center border-b border-sand">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-gold/30 rounded-xl flex items-center justify-center">
                    <i data-lucide="edit-3" class="w-5 h-5 text-gold-dark"></i>
                </div>
                <h2 class="font-serif text-xl text-charcoal">Editar Produto</h2>
            </div>
            <button onclick="closeModal('modalEdit')" class="p-2 hover:bg-white/50 rounded-lg transition-colors">
                <i data-lucide="x" class="w-5 h-5 text-charcoal-light"></i>
            </button>
        </div>
        <form method="POST" id="formEdit" class="p-6 space-y-5">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" id="edit_id">
            
            <div>
                <label class="block text-xs uppercase tracking-wider text-charcoal-light mb-2 font-semibold">Nome do Produto</label>
                <input type="text" name="name" id="edit_name" required 
                       class="w-full px-4 py-3 bg-ivory border border-sand rounded-xl text-charcoal focus:ring-2 focus:ring-gold focus:border-gold transition-all">
            </div>
            
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs uppercase tracking-wider text-charcoal-light mb-2 font-semibold">Categoria</label>
                    <input type="text" name="category" id="edit_category"
                           class="w-full px-4 py-3 bg-ivory border border-sand rounded-xl text-charcoal focus:ring-2 focus:ring-gold focus:border-gold transition-all">
                </div>
                <div>
                    <label class="block text-xs uppercase tracking-wider text-charcoal-light mb-2 font-semibold">Unidade</label>
                    <input type="text" name="unit" id="edit_unit"
                           class="w-full px-4 py-3 bg-ivory border border-sand rounded-xl text-charcoal focus:ring-2 focus:ring-gold focus:border-gold transition-all">
                </div>
            </div>
            
            <div class="grid grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs uppercase tracking-wider text-charcoal-light mb-2 font-semibold">Quantidade</label>
                    <input type="number" name="quantity" id="edit_quantity" required
                           class="w-full px-4 py-3 bg-ivory border border-sand rounded-xl text-charcoal text-center focus:ring-2 focus:ring-gold focus:border-gold transition-all">
                </div>
                <div>
                    <label class="block text-xs uppercase tracking-wider text-charcoal-light mb-2 font-semibold">Qtd Mínima</label>
                    <input type="number" name="min_quantity" id="edit_min" required
                           class="w-full px-4 py-3 bg-ivory border border-sand rounded-xl text-charcoal text-center focus:ring-2 focus:ring-gold focus:border-gold transition-all">
                </div>
                <div>
                    <label class="block text-xs uppercase tracking-wider text-charcoal-light mb-2 font-semibold">Preço (R$)</label>
                    <input type="number" step="0.01" name="price" id="edit_price"
                           class="w-full px-4 py-3 bg-ivory border border-sand rounded-xl text-charcoal text-center focus:ring-2 focus:ring-gold focus:border-gold transition-all">
                </div>
            </div>
            
            <div class="flex gap-3 pt-2">
                <button type="button" onclick="closeModal('modalEdit')" class="flex-1 py-3 rounded-xl border border-sand text-charcoal hover:bg-sand transition-all font-medium">
                    Cancelar
                </button>
                <button type="submit" class="flex-1 py-3 rounded-xl bg-gold text-white hover:bg-gold-dark transition-all font-medium flex items-center justify-center gap-2">
                    <i data-lucide="save" class="w-4 h-4"></i> Salvar
                </button>
            </div>
        </form>
    </div>
</dialog>

<script>
    async function updateQty(id, delta) {
        const formData = new FormData();
        formData.append('action', 'update_qty');
        formData.append('id', id);
        formData.append('delta', delta);

        try {
            const response = await fetch('estoque.php', {
                method: 'POST',
                body: formData
            });
            const data = await response.json();
            if (data.success) {
                const qtyEl = document.getElementById(`qty-${id}`);
                let val = parseInt(qtyEl.innerText) + delta;
                if (val < 0) val = 0;
                qtyEl.innerText = val;
                
                // Visual feedback
                qtyEl.classList.add('scale-125');
                setTimeout(() => qtyEl.classList.remove('scale-125'), 200);
            }
        } catch (e) {
            console.error('Falha ao atualizar quantidade', e);
        }
    }

    function fillEditModal(item) {
        document.getElementById('edit_id').value = item.id;
        document.getElementById('edit_name').value = item.name;
        document.getElementById('edit_category').value = item.category;
        document.getElementById('edit_unit').value = item.unit;
        document.getElementById('edit_quantity').value = item.quantity;
        document.getElementById('edit_min').value = item.min_quantity;
        document.getElementById('edit_price').value = item.price;
        openModal('modalEdit');
    }
</script>

<?php renderFooter(); ?>
