<?php
require_once __DIR__ . '/redsys-api.php';

function generar_formulario_redsys($reserva_data) {
    error_log('=== INICIANDO GENERACIÓN FORMULARIO REDSYS ===');
    error_log('Datos recibidos: ' . print_r($reserva_data, true));
    
    $miObj = new RedsysAPI();

    // CONFIGURACIÓN
    if (is_production_environment()) {
        $clave = 'Q+2780shKFbG3vkPXS2+kY6RWQLQnWD9';
        $codigo_comercio = '014591697';
        $terminal = '001';
        error_log('🟢 USANDO CONFIGURACIÓN DE PRODUCCIÓN');
    } else {
        $clave = 'sq7HjrUOBfKmC576ILgskD5srU870gJ7';
        $codigo_comercio = '999008881';
        $terminal = '001';
        error_log('🟡 USANDO CONFIGURACIÓN DE PRUEBAS');
    }
    
    // ✅ DETECTAR SI ES VISITA O RESERVA NORMAL
    $is_visita = isset($reserva_data['is_visita']) && $reserva_data['is_visita'] === true;
    
    // Obtener precio
    $total_price = null;
    if ($is_visita) {
        $total_price = $reserva_data['precio_total'];
        error_log('✅ Es una VISITA GUIADA, precio: ' . $total_price . '€');
    } else {
        if (isset($reserva_data['total_price'])) {
            $total_price = $reserva_data['total_price'];
        } elseif (isset($reserva_data['precio_final'])) {
            $total_price = $reserva_data['precio_final'];
        }
    }
    
    if ($total_price) {
        $total_price = str_replace(['€', ' ', ','], ['', '', '.'], $total_price);
        $total_price = floatval($total_price);
    }
    
    if (!$total_price || $total_price <= 0) {
        throw new Exception('El importe debe ser mayor que 0. Recibido: ' . $total_price);
    }
    
    $importe = intval($total_price * 100);
    
    $timestamp = time();
    $random = rand(100, 999);
    $pedido = date('ymdHis') . str_pad($random, 3, '0', STR_PAD_LEFT);
    
    if (strlen($pedido) > 12) {
        $pedido = substr($pedido, 0, 12);
    }
    
    $miObj->setParameter("DS_MERCHANT_AMOUNT", $importe);
    $miObj->setParameter("DS_MERCHANT_ORDER", $pedido);
    $miObj->setParameter("DS_MERCHANT_MERCHANTCODE", $codigo_comercio);
    $miObj->setParameter("DS_MERCHANT_CURRENCY", "978");
    $miObj->setParameter("DS_MERCHANT_TRANSACTIONTYPE", "0");
    $miObj->setParameter("DS_MERCHANT_TERMINAL", $terminal);
    
    $base_url = home_url();
    $miObj->setParameter("DS_MERCHANT_MERCHANTURL", $base_url . '/wp-admin/admin-ajax.php?action=redsys_notification');
    
    // ✅ URLs DIFERENTES SEGÚN TIPO
    if ($is_visita) {
        $miObj->setParameter("DS_MERCHANT_URLOK", $base_url . '/confirmacion-reserva-visita/?status=ok&order=' . $pedido);
        $miObj->setParameter("DS_MERCHANT_URLKO", $base_url . '/error-pago/?status=ko&order=' . $pedido);
        error_log('✅ URLs configuradas para VISITA GUIADA');
    } else {
        $miObj->setParameter("DS_MERCHANT_URLOK", $base_url . '/confirmacion-reserva/?status=ok&order=' . $pedido);
        $miObj->setParameter("DS_MERCHANT_URLKO", $base_url . '/error-pago/?status=ko&order=' . $pedido);
    }
    
    $descripcion = $is_visita 
        ? "Visita Guiada Medina Azahara - " . ($reserva_data['fecha'] ?? date('Y-m-d'))
        : "Reserva Medina Azahara - " . ($reserva_data['fecha'] ?? date('Y-m-d'));
    $miObj->setParameter("DS_MERCHANT_PRODUCTDESCRIPTION", $descripcion);
    
    if (isset($reserva_data['nombre']) && isset($reserva_data['apellidos'])) {
        $miObj->setParameter("DS_MERCHANT_TITULAR", $reserva_data['nombre'] . ' ' . $reserva_data['apellidos']);
    }

    $params = $miObj->createMerchantParameters();
    $signature = $miObj->createMerchantSignature($clave);
    $version = "HMAC_SHA256_V1";

    $redsys_url = is_production_environment() ? 
        'https://sis.redsys.es/sis/realizarPago' :
        'https://sis-t.redsys.es:25443/sis/realizarPago';

    error_log("URL de Redsys: " . $redsys_url);
    error_log("Pedido: " . $pedido);
    error_log("Importe: " . $importe . " céntimos");
    error_log("Tipo: " . ($is_visita ? 'VISITA GUIADA' : 'RESERVA BUS'));

    // ✅ FORMULARIO LIMPIO SIN CARACTERES ESPECIALES
    $html = '<div id="redsys-overlay" style="position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.8);display:flex;align-items:center;justify-content:center;z-index:99999;">';
    $html .= '<div style="background:white;padding:30px;border-radius:10px;text-align:center;max-width:400px;">';
    $html .= '<h3 style="margin:0 0 20px 0;color:#333;">Redirigiendo al banco...</h3>';
    $html .= '<div style="margin:20px 0;">Por favor, espere...</div>';
    $html .= '<p style="font-size:14px;color:#666;margin:20px 0 0 0;">Sera redirigido automaticamente a la pasarela de pago segura.</p>';
    $html .= '</div></div>';
    $html .= '<form id="formulario_redsys" action="' . $redsys_url . '" method="POST" style="display:none;">';
    $html .= '<input type="hidden" name="Ds_SignatureVersion" value="' . $version . '">';
    $html .= '<input type="hidden" name="Ds_MerchantParameters" value="' . $params . '">';
    $html .= '<input type="hidden" name="Ds_Signature" value="' . $signature . '">';
    $html .= '</form>';
    $html .= '<script type="text/javascript">';
    $html .= 'console.log("Iniciando redireccion a Redsys...");';
    $html .= 'setTimeout(function() {';
    $html .= 'var form = document.getElementById("formulario_redsys");';
    $html .= 'if(form) { console.log("Formulario encontrado, enviando..."); form.submit(); } else { console.error("Formulario no encontrado"); alert("Error inicializando pago"); }';
    $html .= '}, 1000);';
    $html .= '</script>';

    guardar_datos_pedido($pedido, $reserva_data);
    return $html;
}

