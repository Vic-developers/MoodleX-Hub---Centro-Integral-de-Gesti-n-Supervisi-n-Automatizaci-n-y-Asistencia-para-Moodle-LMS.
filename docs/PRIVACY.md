# Privacidad

MoodleX Hub usa Moodle Privacy API para describir y procesar sus datos personales. Las herramientas consultan los permisos de la persona que solicita datos. Los procesos CSV y de almacenamiento vuelven a comprobar los permisos al ejecutarse.

- **Panel y planificador.** Las preferencias se exportan con el mecanismo de preferencias de Moodle. La privacidad de las notas se vincula al contexto del titular. El favorito se vincula al contexto de la actividad. Los eventos personales se gestionan en Moodle Calendar.
- **Auditorías.** El autor asociado se anonimiza en solicitudes individuales aprobadas. Los hallazgos agregados pueden conservarse hasta el límite de retención configurado.
- **CSV.** La importación conserva identificadores y resultados de filas. El CSV permanece durante el ciclo de vida del borrador privado de Moodle. Los borradores del plugin caducan después de siete días y el historial completado después de 90 días. Se exportan los datos propios de solicitantes y cuentas identificadas. La anonimización bloquea los lotes activos del titular y elimina sus referencias de identidad.
- **Soporte.** El autor de un ticket puede exportar el contenido inicial y sus adjuntos. Las respuestas se exportan para sus autores. Una solicitud aprobada elimina los tickets propios y anonimiza la participación de la persona en otros tickets. La tarea de retención elimina tickets cerrados tras el período establecido. Las notificaciones incluyen una descripción genérica y un enlace autenticado.
- **Asistente.** El plugin conserva el modo, el estado, la fecha, la longitud de entrada y los recuentos de tokens disponibles. La retención es de 90 días. El contenido enviado y la respuesta se rigen por la política del subsistema Moodle AI y del proveedor. La guía del asistente explica qué se envía.
- **Informes, auditorías masivas y automatizaciones.** Las definiciones, solicitudes e historiales propios se asocian al titular. La API de privacidad elimina los registros propios y quita referencias de destinatario cuando corresponde. Las tablas de seguimiento conservan el estado agregado de los procesos.
- **Retroalimentación.** Las plantillas y borradores se exportan y borran en el contexto de curso correspondiente. Los favoritos se gestionan mediante Moodle Favourites API.
- **Webhooks.** Los cuerpos de auditoría y almacenamiento contienen un identificador opaco, el tipo y la fecha del evento. Los registros locales conservan el estado y número de intentos. Los secretos de destinos se cifran con una clave configurada en el servidor.

Las políticas y resultados de verificación se registran en [TEST_RESULTS.md](TEST_RESULTS.md).
