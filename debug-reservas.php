<?php
require_once('wp-load.php');
global $wpdb;


function analizar_tablas() {
    global $wpdb;
    
    $tablas_a_revisar = [
        $wpdb->prefix . 'reservas_reservas',
        $wpdb->prefix . 'reservas_servicios',
        $wpdb->prefix . 'reservas_agencies'
    ];

    foreach ($tablas_a_revisar as $tabla) {
        comparar_tablas($tabla);
    }
}


function comparar_tablas($tabla) {
        global $wpdb;
    
    // Obtener columnas de la tabla
    $columnas = $wpdb->get_results("SHOW COLUMNS FROM $tabla");
    
    echo "=== ANÁLISIS DE TABLA: $tabla ===\n";
    echo "Número de columnas: " . count($columnas) . "\n\n";
    
    echo "Detalle de Columnas:\n";
    echo "-------------------\n";
    
    foreach ($columnas as $columna) {
        echo "Nombre: " . $columna->Field . "\n";
        echo "Tipo: " . $columna->Type . "\n";
        echo "Nulo: " . $columna->Null . "\n";
        echo "Valor por Defecto: " . ($columna->Default ?? 'SIN VALOR POR DEFECTO') . "\n";
        echo "Clave: " . $columna->Key . "\n";
        echo "Extra: " . $columna->Extra . "\n\n";
    }
}


function verificar_campos_vacios() {
    global $wpdb;
    
    $campos_a_verificar = [
        $wpdb->prefix . 'reservas_reservas' => ['redsys_order_id'],
        $wpdb->prefix . 'reservas_servicios' => ['hora_vuelta', 'descuento_tipo'],
        $wpdb->prefix . 'reservas_agencies' => ['email_notificaciones']
    ];

    foreach ($campos_a_verificar as $tabla => $campos) {
        foreach ($campos as $campo) {
            verificar_registros_vacios($tabla, $campo);
        }
    }
}


function verificar_registros_vacios($tabla, $campo_problema) {
    global $wpdb;
    
    $consulta = $wpdb->prepare(
        "SELECT COUNT(*) as total_vacios 
         FROM $tabla 
         WHERE $campo_problema IS NULL OR $campo_problema = ''",
        $tabla, $campo_problema
    );
    
    $resultado = $wpdb->get_row($consulta);
    
    echo "=== VERIFICACIÓN DE CAMPOS VACÍOS EN $tabla ===\n";
    echo "Campo analizado: $campo_problema\n";
    echo "Total registros vacíos: " . $resultado->total_vacios . "\n\n";
}


