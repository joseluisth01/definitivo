/**
 * Funciones para gestión de servicios adicionales de agencias
 * Archivo: wp-content/plugins/sistema-reservas/assets/js/dashboardparte2-script.js
 */

// Variable global para almacenar el ID de agencia actual en edición
let currentAgencyIdForService = null;

/**
 * Cargar configuración de servicio para el modal de CREAR
 */
jQuery(document).ready(function ($) {
    console.log('✅ Dashboard Parte 2 inicializado correctamente');

    // Event listener para el checkbox principal en modal CREAR
    $(document).on('change', '#servicio_activo', function () {
        toggleServiceFields($(this).is(':checked'));
    });

    // Event listener para el checkbox principal en modal EDITAR
    $(document).on('change', '#edit_servicio_activo', function () {
        toggleServiceFieldsEdit($(this).is(':checked'));
    });

    // Event listeners para preview de imágenes - CREAR
    $(document).on('change', '#logo_image', function () {
        previewImageFile(this, 'logo', false);
    });

    $(document).on('change', '#portada_image', function () {
        previewImageFile(this, 'portada', false);
    });

    // Event listeners para preview de imágenes - EDITAR
    $(document).on('change', '#edit_logo_image', function () {
        previewImageFile(this, 'logo', true);
    });

    $(document).on('change', '#edit_portada_image', function () {
        previewImageFile(this, 'portada', true);
    });

    $(document).on('click', '.btn-add-excluded-date', function (e) {
        e.preventDefault();
        e.stopPropagation();
        const day = $(this).data('day');
        console.log('Añadiendo fecha excluida para día:', day);
        addExcludedDateSlot(day, false);
    });

    $(document).on('click', '.btn-remove-excluded-date', function (e) {
        e.preventDefault();
        e.stopPropagation();
        $(this).closest('.excluded-date-slot').remove();
    });

    $(document).on('click', '.btn-add-excluded-date', function () {
        const day = $(this).data('day');
        addExcludedDateSlot(day, false);
    });

    $(document).on('click', '.btn-remove-excluded-date', function () {
        $(this).closest('.excluded-date-slot').remove();
    });

    // Event listeners para añadir/eliminar fechas excluidas - EDITAR
    $(document).on('click', '.btn-add-excluded-date-edit', function () {
        const day = $(this).data('day');
        addExcludedDateSlot(day, true);
    });
});

/**
 * Cargar configuración de servicio cuando se abre modal de edición
 */
function loadAgencyServiceConfigForEdit(agencyId) {
    console.log('=== LOAD AGENCY SERVICE CONFIG FOR EDIT ===');
    console.log('Agency ID:', agencyId);

    currentAgencyIdForService = agencyId;

    const requestData = {
        action: 'get_agency_service',
        agency_id: agencyId,
        nonce: reservasAjax.nonce
    };

    console.log('Request data:', requestData);

    jQuery.ajax({
        url: reservasAjax.ajax_url,
        type: 'POST',
        data: requestData,
        success: function (response) {
            console.log('✅ SUCCESS Response:', response);
            if (response.success) {
                // ✅ CRÍTICO: Asegurarse de que los datos existen antes de popular
                if (response.data && typeof response.data === 'object') {
                    populateServiceForm(response.data, true);
                } else {
                    console.warn('⚠️ Datos vacíos, cargando formulario vacío');
                    populateServiceForm({
                        servicio_activo: 0,
                        horarios_disponibles: {},
                        precio_adulto: 0,
                        precio_nino: 0,
                        precio_nino_menor: 0,
                        logo_url: '',
                        portada_url: '',
                        descripcion: '',
                        titulo: '',
                        orden_prioridad: 999
                    }, true);
                }
            } else {
                console.error('❌ Error en respuesta:', response.data);
                populateServiceForm({
                    servicio_activo: 0,
                    horarios_disponibles: {},
                    precio_adulto: 0,
                    precio_nino: 0,
                    precio_nino_menor: 0,
                    logo_url: '',
                    portada_url: '',
                    descripcion: '',
                    titulo: '',
                    orden_prioridad: 999
                }, true);
            }
        },
        error: function (xhr, status, error) {
            console.error('❌ AJAX ERROR');
            console.error('Status:', status);
            console.error('Error:', error);
            console.error('Response Text:', xhr.responseText);
            console.error('Status Code:', xhr.status);

            // Cargar formulario vacío en caso de error
            populateServiceForm({
                servicio_activo: 0,
                horarios_disponibles: {},
                precio_adulto: 0,
                precio_nino: 0,
                precio_nino_menor: 0,
                logo_url: '',
                portada_url: '',
                descripcion: '',
                titulo: '',
                orden_prioridad: 999
            }, true);
        }
    });
}


