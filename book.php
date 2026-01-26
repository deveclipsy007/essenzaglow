<?php
require_once 'db.php';

// API: Fetch available slots for a date
if (isset($_GET['fetch_slots'])) {
    header('Content-Type: application/json');
    $date = $_GET['date'] ?? '';
    
    if ($date) {
        $stmt = $pdo->prepare("SELECT * FROM available_slots WHERE date = ? AND is_booked = 0 ORDER BY time_start ASC");
        $stmt->execute([$date]);
        $slots = $stmt->fetchAll();
        echo json_encode($slots);
    } else {
        echo json_encode([]);
    }
    exit;
}

// Handle Submission
$success = false;
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'];
    $phone = $_POST['phone'];
    $service_id = $_POST['service_id'];
    $slot_id = $_POST['slot_id'] ?? null;

    if (!$slot_id) {
        $error = 'Por favor, selecione um horário disponível.';
    } else {
        // Get slot info
        $stmt = $pdo->prepare("SELECT * FROM available_slots WHERE id = ? AND is_booked = 0");
        $stmt->execute([$slot_id]);
        $slot = $stmt->fetch();
        
        if (!$slot) {
            $error = 'Este horário não está mais disponível.';
        } else {
            // 1. Find or Create Client
            $stmt = $pdo->prepare("SELECT id FROM clients WHERE phone = ?");
            $stmt->execute([$phone]);
            $client_id = $stmt->fetchColumn();

            if (!$client_id) {
                $stmt = $pdo->prepare("INSERT INTO clients (name, phone, notes) VALUES (?, ?, 'Via Autoagendamento')");
                $stmt->execute([$name, $phone]);
                $client_id = $pdo->lastInsertId();
            }

            // 2. Get Service/Combo Info
            $is_combo = (strpos($service_id, 'combo_') === 0);
            $real_id = $is_combo ? str_replace('combo_', '', $service_id) : $service_id;

            if ($is_combo) {
                $stmt = $pdo->prepare("SELECT promotional_price as price, duration_minutes FROM combos WHERE id = ?");
            } else {
                $stmt = $pdo->prepare("SELECT price, duration_minutes FROM services WHERE id = ?");
            }
            $stmt->execute([$real_id]);
            $item = $stmt->fetch();

            // 3. Create Appointment
            $start_at = $slot['date'] . ' ' . $slot['time_start'] . ':00';
            $end_at_ts = strtotime($start_at) + ($item['duration_minutes'] * 60);
            $end_at = date('Y-m-d H:i:s', $end_at_ts);

            if ($is_combo) {
                $stmt = $pdo->prepare("INSERT INTO appointments (client_id, combo_id, start_at, end_at, status, price, payment_status) VALUES (?, ?, ?, ?, 'PENDING_APPROVAL', ?, 'PENDING')");
            } else {
                $stmt = $pdo->prepare("INSERT INTO appointments (client_id, service_id, start_at, end_at, status, price, payment_status) VALUES (?, ?, ?, ?, 'PENDING_APPROVAL', ?, 'PENDING')");
            }
            $stmt->execute([$client_id, $real_id, $start_at, $end_at, $item['price']]);
            
            $appointment_id = $pdo->lastInsertId();
            
            // 4. Mark slot as booked
            $pdo->prepare("UPDATE available_slots SET is_booked = 1, appointment_id = ? WHERE id = ?")->execute([$appointment_id, $slot_id]);

            $success = true;
        }
    }
}

// Fetch Services and Combos
$services = $pdo->query("SELECT * FROM services")->fetchAll();
$combos = $pdo->query("SELECT * FROM combos")->fetchAll();