function is_production_environment() {
    return true; // PRUEBAS
}

function process_successful_payment($order_id, $params) {
    error_log('=== PROCESANDO PAGO EXITOSO CON REDSYS ===');
    error_log("Order ID: $order_id");
    
    if (!session_id()) {
        session_start();
    }
    
    global $wpdb;
    
    // ✅ VERIFICAR SI ES VISITA O AUTOBÚS
    $reservation_data = recuperar_datos_pedido($order_id);
    
    if (!$reservation_data) {
        error_log('❌ No se encontraron datos para order: ' . $order_id);
        send_lost_payment_alert($order_id, $params);
        return false;
    }
    
    $is_visita = isset($reservation_data['is_visita']) && $reservation_data['is_visita'];
    
    if ($is_visita) {
        error_log('✅ Procesando pago de VISITA GUIADA');
        return process_visita_payment($order_id, $reservation_data, $params);
    } else {
        error_log('✅ Procesando pago de AUTOBÚS');
        $table_reservas = $wpdb->prefix . 'reservas_reservas';
        
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table_reservas WHERE redsys_order_id = %s",
            $order_id
        ));

        if ($existing) {
            error_log("⚠️ Reserva ya procesada para order_id: $order_id");
            return true;
        }

        if (!class_exists('ReservasProcessor')) {
            require_once RESERVAS_PLUGIN_PATH . 'includes/class-reservas-processor.php';
        }

        $processor = new ReservasProcessor();
        
        $processed_data = array(
            'nombre' => $reservation_data['nombre'] ?? '',
            'apellidos' => $reservation_data['apellidos'] ?? '',
            'email' => $reservation_data['email'] ?? '',
            'telefono' => $reservation_data['telefono'] ?? '',
            'reservation_data' => json_encode($reservation_data),
            'metodo_pago' => 'redsys',
            'transaction_id' => $params['Ds_AuthorisationCode'] ?? '',
            'order_id' => $order_id
        );

        $result = $processor->process_reservation_payment($processed_data);
        
        if ($result['success']) {
            error_log('✅ Reserva de autobús procesada: ' . $result['data']['localizador']);
            
            $_SESSION['confirmed_reservation'] = $result['data'];
            set_transient('confirmed_reservation_' . $order_id, $result['data'], 3600);
            set_transient('confirmed_reservation_loc_' . $result['data']['localizador'], $result['data'], 3600);
            set_transient('order_to_localizador_' . $order_id, $result['data']['localizador'], 3600);
            
            delete_transient('redsys_order_' . $order_id);
            delete_option('pending_order_' . $order_id);
            if (isset($_SESSION['pending_orders'][$order_id])) {
                unset($_SESSION['pending_orders'][$order_id]);
            }
            
            return true;
        } else {
            error_log('❌ Error procesando reserva autobús: ' . $result['message']);
            send_lost_payment_alert($order_id, $params, $reservation_data);
            return false;
        }
    }
}



