const BASE_URL = 'https://sasadi.ir/coup/api';

let currentGameId = null;
let currentPlayerId = null; // ID کاربر فعلی

// ذخیره اطلاعات لاگین در LocalStorage
function saveLoginInfo(playerId) {
    localStorage.setItem('currentPlayerId', playerId);
}

// خواندن اطلاعات لاگین از LocalStorage
function loadLoginInfo() {
    const playerId = localStorage.getItem('currentPlayerId');
    if (playerId) {
        currentPlayerId = parseInt(playerId, 10);

        // نمایش بخش بازی و پنهان کردن ثبت‌نام/لاگین
        document.getElementById('registerForm').style.display = 'none';
        document.getElementById('loginForm').style.display = 'none';
        document.getElementById('gameSection').classList.remove('hidden');

        updateGameStatus(); // به‌روزرسانی وضعیت بازی بعد از لاگین
    }
}

// ثبت‌نام کاربر
document.getElementById('registerButton').addEventListener('click', async () => {
    const username = document.getElementById('username').value;
    const password = document.getElementById('password').value;

    try {
        const response = await fetch(`${BASE_URL}/register.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ username, password })
        });

        const result = await response.json();
        alert(result.message);
    } catch (error) {
        console.error('Error during registration:', error);
        alert('An error occurred during registration. Please try again.');
    }
});

// لاگین کاربر
document.getElementById('loginButton').addEventListener('click', async () => {
    const username = document.getElementById('loginUsername').value;
    const password = document.getElementById('loginPassword').value;

    try {
        const response = await fetch(`${BASE_URL}/login.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ username, password })
        });

        const result = await response.json();
        if (result.status === 'success') {
            currentPlayerId = result.player_id; // تنظیم ID کاربر فعلی
            saveLoginInfo(currentPlayerId); // ذخیره اطلاعات لاگین
            document.getElementById('playerName').innerText = username;

            // نمایش بخش بازی و پنهان کردن ثبت‌نام/لاگین
            document.getElementById('registerForm').style.display = 'none';
            document.getElementById('loginForm').style.display = 'none';
            document.getElementById('gameSection').classList.remove('hidden');

            updateGameStatus(); // به‌روزرسانی وضعیت بازی
        } else {
            alert(result.message);
        }
    } catch (error) {
        console.error('Error during login:', error);
        alert('An error occurred during login. Please try again.');
    }
});

// شروع بازی
document.getElementById('startGame').addEventListener('click', async () => {
    if (!currentPlayerId) {
        alert('Please log in first!');
        return;
    }

    const response = await fetch(`${BASE_URL}/game.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'start', player_id: currentPlayerId })
    });

    const result = await response.json();
    if (result.status === 'success') {
        currentGameId = result.game_id;
        document.getElementById('gameStatus').innerText = result.message;
        updateGameStatus();
        startPolling(); // شروع به‌روزرسانی دوره‌ای
    } else {
        alert(result.message);
    }
});

// به‌روزرسانی وضعیت بازی به صورت دوره‌ای
function startPolling() {
    setInterval(() => {
        if (currentGameId) {
            updateGameStatus();
        }
    }, 2000); // هر 2 ثانیه یک‌بار وضعیت بازی رو چک کن
}

// به‌روزرسانی وضعیت بازی
async function updateGameStatus() {
    if (!currentGameId) return;

    try {
        const response = await fetch(`${BASE_URL}/game.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'get_status', game_id: currentGameId, player_id: currentPlayerId })
        });

        const result = await response.json();
        if (result.status === 'success') {
            const game = result.game;
            if (game.status === 'in_progress') {
                document.getElementById('gameStatus').innerText = `
                    Your Cards: ${game.player_cards.join(', ')}
                    Your Coins: ${game.player_coins}
                    Game Status: ${game.status}
                    Turn: ${game.is_my_turn ? 'Your turn' : 'Opponent\'s turn'}
                `;

                // فعال/غیرفعال کردن دکمه‌ها بر اساس نوبت
                updateButtons(game.is_my_turn);
            } else {
                document.getElementById('gameStatus').innerText = 'Waiting for another player...';
            }
        } else {
            console.error(result.message);
        }
    } catch (error) {
        console.error('Error during updating game status:', error);
    }
}

// فعال/غیرفعال کردن دکمه‌ها بر اساس نوبت
function updateButtons(isMyTurn) {
    const buttons = document.querySelectorAll('.moveButton');
    buttons.forEach(button => {
        button.disabled = !isMyTurn;
    });
}

// ارسال حرکت
async function makeMove(move) {
    if (!currentGameId) {
        alert('Please start a game first!');
        return;
    }

    const response = await fetch(`${BASE_URL}/game.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'move', game_id: currentGameId, player_id: currentPlayerId, move })
    });

    const result = await response.json();
    alert(result.message);
    updateGameStatus(); // به‌روزرسانی وضعیت بازی بعد از حرکت
}

// بارگذاری اطلاعات لاگین هنگام لود صفحه
window.addEventListener('load', () => {
    loadLoginInfo();

    // اطمینان از وجود دکمه‌ها قبل از افزودن Event Listener
    if (document.getElementById('collectCoins')) {
        document.getElementById('collectCoins').addEventListener('click', () => makeMove('collect_coins'));
    }
    if (document.getElementById('tax')) {
        document.getElementById('tax').addEventListener('click', () => makeMove('tax'));
    }
    if (document.getElementById('steal')) {
        document.getElementById('steal').addEventListener('click', () => makeMove('steal'));
    }
    if (document.getElementById('coup')) {
        document.getElementById('coup').addEventListener('click', () => makeMove('coup'));
    }
});