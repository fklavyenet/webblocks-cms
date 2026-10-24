<?php

return [
  'refresh_completed' => 'Se han actualizado las comprobaciones y los informes de mantenimiento.',
  'refresh_pending' => 'Ya hay otra actualización de informes en curso. Espera a que termine y vuelve a cargar esta página.',
  'refreshing' => 'Actualizando comprobaciones…',
  'cleanup_report_unavailable' => 'Los datos de almacenamiento e historial estarán disponibles cuando termine el primer informe. Actualiza las comprobaciones para generarlo. Los ajustes de retención siguen disponibles en la siguiente pestaña.',
  'cleanup_actions_unavailable' => 'Las vistas previas de limpieza estarán disponibles cuando termine el primer informe. Actualiza las comprobaciones para calcular los elementos aptos antes de elegir una acción de limpieza.',
  'refresh_failed' => 'No se pudieron actualizar los informes. Se conserva el último informe completado.',
  'missing' => 'El informe aún no está disponible.',
  'stale' => 'Último informe completado: :time. Estos valores pueden estar desactualizados.',
  'fresh' => 'Último informe completado: :time.',
  'help' => 'Los informes se actualizan mediante el programador de Laravel. Para actualizarlos manualmente, abre Estado del sistema y actualiza las comprobaciones.',
  'validation_pending' => 'Hay :count archivo(s) de copia pendientes de validación. La elegibilidad para limpieza queda suspendida hasta que termine.',
];