function actualizar_campos() {
    global $wpdb;
    
    $wpdb->query("
        UPDATE {$wpdb->prefix}reservas_reservas 
        SET redsys_order_id = '' 
        WHERE redsys_order_id IS NULL
    ");

    $wpdb->query("
        UPDATE {$wpdb->prefix}reservas_agencies 
        SET email_notificaciones = email 
        WHERE email_notificaciones IS NULL
    ");

    $wpdb->query("
        UPDATE {$wpdb->prefix}reservas_servicios 
        SET 
            descuento_tipo = 'fijo', 
            descuento_minimo_personas = 1,
            descuento_acumulable = 0,
            descuento_prioridad = 'servicio'
        WHERE descuento_tipo IS NULL
    ");

    echo "Actualización de campos completada\n";
}

function verificar_reservas_redsys() {
    global $wpdb;
    
    // Consulta más amplia
    $consulta = "
        SELECT * FROM {$wpdb->prefix}reservas_reservas 
        WHERE 
            (metodo_pago = 'redsys' OR 
             redsys_order_id IS NOT NULL AND redsys_order_id != '') 
            AND created_at >= DATE_SUB(NOW(), INTERVAL 2 MONTH)
        LIMIT 100
    ";
    
    $resultados = $wpdb->get_results($consulta);
    
    if (!empty($resultados)) {
        echo "Reservas Redsys encontradas:\n";
        foreach ($resultados as $reserva) {
            echo "ID: {$reserva->id}, Localizador: {$reserva->localizador}, Order ID: " . 
                 ($reserva->redsys_order_id ?: 'N/A') . ", Fecha: {$reserva->created_at}, Método: {$reserva->metodo_pago}\n";
        }
    } else {
        echo "No se encontraron reservas Redsys\n";
    }
}

function consulta_especifica_order_id() {
    global $wpdb;
    
    // Buscar reservas con Order ID específico o relacionadas con Redsys
    $consultas = [
        "Búsqueda directa" => $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}reservas_reservas 
             WHERE redsys_order_id = %s OR 
                   metodo_pago = 'redsys' AND 
                   created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)",
            '251106002550'
        ),
        "Rango de fechas" => "
            SELECT * FROM {$wpdb->prefix}reservas_reservas 
            WHERE created_at >= '2025-11-01' 
            AND (redsys_order_id IS NOT NULL OR metodo_pago = 'redsys')
            LIMIT 50
        "
    ];
    
    foreach ($consultas as $nombre => $consulta) {
        $resultados = $wpdb->get_results($consulta);
        
        echo "=== $nombre ===\n";
        echo "Total resultados: " . count($resultados) . "\n";
        
        if (!empty($resultados)) {
            foreach ($resultados as $reserva) {
                echo "ID: {$reserva->id}, Localizador: {$reserva->localizador}, ";
                echo "Order ID: " . ($reserva->redsys_order_id ?: 'N/A') . ", ";
                echo "Método: {$reserva->metodo_pago}, Fecha: {$reserva->created_at}\n";
            }
        }
        echo "\n";
    }
}

function depuracion_detallada() {
        global $wpdb;
    
    // Verificar configuración de la base de datos
    echo "Detalles de Conexión:\n";
    echo "Servidor: " . DB_HOST . "\n";
    echo "Base de Datos: " . DB_NAME . "\n";
    echo "Prefijo de Tablas: " . $wpdb->prefix . "\n\n";
    
    // Últimas 10 reservas
    $ultimas_reservas = $wpdb->get_results("
        SELECT id, localizador, metodo_pago, created_at 
        FROM {$wpdb->prefix}reservas_reservas 
        ORDER BY created_at DESC 
        LIMIT 10
    ");
    
    echo "Últimas 10 Reservas:\n";
    foreach ($ultimas_reservas as $reserva) {
        echo "ID: {$reserva->id}, Localizador: {$reserva->localizador}, Método: {$reserva->metodo_pago}, Fecha: {$reserva->created_at}\n";
    }
}


function probar_consultas_problematicas() {
        global $wpdb;
    
    $consultas_test = [
        'Reserva por Order ID' => $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}reservas_reservas 
             WHERE redsys_order_id = %s",
            '251106002550'
        ),
        'Reservas Redsys Recientes' => 
            "SELECT * FROM {$wpdb->prefix}reservas_reservas 
             WHERE metodo_pago = 'redsys' 
             AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)"
    ];
    
    echo "=== PROBANDO CONSULTAS PROBLEMÁTICAS ===\n";
    
    foreach ($consultas_test as $nombre => $consulta) {
        $resultado = $wpdb->get_results($consulta);
        
        echo "Consulta: $nombre\n";
        echo "Total resultados: " . count($resultado) . "\n";
        
        if (!empty($resultado)) {
            echo "Primer resultado:\n";
            print_r($resultado[0]);
        }
        echo "\n";
    }
}





echo "=== INICIANDO DEPURACIÓN ===\n";

analizar_tablas();
verificar_campos_vacios();
actualizar_campos();
verificar_reservas_redsys();
depuracion_detallada();
probar_consultas_problematicas();

echo "\n=== DEPURACIÓN COMPLETADA ===";