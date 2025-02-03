<?php
require 'db.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $action = $data['action'];

    if ($action === 'start') {
        // شروع بازی
        $player_id = $data['player_id']; // ID کاربری که بازی رو شروع کرده

        // بررسی اینکه آیا بازی در حال انتظار وجود داره یا نه
        $stmt = $pdo->prepare("UPDATE games SET turn = CASE WHEN turn = 1 THEN 2 ELSE 1 END WHERE id = ?");
        $stmt->execute([$game_id]);
        $waiting_game = $stmt->fetch();

        if ($waiting_game) {
            // اگر بازی در حال انتظار وجود داشت، کاربر دوم رو به بازی اضافه کن
            $game_id = $waiting_game['id'];
            $stmt = $pdo->prepare("UPDATE games SET player2_id = ?, status = 'in_progress', turn = 1 WHERE id = ?");
            $stmt->execute([$player_id, $game_id]);

            // اختصاص کارت‌ها به بازیکنان
            $cards = ['Duke', 'Assassin', 'Contessa', 'Captain', 'Ambassador'];
            shuffle($cards);

            $player1_cards = json_encode([$cards[0], $cards[1]]);
            $player2_cards = json_encode([$cards[2], $cards[3]]);

            $stmt = $pdo->prepare("UPDATE games SET player1_cards = ?, player2_cards = ? WHERE id = ?");
            $stmt->execute([$player1_cards, $player2_cards, $game_id]);

            echo json_encode(['status' => 'success', 'message' => 'Game started with another player!', 'game_id' => $game_id]);
        } else {
            // اگر بازی در حال انتظار وجود نداشت، بازی جدید ایجاد کن
            $stmt = $pdo->prepare("INSERT INTO games (player1_id, status, turn) VALUES (?, 'waiting', 1)");
            $stmt->execute([$player_id]);
            $game_id = $pdo->lastInsertId();
            echo json_encode(['status' => 'success', 'message' => 'Waiting for another player...', 'game_id' => $game_id]);
        }
    } elseif ($action === 'move') {
        // مدیریت حرکات بازیکنان
        $game_id = $data['game_id'];
        $player_id = $data['player_id'];
        $move = $data['move'];

        // بررسی وضعیت بازی
        $stmt = $pdo->prepare("SELECT * FROM games WHERE id = ?");
        $stmt->execute([$game_id]);
        $game = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$game || $game['status'] !== 'in_progress') {
            echo json_encode(['status' => 'error', 'message' => 'Game is not in progress']);
            return;
        }

        // تعیین نقش بازیکن فعلی
        $is_player1 = ($game['player1_id'] == $player_id);
        $is_player2 = ($game['player2_id'] == $player_id);

        if (!$is_player1 && !$is_player2) {
            echo json_encode(['status' => 'error', 'message' => 'You are not part of this game']);
            return;
        }

        // بررسی نوبت
        $current_turn = $game['turn'];
        if (($is_player1 && $current_turn !== 1) || ($is_player2 && $current_turn !== 2)) {
            echo json_encode(['status' => 'error', 'message' => 'It is not your turn']);
            return;
        }

        // مدیریت حرکت
        if ($move === 'collect_coins') {
            // جمع‌آوری سکه
            $coins_column = $is_player1 ? 'player1_coins' : 'player2_coins';
            $stmt = $pdo->prepare("UPDATE games SET {$coins_column} = {$coins_column} + 1, turn = CASE WHEN turn = 1 THEN 2 ELSE 1 END WHERE id = ?");
            $stmt->execute([$game_id]);

            echo json_encode(['status' => 'success', 'message' => 'You collected 1 coin']);
        } elseif ($move === 'tax') {
            // جمع‌آوری 3 سکه با Duke
            $player_cards = $is_player1 ? json_decode($game['player1_cards'], true) : json_decode($game['player2_cards'], true);
            if (!in_array('Duke', $player_cards)) {
                echo json_encode(['status' => 'error', 'message' => 'You do not have the Duke card to perform this action']);
                return;
            }

            $coins_column = $is_player1 ? 'player1_coins' : 'player2_coins';
            $stmt = $pdo->prepare("UPDATE games SET {$coins_column} = {$coins_column} + 3, turn = CASE WHEN turn = 1 THEN 2 ELSE 1 END WHERE id = ?");
            $stmt->execute([$game_id]);

            echo json_encode(['status' => 'success', 'message' => 'You collected 3 coins using the Duke']);
        } elseif ($move === 'steal') {
            // سرقت سکه توسط Captain
            $player_cards = $is_player1 ? json_decode($game['player1_cards'], true) : json_decode($game['player2_cards'], true);
            if (!in_array('Captain', $player_cards)) {
                echo json_encode(['status' => 'error', 'message' => 'You do not have the Captain card to perform this action']);
                return;
            }

            $target_coins_column = $is_player1 ? 'player2_coins' : 'player1_coins';
            $player_coins_column = $is_player1 ? 'player1_coins' : 'player2_coins';

            $stmt = $pdo->prepare("UPDATE games SET {$player_coins_column} = {$player_coins_column} + 2, {$target_coins_column} = GREATEST({$target_coins_column} - 2, 0), turn = CASE WHEN turn = 1 THEN 2 ELSE 1 END WHERE id = ?");
            $stmt->execute([$game_id]);

            echo json_encode(['status' => 'success', 'message' => 'You stole 2 coins from your opponent']);
        } elseif ($move === 'coup') {
            // کودتا
            $coins_column = $is_player1 ? 'player1_coins' : 'player2_coins';
            $current_coins = $is_player1 ? $game['player1_coins'] : $game['player2_coins'];

            if ($current_coins < 7) {
                echo json_encode(['status' => 'error', 'message' => 'You need at least 7 coins to perform a coup']);
                return;
            }

            // کاهش 7 سکه از بازیکن
            $stmt = $pdo->prepare("UPDATE games SET {$coins_column} = {$coins_column} - 7, turn = CASE WHEN turn = 1 THEN 2 ELSE 1 END WHERE id = ?");
            $stmt->execute([$game_id]);

            // حذف یک کارت از حریف (برای سادگی، اولین کارت رو حذف می‌کنیم)
            $target_cards_column = $is_player1 ? 'player2_cards' : 'player1_cards';
            $target_cards = $is_player1 ? json_decode($game['player2_cards'], true) : json_decode($game['player1_cards'], true);

            if (count($target_cards) === 0) {
                echo json_encode(['status' => 'error', 'message' => 'Your opponent has no cards left']);
                return;
            }

            array_shift($target_cards); // حذف اولین کارت
            $new_target_cards = json_encode($target_cards);

            $stmt = $pdo->prepare("UPDATE games SET {$target_cards_column} = ?, turn = CASE WHEN turn = 1 THEN 2 ELSE 1 END WHERE id = ?");
            $stmt->execute([$new_target_cards, $game_id]);

            echo json_encode(['status' => 'success', 'message' => 'You performed a coup and removed one of your opponent\'s cards']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Invalid move']);
        }
    } elseif ($action === 'get_status') {
        // دریافت وضعیت بازی
        $game_id = $data['game_id'];
        $player_id = $data['player_id']; // ID بازیکن فعلی

        $stmt = $pdo->prepare("SELECT * FROM games WHERE id = ?");
        $stmt->execute([$game_id]);
        $game = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($game) {
            // تعیین اینکه بازیکن فعلی Player 1 هست یا Player 2
            $is_player1 = ($game['player1_id'] == $player_id);
            $is_player2 = ($game['player2_id'] == $player_id);

            if (!$is_player1 && !$is_player2) {
                echo json_encode(['status' => 'error', 'message' => 'You are not part of this game']);
                return;
            }

            // کارت‌ها و سکه‌های بازیکن فعلی
            $player_cards = $is_player1 ? json_decode($game['player1_cards'], true) : json_decode($game['player2_cards'], true);
            $player_coins = $is_player1 ? $game['player1_coins'] : $game['player2_coins'];

            // نوبت فعلی
            $is_my_turn = ($is_player1 && $game['turn'] === 1) || ($is_player2 && $game['turn'] === 2);

            // وضعیت بازی (بدون نمایش کارت‌های حریف)
            echo json_encode([
                'status' => 'success',
                'game' => [
                    'player_cards' => $player_cards,
                    'player_coins' => $player_coins,
                    'is_my_turn' => $is_my_turn,
                    'status' => $game['status']
                ]
            ]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Game not found']);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
    }
}
?>