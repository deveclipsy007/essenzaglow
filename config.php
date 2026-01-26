<?php
require_once 'db.php';
require_once 'layout.php';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'save_settings') {
        $fields = ['whatsapp_number', 'business_name', 'business_address', 'appointment_reminder_template'];
        
        foreach ($fields as $field) {
            if (isset($_POST[$field])) {
                $value = $_POST[$field];
                $stmt = $pdo->prepare("UPDATE settings SET setting_value = ?, updated_at = datetime('now') WHERE setting_key = ?");
                $stmt->execute([$value, $field]);
                
                // Insert if not exists
                if ($stmt->rowCount() === 0) {
                    $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)");
                    $stmt->execute([$field, $value]);
                }
            }
        }
        
        header("Location: config.php?saved=1");
        exit;
    }
}

// Load current settings
$settings = [];
$result = $pdo->query("SELECT setting_key, setting_value FROM settings");
while ($row = $result->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

renderHeader("Configurações - Essenza");
renderSidebar('Configurações');
?>

<div class="max-w-4xl mx-auto space-y-6 animate-fade-in">
    
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:justify-between md:items-end gap-4">
        <div>
            <h2 class="font-serif text-2xl md:text-3xl text-charcoal mb-1 md:mb-2">Configurações</h2>
            <p class="text-charcoal-light text-sm md:text-base">Configurações gerais do sistema.</p>
        </div>
    </div>

    <?php if (isset($_GET['saved'])): ?>
    <div class="bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-xl text-sm flex items-center gap-2">
        <i data-lucide="check-circle" class="w-4 h-4"></i>
        Configurações salvas com sucesso!
    </div>
    <?php endif; ?>

    <form method="POST" class="space-y-6">
        <input type="hidden" name="action" value="save_settings">
        
        <!-- WhatsApp Section -->
        <div class="bg-white rounded-2xl border border-sand shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-sand bg-gradient-to-r from-emerald-50 to-white flex items-center gap-3">
                <div class="w-10 h-10 bg-emerald-100 rounded-xl flex items-center justify-center">
                    <i data-lucide="message-circle" class="w-5 h-5 text-emerald-600"></i>
                </div>
                <div>
                    <h3 class="font-medium text-charcoal">WhatsApp</h3>
                    <p class="text-xs text-charcoal-light">Configurações de integração com WhatsApp</p>
                </div>
            </div>
            <div class="p-6 space-y-4">
                <div>
                    <label class="block text-[10px] uppercase text-charcoal-light mb-2 font-semibold tracking-wider">
                        Número do WhatsApp (com código do país)
                    </label>
                    <div class="relative">
                        <i data-lucide="phone" class="absolute left-3 top-3 w-5 h-5 text-gray-400"></i>
                        <input type="text" name="whatsapp_number" 
                               value="<?php echo htmlspecialchars($settings['whatsapp_number'] ?? ''); ?>"
                               placeholder="5511999999999"
                               class="w-full pl-11 pr-4 py-3 bg-ivory border border-sand rounded-xl text-charcoal placeholder-charcoal-light/50 focus:ring-2 focus:ring-sage focus:border-sage transition-all">
                    </div>
                    <p class="text-xs text-charcoal-light mt-1">
                        <i data-lucide="info" class="w-3 h-3 inline mr-1"></i>
                        Use formato internacional: 55 (Brasil) + DDD + número, sem espaços ou traços.
                    </p>
                </div>

                <div>
                    <label class="block text-[10px] uppercase text-charcoal-light mb-2 font-semibold tracking-wider">
                        Modelo de Mensagem para Confirmação
                    </label>
                    <textarea name="appointment_reminder_template" rows="8"
                              class="w-full px-4 py-3 bg-ivory border border-sand rounded-xl text-charcoal placeholder-charcoal-light/50 focus:ring-2 focus:ring-sage focus:border-sage transition-all font-mono text-sm"
                    ><?php echo htmlspecialchars($settings['appointment_reminder_template'] ?? ''); ?></textarea>
                    <p class="text-xs text-charcoal-light mt-1">
                        Variáveis disponíveis: <code class="bg-sand px-1 rounded">{nome}</code>, 
                        <code class="bg-sand px-1 rounded">{data}</code>, 
                        <code class="bg-sand px-1 rounded">{horario}</code>, 
                        <code class="bg-sand px-1 rounded">{servico}</code>, 
                        <code class="bg-sand px-1 rounded">{preco}</code>
                    </p>
                </div>
            </div>
        </div>

        <!-- Business Info Section -->
        <div class="bg-white rounded-2xl border border-sand shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-sand bg-gradient-to-r from-sage/10 to-white flex items-center gap-3">
                <div class="w-10 h-10 bg-sage/20 rounded-xl flex items-center justify-center">
                    <i data-lucide="building-2" class="w-5 h-5 text-sage"></i>
                </div>
                <div>
                    <h3 class="font-medium text-charcoal">Informações da Empresa</h3>
                    <p class="text-xs text-charcoal-light">Dados que aparecem nas mensagens e sistema</p>
                </div>
            </div>
            <div class="p-6 space-y-4">
                <div>
                    <label class="block text-[10px] uppercase text-charcoal-light mb-2 font-semibold tracking-wider">
                        Nome da Empresa
                    </label>
                    <input type="text" name="business_name" 
                           value="<?php echo htmlspecialchars($settings['business_name'] ?? 'Essenza Glow'); ?>"
                           class="w-full px-4 py-3 bg-ivory border border-sand rounded-xl text-charcoal focus:ring-2 focus:ring-sage focus:border-sage transition-all">
                </div>

                <div>
                    <label class="block text-[10px] uppercase text-charcoal-light mb-2 font-semibold tracking-wider">
                        Endereço
                    </label>
                    <input type="text" name="business_address" 
                           value="<?php echo htmlspecialchars($settings['business_address'] ?? ''); ?>"
                           placeholder="Rua, número, bairro - Cidade/UF"
                           class="w-full px-4 py-3 bg-ivory border border-sand rounded-xl text-charcoal placeholder-charcoal-light/50 focus:ring-2 focus:ring-sage focus:border-sage transition-all">
                </div>
            </div>
        </div>

        <!-- Save Button -->
        <div class="flex justify-end">
            <button type="submit" class="px-8 py-3 bg-charcoal text-white rounded-xl font-medium hover:bg-charcoal-light transition-all flex items-center gap-2 shadow-lg hover:shadow-xl">
                <i data-lucide="save" class="w-4 h-4"></i>
                Salvar Configurações
            </button>
        </div>
    </form>
</div>

<?php renderFooter(); ?>
