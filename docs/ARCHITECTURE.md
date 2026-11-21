# Arquitectura

MoodleX Hub se instala como un plugin local `local_moodlexhub`. Los controladores PHP establecen el contexto y autorizan operaciones antes de renderizar plantillas Mustache o invocar servicios. Los formularios emplean Moodle Form API y los mensajes de interfaz vienen de los catálogos español e inglés.

| Capa | Ubicación | Responsabilidad |
|---|---|---|
| Entrada y vistas | Controladores raíz, `classes/form`, `classes/output`, `templates` | Sesión, parámetros, contexto, validación de formulario y presentación. |
| Servicios funcionales | `classes/course`, `classes/enrol`, `classes/quality`, `classes/academic`, `classes/grade`, `classes/student`, `classes/feedback`, `classes/question`, `classes/storage`, `classes/support` | Operaciones de cada módulo y cálculo de sus datos. |
| Operaciones compartidas | `classes/report`, `classes/automation`, `classes/task`, `classes/event` | Informes, programaciones, estados, ejecución por bloques y registro. |
| Integraciones | `classes/ai`, `classes/external`, `classes/integration` | Moodle AI, Web Services, cuotas y entregas de webhooks firmados. |
| Persistencia y políticas | `db`, `classes/privacy` | XMLDB, migraciones, capacidades, retención, exportación y borrado. |

Los módulos de informes y automatización invocan los servicios que autorizan la consulta original. Las lecturas de metadatos de Moodle usan DML parametrizado cuando la API de la rama no proporciona una consulta agregada equivalente. Las escrituras sobre matrícula, calendario, notificaciones, favoritos y archivos pasan por la API nativa correspondiente. La copia de cursos usa el flujo de copia de seguridad y restauración de Moodle.

El asistente obtiene los artículos publicados del servicio de soporte y los resultados más recientes del servicio de auditoría. El servicio de tickets expone recuentos agregados y una comprobación de antigüedad para informes y automatizaciones. La ocasión de ejecutar un informe guardado se valida contra su propietario y permisos de origen.

Las operaciones programadas se despachan en lotes con Moodle Task API. Los procesos conservan resultados en las tablas del plugin. Las claves por operación y el bloqueo limitado controlan la repetición. Las tareas ligadas a un solicitante revalidan el contexto al ejecutarse.