/**
 * Añadir slot de fecha excluida
 */
function addExcludedDateSlot(day, isEdit) {
    console.log('=== AÑADIENDO FECHA EXCLUIDA ===');
    console.log('Día:', day);
    console.log('Es edición:', isEdit);

    const prefix = isEdit ? 'edit-' : '';
    const containerSelector = `#${prefix}hours-${day} .excluded-dates-list`;
    const excludedList = document.querySelector(containerSelector);

    console.log('Selector usado:', containerSelector);
    console.log('Elemento encontrado:', excludedList);

    if (!excludedList) {
        console.error('❌ No se encontró excluded-dates-list para el día:', day);
        console.error('Selector intentado:', containerSelector);
        return;
    }

    const dateSlot = document.createElement('div');
    dateSlot.className = 'excluded-date-slot';
    dateSlot.innerHTML = `
        <input type="date" name="fechas_excluidas[${day}][]" required min="${new Date().toISOString().split('T')[0]}">
        <button type="button" class="btn-remove-excluded-date" title="Eliminar fecha">✕</button>
    `;

    excludedList.appendChild(dateSlot);
    console.log('✅ Fecha excluida añadida para:', day);
}


/**
 * Mostrar/ocultar contenedor de horarios al marcar día
 */
function toggleDayHours(checkbox, isEdit) {
    const day = checkbox.value;
    const prefix = isEdit ? 'edit-' : '';
    const hoursContainer = document.getElementById(prefix + 'hours-' + day);

    console.log('Toggling hours para:', day, 'isEdit:', isEdit);

    if (checkbox.checked) {
        hoursContainer.style.display = 'block';

        // Añadir al menos un horario por defecto
        const hoursList = hoursContainer.querySelector('.hours-list');
        if (hoursList && hoursList.children.length === 0) {
            addHourSlot(day, isEdit);
        }

        // ✅ MOSTRAR SECCIÓN DE FECHAS EXCLUIDAS
        const excludedSection = hoursContainer.querySelector('.excluded-dates-section');
        if (excludedSection) {
            excludedSection.style.display = 'block';
            console.log('✅ Sección de fechas excluidas visible');
        } else {
            console.warn('⚠️ No se encontró excluded-dates-section para:', day);
        }
    } else {
        hoursContainer.style.display = 'none';

        // Limpiar horarios
        const hoursList = hoursContainer.querySelector('.hours-list');
        if (hoursList) {
            hoursList.innerHTML = '';
        }

        // ✅ LIMPIAR FECHAS EXCLUIDAS
        const excludedList = hoursContainer.querySelector('.excluded-dates-list');
        if (excludedList) {
            excludedList.innerHTML = '';
            console.log('✅ Fechas excluidas limpiadas');
        }
    }
}

/**
 * Añadir slot de horario
 */
function addHourSlot(day, isEdit) {
    const prefix = isEdit ? 'edit-' : '';
    const hoursList = document.querySelector('#' + prefix + 'hours-' + day + ' .hours-list');

    const hourSlot = document.createElement('div');
    hourSlot.className = 'hour-slot';
    hourSlot.innerHTML = `
        <input type="time" name="horarios[${day}][]" required>
        <button type="button" class="btn-remove-hour" onclick="removeHourSlot(this)">✕</button>
    `;

    hoursList.appendChild(hourSlot);
}

