<?php
require_once('/home/autobusmedinaaza/public_html/wp-load.php');

function diagnosticar_creacion_reserva_redsys() {
    global $wpdb;

    // Simular datos de una reserva reciente
    $datos_reserva = [
        'fecha' => date('Y-m-d'),
        'service_id' => 1,  // ID de servicio existente
        'hora_ida' => date('H:i:s'),
        'adultos' => 2,
        'total_price' => 50.00,
        'nombre' => 'Test',
        'apellidos' => 'Usuario',
        'email' => 'test@example.com',
        'telefono' => '666666666'
    ];

    // Simular parámetros de pago Redsys
    $params_redsys = [
        'order_id' => date('ymdHis'),
        'amount' => 5000,  // 50.00 euros en céntimos
        'transaction_type' => '0'
    ];

    echo "=== DIAGNÓSTICO DE CREACIÓN DE RESERVA REDSYS ===\n";
    echo "Datos de la Reserva:\n";
    print_r($datos_reserva);
    echo "\nDatos Redsys:\n";
    print_r($params_redsys);

    // Simular inserción de reserva
    try {
        $wpdb->insert(
            $wpdb->prefix . 'reservas_reservas',
            [
                'servicio_id' => $datos_reserva['service_id'],
                'fecha' => $datos_reserva['fecha'],
                'hora' => $datos_reserva['hora_ida'],
                'nombre' => $datos_reserva['nombre'],
                'apellidos' => $datos_reserva['apellidos'],
                'email' => $datos_reserva['email'],
                'telefono' => $datos_reserva['telefono'],
                'adultos' => $datos_reserva['adultos'],
                'precio_base' => $datos_reserva['total_price'],
                'precio_final' => $datos_reserva['total_price'],
                'metodo_pago' => 'redsys',
                'redsys_order_id' => $params_redsys['order_id'],
                'estado' => 'confirmada',
                'created_at' => current_time('mysql')
            ]
        );

        $reserva_id = $wpdb->insert_id;

        echo "\n=== RESULTADO DE INSERCIÓN ===\n";
        echo "Reserva ID: $reserva_id\n";
        echo "Order ID: " . $params_redsys['order_id'] . "\n";

        // Verificar la reserva insertada
        $reserva_insertada = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}reservas_reservas WHERE id = %d",
                $reserva_id
            )
        );

        if ($reserva_insertada) {
            echo "\n=== RESERVA INSERTADA ===\n";
            echo "Localizador: {$reserva_insertada->localizador}\n";
            echo "Order ID: {$reserva_insertada->redsys_order_id}\n";
            echo "Estado: {$reserva_insertada->estado}\n";
        } else {
            echo "❌ No se pudo recuperar la reserva insertada\n";
        }

    } catch (Exception $e) {
        echo "❌ Error al insertar reserva: " . $e->getMessage() . "\n";
    }
}

diagnosticar_creacion_reserva_redsys();