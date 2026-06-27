<?php
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/auth.php';
auth_required();
$stream_filter = $_GET['stream_id'] ?? '';
$where = $stream_filter ? "WHERE s.stream_id = " . (int)$stream_filter : '';
$students = $pdo->query("
    SELECT s.adm_number, s.name, c.name AS class, st.name AS stream, s.gender
    FROM students s JOIN streams st ON st.id=s.stream_id JOIN classes c ON c.id=st.class_id $where
    ORDER BY c.name,st.name,s.name
")->fetchAll();

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="students_'.date('Ymd').'.csv"');
$f = fopen('php://output', 'w');
fputcsv($f, ['Adm Number','Name','Class','Stream','Gender']);
foreach ($students as $s) fputcsv($f, $s);
fclose($f);
