# Guía de MoodleX Hub

Abre **MoodleX Hub** con `/local/moodlexhub/index.php` añadido a la URL de tu sitio. También puedes buscar **MoodleX Hub** en **Administración del sitio → Extensiones → Extensiones locales**. Las opciones dependen de tus capacidades, del curso y de los módulos habilitados.

| Versión | Dónde empezar | Directorio de instalación |
|---|---|---|
| 5.0 | `/local/moodlexhub/index.php` | `local/moodlexhub` |
| 5.1 | `/local/moodlexhub/index.php` | `public/local/moodlexhub` |
| 5.2 | `/local/moodlexhub/index.php` | `public/local/moodlexhub` |
| 5.3 | `/local/moodlexhub/index.php` | `public/local/moodlexhub` |

Las rutas de la tabla siguiente se aplican a las cuatro versiones. Los enlaces a calificaciones, actividades, copia de cursos y otras herramientas nativas abren la pantalla de la versión instalada.

| Función | Dónde abrirla | Cómo funciona |
|---|---|---|
| Panel y preferencias | MoodleX Hub → **Preferencias del panel** | Activa los cursos, la actividad reciente y las notificaciones disponibles. El buscador global devuelve cursos, artículos publicados y tickets que puedes abrir. Cambia entre español e inglés en el panel. |
| Catálogo de cursos | MoodleX Hub → **Cursos** | Busca por nombre, nombre corto o número de identificación. Filtra por categoría, visibilidad y hallazgos. Abre un curso para consultar contactos y enlazar a participantes y registros nativos. Compara dos configuraciones desde el catálogo. El enlace de copia abre el asistente y el seguimiento de Moodle. La exportación requiere una capacidad adicional. |
| Usuarios y matrículas | MoodleX Hub → **Usuarios**, o **Cursos** → curso → **Importaciones de matrículas** | Busca usuarios y filtra suspendidos, cuentas sin matrículas y coincidencias por correo. Descarga la plantilla, indica cuentas Moodle existentes en `username`, selecciona un método activo y un rol asignable. Valida, revisa y confirma. Cron procesa los lotes. Consulta y exporta los resultados por fila o reintenta los fallos recuperables. |
| Calidad | MoodleX Hub → **Cursos** → curso → **Auditoría de calidad**, o **Auditoría masiva** desde **Cursos** | Ejecuta una auditoría o selecciona una categoría para procesar un lote. Abre los resultados para consultar evidencia y recomendaciones. Compara auditorías históricas. Las reglas cumplidas aportan sus pesos al numerador y las reglas aplicables establecen el denominador. |
| Diagnóstico de calificaciones | MoodleX Hub → **Cursos** → curso → **Diagnóstico del libro de calificaciones** | Consulta agregaciones, rangos, escalas, visibilidad y problemas de configuración. Filtra los hallazgos o guarda un resumen para comparar el histórico. El diagnóstico mantiene las notas intactas. |
| Almacenamiento | MoodleX Hub → **Cursos** → curso → **Almacenamiento**, o el informe de categoría | Envía un análisis a Moodle cron y revisa histórico, extensiones, archivos grandes, referencias y candidatos a huérfanos. El tamaño lógico suma todos los registros y la estimación local deduplica hashes y excluye referencias externas. |
| Mi planificador | MoodleX Hub → **Mi planificador** | Consulta próximas acciones del calendario, selecciona un curso para ver sus estados de finalización, marca favoritos y crea notas privadas. Los recordatorios son eventos personales del calendario nativo de Moodle. |
| Panel docente | MoodleX Hub → **Panel docente** | Selecciona un curso y un grupo permitido. Consulta participantes con matrícula activa, último acceso, progreso y carga de trabajo de Tareas. Moodle calcula la carga de calificación de tareas individuales. |
| Analítica de cursos | MoodleX Hub → **Analítica de cursos** | Selecciona curso, grupo, estudiante y período disponibles. Los gráficos comparan ventanas del mismo tamaño. Lecturas y participación usan criterios distintos sobre el registro de Moodle. En la vista por categoría puedes filtrar por contacto docente. Consulta las fórmulas en [analítica](ANALYTICS.md). |
| Centro de soporte | MoodleX Hub → **Ayuda y soporte** | Crea un ticket y adjunta documentos si corresponde. Consulta estados y respuestas desde el mismo registro. El solicitante ve sus tickets. El personal con permisos únicamente de respuesta ve los tickets que le asignaron. |
| Base de conocimientos | **Ayuda y soporte → Base de conocimientos** | Busca artículos, preguntas frecuentes y tutoriales por texto, categoría y tipo. Los gestores pueden redactarlos y publicarlos desde la misma pantalla. |
| Banco de preguntas | MoodleX Hub → **Cursos** → curso → **Banco de preguntas** | Filtra por banco, categoría, autor, tipo o estado. Revisa la versión más reciente, las coincidencias de títulos y el número de referencias directas visibles. |
| Supervisión | MoodleX Hub → **Supervisión técnica** | Consulta versiones de Moodle, PHP y base de datos, colas, tareas programadas y resultados del registro de tareas. Con permiso de configuración, abre el resumen general de Moodle. |
| Automatización | MoodleX Hub → **Automatización** | Elige alcance, condición, acción, destinatario y frecuencia. Activa la regla para que cron la ejecute o lánzala manualmente y consulta su historial. Una ejecución programada se registra una sola vez por regla y período. |
| Asistente | MoodleX Hub → **Asistente de IA** | Tras habilitarlo y configurar Moodle AI, elige el tipo de ayuda, revisa el texto y confirma el consentimiento. Algunos modos requieren un artículo publicado o una auditoría de curso accesible. La prueba del proveedor y los límites se explican en [configuración del asistente](AI_INTEGRATION.md). |
| Retroalimentación | MoodleX Hub → **Cursos** → curso → **Biblioteca de retroalimentación** | Crea y busca plantillas personales o compartidas, añade una referencia de rúbrica, guárdalas como favoritas y prepara borradores. El enlace de calificación abre la actividad de Moodle para revisar y aplicar el comentario manualmente. |
| Integraciones | MoodleX Hub → **Integraciones** | Configura destinos HTTPS autorizados y consulta sus entregas. Para la API de lectura habilita los servicios web y otorga las capacidades correspondientes según la [referencia de la API](API.md). |
| Informes | MoodleX Hub → **Informes** | Crea una definición con origen, alcance, filtros y programación. Genera, abre el historial y exporta en CSV, Excel o PDF según los formatos disponibles en Moodle. La ejecución registra fecha, filtros y metodología. |

Consulta [los resultados de pruebas](TEST_RESULTS.md) para saber qué flujos se ejecutaron y en qué versión se comprobaron.