/**
 * Eliminar slot de horario
 */
function removeHourSlot(button) {
    const hourSlot = button.closest('.hour-slot');
    const hoursList = hourSlot.parentElement;

    // Si es el último horario, preguntar confirmación
    if (hoursList.children.length === 1) {
        if (!confirm('¿Eliminar el último horario? Esto desmarcará el día.')) {
            return;
        }

        // Desmarcar el día
        const day = hoursList.dataset.day;
        const checkbox = document.querySelector(`input[value="${day}"]`);
        if (checkbox) {
            checkbox.checked = false;
            const hoursContainer = hourSlot.closest('.hours-container');
            hoursContainer.style.display = 'none';
        }
    }

    hourSlot.remove();
}

/**
 * Recopilar horarios del formulario
 */
function collectHorariosData() {
    const horarios = {};

    // Buscar checkboxes marcados (tanto en crear como en editar)
    const checkboxes = document.querySelectorAll('.day-checkbox input:checked, .edit-day-checkbox:checked');

    checkboxes.forEach(checkbox => {
        const day = checkbox.value;
        const isEdit = checkbox.classList.contains('edit-day-checkbox');
        const prefix = isEdit ? 'edit-' : '';
        const hoursInputs = document.querySelectorAll(`#${prefix}hours-${day} input[type="time"]`);

        horarios[day] = [];
        hoursInputs.forEach(input => {
            if (input.value) {
                horarios[day].push(input.value);
            }
        });
    });

    return horarios;
}


/**
 * Rellenar formulario con datos del servicio (CORREGIDO Y MEJORADO)
 */
