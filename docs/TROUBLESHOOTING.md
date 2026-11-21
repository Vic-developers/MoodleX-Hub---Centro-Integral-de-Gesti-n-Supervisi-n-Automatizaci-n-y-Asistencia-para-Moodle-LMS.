# Solución de problemas

| Síntoma | Comprobación |
|---|---|
| El panel no aparece | Usa la URL directa y verifica `viewdashboard`, la ubicación del plugin y la detección en **Administración del sitio → Notificaciones**. |
| Faltan herramientas | Comprueba el interruptor del módulo, las capacidades de MoodleX Hub y las capacidades nativas en el contexto correspondiente. |
| Un proceso queda en cola | Comprueba que Moodle cron ejecuta tareas ad hoc y revisa **Supervisión técnica**. Si la autorización o la configuración cambiaron, restáuralas antes de reintentar el proceso. |
| Una importación omite registros | Descarga el CSV de resultados para comprobar registros duplicados, cuentas no disponibles o matriculaciones ya existentes en el método seleccionado. |
| El asistente no responde | Verifica el proveedor y la acción de generación de texto en Moodle AI, los permisos y la cuota del usuario. |
| Una entrega webhook falla | Comprueba el host autorizado, HTTPS, el secreto y el código de respuesta en el historial. Revisa la autenticación del receptor con el cuerpo exacto de la petición. |
| Laboratorio Docker no abre | Confirma que Docker Desktop responde a `docker info` y consulta `docker compose -p moodlexhub53 logs --tail 80 moodle`. El sitio inicial puede necesitar varios minutos para preparar la base de datos. |