function recuperar_datos_pedido($order_id) {
    error_log('=== RECUPERANDO DATOS DEL PEDIDO ===');
    error_log("Order ID: $order_id");
    
    // ✅ VERIFICAR SESIÓN PRIMERO
    if (!session_id() && !headers_sent()) {
        session_start();
    }
    
    // ✅ MÉTODO 1: Sesión
    if (isset($_SESSION['pending_orders'][$order_id])) {
        error_log('✅ Datos encontrados en sesión');
        return $_SESSION['pending_orders'][$order_id];
    }
    
    // ✅ MÉTODO 2: Transient (CRÍTICO - más confiable que sesión)
    $data = get_transient('redsys_order_' . $order_id);
    if ($data) {
        error_log('✅ Datos encontrados en transient');
        return $data;
    }
    
    // ✅ MÉTODO 3: Options (backup)
    $data = get_option('pending_order_' . $order_id);
    if ($data) {
        error_log('✅ Datos encontrados en options');
        return $data;
    }
    
    // ✅ MÉTODO 4: BUSCAR EN BASE DE DATOS (NUEVO - CRÍTICO)
    global $wpdb;
    $table_pending = $wpdb->prefix . 'reservas_pending_orders';
    
    // Verificar si existe la tabla
    if ($wpdb->get_var("SHOW TABLES LIKE '$table_pending'") == $table_pending) {
        $pending_data = $wpdb->get_var($wpdb->prepare(
            "SELECT order_data FROM $table_pending WHERE order_id = %s AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)",
            $order_id
        ));
        
        if ($pending_data) {
            error_log('✅ Datos encontrados en tabla pending_orders');
            return json_decode($pending_data, true);
        }
    }
    
    error_log('❌ NO SE ENCONTRARON DATOS - ENVIANDO ALERTA');
    
    // ✅ ENVIAR EMAIL DE ALERTA AL ADMIN
    $admin_email = get_option('admin_email');
    $subject = "⚠️ URGENTE: Pago recibido sin datos - Order: $order_id";
    $message = "Se recibió un pago de Redsys pero no se encontraron los datos de la reserva.\n\n";
    $message .= "Order ID: $order_id\n";
    $message .= "Fecha: " . date('Y-m-d H:i:s') . "\n\n";
    $message .= "ACCIÓN NECESARIA: Contactar con el cliente manualmente.\n";
    $message .= "Revisar logs del servidor para más detalles.";
    
    wp_mail($admin_email, $subject, $message);
    
    return null;
}

function send_lost_payment_alert($order_id, $params, $reservation_data = null) {
    error_log('=== ALERTA: PAGO SIN PROCESAR ===');
    error_log("Order ID: $order_id");
    error_log("Params: " . print_r($params, true));
    error_log("Reservation data: " . print_r($reservation_data, true));
    
    // Enviar email de alerta al administrador
    $admin_email = get_option('admin_email');
    $subject = "⚠️ ALERTA: Pago recibido sin procesar - Order: $order_id";
    
    $message = "Se recibió un pago exitoso de Redsys pero no se pudo procesar la reserva.\n\n";
    $message .= "Order ID: $order_id\n";
    $message .= "Código autorización: " . ($params['Ds_AuthorisationCode'] ?? 'N/A') . "\n";
    $message .= "Fecha: " . date('Y-m-d H:i:s') . "\n\n";
    $message .= "Por favor, revisa los logs del servidor y contacta con el cliente si es necesario.";
    
    wp_mail($admin_email, $subject, $message);
    
    error_log('📧 Email de alerta enviado a: ' . $admin_email);
}


