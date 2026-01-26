<?php
require_once 'db.php';
require_once 'layout.php';

// --- DATE HELPERS ---
$viewMode = $_GET['view'] ?? 'week';
$selectedDateStr = $_GET['date'] ?? date('Y-m-d');
$selectedDate = new DateTime($selectedDateStr);

function getStartOfWeek($date) {
    $d = clone $date;
    $day = $d->format('w');
    $d->modify('-'.$day.' days');
    return $d;
}

// --- HANDLE ACTIONS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add_single') {
        $start_at = $_POST['date'] . ' ' . $_POST['time'] . ':00';
        $stmt = $pdo->prepare("SELECT duration_minutes, price FROM services WHERE id = ?");
        $stmt->execute([$_POST['service_id']]);
        $s = $stmt->fetch();
        $end_at = date('Y-m-d H:i:s', strtotime($start_at) + ($s['duration_minutes'] * 60));
        
        $stmt = $pdo->prepare("INSERT INTO appointments (client_id, service_id, start_at, end_at, status, price, payment_status, type) VALUES (?, ?, ?, ?, 'SCHEDULED', ?, 'PENDING', 'APPOINTMENT')");
        $stmt->execute([$_POST['client_id'], $_POST['service_id'], $start_at, $end_at, $s['price']]);
    } elseif ($action === 'add_package') {
        // Lógica para criar pacote/recorrência
        $start_at = $_POST['date'] . ' ' . $_POST['time'] . ':00';
        $sessions = intval($_POST['sessions'] ?? 1);
        $frequency = $_POST['frequency'] ?? 'weekly';
        
        $stmt = $pdo->prepare("SELECT duration_minutes, price FROM services WHERE id = ?");
        $stmt->execute([$_POST['service_id']]);
        $s = $stmt->fetch();
        
        for($i=0; $i<$sessions; $i++) {
            $current_start = $start_at;
            if($i > 0) {
                if($frequency === 'weekly') {
                    $current_start = date('Y-m-d H:i:s', strtotime($start_at . ' +'.$i.' week'));
                } elseif($frequency === 'biweekly') {
                    $current_start = date('Y-m-d H:i:s', strtotime($start_at . ' +'.($i*2).' week'));
                }
            }
            $current_end = date('Y-m-d H:i:s', strtotime($current_start) + ($s['duration_minutes'] * 60));
            
            $stmt = $pdo->prepare("INSERT INTO appointments (client_id, service_id, start_at, end_at, status, price, payment_status, type) VALUES (?, ?, ?, ?, 'SCHEDULED', ?, 'PENDING', 'APPOINTMENT')");
            $stmt->execute([$_POST['client_id'], $_POST['service_id'], $current_start, $current_end, $s['price']]);
        }
    } elseif ($action === 'add_block') {
        $start_at = $selectedDateStr . ' ' . $_POST['start'] . ':00';
        $end_at = $selectedDateStr . ' ' . $_POST['end'] . ':00';
        $stmt = $pdo->prepare("INSERT INTO appointments (title, start_at, end_at, status, type, payment_status, price) VALUES (?, ?, ?, 'CONFIRMED', 'BLOCK', 'PAID', 0)");
        $stmt->execute([$_POST['title'] ?? 'Horário Reservado', $start_at, $end_at]);
    } elseif ($action === 'add_available_slot') {
        $date = $_POST['slot_date'];
        $time_start = $_POST['slot_time_start'];
        $time_end = $_POST['slot_time_end'];
        
        // Check if slot already exists
        $stmt = $pdo->prepare("SELECT id FROM available_slots WHERE date = ? AND time_start = ?");
        $stmt->execute([$date, $time_start]);
        if (!$stmt->fetch()) {
            $stmt = $pdo->prepare("INSERT INTO available_slots (date, time_start, time_end, is_booked, appointment_id) VALUES (?, ?, ?, 0, NULL)");
            $stmt->execute([$date, $time_start, $time_end]);
        }
    } elseif ($action === 'delete_available_slot') {
        $slot_id = $_POST['slot_id'];
        $stmt = $pdo->prepare("DELETE FROM available_slots WHERE id = ? AND is_booked = 0");
        $stmt->execute([$slot_id]);
    } elseif ($action === 'cancel_appointment') {
        $id = $_POST['id'];
        // Cancel the appointment
        $pdo->prepare("UPDATE appointments SET status = 'CANCELLED' WHERE id = ?")->execute([$id]);
        // Free up the slot if it was booked via online booking
        $pdo->prepare("UPDATE available_slots SET is_booked = 0, appointment_id = NULL WHERE appointment_id = ?")->execute([$id]);
    } elseif ($action === 'move') {
        $stmt = $pdo->prepare("UPDATE appointments SET start_at = ?, end_at = datetime(?, '+' || (strftime('%s', end_at) - strftime('%s', start_at)) || ' seconds') WHERE id = ?");
        $stmt->execute([$_POST['new_start'], $_POST['new_start'], $_POST['id']]);
        echo json_encode(['success' => true]); exit;
    }
    
    header("Location: agenda.php?view=$viewMode&date=$selectedDateStr");
    exit;
}

// --- LOAD DATA ---
$startFetch = clone $selectedDate;
$endFetch = clone $selectedDate;

if ($viewMode === 'week') {
    $startFetch = getStartOfWeek($selectedDate);
    $endFetch = clone $startFetch;
    $endFetch->modify('+6 days');
} elseif ($viewMode === 'month') {
    $startFetch->modify('first day of this month');
    $endFetch->modify('last day of this month');
}

// Determine query conditions
$whereAppointments = "date(a.start_at) BETWEEN ? AND ?";
$whereSlots = "date BETWEEN ? AND ?";
$queryParams = [$startFetch->format('Y-m-d'), $endFetch->format('Y-m-d')];

if ($viewMode === 'list') {
    $whereAppointments = "1=1";
    $whereSlots = "1=1";
    $queryParams = [];
}