function populateServiceForm(serviceData, isEdit) {
    console.log('=== POPULATE SERVICE FORM ===');
    console.log('Service Data recibido:', serviceData);
    console.log('Is Edit:', isEdit);

    const prefix = isEdit ? 'edit_' : '';
    const checkboxId = isEdit ? '#edit_servicio_activo' : '#servicio_activo';

    // ✅ VALIDAR QUE serviceData existe y es un objeto
    if (!serviceData || typeof serviceData !== 'object') {
        console.error('❌ serviceData inválido:', serviceData);
        serviceData = {
            servicio_activo: 0,
            horarios_disponibles: {},
            precio_adulto: 0,
            precio_nino: 0,
            precio_nino_menor: 0,
            logo_url: '',
            portada_url: '',
            descripcion: '',
            titulo: '',
            orden_prioridad: 999
        };
    }

    // ✅ CRÍTICO: Convertir servicio_activo a booleano correctamente
    const servicioActivo = (serviceData.servicio_activo == 1 || serviceData.servicio_activo === true);
    
    console.log('🔍 servicio_activo en datos:', serviceData.servicio_activo);
    console.log('✅ Checkbox servicio activo se marcará como:', servicioActivo);

    // Marcar/desmarcar checkbox
    jQuery(checkboxId).prop('checked', servicioActivo);

    // Mostrar/ocultar campos según estado
    if (isEdit) {
        toggleServiceFieldsEdit(servicioActivo);
    } else {
        toggleServiceFields(servicioActivo);
    }

    // ✅ SI EL SERVICIO NO ESTÁ ACTIVO, SALIR AQUÍ
    if (!servicioActivo) {
        console.log('⚠️ Servicio NO activo, no se cargarán datos adicionales');
        return; // ✅ NO CARGAR HORARIOS NI PRECIOS
    }

    // ✅ SOLO LLEGAR AQUÍ SI EL SERVICIO ESTÁ ACTIVO
    console.log('✅ Servicio activo, cargando datos completos...');

    // ✅ PRECIOS
    jQuery('#' + prefix + 'precio_adulto_servicio').val(serviceData.precio_adulto || '');
    jQuery('#' + prefix + 'precio_nino_servicio').val(serviceData.precio_nino || '');
    jQuery('#' + prefix + 'precio_nino_menor_servicio').val(serviceData.precio_nino_menor || '');

    // ✅ DESCRIPCIÓN Y TÍTULO
    jQuery('#' + prefix + 'descripcion_servicio').val(serviceData.descripcion || '');
    jQuery('#' + prefix + 'titulo_servicio').val(serviceData.titulo || '');
    jQuery('#' + prefix + 'orden_prioridad').val(serviceData.orden_prioridad || 999);

    // ✅ CARGAR DÍAS Y HORARIOS MEJORADO
    if (serviceData.horarios_disponibles) {
        let horarios;

        try {
            // Intentar parsear si es string
            if (typeof serviceData.horarios_disponibles === 'string') {
                horarios = JSON.parse(serviceData.horarios_disponibles);
            } else {
                horarios = serviceData.horarios_disponibles;
            }

            console.log('✅ Horarios parseados correctamente:', horarios);
        } catch (e) {
            console.error('❌ Error parseando horarios:', e);
            console.error('Dato recibido:', serviceData.horarios_disponibles);
            horarios = {};
        }

        // ✅ VALIDAR QUE horarios es un objeto
        if (!horarios || typeof horarios !== 'object') {
            console.warn('⚠️ Horarios inválidos, usando objeto vacío');
            horarios = {};
        }

        // ✅ LIMPIAR TODOS LOS CHECKBOXES Y HORARIOS ANTES DE CARGAR
        if (isEdit) {
            jQuery('.edit-day-checkbox').prop('checked', false);
            jQuery('[id^="edit-hours-"]').hide();
            jQuery('.hours-list').empty();
        } else {
            jQuery('.day-checkbox input[type="checkbox"]').prop('checked', false);
            jQuery('[id^="hours-"]').hide();
            jQuery('.hours-list').empty();
        }

        // Recorrer días y sus horarios
        Object.keys(horarios).forEach(day => {
            console.log(`Procesando día: ${day}`);

            const dayCheckbox = isEdit
                ? jQuery(`.edit-day-checkbox[value="${day}"]`)
                : jQuery(`.day-checkbox input[value="${day}"]`).not('.edit-day-checkbox');

            if (dayCheckbox.length > 0) {
                // Marcar checkbox
                dayCheckbox.prop('checked', true);
                console.log(`✅ Checkbox marcado para ${day}`);

                // Mostrar contenedor de horarios
                toggleDayHours(dayCheckbox[0], isEdit);

                // Obtener contenedor de horarios
                const prefix2 = isEdit ? 'edit-' : '';
                const hoursList = document.querySelector(`#${prefix2}hours-${day} .hours-list`);

                if (!hoursList) {
                    console.error(`❌ No se encontró hours-list para el día: ${day}`);
                    return;
                }

                // Limpiar horarios existentes
                hoursList.innerHTML = '';

                // ✅ VALIDAR QUE horarios[day] es un array
                const horasDelDia = Array.isArray(horarios[day]) ? horarios[day] : [];

                console.log(`Añadiendo ${horasDelDia.length} horarios para ${day}:`, horasDelDia);

                // Añadir cada horario
                horasDelDia.forEach((hora, index) => {
                    const hourSlot = document.createElement('div');
                    hourSlot.className = 'hour-slot';
                    hourSlot.innerHTML = `
                        <input type="time" name="horarios[${day}][]" value="${hora}" required>
                        <button type="button" class="btn-remove-hour" onclick="removeHourSlot(this)">✕</button>
                    `;
                    hoursList.appendChild(hourSlot);
                    console.log(`✅ Horario ${index + 1}/${horasDelDia.length} añadido para ${day}: ${hora}`);
                });
            } else {
                console.warn(`⚠️ No se encontró checkbox para el día: ${day}`);
            }
        });

        console.log('✅ Todos los horarios cargados correctamente');
    } else {
        console.log('ℹ️ No hay horarios disponibles en los datos');
    }

    if (serviceData.idiomas_disponibles) {
        let idiomas;

        try {
            if (typeof serviceData.idiomas_disponibles === 'string') {
                idiomas = JSON.parse(serviceData.idiomas_disponibles);
            } else {
                idiomas = serviceData.idiomas_disponibles;
            }

            console.log('✅ Idiomas parseados:', idiomas);
        } catch (e) {
            console.error('❌ Error parseando idiomas:', e);
            idiomas = {};
        }

        if (idiomas && typeof idiomas === 'object') {
            Object.keys(idiomas).forEach(day => {
                const idiomasDelDia = Array.isArray(idiomas[day]) ? idiomas[day] : [];
                const prefix2 = isEdit ? 'edit-' : '';

                // Desmarcar todos primero
                const idiomasCheckboxes = document.querySelectorAll(`#${prefix2}hours-${day} .idiomas-checkboxes input[type="checkbox"]`);
                idiomasCheckboxes.forEach(cb => cb.checked = false);

                idiomasDelDia.forEach(idioma => {
                    // ✅ NORMALIZAR al buscar checkbox
                    let idiomaValue = idioma;
                    if (idioma === 'español') {
                        idiomaValue = 'espanol';
                    }

                    const checkbox = document.querySelector(`#${prefix2}hours-${day} .idiomas-checkboxes input[value="${idiomaValue}"]`);
                    if (checkbox) {
                        checkbox.checked = true;
                        console.log(`✅ Idioma ${idioma} marcado para ${day}`);
                    } else {
                        console.warn(`⚠️ No se encontró checkbox para idioma ${idioma}`);
                    }
                });
            });
        }
    }

    // ✅ CARGAR FECHAS EXCLUIDAS MEJORADO
    if (serviceData.fechas_excluidas) {
        let fechas_excluidas;

        try {
            if (typeof serviceData.fechas_excluidas === 'string') {
                fechas_excluidas = JSON.parse(serviceData.fechas_excluidas);
            } else {
                fechas_excluidas = serviceData.fechas_excluidas;
            }

            console.log('✅ Fechas excluidas parseadas:', fechas_excluidas);
        } catch (e) {
            console.error('❌ Error parseando fechas excluidas:', e);
            fechas_excluidas = {};
        }

        if (fechas_excluidas && typeof fechas_excluidas === 'object') {
            Object.keys(fechas_excluidas).forEach(day => {
                const fechas = fechas_excluidas[day];

                if (Array.isArray(fechas) && fechas.length > 0) {
                    const prefix2 = isEdit ? 'edit-' : '';
                    const excludedList = document.querySelector(`#${prefix2}hours-${day} .excluded-dates-list`);

                    if (excludedList) {
                        // Limpiar fechas existentes
                        excludedList.innerHTML = '';

                        console.log(`Añadiendo ${fechas.length} fechas excluidas para ${day}`);

                        fechas.forEach((fecha, index) => {
                            const dateSlot = document.createElement('div');
                            dateSlot.className = 'excluded-date-slot';
                            dateSlot.innerHTML = `
                                <input type="date" name="fechas_excluidas[${day}][]" value="${fecha}" required>
                                <button type="button" class="btn-remove-excluded-date" title="Eliminar fecha">✕</button>
                            `;
                            excludedList.appendChild(dateSlot);
                            console.log(`✅ Fecha excluida ${index + 1} añadida: ${fecha}`);
                        });
                    } else {
                        console.warn(`⚠️ No se encontró excluded-dates-list para ${day}`);
                    }
                }
            });
        }
    }

    // ✅ IMÁGENES
    if (serviceData.logo_url) {
        showExistingImage('logo', serviceData.logo_url, isEdit);
    } else {
        hideExistingImage('logo', isEdit);
    }

    if (serviceData.portada_url) {
        showExistingImage('portada', serviceData.portada_url, isEdit);
    } else {
        hideExistingImage('portada', isEdit);
    }

    console.log('=== POPULATE SERVICE FORM COMPLETADO ===');
}

