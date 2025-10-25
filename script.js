// RENAME THIS FILE to script.js and place it in the same directory as index.php

// Fix: Declare Tesseract, QRCode, and SETTINGS as global variables to resolve 'Cannot find name' errors.
// These are expected to be available in the global scope from script tags in the HTML.
declare var Tesseract: any;
declare var QRCode: any;
declare var SETTINGS: any;

document.addEventListener('DOMContentLoaded', () => {

  // --- THEME SWITCHER ---
  const themeToggle = document.getElementById('theme-toggle');
  const sunIcon = document.getElementById('theme-icon-sun');
  const moonIcon = document.getElementById('theme-icon-moon');
  const htmlEl = document.documentElement;

  const savedTheme = localStorage.getItem('theme') || 'light';
  htmlEl.classList.add(savedTheme);
  if (savedTheme === 'dark') {
      sunIcon.classList.add('hidden');
      moonIcon.classList.remove('hidden');
  } else {
      sunIcon.classList.remove('hidden');
      moonIcon.classList.add('hidden');
  }

  themeToggle.addEventListener('click', () => {
    if (htmlEl.classList.contains('dark')) {
      htmlEl.classList.remove('dark');
      htmlEl.classList.add('light');
      localStorage.setItem('theme', 'light');
      sunIcon.classList.remove('hidden');
      moonIcon.classList.add('hidden');
    } else {
      htmlEl.classList.remove('light');
      htmlEl.classList.add('dark');
      localStorage.setItem('theme', 'dark');
      sunIcon.classList.add('hidden');
      moonIcon.classList.remove('hidden');
    }
  });


  // --- OPERATIONAL VIEW ---
  const searchInput = document.getElementById('search-plate');
  if (searchInput) {

    // Plate search
    const vehicleList = document.getElementById('vehicle-list');
    searchInput.addEventListener('input', (e) => {
        // Fix: Cast e.target to HTMLInputElement to access the 'value' property.
        const query = (e.target as HTMLInputElement).value.toUpperCase();
        vehicleList.querySelectorAll('.vehicle-item').forEach(item => {
            // Fix: Cast item to HTMLElement to access 'dataset' and 'style' properties.
            const vehicleItem = item as HTMLElement;
            if (vehicleItem.dataset.plate.includes(query)) {
                vehicleItem.style.display = 'flex';
            } else {
                vehicleItem.style.display = 'none';
            }
        });
    });

    // --- Plate Scanner Modal ---
    const scannerModal = document.getElementById('plate-scanner-modal');
    const openScannerBtn = document.getElementById('open-scanner-btn');
    const closeScannerBtn = document.getElementById('close-scanner-btn');
    // Fix: Cast videoEl to HTMLVideoElement to access video-specific properties like 'srcObject', 'play', 'videoWidth', and 'videoHeight'.
    const videoEl = document.getElementById('scanner-video') as HTMLVideoElement;
    // Fix: Cast canvasEl to HTMLCanvasElement to access canvas-specific properties like 'getContext', 'width', and 'height'.
    const canvasEl = document.getElementById('scanner-canvas') as HTMLCanvasElement;
    const statusEl = document.getElementById('scanner-status');
    const progressContainer = document.getElementById('scanner-progress-bar-container');
    const progressBar = document.getElementById('scanner-progress-bar') as HTMLElement;
    const recognizedTextEl = document.getElementById('scanner-recognized-text') as HTMLElement;
    // Fix: Cast captureBtn to HTMLButtonElement to access the 'disabled' property.
    const captureBtn = document.getElementById('scanner-capture-btn') as HTMLButtonElement;
    // Fix: Cast usePlateBtn to HTMLButtonElement to access the 'disabled' property.
    const usePlateBtn = document.getElementById('scanner-use-plate-btn') as HTMLButtonElement;
    // Fix: Cast plateInput to HTMLInputElement to access the 'value' property.
    const plateInput = document.getElementById('plate') as HTMLInputElement;
    
    let tesseractWorker = null;

    const initializeWorker = async () => {
      statusEl.textContent = 'Carregando modelo de OCR...';
      captureBtn.disabled = true;
      progressContainer.style.display = 'block';

      tesseractWorker = await Tesseract.createWorker('por', 1, {
        logger: m => {
          if (m.status === 'recognizing text') {
            progressBar.style.width = `${m.progress * 100}%`;
          }
        },
      });
      await tesseractWorker.setParameters({
        tessedit_char_whitelist: 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789-',
      });
      statusEl.textContent = 'Modelo carregado.';
      progressContainer.style.display = 'none';
      captureBtn.disabled = false;
    };
    
    const startCamera = async () => {
        try {
            statusEl.textContent = 'Iniciando câmera...';
            const stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } });
            videoEl.srcObject = stream;
            await videoEl.play();
            statusEl.textContent = 'Câmera pronta. Posicione a placa.';
        } catch (err) {
            statusEl.textContent = 'Erro ao acessar câmera.';
            console.error(err);
        }
    };

    const cleanupCamera = () => {
        if (videoEl.srcObject) {
            videoEl.srcObject.getTracks().forEach(track => track.stop());
            videoEl.srcObject = null;
        }
    };
    
    openScannerBtn.addEventListener('click', async () => {
      scannerModal.classList.add('is-open');
      if (!tesseractWorker) {
          await initializeWorker();
      }
      await startCamera();
    });

    const closeModal = () => {
        scannerModal.classList.remove('is-open');
        cleanupCamera();
    }
    closeScannerBtn.addEventListener('click', closeModal);

    captureBtn.addEventListener('click', async () => {
        if (!tesseractWorker || !videoEl.videoWidth || !canvasEl) return;
        
        statusEl.textContent = 'Analisando imagem...';
        captureBtn.disabled = true;
        usePlateBtn.disabled = true;
        recognizedTextEl.style.display = 'none';
        progressContainer.style.display = 'block';
        progressBar.style.width = '0%';
        
        const context = canvasEl.getContext('2d');
        canvasEl.width = videoEl.videoWidth;
        canvasEl.height = videoEl.videoHeight;
        context.drawImage(videoEl, 0, 0, canvasEl.width, canvasEl.height);
        
        const { data: { text } } = await tesseractWorker.recognize(canvasEl);
        const cleanedText = text.replace(/[^A-Z0-9-]/gi, '').toUpperCase().trim();
        
        recognizedTextEl.textContent = cleanedText;
        recognizedTextEl.style.display = 'block';
        statusEl.textContent = 'Reconhecimento concluído.';
        progressContainer.style.display = 'none';
        
        captureBtn.disabled = false;
        if (cleanedText) {
            usePlateBtn.disabled = false;
        }
    });

    usePlateBtn.addEventListener('click', () => {
        plateInput.value = recognizedTextEl.textContent;
        closeModal();
    });


    // --- Vehicle Exit Modal ---
    const exitModal = document.getElementById('vehicle-exit-modal');
    const backBtn = document.getElementById('exit-modal-back-btn');
    const cancelPaymentBtn = document.getElementById('payment-cancel-btn');
    const confirmCashBtn = document.getElementById('payment-confirm-cash-btn');
    const receiptCloseBtn = document.getElementById('receipt-close-btn');

    const steps = {
        select: document.getElementById('exit-modal-step-select'),
        awaiting: document.getElementById('exit-modal-step-awaiting'),
        receipt: document.getElementById('exit-modal-step-receipt'),
    };
    
    let currentVehicle = null;
    let totalToPay = 0;
    let selectedMethod = null;
    let timerInterval = null;
    let currentStep = 'select';

    const showStep = (stepName) => {
        Object.values(steps).forEach(s => s.style.display = 'none');
        steps[stepName].style.display = 'block';
        currentStep = stepName;
    };

    const updateTimeAndCost = () => {
        if (!currentVehicle) return;

        const entry = new Date(currentVehicle.entryTime);
        const now = new Date();
        const durationMs = now.getTime() - entry.getTime();
        const totalMinutes = Math.max(1, Math.ceil(durationMs / 60000));
        
        const hours = Math.floor(totalMinutes / 60);
        const minutes = totalMinutes % 60;
        
        document.getElementById('exit-modal-duration').textContent = `${hours}h ${minutes}m`;
        document.getElementById('exit-modal-exit-time').textContent = now.toLocaleTimeString('pt-BR');

        let calculatedPay = 0;
        if (totalMinutes <= 60) {
            calculatedPay = SETTINGS.hourlyRate;
        } else {
            const fullHours = Math.floor(totalMinutes / 60);
            const remainingMinutes = totalMinutes % 60;
            if (remainingMinutes <= SETTINGS.toleranceMinutes) {
                calculatedPay = fullHours * SETTINGS.hourlyRate;
            } else if (remainingMinutes > SETTINGS.toleranceMinutes && remainingMinutes <= SETTINGS.fractionLimitMinutes) {
                calculatedPay = (fullHours * SETTINGS.hourlyRate) + SETTINGS.fractionRate;
            } else {
                calculatedPay = (fullHours + 1) * SETTINGS.hourlyRate;
            }
        }
        totalToPay = calculatedPay;
        document.getElementById('exit-modal-total').textContent = `R$ ${totalToPay.toFixed(2).replace('.', ',')}`;
    };

    vehicleList.addEventListener('click', e => {
        // Fix: Cast e.target to HTMLElement to access the 'closest' method.
        const exitButton = (e.target as HTMLElement).closest('.register-exit-btn');
        if (exitButton) {
            currentVehicle = JSON.parse((exitButton as HTMLElement).dataset.vehicle);
            
            document.getElementById('exit-modal-plate').textContent = currentVehicle.plate;
            document.getElementById('exit-modal-model-color').textContent = `${currentVehicle.model} - ${currentVehicle.color}`;
            document.getElementById('exit-modal-entry-time').textContent = new Date(currentVehicle.entryTime).toLocaleTimeString('pt-BR');

            updateTimeAndCost();
            timerInterval = setInterval(updateTimeAndCost, 1000);

            showStep('select');
            exitModal.classList.add('is-open');
        }
    });

    const closeExitModal = () => {
        clearInterval(timerInterval);
        currentVehicle = null;
        totalToPay = 0;
        selectedMethod = null;
        exitModal.classList.remove('is-open');
        showStep('select');
    };

    receiptCloseBtn.addEventListener('click', () => {
        closeExitModal();
        window.location.reload(); // Reload to update list
    });

    backBtn.addEventListener('click', () => {
        if (currentStep === 'select') {
            closeExitModal();
        } else {
            showStep('select'); // Go back from awaiting to select
        }
    });
    cancelPaymentBtn.addEventListener('click', () => showStep('select'));

    document.querySelectorAll('.payment-method-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            // Fix: Cast btn to HTMLElement to access the 'dataset' property.
            selectedMethod = (btn as HTMLElement).dataset.method;
            if (selectedMethod === 'convenio') {
                completeExit(0);
            } else {
                setupAwaitingStep(selectedMethod);
                showStep('awaiting');
            }
        });
    });

    const setupAwaitingStep = (method) => {
        ['pix', 'card', 'cash'].forEach(m => (document.getElementById(`payment-awaiting-${m}`) as HTMLElement).style.display = 'none');
        (document.getElementById(`payment-awaiting-${method}`) as HTMLElement).style.display = 'block';
        
        const isElectronic = method === 'pix' || method === 'card';
        (document.getElementById('payment-awaiting-spinner') as HTMLElement).style.display = isElectronic ? 'flex' : 'none';
        (document.getElementById('payment-awaiting-attendant') as HTMLElement).style.display = method === 'cash' ? 'block' : 'none';
        (document.getElementById('payment-confirm-cash-btn') as HTMLElement).style.display = method === 'cash' ? 'block' : 'none';
        
        if (method === 'pix') {
            generatePixQRCode();
        }
    };
    
    confirmCashBtn.addEventListener('click', () => completeExit(totalToPay));

    const completeExit = async (amount) => {
        try {
            const response = await fetch('?action=complete_exit', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    id: currentVehicle.id,
                    amountPaid: amount,
                    paymentMethod: selectedMethod,
                }),
            });
            const result = await response.json();
            if (result.success) {
                showReceipt(amount);
                showStep('receipt');
            } else {
                alert('Erro ao registrar saída.');
            }
        } catch (err) {
            console.error('Error completing exit:', err);
            alert('Erro de conexão.');
        }
    };

    const showReceipt = (amount) => {
        document.getElementById('receipt-plate').textContent = currentVehicle.plate;
        document.getElementById('receipt-amount').textContent = `R$ ${amount.toFixed(2).replace('.', ',')}`;
        document.getElementById('receipt-method').textContent = selectedMethod;
        document.getElementById('receipt-entry-time').textContent = new Date(currentVehicle.entryTime).toLocaleString('pt-BR');
        document.getElementById('receipt-exit-time').textContent = new Date().toLocaleString('pt-BR');
    };

    // PIX QR Code Generation Logic
    const generatePixQRCode = () => {
        const qrCodeEl = document.getElementById('pix-qrcode');
        qrCodeEl.innerHTML = '';
        const txid = currentVehicle.plate.replace(/[^A-Z0-9]/ig, '') + Date.now();
        const payload = generatePixPayload(SETTINGS.pixKey, SETTINGS.pixHolderName, SETTINGS.pixHolderCity, totalToPay, txid);
        
        // Fix: Cast the element to HTMLInputElement to access the 'value' property.
        (document.getElementById('pix-payload') as HTMLInputElement).value = payload;
        // Fix: Cast e.target to HTMLInputElement to access the 'select' method.
        document.getElementById('pix-payload').addEventListener('click', (e) => (e.target as HTMLInputElement).select());

        new QRCode(qrCodeEl, {
            text: payload,
            width: 200,
            height: 200,
            correctLevel: QRCode.CorrectLevel.M
        });
    };

    const generatePixPayload = (key, holder, city, amount, txid) => {
        const format = (id, value) => {
            const len = value.length.toString().padStart(2, '0');
            return `${id}${len}${value}`;
        };
        const sanitize = (str) => str.normalize("NFD").replace(/[\u0300-\u036f]/g, "").replace(/[^a-zA-Z0-9\s]/g, '').trim();
        const holderSanitized = sanitize(holder).substring(0, 25);
        const citySanitized = sanitize(city).substring(0, 15);
        const amountFormatted = amount.toFixed(2);
        let payload = [
            format('00', '01'),
            format('26', `${format('00', 'br.gov.bcb.pix')}${format('01', key)}`),
            format('52', '0000'),
            format('53', '986'), // Real
            format('54', amountFormatted),
            format('58', 'BR'),
            format('59', holderSanitized),
            format('60', citySanitized),
            format('62', format('05', txid)),
        ].join('');
        payload += '6304';
        payload += crc16(payload);
        return payload;
    };

    const crc16 = (payload) => {
        let crc = 0xFFFF;
        for (let i = 0; i < payload.length; i++) {
            crc ^= payload.charCodeAt(i) << 8;
            for (let j = 0; j < 8; j++) {
                crc = (crc & 0x8000) ? (crc << 1) ^ 0x1021 : crc << 1;
            }
        }
        return (crc & 0xFFFF).toString(16).toUpperCase().padStart(4, '0');
    };
  }
});