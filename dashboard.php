<?php
require_once 'db.php';
require_once 'layout.php';

// Handle approval/rejection actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = $_POST['id'] ?? 0;
    
    if ($action === 'approve' && $id) {
        $pdo->prepare("UPDATE appointments SET status = 'CONFIRMED' WHERE id = ?")->execute([$id]);
    } elseif ($action === 'reject' && $id) {
        // Mark appointment as cancelled
        $pdo->prepare("UPDATE appointments SET status = 'CANCELLED' WHERE id = ?")->execute([$id]);
        // Free up the slot
        $pdo->prepare("UPDATE available_slots SET is_booked = 0, appointment_id = NULL WHERE appointment_id = ?")->execute([$id]);
    }
    
    header("Location: dashboard.php");
    exit;
}


// Dados Estatísticos
$today = date('Y-m-d');
$stats = [
    'count' => $pdo->query("SELECT COUNT(*) FROM appointments WHERE date(start_at) = '$today' AND status != 'CANCELLED'")->fetchColumn(),
    'revenue' => $pdo->query("SELECT SUM(price) FROM appointments WHERE date(start_at) = '$today' AND payment_status = 'PAID'")->fetchColumn() ?: 0,
    'pending' => $pdo->query("SELECT SUM(price) FROM appointments WHERE date(start_at) = '$today' AND payment_status = 'PENDING'")->fetchColumn() ?: 0,
    'web_req' => $pdo->query("SELECT COUNT(*) FROM appointments WHERE status = 'PENDING_APPROVAL'")->fetchColumn()
];

