<?php
declare(strict_types=1);

// Proteger con autenticación
require __DIR__ . '/auth_guard.php';

header('Content-Type: application/json; charset=utf-8');

require __DIR__ . '/conexion.php';

// Obtener método HTTP
$method = $_SERVER['REQUEST_METHOD'];

try {
    $pdo = db();
    
    if ($method === 'GET') {
        // Obtener metas de un año específico
        $anio = isset($_GET['anio']) ? (int)$_GET['anio'] : date('Y');
        
        $stmt = $pdo->prepare("
            SELECT id, anio, mes, meta, fecha_registro 
            FROM metas_ventas 
            WHERE anio = ? 
            ORDER BY mes
        ");
        $stmt->execute([$anio]);
        $metas = $stmt->fetchAll();
        
        // Convertir tipos
        $metas = array_map(function($row) {
            return [
                'id' => (int)$row['id'],
                'anio' => (int)$row['anio'],
                'mes' => (int)$row['mes'],
                'meta' => (float)$row['meta'],
                'fecha_registro' => $row['fecha_registro']
            ];
        }, $metas);
        
        echo json_encode([
            'success' => true,
            'data' => $metas
        ]);
        
    } else if ($method === 'POST') {
        // Actualizar o crear meta
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($input['anio']) || !isset($input['mes']) || !isset($input['meta'])) {
            throw new Exception('Faltan parámetros requeridos: anio, mes, meta');
        }
        
        $anio = (int)$input['anio'];
        $mes = (int)$input['mes'];
        $meta = (float)$input['meta'];
        
        // Validaciones
        if ($mes < 0 || $mes > 12) {
            throw new Exception('El mes debe estar entre 0 (anual) y 12');
        }
        
        if ($meta < 0) {
            throw new Exception('La meta debe ser un valor positivo');
        }
        
        // Insertar o actualizar
        $stmt = $pdo->prepare("
            INSERT INTO metas_ventas (anio, mes, meta) 
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE meta = VALUES(meta), fecha_registro = NOW()
        ");
        
        if ($stmt->execute([$anio, $mes, $meta])) {
            echo json_encode([
                'success' => true,
                'message' => 'Meta actualizada correctamente',
                'id' => $pdo->lastInsertId() ?: null
            ]);
        } else {
            throw new Exception('Error al guardar la meta');
        }
        
    } else if ($method === 'PUT') {
        // Actualizar meta existente
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($input['id']) || !isset($input['meta'])) {
            throw new Exception('Faltan parámetros requeridos: id, meta');
        }
        
        $id = (int)$input['id'];
        $meta = (float)$input['meta'];
        
        if ($meta < 0) {
            throw new Exception('La meta debe ser un valor positivo');
        }
        
        $stmt = $pdo->prepare("
            UPDATE metas_ventas 
            SET meta = ?, fecha_registro = NOW() 
            WHERE id = ?
        ");
        
        if ($stmt->execute([$meta, $id])) {
            if ($stmt->rowCount() > 0) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Meta actualizada correctamente'
                ]);
            } else {
                throw new Exception('No se encontró la meta con ese ID');
            }
        } else {
            throw new Exception('Error al actualizar la meta');
        }
        
    } else if ($method === 'DELETE') {
        // Eliminar meta
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($input['id'])) {
            throw new Exception('Falta parámetro requerido: id');
        }
        
        $id = (int)$input['id'];
        
        $stmt = $pdo->prepare("DELETE FROM metas_ventas WHERE id = ?");
        
        if ($stmt->execute([$id])) {
            echo json_encode([
                'success' => true,
                'message' => 'Meta eliminada correctamente'
            ]);
        } else {
            throw new Exception('Error al eliminar la meta');
        }
        
    } else {
        throw new Exception('Método HTTP no permitido');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
