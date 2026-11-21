# Modelo de datos

El esquema se define en `db/install.xml`. Las migraciones son incrementales y están en `db/upgrade.php`. Las relaciones con Moodle identifican el contexto y el sujeto de los datos. Se gestionan mediante XMLDB y Moodle DML.

| Tabla | Contenido y ámbito |
|---|---|
| `local_moodlexhub_audit` | Puntuación, observaciones agregadas, fecha y actor opcional de cada auditoría de curso. |
| `local_moodlexhub_import` | Solicitud de matrícula CSV y estado por curso. Identifica solicitante, método y rol. |
| `local_moodlexhub_imp_row` | Número de registro único por importación, cuenta resuelta opcional, estado, motivo e intentos. |
| `local_moodlexhub_snapshot` | Historial de diagnósticos agregados por curso y módulo. Contiene estado, resumen y fechas. |
| `local_moodlexhub_note` | Texto de las notas privadas del planificador. Cada nota está asociada a su titular. |
| `local_moodlexhub_ticket` | Solicitud de soporte, responsable, estado, prioridad y fechas. |
| `local_moodlexhub_tk_post` | Respuestas y transiciones por ticket. |
| `local_moodlexhub_article` | Entradas de ayuda con categoría, tipo y estado de publicación. |
| `local_moodlexhub_ai_use` | Uso del asistente por usuario y modo, estado, recuentos y fecha. |
| `local_moodlexhub_feedback` | Borradores y plantillas de retroalimentación personales o compartidas por curso. |
| `local_moodlexhub_report` | Definiciones de informes con propietario, filtros y programación. |
| `local_moodlexhub_rpt_run` | Historial de generación por definición de informe y fecha. |
| `local_moodlexhub_auto` | Reglas con ámbito, condición, acción, frecuencia y destinatario. |
| `local_moodlexhub_auto_run` | Resultado de una regla por ejecución y período programado. |
| `local_moodlexhub_endpoint` | Destinos configurados y secretos de firma cifrados. |
| `local_moodlexhub_api_log` | Estado e intentos de entregas, sin credenciales ni cuerpo de respuesta. |
| `local_moodlexhub_aud_batch` | Lotes de auditorías de una categoría y su estado. |
| `local_moodlexhub_aud_row` | Estado y resultado por curso dentro del lote de auditoría. |
| `local_moodlexhub_followup` | Caso único por curso, estudiante, actividad y motivo. Guarda su estado y responsable. |
| `local_moodlexhub_followlog` | Historial de apertura, asignación, liberación, intervenciones y cambios de estado. |

Los adjuntos de tickets se gestionan con el área privada `ticket_attachment` de File API. El panel almacena sus preferencias en Moodle. El planificador usa las APIs nativas de favoritos y calendario.
