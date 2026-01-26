<?php
require_once 'db.php';
require_once 'layout.php';

// --- HANDLE ACTIONS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_expense') {
        $stmt = $pdo->prepare("INSERT INTO expenses (description, category, amount, expense_date, notes) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([
            $_POST['description'],
            $_POST['category'],
            $_POST['amount'],
            $_POST['expense_date'],
            $_POST['notes'] ?? ''
        ]);
        header("Location: financeiro.php?" . http_build_query($_GET));
        exit;
    }

    if ($action === 'delete_expense') {
        $pdo->prepare("DELETE FROM expenses WHERE id = ?")->execute([$_POST['id']]);
        header("Location: financeiro.php?" . http_build_query($_GET));
        exit;
    }

    if ($action === 'mark_paid') {
        $pdo->prepare("UPDATE appointments SET payment_status = 'PAID' WHERE id = ?")->execute([$_POST['id']]);
        header("Location: financeiro.php?" . http_build_query($_GET));
        exit;
    }
}

// --- FILTERS ---
$filter = $_GET['filter'] ?? 'month';
$customStart = $_GET['start'] ?? '';
$customEnd = $_GET['end'] ?? '';

// Calculate date range based on filter
$today = date('Y-m-d');
$startDate = '';
$endDate = $today;

switch ($filter) {
    case 'today':
        $startDate = $today;
        $endDate = $today;
        $filterLabel = 'Hoje';
        break;
    case 'week':
        $startDate = date('Y-m-d', strtotime('monday this week'));
        $endDate = date('Y-m-d', strtotime('sunday this week'));
        $filterLabel = 'Esta Semana';
        break;
    case 'month':
        $startDate = date('Y-m-01');
        $endDate = date('Y-m-t');
        $filterLabel = date('F Y');
        break;
    case 'year':
        $startDate = date('Y-01-01');
        $endDate = date('Y-12-31');
        $filterLabel = 'Este Ano';
        break;
    case 'custom':
        $startDate = $customStart ?: date('Y-m-01');
        $endDate = $customEnd ?: $today;
        $filterLabel = 'Personalizado';
        break;
    default:
        $startDate = date('Y-m-01');
        $endDate = date('Y-m-t');
        $filterLabel = date('F Y');
}

// --- REVENUE (from appointments) ---
$stmt = $pdo->prepare("SELECT SUM(price) FROM appointments WHERE date(start_at) BETWEEN ? AND ? AND payment_status = 'PAID' AND status != 'CANCELLED'");
$stmt->execute([$startDate, $endDate]);
$totalRevenue = $stmt->fetchColumn() ?: 0;

$stmt = $pdo->prepare("SELECT SUM(price) FROM appointments WHERE date(start_at) BETWEEN ? AND ? AND payment_status = 'PENDING' AND status != 'CANCELLED'");
$stmt->execute([$startDate, $endDate]);
$totalPending = $stmt->fetchColumn() ?: 0;

// --- EXPENSES ---
$stmt = $pdo->prepare("SELECT SUM(amount) FROM expenses WHERE expense_date BETWEEN ? AND ?");
$stmt->execute([$startDate, $endDate]);
$totalExpenses = $stmt->fetchColumn() ?: 0;

// --- NET PROFIT ---
$netProfit = $totalRevenue - $totalExpenses;

