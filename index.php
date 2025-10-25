<?php
// RENAME THIS FILE TO index.php

// --- CONFIGURATION & HELPERS ---
error_reporting(0); // Suppress warnings on first run if files don't exist
date_default_timezone_set('America/Sao_Paulo');

define('DATA_DIR', __DIR__ . '/data');
define('VEHICLES_FILE', DATA_DIR . '/vehicles.json');
define('SETTINGS_FILE', DATA_DIR . '/settings.json');

// Ensure data directory exists
if (!is_dir(DATA_DIR)) {
    mkdir(DATA_DIR, 0755, true);
}

function get_settings() {
    $defaults = [
        'hourlyRate' => 10,
        'toleranceMinutes' => 5,
        'fractionRate' => 5,
        'fractionLimitMinutes' => 15,
        'pixKey' => 'seu-pix@email.com',
        'pixHolderName' => 'NOME DO TITULAR',
        'pixHolderCity' => 'CIDADE',
    ];
    if (!file_exists(SETTINGS_FILE)) {
        file_put_contents(SETTINGS_FILE, json_encode($defaults, JSON_PRETTY_PRINT));
        return $defaults;
    }
    return json_decode(file_get_contents(SETTINGS_FILE), true);
}

function save_settings($settings) {
    file_put_contents(SETTINGS_FILE, json_encode($settings, JSON_PRETTY_PRINT));
}

function get_vehicles() {
    if (!file_exists(VEHICLES_FILE)) {
        file_put_contents(VEHICLES_FILE, '[]');
        return [];
    }
    return json_decode(file_get_contents(VEHICLES_FILE), true);
}

function save_vehicles($vehicles) {
    file_put_contents(VEHICLES_FILE, json_encode($vehicles, JSON_PRETTY_PRINT));
}

// --- ACTION HANDLING ---

// Handle AJAX requests
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    $action = $_GET['action'];
    $input = json_decode(file_get_contents('php://input'), true);
    $vehicles = get_vehicles();

    if ($action === 'complete_exit') {
        $id = $input['id'];
        $amountPaid = $input['amountPaid'];
        $paymentMethod = $input['paymentMethod'];

        $found = false;
        foreach ($vehicles as &$v) {
            if ($v['id'] === $id) {
                $v['status'] = 'paid';
                $v['exitTime'] = (new DateTime())->format(DateTime::ATOM);
                $v['amountPaid'] = $amountPaid;
                $v['paymentMethod'] = $paymentMethod;
                $found = true;
                break;
            }
        }
        if ($found) {
            save_vehicles($vehicles);
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Vehicle not found']);
        }
        exit;
    }
    echo json_encode(['success' => false, 'error' => 'Unknown action']);
    exit;
}

// Handle standard POST requests (form submissions)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $view = $_GET['view'] ?? 'operational';

    if ($view === 'operational' && isset($_POST['plate']) && !empty($_POST['plate'])) {
        $vehicles = get_vehicles();
        $new_vehicle = [
            'id' => uniqid('v_'),
            'plate' => strtoupper(trim($_POST['plate'])),
            'model' => $_POST['model'],
            'color' => $_POST['color'],
            'entryTime' => (new DateTime())->format(DateTime::ATOM),
            'status' => 'parked',
        ];
        $vehicles[] = $new_vehicle;
        save_vehicles($vehicles);
        header('Location: ' . $_SERVER['PHP_SELF']); // Redirect to avoid form resubmission
        exit;
    }

    if ($view === 'admin') {
        $settings = get_settings();
        $new_settings = array_merge($settings, $_POST);
        // Cast numeric values
        $new_settings['hourlyRate'] = (float)$new_settings['hourlyRate'];
        $new_settings['toleranceMinutes'] = (int)$new_settings['toleranceMinutes'];
        $new_settings['fractionRate'] = (float)$new_settings['fractionRate'];
        $new_settings['fractionLimitMinutes'] = (int)$new_settings['fractionLimitMinutes'];
        save_settings($new_settings);
        header('Location: ' . $_SERVER['PHP_SELF'] . '?view=admin');
        exit;
    }
}

// --- DATA FOR PAGE RENDER ---
$settings = get_settings();
$vehicles = get_vehicles();
$current_view = $_GET['view'] ?? 'operational';