// Get dates with available slots (next 30 days)
$availableDates = $pdo->query("
    SELECT DISTINCT date FROM available_slots 
    WHERE is_booked = 0 AND date >= date('now') 
    ORDER BY date ASC LIMIT 30
")->fetchAll(PDO::FETCH_COLUMN);

// Fetch Logo Config
$stmtLogo = $pdo->prepare("SELECT * FROM landing_sections WHERE section_key = 'logo'");
$stmtLogo->execute();
$logoData = $stmtLogo->fetch();
$brandLogo = $logoData['image_data'] ?? '';
$brandName = $logoData['title'] ?? 'Essenza';
$logoHeight = $logoData['subtitle'] ?? '32';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agendamento - Essenza</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&family=Playfair+Display:ital,wght@0,400;0,600;1,400&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <script>
      tailwind.config = {
        theme: {
          extend: {
            colors: {
              ivory: { DEFAULT: '#F8F4EA', dark: '#F5EFE3' },
              sand: { DEFAULT: '#EEE9DC', dark: '#E3DECF' },
              sage: { DEFAULT: '#5B7355', dark: '#4A5E44' },
              charcoal: { DEFAULT: '#433C30', light: '#5C5446' },
              gold: { DEFAULT: '#DAC38F', dark: '#B49C73' },
            },
            fontFamily: {
              serif: ['"Playfair Display"', 'serif'],
              sans: ['"Inter"', 'sans-serif'],
            }
          }
        }
      }
    </script>
    <style>
        .btn-primary { 
            background-color: <?php echo $sections['logo']['content'] ?: '#5B7355'; ?> !important; 
            color: white !important; 
        }
        .btn-primary:hover { opacity: 0.9; }
    </style>
</head>
<body class="bg-ivory flex items-center justify-center min-h-screen p-4 font-sans">

    <div class="w-full max-w-lg bg-white rounded-2xl shadow-xl border border-sand overflow-hidden">
        
        <!-- Header -->
        <div class="p-6 text-center text-white relative" style="background-color: <?php echo $sections['logo']['content'] ?: '#5B7355'; ?>;">
            <div class="flex justify-center items-center gap-2 mb-1">
                <?php if(!empty($brandLogo)): ?>
                    <img src="<?php echo $brandLogo; ?>" style="height: <?php echo $logoHeight; ?>px" class="w-auto object-contain brightness-0 invert">
                <?php else: ?>
                    <i data-lucide="sparkles" class="text-gold fill-gold w-5 h-5"></i>
                    <h1 class="font-serif text-xl"><?php echo htmlspecialchars($brandName); ?></h1>
                <?php endif; ?>
            </div>
            <p class="text-xs text-white/70 tracking-widest uppercase">Agendamento Online</p>
        </div>

        <div class="p-6">
            <?php if ($success): ?>
                <div class="text-center py-10">
                     <div class="w-16 h-16 bg-sage/20 text-sage rounded-full flex items-center justify-center mx-auto mb-6">
                         <i data-lucide="check-circle" class="w-8 h-8"></i>
                     </div>
                     <h2 class="text-2xl font-serif text-charcoal mb-2">Solicitação Enviada!</h2>
                     <p class="text-charcoal-light mb-6">
                         Recebemos seu pedido. Aguarde a confirmação.
                     </p>
                     <a href="book.php" class="text-sage hover:underline font-medium">Fazer outro agendamento</a>
                </div>
            <?php else: ?>
                <?php if ($error): ?>
                <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-4 text-sm">
                    <?php echo $error; ?>
                </div>
                <?php endif; ?>
                
                <?php if (empty($availableDates)): ?>
                <div class="text-center py-10">
                    <div class="w-16 h-16 bg-sand rounded-full flex items-center justify-center mx-auto mb-6">
                        <i data-lucide="calendar-x" class="w-8 h-8 text-charcoal-light"></i>
                    </div>
                    <h2 class="text-xl font-serif text-charcoal mb-2">Sem Horários Disponíveis</h2>
                    <p class="text-charcoal-light text-sm">
                        No momento não há horários abertos para agendamento.<br>
                        Por favor, tente novamente mais tarde.
                    </p>
                </div>
                <?php else: ?>
                
                <form method="POST" class="space-y-5" id="bookingForm">
                    
                    <!-- Service -->
                    <div>
                        <label class="block text-xs uppercase tracking-wider text-charcoal-light mb-2 font-medium">Serviço</label>
                        <select name="service_id" required class="w-full p-3 bg-ivory border border-sand rounded-xl text-charcoal focus:ring-2 focus:ring-sage focus:border-sage transition-all">
                            <option value="">Selecione um serviço</option>
                            <optgroup label="Serviços Individuais">
                                <?php foreach($services as $s): ?>
                                    <option value="<?php echo $s['id']; ?>">
                                        <?php echo htmlspecialchars($s['name']); ?> - R$ <?php echo number_format($s['price'], 2, ',', '.'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                            <?php if (!empty($combos)): ?>
                            <optgroup label="Combos Promocionais">
                                <?php foreach($combos as $c): ?>
                                    <option value="combo_<?php echo $c['id']; ?>">
                                        <?php echo htmlspecialchars($c['name']); ?> - R$ <?php echo number_format($c['promotional_price'], 2, ',', '.'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                            <?php endif; ?>
                        </select>
                    </div>

                    <!-- Date Selection -->
                    <div>
                        <label class="block text-xs uppercase tracking-wider text-charcoal-light mb-2 font-medium">Data</label>
                        <select name="date" id="dateSelect" required class="w-full p-3 bg-ivory border border-sand rounded-xl text-charcoal focus:ring-2 focus:ring-sage focus:border-sage transition-all">
                            <option value="">Selecione uma data</option>
                            <?php foreach($availableDates as $date): ?>
                                <option value="<?php echo $date; ?>">
                                    <?php echo date('d/m/Y (D)', strtotime($date)); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <!-- Time Slots (Dynamic) -->
                    <div id="slotsContainer" class="hidden">
                        <label class="block text-xs uppercase tracking-wider text-charcoal-light mb-2 font-medium">Horário Disponível</label>
                        <div id="slotsGrid" class="grid grid-cols-3 gap-2">
                            <!-- Slots loaded dynamically -->
                        </div>
                        <input type="hidden" name="slot_id" id="selectedSlot">
                    </div>
                    
                    <div id="noSlotsMessage" class="hidden bg-sand/50 p-4 rounded-xl text-center text-charcoal-light text-sm">
                        <i data-lucide="info" class="w-5 h-5 inline mr-1"></i>
                        Selecione uma data para ver os horários disponíveis.
                    </div>

                    <!-- Client Info -->
                    <div>
                        <label class="block text-xs uppercase tracking-wider text-charcoal-light mb-2 font-medium">Nome Completo</label>
                        <input type="text" name="name" required class="w-full p-3 bg-ivory border border-sand rounded-xl text-charcoal placeholder-charcoal-light/50 focus:ring-2 focus:ring-sage focus:border-sage transition-all" placeholder="Seu nome">
                    </div>
                    <div>
                        <label class="block text-xs uppercase tracking-wider text-charcoal-light mb-2 font-medium">Telefone / WhatsApp</label>
                        <input type="text" name="phone" required class="w-full p-3 bg-ivory border border-sand rounded-xl text-charcoal placeholder-charcoal-light/50 focus:ring-2 focus:ring-sage focus:border-sage transition-all" placeholder="(00) 00000-0000">
                    </div>

                    <button type="submit" class="w-full py-4 btn-primary rounded-xl font-medium shadow-lg transition-all mt-6 text-base">
                        Solicitar Agendamento
                    </button>
                </form>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <div class="bg-ivory p-4 text-center border-t border-sand text-[10px] text-charcoal-light">
            Agendamento via Essenza Glow
        </div>
    </div>
    
    <script>
        lucide.createIcons();
        
        const dateSelect = document.getElementById('dateSelect');
        const slotsContainer = document.getElementById('slotsContainer');
        const slotsGrid = document.getElementById('slotsGrid');
        const selectedSlotInput = document.getElementById('selectedSlot');
        const noSlotsMessage = document.getElementById('noSlotsMessage');
        
        if (dateSelect) {
            dateSelect.addEventListener('change', async function() {
                const date = this.value;
                
                if (!date) {
                    slotsContainer.classList.add('hidden');
                    noSlotsMessage.classList.remove('hidden');
                    return;
                }
                
                // Fetch slots for selected date
                try {
                    const response = await fetch(`book.php?fetch_slots=1&date=${date}`);
                    const slots = await response.json();
                    
                    if (slots.length === 0) {
                        slotsGrid.innerHTML = '<p class="col-span-3 text-center text-charcoal-light py-4">Sem horários disponíveis para esta data.</p>';
                        slotsContainer.classList.remove('hidden');
                        noSlotsMessage.classList.add('hidden');
                        return;
                    }
                    
                    slotsGrid.innerHTML = slots.map(slot => `
                        <button type="button" 
                                onclick="selectSlot(${slot.id}, '${slot.time_start}')"
                                data-slot-id="${slot.id}"
                                class="slot-btn p-3 border-2 border-sand rounded-xl text-center hover:border-sage hover:bg-sage/10 transition-all">
                            <span class="text-lg font-semibold text-charcoal">${slot.time_start}</span>
                        </button>
                    `).join('');
                    
                    slotsContainer.classList.remove('hidden');
                    noSlotsMessage.classList.add('hidden');
                    selectedSlotInput.value = '';
                    
                } catch (error) {
                    console.error('Error fetching slots:', error);
                }
            });
        }
        
        function selectSlot(slotId, time) {
            // Remove selection from all
            document.querySelectorAll('.slot-btn').forEach(btn => {
                btn.classList.remove('border-sage', 'bg-sage/20');
                btn.classList.add('border-sand');
            });
            
            // Add selection to clicked
            const selectedBtn = document.querySelector(`[data-slot-id="${slotId}"]`);
            if (selectedBtn) {
                selectedBtn.classList.remove('border-sand');
                selectedBtn.classList.add('border-sage', 'bg-sage/20');
            }
            
            selectedSlotInput.value = slotId;
        }
    </script>
</body>
</html>