/**
 * Mostrar/ocultar campos de servicio (CREAR)
 */
function toggleServiceFields(show) {
    const fieldsContainer = jQuery('#service-fields-container');
    if (show) {
        fieldsContainer.slideDown(300);
    } else {
        fieldsContainer.slideUp(300);
    }
}

/**
 * Mostrar/ocultar campos de servicio (EDITAR)
 */
function toggleServiceFieldsEdit(show) {
    const fieldsContainer = jQuery('#edit-service-fields-container');
    if (show) {
        fieldsContainer.slideDown(300);
    } else {
        fieldsContainer.slideUp(300);
    }
}

/**
 * Mostrar imagen existente
 */
function showExistingImage(type, url, isEdit) {
    const prefix = isEdit ? 'edit-' : '';
    const previewId = prefix + type + '-preview';
    const containerId = prefix + type + '-preview-container';

    // Eliminar preview anterior si existe
    jQuery('#' + previewId).remove();

    const containerHtml = `
        <div id="${previewId}" class="image-preview" style="margin-top: 10px;">
            <img src="${url}" alt="${type}" style="max-width: 200px; max-height: 150px; border-radius: 4px; border: 2px solid #ddd;">
            <button type="button" class="btn-remove-image" onclick="removeExistingImage('${type}', ${isEdit})">
                ✕ Eliminar
            </button>
        </div>
    `;

    jQuery('#' + containerId).html(containerHtml);
}