// --- RECENT SALES ---
$stmt = $pdo->prepare("
    SELECT a.*, c.name as client_name, s.name as service_name
    FROM appointments a
    LEFT JOIN clients c ON a.client_id = c.id
    LEFT JOIN services s ON a.service_id = s.id
    WHERE date(a.start_at) BETWEEN ? AND ? AND a.status != 'CANCELLED'
    ORDER BY a.start_at DESC
    LIMIT 15
");
$stmt->execute([$startDate, $endDate]);
$recentSales = $stmt->fetchAll();

// --- RECENT EXPENSES ---
$stmt = $pdo->prepare("SELECT * FROM expenses WHERE expense_date BETWEEN ? AND ? ORDER BY expense_date DESC LIMIT 15");
$stmt->execute([$startDate, $endDate]);
$recentExpenses = $stmt->fetchAll();

// --- EXPENSE CATEGORIES ---
$expenseCategories = ['Aluguel', 'Produtos', 'Energia', 'Água', 'Marketing', 'Manutenção', 'Fornecedores', 'Salários', 'Impostos', 'Outros'];

// --- VIEW ---
renderHeader("Financeiro - Essenza");
renderSidebar('Financeiro');
?>

<div class="max-w-7xl mx-auto space-y-6 animate-fade-in">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:justify-between md:items-end gap-4">
        <div>
            <h2 class="font-serif text-2xl md:text-3xl text-charcoal mb-1">Controle Financeiro</h2>
            <p class="text-charcoal-light text-sm">Gerencie receitas, despesas e fluxo de caixa</p>
        </div>
        <button onclick="openModal('modalExpense')" class="bg-red-600 text-white px-5 py-3 rounded-xl shadow-lg hover:shadow-xl flex items-center justify-center gap-2 transition-all">
            <i data-lucide="minus-circle" class="w-4 h-4"></i> Nova Despesa
        </button>
    </div>

    <!-- Filters -->
    <div class="bg-white p-4 rounded-2xl border border-sand shadow-sm">
        <form class="flex flex-wrap items-end gap-4">
            <div>
                <label class="block text-xs uppercase tracking-wider text-charcoal-light mb-2 font-medium">Período</label>
                <select name="filter" onchange="this.form.submit()" class="px-4 py-2 bg-ivory border border-sand rounded-xl text-charcoal focus:ring-2 focus:ring-sage">
                    <option value="today" <?php echo $filter === 'today' ? 'selected' : ''; ?>>Hoje</option>
                    <option value="week" <?php echo $filter === 'week' ? 'selected' : ''; ?>>Esta Semana</option>
                    <option value="month" <?php echo $filter === 'month' ? 'selected' : ''; ?>>Este Mês</option>
                    <option value="year" <?php echo $filter === 'year' ? 'selected' : ''; ?>>Este Ano</option>
                    <option value="custom" <?php echo $filter === 'custom' ? 'selected' : ''; ?>>Personalizado</option>
                </select>
            </div>
            
            <?php if ($filter === 'custom'): ?>
            <div>
                <label class="block text-xs uppercase tracking-wider text-charcoal-light mb-2 font-medium">De</label>
                <input type="date" name="start" value="<?php echo $startDate; ?>" class="px-4 py-2 bg-ivory border border-sand rounded-xl text-charcoal focus:ring-2 focus:ring-sage">
            </div>
            <div>
                <label class="block text-xs uppercase tracking-wider text-charcoal-light mb-2 font-medium">Até</label>
                <input type="date" name="end" value="<?php echo $endDate; ?>" class="px-4 py-2 bg-ivory border border-sand rounded-xl text-charcoal focus:ring-2 focus:ring-sage">
            </div>
            <button type="submit" class="px-4 py-2 bg-charcoal text-white rounded-xl hover:bg-charcoal/90 transition-colors">Aplicar</button>
            <?php endif; ?>
            
            <div class="ml-auto">
                <span class="text-sm text-charcoal-light">
                    <i data-lucide="calendar" class="w-4 h-4 inline"></i>
                    <?php echo date('d/m/Y', strtotime($startDate)); ?> - <?php echo date('d/m/Y', strtotime($endDate)); ?>
                </span>
            </div>
        </form>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Receita -->
        <div class="bg-white p-5 rounded-2xl border border-sand shadow-sm">
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 bg-sage/20 rounded-xl flex items-center justify-center">
                    <i data-lucide="trending-up" class="w-6 h-6 text-sage"></i>
                </div>
                <div>
                    <p class="text-xs text-charcoal-light uppercase tracking-wider font-medium">Receitas</p>
                    <p class="text-2xl font-serif text-sage">R$ <?php echo number_format($totalRevenue, 2, ',', '.'); ?></p>
                </div>
            </div>
        </div>
        
        <!-- Despesas -->
        <div class="bg-white p-5 rounded-2xl border border-sand shadow-sm">
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 bg-red-100 rounded-xl flex items-center justify-center">
                    <i data-lucide="trending-down" class="w-6 h-6 text-red-500"></i>
                </div>
                <div>
                    <p class="text-xs text-charcoal-light uppercase tracking-wider font-medium">Despesas</p>
                    <p class="text-2xl font-serif text-red-500">R$ <?php echo number_format($totalExpenses, 2, ',', '.'); ?></p>
                </div>
            </div>
        </div>
        
        <!-- Lucro Líquido -->
        <div class="bg-white p-5 rounded-2xl border border-sand shadow-sm">
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 <?php echo $netProfit >= 0 ? 'bg-gold/20' : 'bg-red-100'; ?> rounded-xl flex items-center justify-center">
                    <i data-lucide="wallet" class="w-6 h-6 <?php echo $netProfit >= 0 ? 'text-gold-dark' : 'text-red-500'; ?>"></i>
                </div>
                <div>
                    <p class="text-xs text-charcoal-light uppercase tracking-wider font-medium">Lucro Líquido</p>
                    <p class="text-2xl font-serif <?php echo $netProfit >= 0 ? 'text-gold-dark' : 'text-red-500'; ?>">
                        R$ <?php echo number_format($netProfit, 2, ',', '.'); ?>
                    </p>
                </div>
            </div>
        </div>
        
        <!-- Pendente -->
        <div class="bg-white p-5 rounded-2xl border border-sand shadow-sm">
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 bg-amber-100 rounded-xl flex items-center justify-center">
                    <i data-lucide="clock" class="w-6 h-6 text-amber-600"></i>
                </div>
                <div>
                    <p class="text-xs text-charcoal-light uppercase tracking-wider font-medium">A Receber</p>
                    <p class="text-2xl font-serif text-amber-600">R$ <?php echo number_format($totalPending, 2, ',', '.'); ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Tables Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Receitas (Vendas) -->
        <div class="bg-white rounded-2xl border border-sand shadow-sm overflow-hidden">
            <div class="p-5 border-b border-sand flex items-center gap-3">
                <div class="w-8 h-8 bg-sage/20 rounded-lg flex items-center justify-center">
                    <i data-lucide="arrow-up-right" class="w-4 h-4 text-sage"></i>
                </div>
                <h3 class="font-serif text-lg text-charcoal">Receitas</h3>
                <span class="text-xs bg-sage/10 text-sage px-2 py-1 rounded-full ml-auto"><?php echo count($recentSales); ?> lançamentos</span>
            </div>
            <div class="max-h-80 overflow-y-auto">
                <table class="w-full text-left">
                    <thead class="bg-ivory sticky top-0">
                        <tr>
                            <th class="px-5 py-3 text-xs font-medium text-charcoal-light uppercase">Data</th>
                            <th class="px-5 py-3 text-xs font-medium text-charcoal-light uppercase">Cliente</th>
                            <th class="px-5 py-3 text-xs font-medium text-charcoal-light uppercase">Valor</th>
                            <th class="px-5 py-3 text-xs font-medium text-charcoal-light uppercase">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-sand/50">
                        <?php foreach ($recentSales as $sale): ?>
                        <tr class="hover:bg-ivory/50">
                            <td class="px-5 py-3 text-sm"><?php echo date('d/m', strtotime($sale['start_at'])); ?></td>
                            <td class="px-5 py-3 text-sm font-medium truncate max-w-[120px]"><?php echo htmlspecialchars($sale['client_name'] ?? 'N/A'); ?></td>
                            <td class="px-5 py-3 text-sm font-bold text-sage">R$ <?php echo number_format($sale['price'], 2, ',', '.'); ?></td>
                            <td class="px-5 py-3">
                                <?php if ($sale['payment_status'] === 'PAID'): ?>
                                <span class="px-2 py-1 bg-sage/10 text-sage text-[10px] font-bold rounded-full uppercase">Pago</span>
                                <?php else: ?>
                                <form method="POST" class="inline">
                                    <input type="hidden" name="action" value="mark_paid">
                                    <input type="hidden" name="id" value="<?php echo $sale['id']; ?>">
                                    <button type="submit" class="px-2 py-1 bg-amber-100 text-amber-700 text-[10px] font-bold rounded-full uppercase hover:bg-amber-200 transition-colors">
                                        Pendente
                                    </button>
                                </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($recentSales)): ?>
                        <tr><td colspan="4" class="px-5 py-8 text-center text-charcoal-light text-sm">Nenhum lançamento no período</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Despesas -->
        <div class="bg-white rounded-2xl border border-sand shadow-sm overflow-hidden">
            <div class="p-5 border-b border-sand flex items-center gap-3">
                <div class="w-8 h-8 bg-red-100 rounded-lg flex items-center justify-center">
                    <i data-lucide="arrow-down-left" class="w-4 h-4 text-red-500"></i>
                </div>
                <h3 class="font-serif text-lg text-charcoal">Despesas</h3>
                <span class="text-xs bg-red-100 text-red-600 px-2 py-1 rounded-full ml-auto"><?php echo count($recentExpenses); ?> lançamentos</span>
            </div>
            <div class="max-h-80 overflow-y-auto">
                <table class="w-full text-left">
                    <thead class="bg-ivory sticky top-0">
                        <tr>
                            <th class="px-5 py-3 text-xs font-medium text-charcoal-light uppercase">Data</th>
                            <th class="px-5 py-3 text-xs font-medium text-charcoal-light uppercase">Descrição</th>
                            <th class="px-5 py-3 text-xs font-medium text-charcoal-light uppercase">Valor</th>
                            <th class="px-5 py-3 text-xs font-medium text-charcoal-light uppercase"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-sand/50">
                        <?php foreach ($recentExpenses as $exp): ?>
                        <tr class="hover:bg-ivory/50 group">
                            <td class="px-5 py-3 text-sm"><?php echo date('d/m', strtotime($exp['expense_date'])); ?></td>
                            <td class="px-5 py-3">
                                <div class="text-sm font-medium truncate max-w-[150px]"><?php echo htmlspecialchars($exp['description']); ?></div>
                                <div class="text-[10px] text-charcoal-light"><?php echo htmlspecialchars($exp['category']); ?></div>
                            </td>
                            <td class="px-5 py-3 text-sm font-bold text-red-500">-R$ <?php echo number_format($exp['amount'], 2, ',', '.'); ?></td>
                            <td class="px-5 py-3">
                                <form method="POST" onsubmit="return confirm('Excluir esta despesa?')" class="opacity-0 group-hover:opacity-100 transition-opacity">
                                    <input type="hidden" name="action" value="delete_expense">
                                    <input type="hidden" name="id" value="<?php echo $exp['id']; ?>">
                                    <button type="submit" class="p-1 rounded hover:bg-red-50 text-charcoal-light hover:text-red-500">
                                        <i data-lucide="trash-2" class="w-4 h-4"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($recentExpenses)): ?>
                        <tr><td colspan="4" class="px-5 py-8 text-center text-charcoal-light text-sm">Nenhuma despesa no período</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Nova Despesa -->