// Próximo agendamento
$nextApp = $pdo->query("
    SELECT a.*, c.name as client_name, s.name as service_name, s.duration_minutes 
    FROM appointments a
    JOIN clients c ON a.client_id = c.id
    JOIN services s ON a.service_id = s.id
    WHERE a.start_at >= datetime('now', 'localtime') AND a.status != 'CANCELLED'
    ORDER BY a.start_at ASC LIMIT 1
")->fetch();

// Solicitações pendentes de aprovação
$pendingApprovals = $pdo->query("
    SELECT a.*, c.name as client_name, c.phone as client_phone, s.name as service_name 
    FROM appointments a
    JOIN clients c ON a.client_id = c.id
    JOIN services s ON a.service_id = s.id
    WHERE a.status = 'PENDING_APPROVAL'
    ORDER BY a.start_at ASC
")->fetchAll();

renderHeader();
renderSidebar('Dashboard');
?>

<div class="space-y-6 md:space-y-8 animate-fade-in">
    <div class="flex flex-col md:flex-row md:justify-between md:items-end gap-4">
        <div>
            <h2 class="font-serif text-2xl md:text-3xl text-charcoal mb-1 md:mb-2">Bem vinda, Essenza.</h2>
            <p class="text-charcoal-light text-sm md:text-base">Resumo de hoje.</p>
        </div>
        <a href="agenda.php" class="bg-charcoal text-white px-4 md:px-6 py-3 rounded-xl shadow-lg hover:shadow-xl flex items-center justify-center gap-2 text-sm md:text-base">
            <i data-lucide="plus" class="w-4 h-4"></i> <span>Novo Agendamento</span>
        </a>
    </div>

    <!-- Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 md:gap-6">
        <div class="bg-white p-4 md:p-6 rounded-2xl border border-sand shadow-sm flex justify-between items-center">
            <div>
                <p class="text-[10px] md:text-xs uppercase tracking-widest text-charcoal-light mb-1 md:mb-2">Faturamento</p>
                <h3 class="font-serif text-2xl md:text-3xl text-charcoal">R$ <?php echo number_format($stats['revenue'], 2, ',', '.'); ?></h3>
            </div>
            <div class="p-2 md:p-3 rounded-xl bg-gold/20"><i data-lucide="wallet" class="w-5 h-5 md:w-6 md:h-6 text-charcoal"></i></div>
        </div>
        <div class="bg-white p-4 md:p-6 rounded-2xl border border-sand shadow-sm flex justify-between items-center">
            <div>
                <p class="text-[10px] md:text-xs uppercase tracking-widest text-charcoal-light mb-1 md:mb-2">Atendimentos</p>
                <h3 class="font-serif text-2xl md:text-3xl text-charcoal"><?php echo $stats['count']; ?></h3>
            </div>
            <div class="p-2 md:p-3 rounded-xl bg-sage/20"><i data-lucide="calendar-check" class="w-5 h-5 md:w-6 md:h-6 text-charcoal"></i></div>
        </div>
        <div class="bg-white p-4 md:p-6 rounded-2xl border border-sand shadow-sm flex justify-between items-center sm:col-span-2 lg:col-span-1">
            <div>
                <p class="text-[10px] md:text-xs uppercase tracking-widest text-charcoal-light mb-1 md:mb-2">Pendente</p>
                <h3 class="font-serif text-2xl md:text-3xl text-charcoal">R$ <?php echo number_format($stats['pending'], 2, ',', '.'); ?></h3>
            </div>
            <div class="p-2 md:p-3 rounded-xl bg-sand"><i data-lucide="bell" class="w-5 h-5 md:w-6 md:h-6 text-charcoal"></i></div>
        </div>
    </div>

    <!-- Solicitações Pendentes de Aprovação -->
    <?php if (!empty($pendingApprovals)): ?>
    <div class="bg-orange-50 rounded-2xl border border-orange-200 overflow-hidden">
        <div class="px-4 md:px-6 py-4 border-b border-orange-200 flex items-center gap-3">
            <div class="w-3 h-3 rounded-full bg-orange-500 animate-pulse"></div>
            <h3 class="font-serif text-lg md:text-xl text-charcoal">Solicitações Pendentes</h3>
            <span class="ml-auto bg-orange-500 text-white text-xs font-bold px-2 py-1 rounded-full"><?php echo count($pendingApprovals); ?></span>
        </div>
        <div class="divide-y divide-orange-100">
            <?php foreach ($pendingApprovals as $req): 
                $startDate = new DateTime($req['start_at']);
            ?>
            <div class="p-4 md:p-6 flex flex-col md:flex-row md:items-center gap-4">
                <div class="flex-1">
                    <div class="flex items-center gap-2 mb-2">
                        <span class="font-serif text-lg text-charcoal"><?php echo htmlspecialchars($req['client_name']); ?></span>
                        <span class="text-xs text-charcoal-light"><?php echo htmlspecialchars($req['client_phone']); ?></span>
                    </div>
                    <div class="flex flex-wrap items-center gap-2 md:gap-4 text-sm">
                        <span class="bg-white px-3 py-1 rounded-full border border-orange-200 text-charcoal">
                            <i data-lucide="sparkles" class="w-3 h-3 inline mr-1"></i>
                            <?php echo htmlspecialchars($req['service_name']); ?>
                        </span>
                        <span class="text-charcoal-light">
                            <i data-lucide="calendar" class="w-3 h-3 inline mr-1"></i>
                            <?php echo $startDate->format('d/m/Y'); ?>
                        </span>
                        <span class="text-charcoal-light">
                            <i data-lucide="clock" class="w-3 h-3 inline mr-1"></i>
                            <?php echo $startDate->format('H:i'); ?>
                        </span>
                        <span class="font-semibold text-sage">
                            R$ <?php echo number_format($req['price'], 2, ',', '.'); ?>
                        </span>
                    </div>
                </div>
                <div class="flex gap-2">
                    <form method="POST" class="inline">
                        <input type="hidden" name="action" value="approve">
                        <input type="hidden" name="id" value="<?php echo $req['id']; ?>">
                        <button type="submit" class="px-4 py-2 bg-sage text-white rounded-lg hover:bg-sage-dark transition-colors flex items-center gap-2 text-sm font-medium">
                            <i data-lucide="check" class="w-4 h-4"></i> Aprovar
                        </button>
                    </form>
                    <form method="POST" class="inline">
                        <input type="hidden" name="action" value="reject">
                        <input type="hidden" name="id" value="<?php echo $req['id']; ?>">
                        <button type="submit" onclick="return confirm('Recusar esta solicitação?')" class="px-4 py-2 bg-red-50 text-red-600 border border-red-200 rounded-lg hover:bg-red-100 transition-colors flex items-center gap-2 text-sm font-medium">
                            <i data-lucide="x" class="w-4 h-4"></i> Recusar
                        </button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 md:gap-8">
        <!-- Next Appointment -->
        <div class="lg:col-span-2 space-y-4 md:space-y-6">
            <h3 class="font-serif text-lg md:text-xl text-charcoal flex items-center gap-2"><span class="w-2 h-2 rounded-full bg-gold"></span> Próximo</h3>
            <?php if ($nextApp): $start = new DateTime($nextApp['start_at']); ?>
            <div class="bg-white rounded-2xl p-6 md:p-8 border border-sand shadow-sm relative overflow-hidden group">
                <div class="absolute top-0 right-0 w-24 md:w-32 h-24 md:h-32 bg-gold/10 rounded-full -mr-8 md:-mr-10 -mt-8 md:-mt-10"></div>
                <div class="relative z-10 flex flex-col md:flex-row md:justify-between md:items-center gap-4">
                    <div>
                        <div class="flex items-center gap-3 mb-2">
                            <span class="text-3xl md:text-4xl font-serif text-charcoal"><?php echo $start->format('H:i'); ?></span>
                            <span class="text-charcoal-light uppercase text-xs md:text-sm">Hoje</span>
                        </div>
                        <h4 class="text-lg md:text-xl font-medium text-charcoal"><?php echo htmlspecialchars($nextApp['client_name']); ?></h4>
                        <p class="text-gold-dark text-sm md:text-base"><?php echo htmlspecialchars($nextApp['service_name']); ?></p>
                    </div>
                </div>
            </div>
            <?php else: ?>
            <div class="bg-white p-6 md:p-8 rounded-2xl border border-dashed border-sand text-center text-charcoal-light">Sem mais atendimentos hoje.</div>
            <?php endif; ?>
        </div>

        <!-- Alerts -->
        <div class="space-y-4 md:space-y-6">
            <h3 class="font-serif text-lg md:text-xl text-charcoal">Lembretes</h3>
            <div class="bg-white rounded-2xl border border-sand p-4 md:p-6">
                <?php if ($stats['web_req'] > 0): ?>
                <div class="flex items-start gap-3 bg-orange-50 p-3 md:p-4 rounded-lg border border-orange-100">
                    <div class="w-2 h-2 rounded-full bg-orange-500 mt-2 animate-pulse"></div>
                    <div>
                        <p class="text-sm font-medium text-charcoal">Solicitações Web</p>
                        <p class="text-xs text-charcoal-light">Você tem <?php echo $stats['web_req']; ?> pedidos pendentes.</p>
                    </div>
                </div>
                <?php else: ?>
                <div class="flex items-center gap-3"><div class="w-2 h-2 rounded-full bg-sage"></div><span class="text-sm text-charcoal">Tudo tranquilo.</span></div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php renderFooter(); ?>