function process_visita_payment($order_id, $reservation_data, $params) {
    global $wpdb;
    $table_visitas = $wpdb->prefix . 'reservas_visitas';
    
    // Verificar si ya existe
    $existing = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM $table_visitas WHERE redsys_order_id = %s",
        $order_id
    ));

    if ($existing) {
        error_log("⚠️ Visita ya procesada para order_id: $order_id");
        return true;
    }

    try {
        // Generar localizador
        $localizador = generar_localizador_visita_simple($reservation_data['agency_id']);
        
        // ✅ CALCULAR PRECIO DIRECTAMENTE (SIN VALIDACIÓN)
        $table_services = $wpdb->prefix . 'reservas_agency_services';
        
        $servicio = $wpdb->get_row($wpdb->prepare(
            "SELECT precio_adulto, precio_nino, precio_nino_menor FROM $table_services WHERE id = %d",
            $reservation_data['service_id']
        ));
        
        if (!$servicio) {
            throw new Exception('Servicio no encontrado');
        }
        
        $precio_total = ($reservation_data['adultos'] * floatval($servicio->precio_adulto)) +
                       ($reservation_data['ninos'] * floatval($servicio->precio_nino)) +
                       ($reservation_data['ninos_menores'] * floatval($servicio->precio_nino_menor));
        
        $insert_data = array(
            'localizador' => $localizador,
            'redsys_order_id' => $order_id,
            'service_id' => $reservation_data['service_id'],
            'agency_id' => $reservation_data['agency_id'],
            'fecha' => $reservation_data['fecha'],
            'hora' => $reservation_data['hora'],
            'nombre' => $reservation_data['nombre'],
            'apellidos' => $reservation_data['apellidos'],
            'email' => $reservation_data['email'],
            'telefono' => $reservation_data['telefono'],
            'adultos' => $reservation_data['adultos'],
            'ninos' => $reservation_data['ninos'],
            'ninos_menores' => $reservation_data['ninos_menores'],
            'total_personas' => $reservation_data['adultos'] + $reservation_data['ninos'] + $reservation_data['ninos_menores'],
            'idioma' => $reservation_data['idioma'] ?? 'español',
            'precio_total' => $precio_total,
            'estado' => 'confirmada',
            'metodo_pago' => 'redsys',
            'transaction_id' => $params['Ds_AuthorisationCode'] ?? '',
            'created_at' => current_time('mysql')
        );

        $result = $wpdb->insert($table_visitas, $insert_data);

        if ($result === false) {
            throw new Exception('Error insertando visita: ' . $wpdb->last_error);
        }

        $reserva_id = $wpdb->insert_id;
        error_log('✅ Visita guardada con ID: ' . $reserva_id . ' y localizador: ' . $localizador);

        // Preparar datos completos para email
        $table_services = $wpdb->prefix . 'reservas_agency_services';
        $servicio = $wpdb->get_row($wpdb->prepare(
            "SELECT s.*, a.agency_name, a.inicial_localizador, a.cif, a.razon_social, 
                    a.domicilio_fiscal, a.email as agency_email, a.phone
             FROM $table_services s
             INNER JOIN {$wpdb->prefix}reservas_agencies a ON s.agency_id = a.id
             WHERE s.id = %d",
            $reservation_data['service_id']
        ));

        $reserva_completa = array_merge($insert_data, array(
            'id' => $reserva_id,
            'precio_adulto' => $servicio->precio_adulto,
            'precio_nino' => $servicio->precio_nino,
            'precio_nino_menor' => $servicio->precio_nino_menor,
            'agency_name' => $servicio->agency_name,
            'is_visita' => true,
            'agency_logo_url' => $servicio->logo_url,
            'agency_cif' => $servicio->cif ?? '',
            'agency_razon_social' => $servicio->razon_social ?? '',
            'agency_domicilio_fiscal' => $servicio->domicilio_fiscal ?? '',
            'agency_email' => $servicio->agency_email ?? '',
            'agency_phone' => $servicio->phone ?? ''
        ));

        // Enviar emails
        enviar_email_confirmacion_visita($reserva_completa);

        // Guardar en sesión para página de confirmación
        if (!session_id()) {
            session_start();
        }
        
        $_SESSION['confirmed_visita'] = array(
            'localizador' => $localizador,
            'reserva_id' => $reserva_id,
            'detalles' => array(
                'fecha' => $reservation_data['fecha'],
                'hora' => $reservation_data['hora'],
                'personas' => $insert_data['total_personas'],
                'precio_total' => $precio_total
            )
        );

        // Guardar en transients también
        set_transient('confirmed_visita_' . $order_id, array(
            'localizador' => $localizador,
            'reserva_id' => $reserva_id
        ), 3600);
        
        set_transient('confirmed_visita_loc_' . $localizador, array(
            'localizador' => $localizador,
            'reserva_id' => $reserva_id
        ), 3600);
        
        set_transient('order_to_localizador_visita_' . $order_id, $localizador, 3600);

        // Limpiar datos temporales del pedido
        delete_transient('redsys_order_' . $order_id);
        delete_option('pending_order_' . $order_id);
        if (isset($_SESSION['pending_orders'][$order_id])) {
            unset($_SESSION['pending_orders'][$order_id]);
        }

        error_log('✅ Visita procesada exitosamente: ' . $localizador);
        return true;

    } catch (Exception $e) {
        error_log('❌ Error procesando visita: ' . $e->getMessage());
        send_lost_payment_alert($order_id, $params, $reservation_data);
        return false;
    }
}


