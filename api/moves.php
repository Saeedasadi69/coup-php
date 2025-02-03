<?php
require 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $game_id = $data['game_id'];
    $player_id = $data['player_id'];
    $move = $data['move'];

    try {
        $stmt = $pdo->prepare("INSERT INTO moves (game_id, player_id, action) VALUES (?, ?, ?)");
        $stmt->execute([$game_id, $player_id, $move]);
        echo json_encode(['status' => 'success', 'message' => 'Move recorded']);
    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => 'Failed to record move']);
    }
}
?>