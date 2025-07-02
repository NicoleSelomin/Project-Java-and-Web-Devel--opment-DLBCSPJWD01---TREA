<?php
require 'db_connect.php';

$agent_id = intval($_GET['agent_id'] ?? 0);
$week_start = $_GET['week_start'] ?? date('Y-m-d');
$slot_minutes = $_GET['slot_minutes'] ?? 120;
$num_days = intval($_GET['num_days'] ?? 7);

$start_hour = 9;
$end_hour = 19;

if (!$agent_id) exit(json_encode([]));

$start = new DateTime($week_start);
$end = (clone $start)->modify('+' . ($num_days - 1) . ' days');

$stmt = $pdo->prepare("
    SELECT start_time, end_time 
    FROM agent_schedule
    WHERE agent_id = ? AND status IN ('booked','blocked')
      AND start_time < ? AND end_time > ?
");
$stmt->execute([
    $agent_id, 
    $end->format('Y-m-d 23:59:59'),  // window_end
    $start->format('Y-m-d 00:00:00') // window_start
]);
$taken = $stmt->fetchAll(PDO::FETCH_ASSOC);

$now = new DateTime();
$slots = [];

for ($d = 0; $d < $num_days; $d++) {
    $date = (clone $start)->modify("+$d days");
    for ($h = $start_hour; $h <= $end_hour - ($slot_minutes / 60); $h++) {
        $slotStart = (clone $date)->setTime($h, 0);
        $slotEnd = (clone $slotStart)->modify("+$slot_minutes minutes");
        $isAvailable = true;

        foreach ($taken as $block) {
            $blockStart = strtotime($block['start_time']);
            $blockEnd = strtotime($block['end_time']);
            $thisStart = $slotStart->getTimestamp();
            $thisEnd = $slotEnd->getTimestamp();
            // overlap check
            if ($thisStart < $blockEnd && $thisEnd > $blockStart) {
                $isAvailable = false;
                break;
            }
        }

        // Only block "past" slots for today
        if ($slotStart->format('Y-m-d') == $now->format('Y-m-d')) {
            if ($slotStart < $now) {
                $isAvailable = false;
            }
        } elseif ($slotStart < $now) {
            $isAvailable = false;
        }

        $slots[] = [
            'date' => $date->format('Y-m-d'),
            'start' => $slotStart->format('Y-m-d H:i:s'),
            'end' => $slotEnd->format('Y-m-d H:i:s'),
            'available' => $isAvailable
        ];
    }
}

header('Content-Type: application/json');
echo json_encode($slots);
exit;
?>