function generar_localizador_visita_simple($agency_id) {
    global $wpdb;
    $table_visitas = $wpdb->prefix . 'reservas_visitas';
    $table_config = $wpdb->prefix . 'reservas_configuration';
    $table_agencies = $wpdb->prefix . 'reservas_agencies';

    // Obtener inicial de la agencia
    $agency = $wpdb->get_row($wpdb->prepare(
        "SELECT inicial_localizador FROM $table_agencies WHERE id = %d",
        $agency_id
    ));

    if (!$agency) {
        throw new Exception('Agencia no encontrada para generar localizador');
    }

    $inicial_agencia = $agency->inicial_localizador;
    $año_actual = date('Y');
    $config_key = "ultimo_localizador_visita_{$agency_id}_{$año_actual}";

    // Obtener el último número
    $ultimo_numero = $wpdb->get_var($wpdb->prepare(
        "SELECT config_value FROM $table_config WHERE config_key = %s",
        $config_key
    ));

    if ($ultimo_numero === null) {
        $nuevo_numero = 1;

        $wpdb->insert(
            $table_config,
            array(
                'config_key' => $config_key,
                'config_value' => '1',
                'config_group' => 'localizadores_visitas',
                'description' => "Último localizador de visita para agencia $agency_id en $año_actual"
            )
        );
    } else {
        $nuevo_numero = intval($ultimo_numero) + 1;

        $wpdb->update(
            $table_config,
            array('config_value' => $nuevo_numero),
            array('config_key' => $config_key)
        );
    }

    // Formato: VIS + INICIAL_AGENCIA + NÚMERO (6 dígitos)
    $localizador = 'VIS' . strtoupper($inicial_agencia) . str_pad($nuevo_numero, 6, '0', STR_PAD_LEFT);

    // Verificar que no exista
    $existe = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $table_visitas WHERE localizador = %s",
        $localizador
    ));

    if ($existe > 0) {
        // Si ya existe, buscar el siguiente disponible recursivamente
        return generar_localizador_visita_simple($agency_id);
    }

    error_log("✅ Localizador visita generado: $localizador");
    return $localizador;
}



function enviar_email_confirmacion_visita($reserva_data) {
    if (!class_exists('ReservasEmailService')) {
        require_once RESERVAS_PLUGIN_PATH . 'includes/class-email-service.php';
    }

    // Enviar email al cliente CON PDF
    $customer_result = ReservasEmailService::send_customer_confirmation($reserva_data);

    if ($customer_result['success']) {
        error_log('✅ Email enviado al cliente de visita guiada: ' . $reserva_data['email']);
    } else {
        error_log('❌ Error enviando email al cliente de visita: ' . $customer_result['message']);
    }

    // Enviar email al administrador
    $admin_result = ReservasEmailService::send_admin_notification($reserva_data);

    if ($admin_result['success']) {
        error_log('✅ Email enviado al admin sobre visita guiada');
    } else {
        error_log('❌ Error enviando email al admin: ' . $admin_result['message']);
    }
}



