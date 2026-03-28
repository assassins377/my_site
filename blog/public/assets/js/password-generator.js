/**
 * Генератор надежных паролей и утилиты безопасности
 * Подключается в layouts/main.php
 */

/**
 * Инициализация генератора пароля
 * @param {string} passwordFieldId - ID поля ввода пароля
 * @param {string} generateBtnId - ID кнопки генерации
 * @param {string} toggleBtnId - ID кнопки показа/скрытия
 * @param {string} strengthBarId - ID индикатора сложности
 * @param {string} strengthTextId - ID текста сложности
 */
function initPasswordGenerator(passwordFieldId, generateBtnId, toggleBtnId, strengthBarId, strengthTextId) {
    const passwordInput = document.getElementById(passwordFieldId);
    const generateBtn = document.getElementById(generateBtnId);
    const toggleBtn = document.getElementById(toggleBtnId);
    const strengthBar = document.getElementById(strengthBarId);
    const strengthText = document.getElementById(strengthTextId);

    // Генерация пароля
    if (generateBtn) {
        generateBtn.addEventListener('click', function() {
            const password = generateSecurePassword();
            passwordInput.value = password;
            updatePasswordStrength(password, strengthBar, strengthText);
            
            // Анимация копирования
            const originalIcon = generateBtn.innerHTML;
            generateBtn.innerHTML = '<i class="bi bi-clipboard-check"></i>';
            setTimeout(() => {
                generateBtn.innerHTML = originalIcon;
            }, 1500);
        });
    }

    // Показать/скрыть пароль
    if (toggleBtn) {
        toggleBtn.addEventListener('click', function() {
            const type = passwordInput.type === 'password' ? 'text' : 'password';
            passwordInput.type = type;
            toggleBtn.innerHTML = type === 'password' 
                ? '<i class="bi bi-eye"></i>' 
                : '<i class="bi bi-eye-slash"></i>';
        });
    }

    // Проверка сложности при вводе
    if (passwordInput) {
        passwordInput.addEventListener('input', function() {
            updatePasswordStrength(this.value, strengthBar, strengthText);
        });
    }
}

/**
 * Генерация криптографически стойкого пароля
 * @param {number} length - Длина пароля (по умолчанию 16)
 * @returns {string} Сгенерированный пароль
 */
function generateSecurePassword(length = 16) {
    const charset = {
        lowercase: 'abcdefghijklmnopqrstuvwxyz',
        uppercase: 'ABCDEFGHIJKLMNOPQRSTUVWXYZ',
        numbers: '0123456789',
        symbols: '!@#$%^&*()_+~`|}{[]:;?><,./-='
    };

    const allChars = charset.lowercase + charset.uppercase + charset.numbers + charset.symbols;
    let password = '';

    // Гарантируем наличие хотя бы одного символа из каждой категории
    password += charset.lowercase[Math.floor(Math.random() * charset.lowercase.length)];
    password += charset.uppercase[Math.floor(Math.random() * charset.uppercase.length)];
    password += charset.numbers[Math.floor(Math.random() * charset.numbers.length)];
    password += charset.symbols[Math.floor(Math.random() * charset.symbols.length)];

    // Заполняем оставшуюся длину случайными символами
    for (let i = password.length; i < length; i++) {
        const randomIndex = crypto.getRandomValues(new Uint32Array(1))[0] % allChars.length;
        password += allChars[randomIndex];
    }

    // Перемешиваем пароль
    return shuffleString(password);
}

/**
 * Перемешивание строки (алгоритм Фишера-Йетса)
 * @param {string} str - Строка для перемешивания
 * @returns {string} Перемешанная строка
 */
function shuffleString(str) {
    const arr = str.split('');
    for (let i = arr.length - 1; i > 0; i--) {
        const j = crypto.getRandomValues(new Uint32Array(1))[0] % (i + 1);
        [arr[i], arr[j]] = [arr[j], arr[i]];
    }
    return arr.join('');
}

/**
 * Оценка сложности пароля
 * @param {string} password - Пароль для проверки
 * @returns {object} Объект с оценкой сложности
 */
function checkPasswordStrength(password) {
    let score = 0;
    const feedback = [];

    if (!password) return { score: 0, label: '', color: '' };

    // Длина
    if (password.length >= 8) score++;
    if (password.length >= 12) score++;
    if (password.length >= 16) score++;

    // Разнообразие символов
    if (/[a-z]/.test(password)) score++;
    if (/[A-Z]/.test(password)) score++;
    if (/[0-9]/.test(password)) score++;
    if (/[^a-zA-Z0-9]/.test(password)) score++;

    // Определение уровня
    let label, color, percentage;
    
    if (score <= 3) {
        label = 'Слабый';
        color = 'bg-danger';
        percentage = 25;
    } else if (score <= 5) {
        label = 'Средний';
        color = 'bg-warning';
        percentage = 50;
    } else if (score <= 7) {
        label = 'Надежный';
        color = 'bg-info';
        percentage = 75;
    } else {
        label = 'Очень надежный';
        color = 'bg-success';
        percentage = 100;
    }

    return { score, label, color, percentage };
}

/**
 * Обновление индикатора сложности пароля
 * @param {string} password - Текущий пароль
 * @param {HTMLElement} bar - Элемент прогресс-бара
 * @param {HTMLElement} text - Элемент текста
 */
function updatePasswordStrength(password, bar, text) {
    if (!bar || !text) return;

    const strength = checkPasswordStrength(password);
    
    bar.className = `progress-bar ${strength.color}`;
    bar.style.width = `${strength.percentage}%`;
    bar.setAttribute('aria-valuenow', strength.percentage);
    
    text.textContent = strength.label;
    text.className = `form-text ${strength.color.replace('bg-', 'text-')}`;
}

/**
 * Копирование текста в буфер обмена
 * @param {string} text - Текст для копирования
 * @returns {Promise<boolean>} Успех операции
 */
async function copyToClipboard(text) {
    try {
        await navigator.clipboard.writeText(text);
        return true;
    } catch (err) {
        // Fallback для старых браузеров
        const textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.style.position = 'fixed';
        textarea.style.opacity = '0';
        document.body.appendChild(textarea);
        textarea.select();
        try {
            document.execCommand('copy');
            document.body.removeChild(textarea);
            return true;
        } catch (e) {
            document.body.removeChild(textarea);
            return false;
        }
    }
}

// Делаем функции доступными глобально
window.initPasswordGenerator = initPasswordGenerator;
window.generateSecurePassword = generateSecurePassword;
window.copyToClipboard = copyToClipboard;
