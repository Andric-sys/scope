<?php
require __DIR__ . '/conexion.php';
$pdo = db();

echo "COLUMNAS EN scope_jobcosting_entries:\n";
echo "═════════════════════════════════════════\n";
$sql = "DESCRIBE scope_jobcosting_entries";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$cols = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($cols as $col) {
    echo $col['Field'] . " (" . $col['Type'] . ") - " . ($col['Null'] === 'YES' ? 'nullable' : 'NOT NULL') . "\n";
}

echo "\n\nCOLUMNAS EN scope_orders:\n";
echo "═════════════════════════════════════════\n";

$sql = "DESCRIBE scope_orders";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$cols = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($cols as $col) {
    echo $col['Field'] . " (" . $col['Type'] . ") - " . ($col['Null'] === 'YES' ? 'nullable' : 'NOT NULL') . "\n";
}

// Buscar campos con "antic" en el nombre
echo "\n\nBÚSQUEDA DE CAMPOS CON 'ANTIC':\n";
echo "═════════════════════════════════════════\n";

$sql = "
  SELECT TABLE_NAME, COLUMN_NAME, DATA_TYPE
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
  AND COLUMN_NAME LIKE '%antic%'
";

$stmt = $pdo->prepare($sql);
$stmt->execute();
$result = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($result) > 0) {
    foreach ($result as $row) {
        echo "Tabla: " . $row['TABLE_NAME'] . " | Campo: " . $row['COLUMN_NAME'] . " (" . $row['DATA_TYPE'] . ")\n";
    }
} else {
    echo "No se encontró ningún campo con 'antic' en el nombre.\n";
}
?>