// --- START HTML ---
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>estacionamento - Gerenciador de Estacionamento</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
      tailwind.config = { darkMode: 'class' }
    </script>
    <script src='https://unpkg.com/tesseract.js@5/dist/tesseract.min.js'></script>
    <script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
    <style>
        .modal { display: none; }
        .modal.is-open { display: flex; }
    </style>
<link rel="stylesheet" href="/index.css">
</head>
<body class="bg-slate-100 dark:bg-slate-900">
    <div id="app" class="min-h-screen font-sans">
      <header class="bg-white shadow-md dark:bg-slate-800 dark:border-b dark:border-slate-700">
        <div class="container mx-auto px-4 py-4 flex flex-col sm:flex-row justify-between items-center">
          <h1 class="text-2xl font-bold text-slate-800 dark:text-slate-100 mb-4 sm:mb-0">estacionamento</h1>
          <div class="flex items-center gap-4">
            <nav class="flex items-center space-x-2 p-1 bg-slate-200 dark:bg-slate-700 rounded-lg">
              <?php
                $views = ['operational' => 'Operacional', 'reports' => 'Relatórios', 'admin' => 'Configurações'];
                foreach ($views as $view_id => $view_label) {
                    $is_active = $current_view === $view_id;
                    $classes = $is_active
                        ? 'bg-blue-600 text-white shadow'
                        : 'bg-white text-slate-600 hover:bg-slate-100 dark:bg-slate-600 dark:text-slate-200 dark:hover:bg-slate-500';
                    echo "<a href=\"?view=$view_id\" class=\"px-4 py-2 text-sm sm:text-base font-semibold rounded-md transition-colors $classes\">$view_label</a>";
                }
              ?>
            </nav>
            <button id="theme-toggle" class="p-2 rounded-full text-slate-500 hover:bg-slate-200 dark:text-slate-400 dark:hover:bg-slate-700 transition-colors" aria-label="Toggle theme">
                <svg id="theme-icon-sun" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-6 w-6"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2.25m6.364.386l-1.591 1.591M21 12h-2.25m-.386 6.364l-1.591-1.591M12 18.75V21m-4.95-4.243l-1.59-1.59M3 12H.75m4.243-4.95l1.59-1.59"></path><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 12a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0z"></path></svg>
                <svg id="theme-icon-moon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-6 w-6 hidden"><path stroke-linecap="round" stroke-linejoin="round" d="M21.752 15.002A9.718 9.718 0 0118 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 003 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 009.002-5.998z"></path></svg>
            </button>
          </div>
        </div>
      </header>
      <main class="container mx-auto p-4 sm:p-8">
        <?php if ($current_view === 'operational'): ?>
            <!-- Operational View -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <!-- Entry Panel -->
                <div class="lg:col-span-1">
                    <div class="bg-white dark:bg-slate-800 p-6 rounded-lg shadow-md">
                        <h2 class="text-xl font-bold text-slate-800 dark:text-slate-100 mb-4">Registrar Entrada</h2>
                        <!-- Entry Form -->
                        <form method="POST" action="?view=operational" class="space-y-4">
                            <div class="relative">
                                <label for="plate" class="block text-sm font-medium text-slate-700 dark:text-slate-300">Placa do Veículo</label>
                                <input type="text" id="plate" name="plate" class="mt-1 block w-full px-3 py-2 pr-12 border border-slate-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-slate-700 dark:border-slate-600 dark:text-white dark:placeholder:text-slate-400" placeholder="AAA-1234" required>
                                <button type="button" id="open-scanner-btn" class="absolute inset-y-0 right-0 top-6 flex items-center px-3 text-slate-500 hover:text-blue-600 dark:text-slate-400 dark:hover:text-blue-400" aria-label="Escanear placa">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-6 w-6"><path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 015.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 00-1.134-.175 2.31 2.31 0 01-1.64-1.055l-.822-1.316a2.192 2.192 0 00-1.736-1.039 48.776 48.776 0 00-5.232 0 2.192 2.192 0 00-1.736 1.039l-.821 1.316z"></path><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 11-9 0 4.5 4.5 0 019 0zM18.75 10.5h.008v.008h-.008V10.5z"></path></svg>
                                </button>
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <?php
                                $car_models = ["Outro", "VW Gol", "Fiat Uno", "Chevrolet Onix", "Hyundai HB20", "Ford Ka", "Toyota Corolla", "Honda Civic", "Jeep Renegade", "Renault Kwid"];
                                $car_colors = ["Outra", "Prata", "Preto", "Branco", "Cinza", "Vermelho", "Azul", "Marrom"];
                                ?>
                                <div>
                                    <label for="model" class="block text-sm font-medium text-slate-700 dark:text-slate-300">Modelo</label>
                                    <select id="model" name="model" class="mt-1 block w-full px-3 py-2 border border-slate-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-slate-700 dark:border-slate-600 dark:text-white">
                                        <?php foreach($car_models as $m) echo "<option value=\"$m\">$m</option>"; ?>
                                    </select>
                                </div>
                                <div>
                                    <label for="color" class="block text-sm font-medium text-slate-700 dark:text-slate-300">Cor</label>
                                    <select id="color" name="color" class="mt-1 block w-full px-3 py-2 border border-slate-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-slate-700 dark:border-slate-600 dark:text-white">
                                        <?php foreach($car_colors as $c) echo "<option value=\"$c\">$c</option>"; ?>
                                    </select>
                                </div>
                            </div>
                            <button type="submit" class="w-full bg-blue-600 text-white font-bold py-3 px-4 rounded-lg shadow-md hover:bg-blue-700 transition-colors">Registrar Entrada</button>
                        </form>
                    </div>
                </div>
                <!-- Patio Panel -->
                <div class="lg:col-span-2">
                    <div class="bg-white dark:bg-slate-800 p-6 rounded-lg shadow-md">
                        <?php
                            $parked_vehicles = array_filter($vehicles, fn($v) => $v['status'] === 'parked');
                        ?>
                        <div class="flex flex-col sm:flex-row justify-between sm:items-center mb-4 gap-4">
                            <h2 class="text-xl font-bold text-slate-800 dark:text-slate-100">Veículos no Pátio (<?= count($parked_vehicles) ?>)</h2>
                            <input type="text" id="search-plate" placeholder="Buscar placa..." class="w-full sm:w-auto px-3 py-2 border border-slate-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-slate-700 dark:border-slate-600 dark:text-white dark:placeholder:text-slate-400">
                        </div>
                        <div id="vehicle-list" class="space-y-3">
                            <?php if (count($parked_vehicles) > 0): ?>
                                <?php foreach (array_reverse($parked_vehicles) as $v): ?>
                                <div class="vehicle-item bg-white dark:bg-slate-700 p-4 rounded-lg shadow flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4" data-plate="<?= htmlspecialchars(strtoupper($v['plate'])) ?>">
                                    <div class="flex-1">
                                        <p class="font-mono text-xl font-bold text-slate-800 dark:text-slate-100"><?= htmlspecialchars($v['plate']) ?></p>
                                        <p class="text-sm text-slate-500 dark:text-slate-400"><?= htmlspecialchars($v['model']) ?> - <?= htmlspecialchars($v['color']) ?></p>
                                    </div>
                                    <div class="text-left sm:text-right">
                                        <p class="text-sm text-slate-500 dark:text-slate-400">Entrada:</p>
                                        <p class="font-semibold text-slate-700 dark:text-slate-200"><?= (new DateTime($v['entryTime']))->format('d/m/Y H:i:s') ?></p>
                                    </div>
                                    <button class="register-exit-btn w-full sm:w-auto bg-green-500 text-white font-semibold py-2 px-4 rounded-md shadow-sm hover:bg-green-600 transition-colors" data-vehicle='<?= htmlspecialchars(json_encode($v), ENT_QUOTES, 'UTF-8') ?>'>
                                        Registrar Saída
                                    </button>
                                </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="text-center text-slate-500 dark:text-slate-400 py-8">Pátio vazio.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Modals -->
            <!-- Plate Scanner Modal -->
            <div id="plate-scanner-modal" class="modal fixed inset-0 bg-black bg-opacity-75 items-center justify-center z-50 p-4">
                <div class="bg-white dark:bg-slate-800 rounded-lg shadow-xl p-4 sm:p-6 w-full max-w-lg flex flex-col max-h-full">
                    <div class="flex justify-between items-center mb-4 flex-shrink-0">
                        <h2 class="text-xl font-bold text-slate-800 dark:text-slate-100">Escanear Placa</h2>
                        <button id="close-scanner-btn" class="text-slate-400 hover:text-slate-600">
                             <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-6 w-6"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"></path></svg>
                        </button>
                    </div>
                    <div class="relative w-full aspect-video bg-black rounded overflow-hidden mb-4">
                        <video id="scanner-video" class="w-full h-full object-cover" playsinline></video>
                        <div class="absolute inset-0 flex items-center justify-center">
                            <div class="w-10/12 h-1/2 border-4 border-dashed border-yellow-400 opacity-75 rounded-md"></div>
                        </div>
                        <canvas id="scanner-canvas" class="hidden"></canvas>
                    </div>
                    <div class="bg-slate-100 dark:bg-slate-700 p-3 rounded-md text-center mb-4 flex-shrink-0">
                        <p id="scanner-status" class="text-sm font-semibold text-slate-700 dark:text-slate-200 h-5">Aguardando câmera...</p>
                        <div id="scanner-progress-bar-container" class="w-full bg-slate-200 rounded-full h-2.5 mt-2 hidden">
                            <div id="scanner-progress-bar" class="bg-blue-600 h-2.5 rounded-full" style="width: 0%"></div>
                        </div>
                        <p id="scanner-recognized-text" class="text-2xl font-mono tracking-widest bg-white dark:bg-slate-600 p-2 mt-2 rounded border border-slate-300 hidden"></p>
                    </div>
                    <div class="mt-auto grid grid-cols-1 sm:grid-cols-2 gap-4 flex-shrink-0">
                        <button id="scanner-capture-btn" disabled class="w-full bg-blue-600 text-white font-semibold py-3 px-4 rounded-md shadow-sm hover:bg-blue-700 disabled:bg-slate-400 disabled:cursor-not-allowed transition-colors">Capturar Imagem</button>
                        <button id="scanner-use-plate-btn" disabled class="w-full bg-green-600 text-white font-semibold py-3 px-4 rounded-md shadow-sm hover:bg-green-700 disabled:bg-slate-400 disabled:cursor-not-allowed transition-colors">Usar Placa</button>
                    </div>
                </div>
            </div>
            <!-- Vehicle Exit Modal -->
            <div id="vehicle-exit-modal" class="modal fixed inset-0 bg-black bg-opacity-75 items-center justify-center z-50 p-4">
                <div class="bg-white dark:bg-slate-800 p-6 rounded-lg shadow-md max-w-2xl mx-auto w-full">
                    <div class="flex items-center mb-6">
                        <button id="exit-modal-back-btn" class="mr-4 p-2 rounded-full hover:bg-slate-100 dark:hover:bg-slate-700">
                             <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-6 w-6 text-slate-600 dark:text-slate-300"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"></path></svg>
                        </button>
                        <h2 class="text-2xl font-bold text-slate-800 dark:text-slate-100">Registrar Saída</h2>
                    </div>
                    <div class="bg-slate-50 dark:bg-slate-700 p-4 rounded-lg mb-6">
                        <p id="exit-modal-plate" class="font-mono text-3xl font-bold text-slate-800 dark:text-slate-100 text-center mb-2"></p>
                        <p id="exit-modal-model-color" class="text-md text-slate-600 dark:text-slate-400 text-center"></p>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-center mb-6">
                        <div><p class="text-sm text-slate-500 dark:text-slate-400">Entrada</p><p id="exit-modal-entry-time" class="font-semibold text-lg text-slate-800 dark:text-slate-100"></p></div>
                        <div><p class="text-sm text-slate-500 dark:text-slate-400">Saída</p><p id="exit-modal-exit-time" class="font-semibold text-lg text-slate-800 dark:text-slate-100"></p></div>
                        <div><p class="text-sm text-slate-500 dark:text-slate-400">Permanência</p><p id="exit-modal-duration" class="font-semibold text-lg text-slate-800 dark:text-slate-100"></p></div>
                    </div>
                    
                    <div id="exit-modal-step-select">
                        <div class="text-center bg-blue-50 dark:bg-blue-900/20 border-2 border-blue-200 dark:border-blue-800 rounded-lg p-6 mb-6">
                            <p class="text-lg text-blue-800 dark:text-blue-300 font-medium">Valor a Pagar</p>
                            <p id="exit-modal-total" class="text-5xl font-bold text-blue-900 dark:text-blue-100 tracking-tight"></p>
                        </div>
                        <div class="mb-6">
                            <p class="text-lg font-medium text-slate-700 dark:text-slate-300 mb-3 text-center">Forma de Pagamento</p>
                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                                <button data-method="pix" class="payment-method-btn p-4 rounded-lg font-semibold border-2 bg-white dark:bg-slate-600 text-slate-700 dark:text-slate-200 border-slate-300 dark:border-slate-500 hover:border-blue-500 hover:bg-blue-50 dark:hover:bg-slate-500">PIX</button>
                                <button data-method="cash" class="payment-method-btn p-4 rounded-lg font-semibold border-2 bg-white dark:bg-slate-600 text-slate-700 dark:text-slate-200 border-slate-300 dark:border-slate-500 hover:border-blue-500 hover:bg-blue-50 dark:hover:bg-slate-500">Dinheiro</button>
                                <button data-method="card" class="payment-method-btn p-4 rounded-lg font-semibold border-2 bg-white dark:bg-slate-600 text-slate-700 dark:text-slate-200 border-slate-300 dark:border-slate-500 hover:border-blue-500 hover:bg-blue-50 dark:hover:bg-slate-500">Cartão</button>
                                <button data-method="convenio" class="payment-method-btn p-4 rounded-lg font-semibold border-2 bg-white dark:bg-slate-600 text-slate-700 dark:text-slate-200 border-slate-300 dark:border-slate-500 hover:border-blue-500 hover:bg-blue-50 dark:hover:bg-slate-500">Convênio</button>
                            </div>
                        </div>
                    </div>
                    
                    <div id="exit-modal-step-awaiting" class="hidden text-center">
                        <div id="payment-awaiting-pix" class="hidden">
                           <h3 class="text-xl font-bold text-slate-800 dark:text-slate-100 mb-2">Pagar com PIX</h3>
                           <p class="text-slate-600 dark:text-slate-400 mb-4">Escaneie o QR Code com o app do seu banco.</p>
                           <div id="pix-qrcode" class="flex justify-center mb-4 p-2 bg-white border rounded-lg w-fit mx-auto"></div>
                           <div class="mb-4">
                               <p class="text-sm font-semibold text-slate-500 dark:text-slate-400 mb-1">Ou copie o código:</p>
                               <input type="text" readonly id="pix-payload" class="w-full text-center bg-slate-100 dark:bg-slate-700 p-2 border rounded-md text-xs">
                           </div>
                        </div>
                        <div id="payment-awaiting-card" class="hidden">
                           <h3 class="text-xl font-bold text-slate-800 dark:text-slate-100 mb-2">Pagamento com Cartão</h3>
                           <p class="text-slate-600 dark:text-slate-400 mb-4">Insira o cartão na maquininha e aguarde a aprovação.</p>
                        </div>
                        <div id="payment-awaiting-cash" class="hidden">
                           <h3 class="text-xl font-bold text-slate-800 dark:text-slate-100 mb-2">Pagamento em Dinheiro</h3>
                           <p class="text-slate-600 dark:text-slate-400 mb-4">Aguardando recebimento do valor em espécie.</p>
                        </div>
                        <p id="payment-awaiting-spinner" class="text-lg font-semibold text-slate-600 dark:text-slate-300 my-6 flex items-center justify-center hidden">
                           <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                           Aguardando confirmação bancária...
                        </p>
                        <p id="payment-awaiting-attendant" class="text-lg font-semibold text-slate-600 dark:text-slate-300 mb-6 hidden">Aguardando confirmação do atendente.</p>
                        <div class="flex flex-col sm:flex-row gap-3">
                           <button id="payment-confirm-cash-btn" class="w-full bg-green-600 text-white font-bold py-3 px-4 rounded-lg shadow-md hover:bg-green-700 hidden">Confirmar Recebimento</button>
                           <button id="payment-cancel-btn" class="w-full bg-slate-500 text-white font-bold py-3 px-4 rounded-lg shadow-md hover:bg-slate-600">Cancelar / Voltar</button>
                        </div>
                    </div>

                    <div id="exit-modal-step-receipt" class="hidden text-center">
                        <h3 class="text-2xl font-bold text-green-600 mb-4">Pagamento Confirmado!</h3>
                        <div class="bg-slate-50 dark:bg-slate-700 p-4 rounded-lg space-y-2 text-left mb-6">
                            <p><strong>Placa:</strong> <span id="receipt-plate" class="font-mono"></span></p>
                            <p><strong>Valor Pago:</strong> <span id="receipt-amount"></span></p>
                            <p><strong>Forma de Pagamento:</strong> <span id="receipt-method" class="capitalize"></span></p>
                            <p><strong>Entrada:</strong> <span id="receipt-entry-time"></span></p>
                            <p><strong>Saída:</strong> <span id="receipt-exit-time"></span></p>
                        </div>
                        <button id="receipt-close-btn" class="w-full bg-blue-600 text-white font-bold py-3 px-4 rounded-lg shadow-md hover:bg-blue-700">Voltar para o Pátio</button>
                    </div>
                </div>
            </div>
            
        <?php elseif ($current_view === 'reports'): ?>
            <!-- Reports View -->
            <?php
                $active_filter = $_GET['period'] ?? 'today';
                $now = new DateTime();
                $start_date = new DateTime();
                $title = 'Relatório do Dia';

                switch ($active_filter) {
                    case 'today': $start_date->setTime(0, 0, 0); $title = "Relatório do Dia - {$now->format('d/m/Y')}"; break;
                    case '7days': $start_date->modify('-6 days')->setTime(0, 0, 0); $title = 'Relatório - Últimos 7 Dias'; break;
                    case '15days': $start_date->modify('-14 days')->setTime(0, 0, 0); $title = 'Relatório - Últimos 15 Dias'; break;
                    case '30days': $start_date->modify('-29 days')->setTime(0, 0, 0); $title = 'Relatório - Últimos 30 Dias'; break;
                }

                $filtered = array_filter($vehicles, fn($v) =>
                    $v['status'] === 'paid' &&
                    isset($v['exitTime']) &&
                    (new DateTime($v['exitTime'])) >= $start_date
                );

                $total_revenue = array_reduce($filtered, fn($sum, $v) => $sum + ($v['amountPaid'] ?? 0), 0);

                $revenue_by_method = [];
                foreach ($filtered as $v) {
                    if (isset($v['paymentMethod'])) {
                        $method = $v['paymentMethod'];
                        $revenue_by_method[$method] = ($revenue_by_method[$method] ?? 0) + ($v['amountPaid'] ?? 0);
                    }
                }
                
                $total_stay_minutes = array_reduce($filtered, function($sum, $v) {
                    if (isset($v['exitTime'])) {
                        $duration = (new DateTime($v['exitTime']))->getTimestamp() - (new DateTime($v['entryTime']))->getTimestamp();
                        return $sum + ($duration / 60);
                    }
                    return $sum;
                }, 0);

                $avg_minutes = count($filtered) > 0 ? $total_stay_minutes / count($filtered) : 0;
                $avg_hours = floor($avg_minutes / 60);
                $avg_mins = round($avg_minutes % 60);
                $average_stay = "{$avg_hours}h {$avg_mins}m";

                $payment_method_labels = ['pix' => 'PIX', 'cash' => 'Dinheiro', 'card' => 'Cartão', 'convenio' => 'Convênio'];
            ?>
             <div class="space-y-8">
                <div class="flex flex-col sm:flex-row justify-between sm:items-center gap-4">
                    <h2 class="text-2xl font-bold text-slate-800 dark:text-slate-100"><?= $title ?></h2>
                    <div class="flex items-center space-x-2 p-1 bg-slate-100 dark:bg-slate-700 rounded-lg">
                        <?php
                            $filters = ['today' => 'Hoje', '7days' => '7 Dias', '15days' => '15 Dias', '30days' => '30 Dias'];
                            foreach($filters as $period => $label) {
                                $is_active = $active_filter === $period;
                                $classes = $is_active ? 'bg-blue-600 text-white shadow-sm' : 'bg-white dark:bg-slate-600 text-slate-600 dark:text-slate-200 hover:bg-slate-100 border dark:border-slate-500';
                                echo "<a href=\"?view=reports&period=$period\" class=\"px-4 py-2 text-sm font-semibold rounded-md transition-colors $classes\">$label</a>";
                            }
                        ?>
                    </div>
                </div>
                
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                    <div class="bg-white dark:bg-slate-800 p-6 rounded-lg shadow-md"><p class="text-sm font-medium text-slate-500 dark:text-slate-400 mb-1">Total Arrecadado</p><p class="text-3xl font-bold text-slate-800 dark:text-slate-100">R$ <?= number_format($total_revenue, 2, ',', '.') ?></p></div>
                    <div class="bg-white dark:bg-slate-800 p-6 rounded-lg shadow-md"><p class="text-sm font-medium text-slate-500 dark:text-slate-400 mb-1">Veículos (Saída)</p><p class="text-3xl font-bold text-slate-800 dark:text-slate-100"><?= count($filtered) ?></p></div>
                    <div class="bg-white dark:bg-slate-800 p-6 rounded-lg shadow-md"><p class="text-sm font-medium text-slate-500 dark:text-slate-400 mb-1">Permanência Média</p><p class="text-3xl font-bold text-slate-800 dark:text-slate-100"><?= $average_stay ?></p></div>
                    <div class="bg-white dark:bg-slate-800 p-6 rounded-lg shadow-md"><p class="text-sm font-medium text-slate-500 dark:text-slate-400 mb-1">PIX</p><p class="text-3xl font-bold text-slate-800 dark:text-slate-100">R$ <?= number_format($revenue_by_method['pix'] ?? 0, 2, ',', '.') ?></p></div>
                </div>

                <div class="bg-white dark:bg-slate-800 p-6 rounded-lg shadow-md">
                    <h3 class="text-xl font-bold text-slate-800 dark:text-slate-100 mb-4">Saídas Registradas no Período</h3>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm text-left text-slate-500 dark:text-slate-400">
                            <thead class="text-xs text-slate-700 dark:text-slate-300 uppercase bg-slate-100 dark:bg-slate-700">
                                <tr>
                                    <th scope="col" class="px-6 py-3">Placa</th>
                                    <th scope="col" class="px-6 py-3">Entrada</th>
                                    <th scope="col" class="px-6 py-3">Saída</th>
                                    <th scope="col" class="px-6 py-3">Valor Pago</th>
                                    <th scope="col" class="px-6 py-3">Pagamento</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if (count($filtered) > 0): ?>
                                <?php foreach (array_reverse($filtered) as $v): ?>
                                <tr class="bg-white dark:bg-slate-800 border-b dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-600">
                                    <td class="px-6 py-4 font-mono font-semibold text-slate-900 dark:text-white"><?= htmlspecialchars($v['plate']) ?></td>
                                    <td class="px-6 py-4"><?= (new DateTime($v['entryTime']))->format('d/m/Y H:i:s') ?></td>
                                    <td class="px-6 py-4"><?= (new DateTime($v['exitTime']))->format('d/m/Y H:i:s') ?></td>
                                    <td class="px-6 py-4">R$ <?= number_format($v['amountPaid'] ?? 0, 2, ',', '.') ?></td>
                                    <td class="px-6 py-4 capitalize"><?= $v['paymentMethod'] ? htmlspecialchars($payment_method_labels[$v['paymentMethod']]) : '-' ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="5" class="text-center py-10 text-slate-500 dark:text-slate-400">Nenhuma saída registrada no período.</td></tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        <?php elseif ($current_view === 'admin'): ?>
            <!-- Admin View -->
             <div class="bg-white dark:bg-slate-800 p-6 rounded-lg shadow-md max-w-3xl mx-auto">
                <h2 class="text-xl font-bold text-slate-800 dark:text-slate-100 mb-6">Configurações do Estacionamento</h2>
                <form method="POST" action="?view=admin" class="space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="space-y-4 p-4 border dark:border-slate-700 rounded-lg">
                            <h3 class="font-semibold text-slate-700 dark:text-slate-200">Precificação</h3>
                            <div>
                                <label for="hourlyRate" class="block text-sm font-medium text-slate-700 dark:text-slate-300">Valor da Hora (R$)</label>
                                <input type="number" step="0.01" name="hourlyRate" id="hourlyRate" value="<?= htmlspecialchars($settings['hourlyRate']) ?>" class="mt-1 block w-full px-3 py-2 border border-slate-300 rounded-md shadow-sm dark:bg-slate-700 dark:border-slate-600 dark:text-white">
                            </div>
                            <div>
                                <label for="fractionRate" class="block text-sm font-medium text-slate-700 dark:text-slate-300">Valor da Fração (R$)</label>
                                <input type="number" step="0.01" name="fractionRate" id="fractionRate" value="<?= htmlspecialchars($settings['fractionRate']) ?>" class="mt-1 block w-full px-3 py-2 border border-slate-300 rounded-md shadow-sm dark:bg-slate-700 dark:border-slate-600 dark:text-white">
                            </div>
                        </div>
                        <div class="space-y-4 p-4 border dark:border-slate-700 rounded-lg">
                            <h3 class="font-semibold text-slate-700 dark:text-slate-200">Regras de Tempo</h3>
                            <div>
                                <label for="toleranceMinutes" class="block text-sm font-medium text-slate-700 dark:text-slate-300">Minutos de Tolerância</label>
                                <input type="number" name="toleranceMinutes" id="toleranceMinutes" value="<?= htmlspecialchars($settings['toleranceMinutes']) ?>" class="mt-1 block w-full px-3 py-2 border border-slate-300 rounded-md shadow-sm dark:bg-slate-700 dark:border-slate-600 dark:text-white">
                            </div>
                            <div>
                                <label for="fractionLimitMinutes" class="block text-sm font-medium text-slate-700 dark:text-slate-300">Limite da Fração (Minutos)</label>
                                <input type="number" name="fractionLimitMinutes" id="fractionLimitMinutes" value="<?= htmlspecialchars($settings['fractionLimitMinutes']) ?>" class="mt-1 block w-full px-3 py-2 border border-slate-300 rounded-md shadow-sm dark:bg-slate-700 dark:border-slate-600 dark:text-white">
                            </div>
                        </div>
                    </div>
                    <div class="space-y-4 p-4 border dark:border-slate-700 rounded-lg">
                        <h3 class="font-semibold text-slate-700 dark:text-slate-200">Configurações PIX</h3>
                        <div>
                            <label for="pixKey" class="block text-sm font-medium text-slate-700 dark:text-slate-300">Chave PIX</label>
                            <input type="text" name="pixKey" id="pixKey" value="<?= htmlspecialchars($settings['pixKey']) ?>" class="mt-1 block w-full px-3 py-2 border border-slate-300 rounded-md shadow-sm dark:bg-slate-700 dark:border-slate-600 dark:text-white">
                        </div>
                        <div>
                            <label for="pixHolderName" class="block text-sm font-medium text-slate-700 dark:text-slate-300">Nome do Titular</label>
                            <input type="text" name="pixHolderName" id="pixHolderName" value="<?= htmlspecialchars($settings['pixHolderName']) ?>" class="mt-1 block w-full px-3 py-2 border border-slate-300 rounded-md shadow-sm dark:bg-slate-700 dark:border-slate-600 dark:text-white">
                        </div>
                        <div>
                            <label for="pixHolderCity" class="block text-sm font-medium text-slate-700 dark:text-slate-300">Cidade do Titular</label>
                            <input type="text" name="pixHolderCity" id="pixHolderCity" value="<?= htmlspecialchars($settings['pixHolderCity']) ?>" class="mt-1 block w-full px-3 py-2 border border-slate-300 rounded-md shadow-sm dark:bg-slate-700 dark:border-slate-600 dark:text-white">
                        </div>
                    </div>
                    <button type="submit" class="w-full bg-blue-600 text-white font-bold py-3 px-4 rounded-lg shadow-md hover:bg-blue-700">Salvar Alterações</button>
                </form>
            </div>
        <?php endif; ?>
      </main>
    </div>
    <script>
        // Pass PHP settings to JavaScript
        const SETTINGS = <?= json_encode($settings) ?>;
    </script>
    <script src="App.tsx"></script> <!-- This should be renamed to script.js -->
<script type="module" src="/index.tsx"></script>
</body>
</html>
