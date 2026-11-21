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

## Capacidades por herramienta

El prefijo de todas las capacidades de la tabla es `local/moodlexhub:`.

| Herramienta | Capacidades y alcance |
|---|---|
| Panel | `viewdashboard` en el sitio. `manageconfiguration` habilita la configuración de MoodleX Hub. |
| Cursos | `viewcourses` en el curso. La copia exige `managecourses` y las capacidades nativas de respaldo, restauración y destino. |
| Usuarios y matrículas | `viewusers` en el sitio. El CSV requiere `bulkenrolusers` en el curso y permisos nativos para el método de matrícula y el rol elegido. |
| Calidad | `auditcourses` y capacidad nativa de actualización en el curso. `managequalityrules` permite definir pesos y retención. |
| Analítica y docencia | `viewanalytics` o `viewteacher` y acceso nativo a participantes en el curso. Los gráficos, la finalización y el trabajo de calificación aplican sus permisos Moodle respectivos. |
| Seguimiento académico | `managefollowup` junto con `viewteacher` y acceso a participantes. Se aplica el grupo del curso, los permisos del módulo y la visibilidad de cada actividad. La asignación es voluntaria y la persona responsable puede liberarla. |
| Calificaciones, almacenamiento y banco | `viewgradesdiagnostics`, `viewstorage` o `viewquestions` en el curso. Cada controlador aplica además las capacidades del origen de datos. |
| Planificador y soporte | `viewstudent` y `usesupport` en el sitio. El acceso al ticket depende del solicitante, de la asignación o de `managetickets`. |
| Supervisión, automatización y asistente | `viewmonitoring`, `manageautomation`, `useassistant` o `manageai`, según la operación. Las automatizaciones vuelven a comprobar las capacidades al ejecutar. |
| Retroalimentación | `managefeedback` y `mod/assign:grade` en el curso, con acceso a la Tarea si se vincula una. |
| Informes e integraciones | `exportreports`, `manageintegrations` o `useapi` en el sitio. Las lecturas conservan las capacidades y el alcance de la fuente. |

## Tareas y retención

Moodle cron procesa copias, importaciones, auditorías masivas, almacenamiento, notificaciones, generación de informes, reglas y webhooks. Supervisa el historial en **MoodleX Hub → Supervisión técnica** y la cola detallada en la administración de tareas nativa de Moodle.

Las reglas de calidad admiten pesos 1, 2 y 3. El valor 0 desactiva la regla. El plazo de retención configurado en esa página se aplica al historial de auditoría y a resultados históricos del plugin. Los metadatos de uso del asistente mantienen su retención de 90 días. Consulte [privacidad](PRIVACY.md) para tickets, notas y CSV.