function get_reservation_data_for_confirmation() {
    error_log('=== INTENTANDO RECUPERAR DATOS DE CONFIRMACIÓN ===');
    
    // ✅ Método 1: Desde URL (order_id)
    if (isset($_GET['order']) && !empty($_GET['order'])) {
        $order_id = sanitize_text_field($_GET['order']);
        error_log('Order ID desde URL: ' . $order_id);
        
        // Buscar en transients
        $data = get_transient('confirmed_reservation_' . $order_id);
        if ($data) {
            error_log('✅ Datos encontrados en transient por order_id');
            return $data;
        }
        
        // Buscar en options temporales
        $data = get_option('temp_reservation_' . $order_id);
        if ($data) {
            error_log('✅ Datos encontrados en options por order_id');
            // Limpiar después de usar
            delete_option('temp_reservation_' . $order_id);
            return $data;
        }
    }
    
    // ✅ Método 2: Desde sesión
    if (!session_id()) {
        session_start();
    }
    
    if (isset($_SESSION['confirmed_reservation'])) {
        error_log('✅ Datos encontrados en sesión');
        $data = $_SESSION['confirmed_reservation'];
        // Limpiar sesión después de usar
        unset($_SESSION['confirmed_reservation']);
        return $data;
    }
    
    // ✅ Método 3: Buscar la reserva más reciente del último minuto
    global $wpdb;
    $table_reservas = $wpdb->prefix . 'reservas_reservas';
    
    $recent_reservation = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table_reservas 
         WHERE created_at >= DATE_SUB(NOW(), INTERVAL 2 MINUTE)
         AND metodo_pago = 'redsys'
         ORDER BY created_at DESC 
         LIMIT 1"
    ));
    
    if ($recent_reservation) {
        error_log('✅ Reserva reciente encontrada en BD: ' . $recent_reservation->localizador);
        
        return array(
            'localizador' => $recent_reservation->localizador,
            'reserva_id' => $recent_reservation->id,
            'detalles' => array(
                'fecha' => $recent_reservation->fecha,
                'hora' => $recent_reservation->hora,
                'personas' => $recent_reservation->total_personas,
                'precio_final' => $recent_reservation->precio_final
            )
        );
    }
    
    error_log('❌ No se encontraron datos de confirmación por ningún método');
    return null;
}

// CORREGIR EN class-redsys-handler.php

function guardar_datos_pedido($order_id, $reserva_data) {
    error_log('=== GUARDANDO DATOS DEL PEDIDO (MEJORADO) ===');
    error_log("Order ID: $order_id");
    
    // ✅ MÉTODO 1: Sesión
    if (!session_id() && !headers_sent()) {
        session_start();
    }
    
    if (!isset($_SESSION['pending_orders'])) {
        $_SESSION['pending_orders'] = array();
    }
    
    $_SESSION['pending_orders'][$order_id] = $reserva_data;
    error_log("✅ Guardado en sesión");
    
    // ✅ MÉTODO 2: Transient (24 horas - MÁS CONFIABLE)
    set_transient('redsys_order_' . $order_id, $reserva_data, 86400);
    error_log("✅ Guardado en transient (24h)");
    
    // ✅ MÉTODO 3: Option (backup - 24 horas)
    update_option('pending_order_' . $order_id, $reserva_data, false);
    // Programar limpieza
    wp_schedule_single_event(time() + 86400, 'delete_pending_order', array($order_id));
    error_log("✅ Guardado en option (24h)");
    
    // ✅ MÉTODO 4: BASE DE DATOS (NUEVO - MÁS PERSISTENTE)
    global $wpdb;
    $table_pending = $wpdb->prefix . 'reservas_pending_orders';
    
    $wpdb->replace(
        $table_pending,
        array(
            'order_id' => $order_id,
            'order_data' => json_encode($reserva_data),
            'created_at' => current_time('mysql')
        ),
        array('%s', '%s', '%s')
    );
    
    if ($wpdb->last_error) {
        error_log("❌ Error guardando en BD: " . $wpdb->last_error);
    } else {
        error_log("✅ Guardado en BD pending_orders");
    }
    
    error_log("✅ Datos guardados en 4 ubicaciones para order: $order_id");
}