$stmt = $pdo->prepare("
    SELECT a.*, c.name as client_name, c.phone as client_phone, 
           CASE 
               WHEN a.combo_id IS NOT NULL THEN (SELECT name FROM combos WHERE id = a.combo_id)
               ELSE s.name 
           END as service_display_name
    FROM appointments a
    LEFT JOIN clients c ON a.client_id = c.id
    LEFT JOIN services s ON a.service_id = s.id
    WHERE $whereAppointments
    AND a.status != 'CANCELLED'
    ORDER BY a.start_at ASC
");
$stmt->execute($queryParams);
$appointments = $stmt->fetchAll();

$clients = $pdo->query("SELECT id, name FROM clients ORDER BY name ASC")->fetchAll();
$services = $pdo->query("SELECT id, name, duration_minutes, price FROM services ORDER BY name ASC")->fetchAll();

// Fetch available slots for the selected period
$stmt = $pdo->prepare("SELECT * FROM available_slots WHERE $whereSlots ORDER BY date ASC, time_start ASC");
$stmt->execute($queryParams);
$availableSlots = $stmt->fetchAll();

// Fetch WhatsApp number from settings
$whatsappNumber = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'whatsapp_number'")->fetchColumn() ?: '5511999999999';
$msgTemplate = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'appointment_reminder_template'")->fetchColumn() ?: '';


// --- VIEW RENDERERS ---
function renderDayView($date, $apps) {
    $hours = range(8, 20);
    ?>
    <div class="flex-1 overflow-y-auto relative custom-scrollbar bg-white" style="min-height: 780px;">
        <div class="absolute inset-0 z-0">
            <?php foreach ($hours as $h): ?>
                <div class="h-[65px] border-b border-gray-100 flex items-start px-4">
                    <span class="text-xs text-gray-400 w-16 -mt-2"><?php echo str_pad($h, 2, '0', STR_PAD_LEFT); ?>:00</span>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="relative ml-20 pt-2" style="height: 845px;" ondragover="event.preventDefault()" ondrop="handleDrop(event, '<?php echo $date->format('Y-m-d'); ?>', 65)">
            <?php foreach ($apps as $app): 
                $start = new DateTime($app['start_at']);
                $end = new DateTime($app['end_at']);
                if ($start->format('Y-m-d') !== $date->format('Y-m-d')) continue;
                
                $top = (($start->format('H') - 8) * 65) + (($start->format('i') / 60) * 65);
                $duration = ($end->getTimestamp() - $start->getTimestamp()) / 60;
                $height = ($duration / 60) * 65;
                $isBlock = ($app['type'] === 'BLOCK');
            ?>
                <div draggable="true" ondragstart="handleDragStart(event, '<?php echo $app['id']; ?>')"
                     class="absolute left-0 right-8 p-2 rounded-lg border-l-3 shadow-sm transition-all hover:shadow-md z-10 cursor-move
                     <?php echo $isBlock ? 'bg-gray-50 border-gray-300' : 'bg-blue-50 border-blue-400'; ?>"
                     style="top: <?php echo $top; ?>px; height: <?php echo $height; ?>px;">
                    <div class="text-xs font-medium text-gray-700 truncate">
                        <?php echo htmlspecialchars($app['client_name'] ?? $app['title']); ?>
                    </div>
                    <div class="text-[10px] text-gray-500 truncate"><?php echo htmlspecialchars($app['service_display_name'] ?? ''); ?></div>
                   <div class="text-[10px] text-gray-400 mt-1"><?php echo $start->format('H:i'); ?> - <?php echo $end->format('H:i'); ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php
}

function renderWeekView($date, $apps) {
    $startOfWeek = getStartOfWeek($date);
    $hours = range(8, 20);
    $weekDays = ['DOM', 'SEG', 'TER', 'QUA', 'QUI', 'SEX', 'SÁB'];
    ?>
    <div class="flex-1 flex flex-col h-full overflow-hidden bg-white">
        <div class="flex border-b border-gray-200">
            <div class="w-16 flex-shrink-0"></div>
            <div class="flex-1 grid grid-cols-7">
                <?php for($i=0; $i<7; $i++): 
                    $curr = clone $startOfWeek; $curr->modify("+$i days");
                    $isToday = $curr->format('Y-m-d') === date('Y-m-d');
                ?>
                <div class="text-center py-3 border-r border-gray-100 last:border-r-0 <?php echo $isToday ? 'bg-amber-50' : ''; ?>">
                    <div class="text-[10px] text-gray-500 uppercase font-medium tracking-wider"><?php echo $weekDays[$i]; ?></div>
                    <div class="text-lg font-medium mt-1 <?php echo $isToday ? 'text-amber-600' : 'text-gray-700'; ?>">
                        <?php echo $curr->format('d'); ?>
                    </div>
                </div>
                <?php endfor; ?>
            </div>
        </div>
        <div class="flex-1 overflow-y-auto custom-scrollbar">
            <div class="flex" style="min-height: 780px;">
                <div class="w-16 flex-shrink-0 border-r border-gray-100 bg-gray-50">
                    <?php foreach ($hours as $h): ?>
                    <div class="h-[60px] text-right pr-2 text-[10px] text-gray-400">
                        <div class="-mt-2"><?php echo str_pad($h, 2, '0', STR_PAD_LEFT); ?>:00</div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="flex-1 grid grid-cols-7 relative">
                    <?php for($i=0; $i<7; $i++): 
                        $currDay = clone $startOfWeek; $currDay->modify("+$i days");
                        $dayStr = $currDay->format('Y-m-d');
                    ?>
                    <div class="relative border-r border-gray-100 last:border-r-0" ondragover="event.preventDefault()" ondrop="handleDrop(event, '<?php echo $dayStr; ?>', 60)">
                        <?php foreach($hours as $h): ?>
                            <div class="absolute w-full border-b border-gray-50" style="top: <?php echo ($h-8)*60; ?>px; height: 60px;"></div>
                        <?php endforeach; ?>
                        <?php foreach($apps as $app): 
                            $start = new DateTime($app['start_at']);
                            $end = new DateTime($app['end_at']);
                            if ($start->format('Y-m-d') !== $dayStr) continue;
                            $top = (($start->format('H')-8)*60) + (($start->format('i')/60)*60);
                            $duration = ($end->getTimestamp() - $start->getTimestamp()) / 60;
                            $height = max(20, ($duration/60)*60);
                        ?>
                        <div draggable="true" ondragstart="handleDragStart(event, '<?php echo $app['id']; ?>')"
                             class="absolute left-0.5 right-0.5 rounded border-l-2 text-[9px] px-1 py-0.5 overflow-hidden shadow-sm hover:shadow transition-all cursor-move bg-blue-50 border-blue-400"
                             style="top: <?php echo $top; ?>px; height: <?php echo $height; ?>px;">
                            <div class="font-medium text-gray-700 truncate"><?php echo $start->format('H:i'); ?></div>
                            <div class="truncate text-gray-600"><?php echo htmlspecialchars($app['client_name'] ?? $app['title']); ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endfor; ?>
                </div>
            </div>
        </div>
    </div>
    <?php
}

function renderMonthView($date, $apps) {
    $firstDay = clone $date; $firstDay->modify('first day of this month');
    $lastDay = clone $date; $lastDay->modify('last day of this month');
    $startOffset = $firstDay->format('w');
    $daysInMonth = $lastDay->format('d');
    $weekDays = ['DOM', 'SEG', 'TER', 'QUA', 'QUI', 'SEX', 'SÁB'];
    ?>
    <div class="flex-1 overflow-y-auto p-6 custom-scrollbar bg-gray-50">
        <div class="bg-white rounded-xl shadow-sm overflow-hidden border border-gray-200">
            <div class="grid grid-cols-7 border-b border-gray-200">
                <?php foreach($weekDays as $d): ?>
                    <div class="py-3 text-center text-[10px] text-gray-500 uppercase font-semibold tracking-wider"><?php echo $d; ?></div>
                <?php endforeach; ?>
            </div>
            <div class="grid grid-cols-7">
                <?php for($i=0; $i<$startOffset; $i++) echo '<div class="bg-gray-50 h-24 border-r border-b border-gray-100"></div>'; ?>
                <?php for($d=1; $d<=$daysInMonth; $d++): 
                    $curr = clone $firstDay; $curr->modify("+".($d-1)." days");
                    $isToday = $curr->format('Y-m-d') === date('Y-m-d');
                    $dayApps = array_filter($apps, fn($a) => date('Y-m-d', strtotime($a['start_at'])) === $curr->format('Y-m-d'));
                ?>
                <div onclick="window.location.href='?view=day&date=<?php echo $curr->format('Y-m-d'); ?>'"
                     class="h-24 p-2 border-r border-b border-gray-100 hover:bg-gray-50 cursor-pointer transition-colors relative">
                    <div class="flex justify-center mb-1">
                        <span class="w-7 h-7 flex items-center justify-center text-xs font-medium rounded-full <?php echo $isToday ? 'bg-amber-500 text-white' : 'text-gray-700'; ?>">
                            <?php echo $d; ?>
                        </span>
                    </div>
                    <div class="space-y-0.5">
                        <?php foreach(array_slice($dayApps, 0, 2) as $app): ?>
                            <div class="text-[9px] truncate px-1 py-0.5 rounded bg-blue-50 text-gray-700">
                                <?php echo date('H:i', strtotime($app['start_at'])); ?> <?php echo htmlspecialchars(substr($app['client_name'] ?? $app['title'], 0, 8)); ?>
                            </div>
                        <?php endforeach; ?>
                        <?php if(count($dayApps) > 2): ?>
                            <div class="text-[8px] text-center text-gray-400">+<?php echo count($dayApps)-2; ?></div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endfor; ?>
            </div>
        </div>
    </div>
    <?php
}

function renderListView($date, $apps, $slots, $whatsapp, $template) {
    // Group appointments by date
    $appsByDate = [];
    foreach ($apps as $app) {
        $d = date('Y-m-d', strtotime($app['start_at']));
        $appsByDate[$d][] = $app;
    }
    
    // Group slots by date
    $slotsByDate = [];
    foreach ($slots as $slot) {
        $slotsByDate[$slot['date']][] = $slot;
    }
    
    // Merge all dates
    $allDates = array_unique(array_merge(array_keys($appsByDate), array_keys($slotsByDate)));
    sort($allDates);
    ?>
    <div class="flex-1 overflow-y-auto p-6 custom-scrollbar bg-gray-50">
        <?php if (empty($allDates)): ?>
        <div class="text-center py-12 text-gray-400">
            <i data-lucide="calendar-x" class="w-16 h-16 mx-auto mb-4 opacity-50"></i>
            <p class="text-lg">Nenhum agendamento ou slot neste período.</p>
        </div>
        <?php else: ?>
        <div class="space-y-6">
            <?php foreach ($allDates as $dateStr): 
                $dateApps = $appsByDate[$dateStr] ?? [];
                $dateSlots = $slotsByDate[$dateStr] ?? [];
                $dateObj = new DateTime($dateStr);
                $days = ['Domingo', 'Segunda-feira', 'Terça-feira', 'Quarta-feira', 'Quinta-feira', 'Sexta-feira', 'Sábado'];
            ?>
            <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
                <!-- Date Header -->
                <div class="px-6 py-4 bg-gradient-to-r from-gray-800 to-gray-700 text-white flex items-center gap-3">
                    <div class="w-12 h-12 bg-white/20 rounded-xl flex flex-col items-center justify-center">
                        <span class="text-lg font-bold"><?php echo $dateObj->format('d'); ?></span>
                        <span class="text-[10px] uppercase"><?php echo $dateObj->format('M'); ?></span>
                    </div>
                    <div>
                        <p class="font-medium"><?php echo $days[$dateObj->format('w')]; ?></p>
                        <p class="text-xs text-white/70"><?php echo count($dateApps); ?> agendamentos • <?php echo count(array_filter($dateSlots, fn($s) => !$s['is_booked'])); ?> slots livres</p>
                    </div>
                </div>
                
                <!-- Slots Section -->
                <?php if (!empty($dateSlots)): ?>
                <div class="border-b border-gray-100">
                    <div class="px-6 py-3 bg-emerald-50 flex items-center gap-2">
                        <i data-lucide="clock" class="w-4 h-4 text-emerald-600"></i>
                        <span class="text-xs font-semibold text-emerald-700 uppercase tracking-wider">Horários Disponíveis</span>
                    </div>
                    <div class="divide-y divide-gray-50">
                        <?php foreach ($dateSlots as $slot): ?>
                        <div class="px-6 py-3 flex items-center justify-between hover:bg-gray-50 transition-colors">
                            <div class="flex items-center gap-4">
                                <div class="w-10 h-10 rounded-lg flex items-center justify-center <?php echo $slot['is_booked'] ? 'bg-amber-100' : 'bg-emerald-100'; ?>">
                                    <i data-lucide="<?php echo $slot['is_booked'] ? 'user-check' : 'clock'; ?>" class="w-5 h-5 <?php echo $slot['is_booked'] ? 'text-amber-600' : 'text-emerald-600'; ?>"></i>
                                </div>
                                <div>
                                    <p class="font-medium text-gray-700"><?php echo $slot['time_start']; ?> — <?php echo $slot['time_end']; ?></p>
                                    <p class="text-xs <?php echo $slot['is_booked'] ? 'text-amber-600' : 'text-emerald-600'; ?>">
                                        <?php echo $slot['is_booked'] ? 'Reservado (aguardando)' : 'Disponível para agendamento'; ?>
                                    </p>
                                </div>
                            </div>
                            <?php if (!$slot['is_booked']): ?>
                            <form method="POST" class="inline" onsubmit="return confirm('Remover este horário?')">
                                <input type="hidden" name="action" value="delete_available_slot">
                                <input type="hidden" name="slot_id" value="<?php echo $slot['id']; ?>">
                                <button type="submit" class="p-2 text-red-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors" title="Remover slot">
                                    <i data-lucide="trash-2" class="w-4 h-4"></i>
                                </button>
                            </form>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Appointments Section -->
                <?php if (!empty($dateApps)): ?>
                <div>
                    <div class="px-6 py-3 bg-blue-50 flex items-center gap-2">
                        <i data-lucide="calendar-check" class="w-4 h-4 text-blue-600"></i>
                        <span class="text-xs font-semibold text-blue-700 uppercase tracking-wider">Agendamentos</span>
                    </div>
                    <div class="divide-y divide-gray-50">
                        <?php foreach ($dateApps as $app): 
                            $start = new DateTime($app['start_at']);
                            $end = new DateTime($app['end_at']);
                            $isBlock = ($app['type'] === 'BLOCK');
                            $isPending = ($app['status'] === 'PENDING_APPROVAL');
                            
                            // Build WhatsApp message
                            $msg = str_replace(
                                ['{nome}', '{data}', '{horario}', '{servico}', '{preco}', '\\n'],
                                [
                                    $app['client_name'] ?? '',
                                    $start->format('d/m/Y'),
                                    $start->format('H:i'),
                                    $app['service_display_name'] ?? '',
                                    number_format($app['price'] ?? 0, 2, ',', '.'),
                                    "\n"
                                ],
                                $template
                            );
                            $clientPhone = preg_replace('/[^0-9]/', '', $app['client_phone'] ?? '');
                            if (strlen($clientPhone) < 10) $clientPhone = $whatsapp; // fallback
                            $waLink = 'https://wa.me/' . $clientPhone . '?text=' . urlencode($msg);
                        ?>
                        <div class="px-6 py-4 flex items-center justify-between hover:bg-gray-50 transition-colors <?php echo $isPending ? 'bg-amber-50/50' : ''; ?>">
                            <div class="flex items-center gap-4">
                                <div class="w-12 h-12 rounded-xl flex items-center justify-center <?php echo $isBlock ? 'bg-gray-100' : ($isPending ? 'bg-amber-100' : 'bg-blue-100'); ?>">
                                    <i data-lucide="<?php echo $isBlock ? 'lock' : ($isPending ? 'hourglass' : 'user'); ?>" class="w-6 h-6 <?php echo $isBlock ? 'text-gray-500' : ($isPending ? 'text-amber-600' : 'text-blue-600'); ?>"></i>
                                </div>
                                <div>
                                    <div class="flex items-center gap-2">
                                        <p class="font-medium text-gray-800"><?php echo htmlspecialchars($app['client_name'] ?? $app['title'] ?? 'Bloqueio'); ?></p>
                                        <?php if ($isPending): ?>
                                        <span class="px-2 py-0.5 bg-amber-100 text-amber-700 text-[10px] font-semibold rounded-full uppercase">Pendente</span>
                                        <?php endif; ?>
                                    </div>
                                    <p class="text-sm text-gray-500">
                                        <?php echo $start->format('H:i'); ?> — <?php echo $end->format('H:i'); ?>
                                        <?php if (!$isBlock): ?>
                                        • <?php echo htmlspecialchars($app['service_display_name'] ?? ''); ?>
                                        • R$ <?php echo number_format($app['price'] ?? 0, 2, ',', '.'); ?>
                                        <?php endif; ?>
                                    </p>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <?php if (!$isBlock && !empty($app['client_phone'])): ?>
                                <a href="<?php echo $waLink; ?>" target="_blank" class="p-2 bg-emerald-100 text-emerald-600 hover:bg-emerald-200 rounded-lg transition-colors" title="Enviar WhatsApp">
                                    <i data-lucide="message-circle" class="w-5 h-5"></i>
                                </a>
                                <?php endif; ?>
                                <?php if (!$isBlock): ?>
                                <form method="POST" class="inline" onsubmit="return confirm('Cancelar este agendamento?')">
                                    <input type="hidden" name="action" value="cancel_appointment">
                                    <input type="hidden" name="id" value="<?php echo $app['id']; ?>">
                                    <button type="submit" class="p-2 text-red-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors" title="Cancelar agendamento">
                                        <i data-lucide="x-circle" class="w-5 h-5"></i>
                                    </button>
                                </form>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
    <?php
}

renderHeader("Agenda - Essenza");
renderSidebar('agenda');

// Page title
$pageTitle = '';
if ($viewMode === 'day') {
    $days = ['Dom.', 'Seg.', 'Ter.', 'Qua.', 'Qui.', 'Sex.', 'Sáb.'];
    $months = ['Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];
    $pageTitle = $days[$selectedDate->format('w')] . ', ' . $selectedDate->format('d') . ' De ' . $months[$selectedDate->format('n')-1];
} elseif ($viewMode === 'week') {
    $startWeek = getStartOfWeek($selectedDate);
    $pageTitle = 'Semana ' . $startWeek->format('W');
} elseif ($viewMode === 'list') {
    $pageTitle = 'Programação Completa';
} else {
    $months = ['Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];
    $pageTitle = $months[$selectedDate->format('n')-1] . ' De ' . $selectedDate->format('Y');
}
?>

<div class="max-w-7xl mx-auto h-[calc(100vh-8rem)] flex flex-col space-y-4 animate-fade-in">
    <!-- Header Nav - Mobile Responsive -->
    <div class="flex flex-col gap-3 py-4">
        <!-- Title Row -->
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-2 md:gap-6">
                <button onclick="navigate('prev')" class="w-8 h-8 flex items-center justify-center hover:bg-gray-100 rounded-full transition-colors">
                    <i data-lucide="chevron-left" class="w-5 h-5 text-gray-600"></i>
                </button>
                <div class="text-center">
                    <h1 class="font-serif text-lg md:text-2xl text-gray-800"><?php echo $pageTitle; ?></h1>
                    <p class="text-[9px] md:text-[10px] text-gray-400 uppercase tracking-widest mt-0.5 hidden sm:block">
                        <?php 
                            if ($viewMode === 'day') echo 'AGENDA DIÁRIA';
                            elseif ($viewMode === 'week') echo 'VISÃO SEMANAL';
                            elseif ($viewMode === 'list') echo 'VISÃO EM LISTA';
                            else echo 'VISÃO MENSAL';
                        ?>
                    </p>
                </div>
                <button onclick="navigate('next')" class="w-8 h-8 flex items-center justify-center hover:bg-gray-100 rounded-full transition-colors">
                    <i data-lucide="chevron-right" class="w-5 h-5 text-gray-600"></i>
                </button>
            </div>
            
            <!-- Add Button (always visible) -->
            <button onclick="openModal('modalAppointment')" class="px-4 py-2 md:px-5 bg-gray-800 text-white rounded-lg text-xs font-medium hover:bg-gray-700 transition-colors flex items-center gap-2 shadow-sm">
                <i data-lucide="plus" class="w-4 h-4"></i> <span class="hidden sm:inline">Novo</span>
            </button>
        </div>
        
        <!-- Controls Row -->
        <div class="flex items-center justify-between gap-2 overflow-x-auto pb-1">
            <!-- View Toggle - Responsive -->
            <div class="bg-white border border-gray-200 rounded-lg p-0.5 flex shadow-sm flex-shrink-0">
                <button onclick="setView('day')" class="p-2 md:px-3 md:py-1.5 rounded text-xs font-medium transition-all flex items-center gap-1.5 <?php echo $viewMode === 'day' ? 'bg-gray-800 text-white' : 'text-gray-600 hover:bg-gray-50'; ?>">
                    <i data-lucide="calendar-days" class="w-4 h-4"></i><span class="hidden md:inline">Dia</span>
                </button>
                <button onclick="setView('week')" class="p-2 md:px-3 md:py-1.5 rounded text-xs font-medium transition-all flex items-center gap-1.5 <?php echo $viewMode === 'week' ? 'bg-gray-800 text-white' : 'text-gray-600 hover:bg-gray-50'; ?>">
                    <i data-lucide="layout-grid" class="w-4 h-4"></i><span class="hidden md:inline">Semana</span>
                </button>
                <button onclick="setView('month')" class="p-2 md:px-3 md:py-1.5 rounded text-xs font-medium transition-all flex items-center gap-1.5 <?php echo $viewMode === 'month' ? 'bg-gray-800 text-white' : 'text-gray-600 hover:bg-gray-50'; ?>">
                    <i data-lucide="calendar" class="w-4 h-4"></i><span class="hidden md:inline">Mês</span>
                </button>
                <button onclick="setView('list')" class="p-2 md:px-3 md:py-1.5 rounded text-xs font-medium transition-all flex items-center gap-1.5 <?php echo $viewMode === 'list' ? 'bg-gray-800 text-white' : 'text-gray-600 hover:bg-gray-50'; ?>">
                    <i data-lucide="list-checks" class="w-4 h-4"></i><span class="hidden md:inline">Lista</span>
                </button>
            </div>

            <!-- Action Buttons -->
            <div class="flex items-center gap-1 md:gap-2 flex-shrink-0">
                <button onclick="openModal('modalOpenSlot')" class="p-2 md:px-3 md:py-1.5 bg-emerald-50 border border-emerald-200 rounded-lg text-emerald-700 hover:bg-emerald-100 transition-colors flex items-center gap-1.5 shadow-sm">
                    <i data-lucide="calendar-plus" class="w-4 h-4"></i><span class="hidden lg:inline text-xs font-medium">Abrir Slot</span>
                </button>

                <button onclick="openModal('modalViewSlots')" class="p-2 bg-white border border-gray-200 rounded-lg text-gray-600 hover:bg-gray-50 transition-colors shadow-sm" title="Ver Slots">
                    <i data-lucide="clock" class="w-4 h-4"></i>
                </button>

                <button onclick="openModal('modalBlock')" class="p-2 bg-white border border-gray-200 rounded-lg text-gray-600 hover:bg-gray-50 transition-colors shadow-sm" title="Bloquear">
                    <i data-lucide="lock" class="w-4 h-4"></i>
                </button>
            </div>
        </div>
    </div>


    <!-- Calendar Container -->
    <div class="flex-1 bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden flex flex-col">
        <?php 
            if ($viewMode === 'day') renderDayView($selectedDate, $appointments);
            elseif ($viewMode === 'week') renderWeekView($selectedDate, $appointments);
            elseif ($viewMode === 'list') renderListView($selectedDate, $appointments, $availableSlots, $whatsappNumber, $msgTemplate);
            else renderMonthView($selectedDate, $appointments);
        ?>
    </div>
</div>

<!-- Modal: Novo Agendamento (com abas) -->
<dialog id="modalAppointment" class="p-0 rounded-2xl shadow-2xl w-full max-w-lg backdrop:bg-black/20 border-0">
    <div class="w-full bg-white rounded-2xl overflow-hidden">
        <div class="px-6 py-5 flex justify-between items-center border-b border-gray-100">
            <h2 class="font-serif text-xl text-gray-800">Novo Agendamento</h2>
            <button onclick="closeModal('modalAppointment')" class="p-1 hover:bg-gray-100 rounded transition-colors">
                <i data-lucide="x" class="w-5 h-5 text-gray-600"></i>
            </button>
        </div>

        <!-- Tabs -->
        <div class="flex border-b border-gray-100">
            <button onclick="switchTab('single')" id="tabSingle" class="flex-1 px-6 py-3 text-sm font-medium text-gray-600 border-b-2 border-gray-800 transition-colors">
                Sessão Única
            </button>
            <button onclick="switchTab('package')" id="tabPackage" class="flex-1 px-6 py-3 text-sm font-medium text-gray-400 hover:text-gray-600 border-b-2 border-transparent transition-colors flex items-center justify-center gap-2">
                <i data-lucide="repeat" class="w-3.5 h-3.5"></i> Pacote / Recorrência
            </button>
        </div>

        <!-- Tab Content: Single -->
        <form method="POST" id="formSingle" class="p-6 space-y-4">
            <input type="hidden" name="action" value="add_single">
            <input type="hidden" name="date" value="<?php echo $selectedDateStr; ?>">
            
            <div>
                <label class="block text-[10px] uppercase text-gray-500 mb-1.5 font-semibold tracking-wider">CLIENTE</label>
                <select name="client_id" required class="w-full px-3 py-2.5 bg-gray-50 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-gray-300 transition-all">
                    <option>Selecione...</option>
                    <?php foreach($clients as $c): ?>
                        <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-[10px] uppercase text-gray-500 mb-1.5 font-semibold tracking-wider">SERVIÇO</label>
                <select name="service_id" required class="w-full px-3 py-2.5 bg-gray-50 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-gray-300 transition-all">
                    <option>Selecione...</option>
                    <?php foreach($services as $s): ?>
                        <option value="<?php echo $s['id']; ?>"><?php echo htmlspecialchars($s['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-[10px] uppercase text-gray-500 mb-1.5 font-semibold tracking-wider">DATA</label>
                    <div class="relative">
                        <i data-lucide="calendar" class="absolute left-3 top-2.5 w-4 h-4 text-amber-500"></i>
                        <input type="date" name="date" required value="<?php echo $selectedDateStr; ?>" class="w-full pl-10 pr-3 py-2.5 bg-gray-50 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-gray-300 transition-all">
                    </div>
                </div>
                <div>
                    <label class="block text-[10px] uppercase text-gray-500 mb-1.5 font-semibold tracking-wider">HORÁRIO</label>
                    <div class="relative">
                        <i data-lucide="clock" class="absolute left-3 top-2.5 w-4 h-4 text-amber-500"></i>
                        <input type="time" name="time" required class="w-full pl-10 pr-3 py-2.5 bg-gray-50 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-gray-300 transition-all">
                    </div>
                </div>
            </div>

            <div>
                <label class="block text-[10px] uppercase text-gray-500 mb-1.5 font-semibold tracking-wider">OBSERVAÇÕES</label>
                <textarea name="notes" rows="2" placeholder="Ex: Alergias..." class="w-full px-3 py-2.5 bg-gray-50 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2focus:ring-gray-300 transition-all resize-none"></textarea>
            </div>

            <div class="flex gap-3 pt-2">
                <button type="button" onclick="closeModal('modalAppointment')" class="flex-1 py-3 rounded-lg border border-gray-200 text-gray-600 hover:bg-gray-50 font-medium transition-colors">
                    Cancelar
                </button>
                <button type="submit" class="flex-1 py-3 rounded-lg bg-gray-800 text-white hover:bg-gray-700 font-medium transition-colors">
                    Confirmar Agendamento
                </button>
            </div>
        </form>

        <!-- Tab Content: Package -->
        <form method="POST" id="formPackage" class="p-6 space-y-4 hidden">
            <input type="hidden" name="action" value="add_package">
            
            <div>
                <label class="block text-[10px] uppercase text-gray-500 mb-1.5 font-semibold tracking-wider">CLIENTE</label>
                <select name="client_id" required class="w-full px-3 py-2.5 bg-gray-50 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-gray-300 transition-all">
                    <option>Selecione...</option>
                    <?php foreach($clients as $c): ?>
                        <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-[10px] uppercase text-gray-500 mb-1.5 font-semibold tracking-wider">SERVIÇO</label>
                <select name="service_id" required class="w-full px-3 py-2.5 bg-gray-50 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-gray-300 transition-all">
                    <option>Selecione...</option>
                    <?php foreach($services as $s): ?>
                        <option value="<?php echo $s['id']; ?>"><?php echo htmlspecialchars($s['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-[10px] uppercase text-gray-500 mb-1.5 font-semibold tracking-wider">INÍCIO</label>
                    <div class="relative">
                        <i data-lucide="calendar" class="absolute left-3 top-2.5 w-4 h-4 text-amber-500"></i>
                        <input type="date" name="date" required value="<?php echo $selectedDateStr; ?>" class="w-full pl-10 pr-3 py-2.5 bg-gray-50 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-gray-300 transition-all">
                    </div>
                </div>
                <div>
                    <label class="block text-[10px] uppercase text-gray-500 mb-1.5 font-semibold tracking-wider">HORÁRIO (FIXO)</label>
                    <div class="relative">
                        <i data-lucide="clock" class="absolute left-3 top-2.5 w-4 h-4 text-amber-500"></i>
                        <input type="time" name="time" required class="w-full pl-10 pr-3 py-2.5 bg-gray-50 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-gray-300 transition-all">
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-[10px] uppercase text-gray-500 mb-1.5 font-semibold tracking-wider">FREQUÊNCIA</label>
                    <select name="frequency" class="w-full px-3 py-2.5 bg-gray-50 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-gray-300 transition-all">
                        <option value="weekly">Semanal (Toda semana)</option>
                        <option value="biweekly">Quinzenal</option>
                        <option value="monthly">Mensal</option>
                    </select>
                </div>
                <div>
                    <label class="block text-[10px] uppercase text-gray-500 mb-1.5 font-semibold tracking-wider">Nº SESSÕES</label>
                    <input type="number" name="sessions" value="4" min="1" class="w-full px-3 py-2.5 bg-gray-50 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-gray-300 transition-all">
                </div>
            </div>

            <div>
                <label class="block text-[10px] uppercase text-gray-500 mb-1.5 font-semibold tracking-wider">OBSERVAÇÕES</label>
                <textarea name="notes" rows="2" placeholder="Ex: Alergias..." class="w-full px-3 py-2.5 bg-gray-50 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-gray-300 transition-all resize-none"></textarea>
            </div>

            <div class="flex gap-3 pt-2">
                <button type="button" onclick="closeModal('modalAppointment')" class="flex-1 py-3 rounded-lg border border-gray-200 text-gray-600 hover:bg-gray-50 font-medium transition-colors">
                    Cancelar
                </button>
                <button type="submit" class="flex-1 py-3 rounded-lg bg-gray-800 text-white hover:bg-gray-700 font-medium transition-colors">
                    Confirmar Pacote
                </button>
            </div>
        </form>
    </div>
</dialog>

<!-- Modal: Abrir Slot para Agendamento Online -->
<dialog id="modalOpenSlot" class="p-0 rounded-2xl shadow-2xl w-full max-w-md backdrop:bg-black/20 border-0">
    <div class="w-full bg-white rounded-2xl overflow-hidden">
        <div class="px-6 py-5 flex justify-between items-center border-b border-emerald-100 bg-emerald-50">
            <h2 class="font-serif text-xl text-emerald-800 flex items-center gap-2">
                <i data-lucide="calendar-plus" class="w-5 h-5"></i> Abrir Horário Online
            </h2>
            <button onclick="closeModal('modalOpenSlot')" class="hover:bg-emerald-100 rounded p-1 transition-colors"><i data-lucide="x" class="w-5 h-5 text-emerald-600"></i></button>
        </div>
        <form method="POST" class="p-6 space-y-4">
            <input type="hidden" name="action" value="add_available_slot">
            <p class="text-sm text-gray-600 bg-gray-50 p-3 rounded-lg border border-gray-100">
                <i data-lucide="info" class="w-4 h-4 inline mr-1 text-gray-400"></i>
                Este horário ficará disponível para clientes agendarem online.
            </p>
            <div>
                <label class="block text-[10px] uppercase text-gray-500 mb-1.5 font-semibold tracking-wider">DATA</label>
                <input type="date" name="slot_date" required value="<?php echo $selectedDateStr; ?>" class="w-full px-3 py-2.5 bg-gray-50 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-emerald-300 transition-all">
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-[10px] uppercase text-gray-500 mb-1.5 font-semibold tracking-wider">INÍCIO</label>
                    <input type="time" name="slot_time_start" required class="w-full px-3 py-2.5 bg-gray-50 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-emerald-300 transition-all" value="09:00">
                </div>
                <div>
                    <label class="block text-[10px] uppercase text-gray-500 mb-1.5 font-semibold tracking-wider">FIM</label>
                    <input type="time" name="slot_time_end" required class="w-full px-3 py-2.5 bg-gray-50 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-emerald-300 transition-all" value="10:00">
                </div>
            </div>
            <button type="submit" class="w-full py-3 bg-emerald-600 text-white rounded-lg font-medium hover:bg-emerald-700 transition-colors mt-4 flex items-center justify-center gap-2">
                <i data-lucide="check" class="w-4 h-4"></i> Abrir Horário
            </button>
        </form>
    </div>
</dialog>

<!-- Modal: Ver/Gerenciar Slots Disponíveis -->
<dialog id="modalViewSlots" class="p-0 rounded-2xl shadow-2xl w-full max-w-lg backdrop:bg-black/20 border-0">
    <div class="w-full bg-white rounded-2xl overflow-hidden">
        <div class="px-6 py-5 flex justify-between items-center border-b border-gray-100">
            <h2 class="font-serif text-xl text-gray-800 flex items-center gap-2">
                <i data-lucide="clock" class="w-5 h-5 text-emerald-600"></i> Horários Disponíveis
            </h2>
            <button onclick="closeModal('modalViewSlots')" class="hover:bg-gray-100 rounded p-1 transition-colors"><i data-lucide="x" class="w-5 h-5 text-gray-600"></i></button>
        </div>
        <div class="p-6 max-h-[400px] overflow-y-auto">
            <?php if (empty($availableSlots)): ?>
                <div class="text-center py-8 text-gray-400">
                    <i data-lucide="calendar-x" class="w-12 h-12 mx-auto mb-3 opacity-50"></i>
                    <p>Nenhum horário disponível neste período.</p>
                    <p class="text-xs mt-1">Use o botão "Abrir Slot" para criar horários.</p>
                </div>
            <?php else: ?>
                <div class="space-y-2">
                    <?php foreach($availableSlots as $slot): ?>
                        <div class="flex items-center justify-between p-3 rounded-lg border <?php echo $slot['is_booked'] ? 'bg-amber-50 border-amber-200' : 'bg-emerald-50 border-emerald-200'; ?>">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-lg flex items-center justify-center <?php echo $slot['is_booked'] ? 'bg-amber-100' : 'bg-emerald-100'; ?>">
                                    <i data-lucide="<?php echo $slot['is_booked'] ? 'user-check' : 'clock'; ?>" class="w-5 h-5 <?php echo $slot['is_booked'] ? 'text-amber-600' : 'text-emerald-600'; ?>"></i>
                                </div>
                                <div>
                                    <p class="font-medium text-gray-700">
                                        <?php echo date('d/m/Y', strtotime($slot['date'])); ?> — 
                                        <?php echo $slot['time_start']; ?> às <?php echo $slot['time_end']; ?>
                                    </p>
                                    <p class="text-xs <?php echo $slot['is_booked'] ? 'text-amber-600' : 'text-emerald-600'; ?>">
                                        <?php echo $slot['is_booked'] ? 'Reservado' : 'Disponível'; ?>
                                    </p>
                                </div>
                            </div>
                            <?php if (!$slot['is_booked']): ?>
                            <form method="POST" class="inline" onsubmit="return confirm('Remover este horário?')">
                                <input type="hidden" name="action" value="delete_available_slot">
                                <input type="hidden" name="slot_id" value="<?php echo $slot['id']; ?>">
                                <button type="submit" class="p-2 text-red-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors" title="Remover">
                                    <i data-lucide="trash-2" class="w-4 h-4"></i>
                                </button>
                            </form>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <div class="px-6 py-4 border-t border-gray-100 bg-gray-50">
            <button onclick="closeModal('modalViewSlots'); openModal('modalOpenSlot');" class="w-full py-2.5 bg-emerald-600 text-white rounded-lg font-medium hover:bg-emerald-700 transition-colors flex items-center justify-center gap-2">
                <i data-lucide="plus" class="w-4 h-4"></i> Abrir Novo Horário
            </button>
        </div>
    </div>
</dialog>

<!-- Modal: Bloquear Agenda -->
<dialog id="modalBlock" class="p-0 rounded-2xl shadow-2xl w-full max-w-md backdrop:bg-black/20 border-0">
    <div class="w-full bg-white rounded-2xl overflow-hidden">
        <div class="px-6 py-5 flex justify-between items-center border-b border-gray-100">
            <h2 class="font-serif text-xl text-gray-800">Bloquear Horário</h2>
            <button onclick="closeModal('modalBlock')"><i data-lucide="x" class="w-5 h-5 text-gray-600"></i></button>
        </div>
        <form method="POST" class="p-6 space-y-4">
            <input type="hidden" name="action" value="add_block">
            <div>
                <label class="block text-[10px] uppercase text-gray-500 mb-1.5 font-semibold tracking-wider">TÍTULO</label>
                <input type="text" name="title" required class="w-full px-3 py-2.5 bg-gray-50 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-gray-300 transition-all" value="Horário Reservado">
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-[10px] uppercase text-gray-500 mb-1.5 font-semibold tracking-wider">INÍCIO</label>
                    <input type="time" name="start" required class="w-full px-3 py-2.5 bg-gray-50 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-gray-300 transition-all" value="12:00">
                </div>
                <div>
                    <label class="block text-[10px] uppercase text-gray-500 mb-1.5 font-semibold tracking-wider">FIM</label>
                    <input type="time" name="end" required class="w-full px-3 py-2.5 bg-gray-50 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-gray-300 transition-all" value="13:00">
                </div>
            </div>
            <button type="submit" class="w-full py-3 bg-gray-800 text-white rounded-lg font-medium hover:bg-gray-700 transition-colors mt-4">Bloquear Agenda</button>
        </form>
    </div>
</dialog>

<!-- Modal: Sugestões -->
<dialog id="modalSuggestions" class="p-0 rounded-2xl shadow-2xl w-full max-w-md backdrop:bg-black/20 border-0">
    <div class="w-full bg-white rounded-2xl overflow-hidden">
        <div class="px-6 py-5 flex justify-between items-center border-b border-gray-100">
            <h2 class="font-serif text-xl text-gray-800 flex items-center gap-2">
                <i data-lucide="sparkles" class="w-5 h-5 text-amber-500"></i> Horários Livres
            </h2>
            <button onclick="closeModal('modalSuggestions')"><i data-lucide="x" class="w-5 h-5 text-gray-600"></i></button>
        </div>
        <div class="p-6">
            <p class="text-sm text-gray-600 mb-4">Encontramos estes horários disponíveis hoje:</p>
            <div class="space-y-3">
                <div class="p-4 rounded-lg border border-gray-200 hover:border-amber-400 hover:bg-amber-50 cursor-pointer transition-all flex justify-between items-center group">
                    <div>
                        <p class="font-medium text-gray-700">09:00 — 10:30</p>
                        <p class="text-[10px] text-gray-500 uppercase font-semibold tracking-wider">90 minutos livres</p>
                    </div>
                    <i data-lucide="arrow-right" class="w-4 h-4 text-amber-500 opacity-0 group-hover:opacity-100 transition-opacity"></i>
                </div>
            </div>
        </div>
    </div>
</dialog>

<script>
    let draggingId = null;

    function openModal(id) {
        document.getElementById(id).showModal();
    }

    function closeModal(id) {
        document.getElementById(id).close();
    }

    function navigate(dir) {
        const url = new URL(window.location);
        const dateStr = '<?php echo $selectedDateStr; ?>';
        const view = '<?php echo $viewMode; ?>';
        const date = new Date(dateStr + 'T00:00:00');
        
        if (view === 'day') date.setDate(date.getDate() + (dir === 'next' ? 1 : -1));
        else if (view === 'week') date.setDate(date.getDate() + (dir === 'next' ? 7 : -7));
        else date.setMonth(date.getMonth() + (dir === 'next' ? 1 : -1));
        
        const newDate = date.toISOString().split('T')[0];
        window.location.search = `?view=${view}&date=${newDate}`;
    }

    function setView(v) {
        window.location.search = `?view=${v}&date=<?php echo $selectedDateStr; ?>`;
    }

    function switchTab(tab) {
        if(tab === 'single') {
            document.getElementById('formSingle').classList.remove('hidden');
            document.getElementById('formPackage').classList.add('hidden');
            document.getElementById('tabSingle').classList.add('border-gray-800', 'text-gray-800');
            document.getElementById('tabSingle').classList.remove('text-gray-400');
            document.getElementById('tabPackage').classList.remove('border-gray-800', 'text-gray-800');
            document.getElementById('tabPackage').classList.add('text-gray-400', 'border-transparent');
        } else {
            document.getElementById('formSingle').classList.add('hidden');
            document.getElementById('formPackage').classList.remove('hidden');
            document.getElementById('tabPackage').classList.add('border-gray-800', 'text-gray-800');
            document.getElementById('tabPackage').classList.remove('text-gray-400');
            document.getElementById('tabSingle').classList.remove('border-gray-800', 'text-gray-800');
            document.getElementById('tabSingle').classList.add('text-gray-400', 'border-transparent');
        }
    }

    function handleDragStart(e, id) {
        draggingId = id;
        e.dataTransfer.effectAllowed = 'move';
    }

    async function handleDrop(e, targetDate, pixelsPerHour) {
        e.preventDefault();
        if(!draggingId) return;

        const rect = e.currentTarget.getBoundingClientRect();
        const offsetY = e.clientY - rect.top;
        const decimalTime = 8 + (offsetY / pixelsPerHour);
        const hours = Math.floor(decimalTime);
        const minutes = Math.round(((decimalTime - hours) * 60) / 15) * 15;
        
        const newStart = `${targetDate} ${String(hours).padStart(2,'0')}:${String(minutes).padStart(2,'0')}:00`;
        
        const formData = new FormData();
        formData.append('action', 'move');
        formData.append('id', draggingId);
        formData.append('new_start', newStart);

        try {
            await fetch('agenda.php', { method: 'POST', body: formData });
            window.location.reload();
        } catch(err) {
            console.error('Erro ao mover');
        } finally { draggingId = null; }
    }
</script>

<?php renderFooter(); ?>
