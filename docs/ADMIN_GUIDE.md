# Administración

## Ajustes

En **Administración del sitio → Extensiones → Extensiones locales** están el panel, la configuración general, los módulos, las reglas de calidad y el asistente. En **Módulos de MoodleX Hub** se controla la disponibilidad de cada herramienta. El asistente tiene un interruptor propio y comienza desactivado. Los ajustes generales incluyen el tamaño de lote para matrícula CSV y auditoría masiva, y la activación de webhooks para destinos autorizados.

## Permisos y perfiles

Consulta **Administración del sitio → Usuarios → Permisos → Definir roles** para asignar las capacidades con prefijo `local/moodlexhub:`. Los permisos de contexto de Moodle siguen aplicando en las herramientas que operan sobre cursos.

| Perfil | Capacidades relevantes |
|---|---|
| Administración | Configuración de módulos, supervisión, IA, API e integraciones. Moodle administra la condición de administrador del sitio. |
| Gestión | Catálogo, usuarios, auditoría, almacenamiento, informes y gestión de soporte según las capacidades del gestor. |
| Coordinación | Asigna un rol Moodle en las categorías correspondientes y concede únicamente las capacidades de curso que deba ejercer. |
| Docencia | Panel docente y flujos de curso. Las operaciones de evaluación requieren además las capacidades nativas de calificación. |
| Estudiantes | Panel, planificador y solicitudes propias de soporte con la capacidad de usuario autenticado. |
| Soporte | `respondtickets` para responder los tickets asignados y `managetickets` para gestionar la cola completa. `manageknowledgebase` autoriza la publicación de ayuda. |

## Tareas y retención

Moodle cron procesa copias, importaciones, auditorías masivas, almacenamiento, notificaciones, generación de informes, reglas y webhooks. Supervisa el historial en **MoodleX Hub → Supervisión técnica** y la cola detallada en la administración de tareas nativa de Moodle.

Las reglas de calidad admiten pesos 1, 2 y 3. El valor 0 desactiva la regla. El plazo de retención configurado en esa página se aplica al historial de auditoría y a resultados históricos del plugin. Los metadatos de uso del asistente mantienen su retención de 90 días. Consulte [privacidad](PRIVACY.md) para tickets, notas y CSV.