/**
 * Ocultar preview de imagen
 */
function hideExistingImage(type, isEdit) {
    const prefix = isEdit ? 'edit-' : '';
    const previewId = prefix + type + '-preview';
    jQuery('#' + previewId).remove();
}

/**
 * Eliminar imagen existente
 */
function removeExistingImage(type, isEdit) {
    if (confirm('¿Deseas eliminar esta imagen?')) {
        hideExistingImage(type, isEdit);

        const prefix = isEdit ? 'edit_' : '';
        const inputId = prefix + type + '_image';

        // Resetear input file
        jQuery('#' + inputId).val('');
    }
}

/**
 * Preview de imagen al seleccionar archivo
 */
function previewImageFile(input, type, isEdit) {
    if (input.files && input.files[0]) {
        // Validar tamaño (2MB máximo)
        if (input.files[0].size > 2097152) {
            alert('La imagen no puede superar los 2MB');
            jQuery(input).val('');
            return;
        }

        // Validar tipo
        const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
        if (!allowedTypes.includes(input.files[0].type)) {
            alert('Solo se permiten imágenes (JPG, PNG, GIF)');
            jQuery(input).val('');
            return;
        }

        const reader = new FileReader();

        reader.onload = function (e) {
            showExistingImage(type, e.target.result, isEdit);
        };

        reader.readAsDataURL(input.files[0]);
    }
}

/**
 * Función auxiliar para mostrar notificaciones
 */
function showNotification(message, type) {
    // Tipos: success, error, info, warning
    const bgColors = {
        success: '#00a32a',
        error: '#d63638',
        info: '#0073aa',
        warning: '#f57c00'
    };

    const notification = jQuery('<div>')
        .css({
            position: 'fixed',
            top: '20px',
            right: '20px',
            padding: '15px 25px',
            background: bgColors[type] || bgColors.info,
            color: 'white',
            borderRadius: '8px',
            boxShadow: '0 4px 12px rgba(0,0,0,0.3)',
            zIndex: 10000,
            fontSize: '14px',
            fontWeight: '600',
            maxWidth: '400px',
            animation: 'slideInRight 0.3s ease'
        })
        .text(message);

    jQuery('body').append(notification);

    setTimeout(function () {
        notification.fadeOut(300, function () {
            jQuery(this).remove();
        });
    }, 3000);
}