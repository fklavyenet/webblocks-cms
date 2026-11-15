<?php

return [
  'title' => 'Informe de diagnóstico',
  'help' => 'Prepara un informe limitado de la instalación para revisarlo o descargarlo. No se envían datos a ningún proveedor de soporte. Los informes caducan a los 30 minutos.',
  'categories' => 'Incluir en el informe',
  'category' => [
    'system_summary' => 'Versiones del sistema y del entorno de ejecución',
    'update_run' => 'Ejecución de actualización seleccionada o más reciente',
    'plugin_health' => 'Versiones y estado de activación de los plugins instalados',
    'saved_health' => 'Recuentos guardados de comprobaciones de estado',
    'recent_application_errors' => 'Marcas de tiempo y huellas de errores recientes',
  ],
  'errors_help' => 'El diagnóstico de errores solo incluye marcas de tiempo, niveles de gravedad y huellas. Se excluyen los mensajes, los datos de las solicitudes, las credenciales, las rutas, el contenido y los registros completos. No se recopilan datos de otros canales de registro.',
  'consent' => 'Preparar la información de diagnóstico seleccionada para que pueda revisarla.',
  'prepare' => 'Preparar informe de diagnóstico',
  'review' => 'Revisa a continuación el informe tal como se descargará. Para quitar categorías, cambia la selección y prepara otro informe. La descarga no recopila datos nuevos.',
  'download' => 'Descargar informe revisado',
  'expired' => 'Este informe ha caducado o pertenece a otro usuario. Prepara un informe nuevo.',
];
