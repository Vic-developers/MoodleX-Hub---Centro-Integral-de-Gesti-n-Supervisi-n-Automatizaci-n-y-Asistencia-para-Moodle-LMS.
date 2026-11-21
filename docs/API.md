# Servicios web e integraciones

## API de Moodle

El servicio predefinido **MoodleX Hub API** tiene usuarios restringidos y empieza deshabilitado. En **Administración del sitio → Servidor → Servicios web**, habilita el protocolo que vayas a usar, autoriza un usuario y emite un token en Moodle. El usuario necesita `local/moodlexhub:useapi` y las capacidades del dato consultado. El límite del plugin es de 60 solicitudes por minuto por usuario.

Usa el punto de entrada de Moodle `/webservice/rest/server.php` con `moodlewsrestformat=json`. Envía el token por POST. Mantén cada token en el sistema de secretos del consumidor.

| Función | Parámetros | Resultado |
|---|---|---|
| `local_moodlexhub_get_course_report` | `courseid` entero, `source` uno de `quality`, `storage`, `grades`, `learners` | Curso, fuente, fecha Unix y métricas con nombre y valor. Requiere `exportreports` y acceso a la fuente. Para estudiantes sin actividad usa 14 días. Para calidad incluye todos los pesos activos. |
| `local_moodlexhub_get_ticket_status` | `ticketid` entero | ID, estado, prioridad y fecha Unix de modificación. Requiere acceso al ticket. |

## Webhooks salientes

En el `config.php` de Moodle, toma la clave maestra y la lista de hosts desde la configuración del servidor. La clave se codifica en base64 y se decodifica a exactamente 32 bytes. El plugin cifra los secretos HMAC antes de persistirlos.

```php
$CFG->local_moodlexhub_webhook_key = getenv('MOODLEXHUB_WEBHOOK_MASTER_KEY');
$CFG->local_moodlexhub_webhook_hosts = ['hooks.example.org'];
```

En **MoodleX Hub → Integraciones** añade destinos HTTPS para auditorías o análisis de almacenamiento y guarda un secreto HMAC de 32 a 256 bytes por destino. Habilita la entrega en configuración general. La lista usa hosts exactos. Se requiere salida HTTPS directa y la configuración de seguridad cURL de Moodle se aplica durante la entrega.

El cuerpo JSON contiene `id`, `event` y `occurred_at`. El evento es `moodlexhub.quality.completed` o `moodlexhub.storage.completed`. Los encabezados son `X-MoodleX-Id`, `X-MoodleX-Timestamp` y `X-MoodleX-Signature`. La firma es `sha256=` seguida de HMAC SHA-256 hexadecimal de la cadena «marca Unix, punto y cuerpo exacto». Verifica la firma con comparación de tiempo constante y rechaza marcas antiguas según tu ventana operativa. Usa el ID estable para deduplicar reintentos y confirma con HTTP 2xx.

Consulta la entrega en **Integraciones → Historial de entregas** y conserva la clave maestra para poder descifrar las configuraciones. Para cambiar de clave, vuelve a introducir los secretos de los destinos usando la clave nueva y valida la entrega antes de activar el servicio.