<dialog id="modalExpense" class="p-0 rounded-2xl shadow-2xl w-full max-w-md backdrop:bg-black/30 border-0">
    <div class="w-full bg-white rounded-2xl overflow-hidden">
        <div class="px-6 py-5 bg-gradient-to-r from-red-50 to-ivory flex justify-between items-center border-b border-sand">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-red-100 rounded-xl flex items-center justify-center">
                    <i data-lucide="minus-circle" class="w-5 h-5 text-red-500"></i>
                </div>
                <h2 class="font-serif text-xl text-charcoal">Nova Despesa</h2>
            </div>
            <button onclick="closeModal('modalExpense')" class="p-2 hover:bg-white/50 rounded-lg transition-colors">
                <i data-lucide="x" class="w-5 h-5 text-charcoal-light"></i>
            </button>
        </div>
        <form method="POST" class="p-6 space-y-5">
            <input type="hidden" name="action" value="add_expense">
            
            <div>
                <label class="block text-xs uppercase tracking-wider text-charcoal-light mb-2 font-semibold">Descrição</label>
                <input type="text" name="description" required 
                       class="w-full px-4 py-3 bg-ivory border border-sand rounded-xl text-charcoal placeholder-charcoal-light/50 focus:ring-2 focus:ring-red-300 focus:border-red-300 transition-all"
                       placeholder="Ex: Conta de energia">
            </div>
            
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs uppercase tracking-wider text-charcoal-light mb-2 font-semibold">Categoria</label>
                    <select name="category" class="w-full px-4 py-3 bg-ivory border border-sand rounded-xl text-charcoal focus:ring-2 focus:ring-red-300 focus:border-red-300 transition-all">
                        <?php foreach ($expenseCategories as $cat): ?>
                        <option value="<?php echo $cat; ?>"><?php echo $cat; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs uppercase tracking-wider text-charcoal-light mb-2 font-semibold">Valor (R$)</label>
                    <input type="number" step="0.01" name="amount" required min="0.01"
                           class="w-full px-4 py-3 bg-ivory border border-sand rounded-xl text-charcoal text-center focus:ring-2 focus:ring-red-300 focus:border-red-300 transition-all"
                           placeholder="0,00">
                </div>
            </div>
            
            <div>
                <label class="block text-xs uppercase tracking-wider text-charcoal-light mb-2 font-semibold">Data</label>
                <input type="date" name="expense_date" required value="<?php echo date('Y-m-d'); ?>"
                       class="w-full px-4 py-3 bg-ivory border border-sand rounded-xl text-charcoal focus:ring-2 focus:ring-red-300 focus:border-red-300 transition-all">
            </div>
            
            <div>
                <label class="block text-xs uppercase tracking-wider text-charcoal-light mb-2 font-semibold">Observações (opcional)</label>
                <textarea name="notes" rows="2"
                          class="w-full px-4 py-3 bg-ivory border border-sand rounded-xl text-charcoal placeholder-charcoal-light/50 focus:ring-2 focus:ring-red-300 focus:border-red-300 transition-all"
                          placeholder="Detalhes adicionais..."></textarea>
            </div>
            
            <div class="flex gap-3 pt-2">
                <button type="button" onclick="closeModal('modalExpense')" class="flex-1 py-3 rounded-xl border border-sand text-charcoal hover:bg-sand transition-all font-medium">
                    Cancelar
                </button>
                <button type="submit" class="flex-1 py-3 rounded-xl bg-red-600 text-white hover:bg-red-700 transition-all font-medium flex items-center justify-center gap-2">
                    <i data-lucide="check" class="w-4 h-4"></i> Cadastrar
                </button>
            </div>
        </form>
    </div>
</dialog>

<?php renderFooter(); ?>
