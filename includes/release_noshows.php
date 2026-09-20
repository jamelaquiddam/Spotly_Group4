<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/settings.php';

function releaseNoShows(PDO $pdo): int
{
    $released = 0;
    try {
        $pdo->beginTransaction();
        $find = $pdo->prepare(
            "SELECT r.reservation_id, r.user_id, r.date, r.start_time, r.end_time, l.room_code
             FROM reservations r
             INNER JOIN laboratories l ON l.room_id = r.room_id
             WHERE r.status = 'Approved'
               AND r.checked_in_at IS NULL
               AND NOW() > TIMESTAMP(r.date, r.start_time) + INTERVAL " . GRACE_PERIOD_MINUTES . " MINUTE
             FOR UPDATE"
        );
        $find->execute();
        $rows = $find->fetchAll();
        $update = $pdo->prepare("UPDATE reservations SET status = 'No-Show', released_at = NOW() WHERE reservation_id = :reservation_id AND status = 'Approved' AND checked_in_at IS NULL");
        $notify = $pdo->prepare('INSERT INTO notifications (user_id, reservation_id, message) VALUES (:user_id, :reservation_id, :message)');
        foreach ($rows as $row) {
            $update->execute(['reservation_id' => $row['reservation_id']]);
            if ($update->rowCount() === 1) {
                $notify->execute([
                    'user_id' => $row['user_id'],
                    'reservation_id' => $row['reservation_id'],
                    'message' => 'Your reservation for ' . $row['room_code'] . ' was released because you did not check in within ' . GRACE_PERIOD_MINUTES . ' minutes of the start time.',
                ]);
                $released++;
            }
        }
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $exception;
    }
    return $released;
}
