# Seguridad

El acceso a acciones se valida en el servidor con sesión Moodle, capacidades explícitas y contexto. Un enlace visible en la interfaz no otorga acceso. Los formularios Moodle y las acciones POST validan la sesión. Los servicios web requieren autenticación Moodle y sus propias capacidades. Se conservan los controles de acceso de los cursos, grupos, banco de preguntas, métodos de matrícula y libro de calificaciones.

- La matrícula valida el método y el rol al previsualizar, confirmar y ejecutar. Una cuenta suspendida, borrada o sin confirmar no se matricula. Una matrícula existente en el método se registra como omitida.
- El seguimiento vuelve a comprobar el curso, el grupo y la matrícula en cada cambio de caso. La vista individual omite las Tareas con calificación ciega activa. Las operaciones de cambio usan POST con el token de sesión, y el historial se muestra como texto escapado.
- Los adjuntos viven en un área privada de File API y requieren acceso al ticket en el momento de la descarga.
- La base de conocimientos renderiza Markdown mediante limpieza de Moodle. Los tickets, notas, borradores y respuestas IA se escapan al renderizar.
- Los webhooks requieren un destino HTTPS autorizado, validación de dirección pública y fijación DNS de Moodle cURL. El secreto HMAC está cifrado y la clave maestra se obtiene de la configuración del servidor.
- Las respuestas IA son texto informativo. La entrada y las referencias se recortan y se comprueba si contienen patrones habituales de datos sensibles o credenciales. El usuario autoriza la transferencia en cada solicitud. Las capacidades de lectura se verifican antes de extraer una referencia.
- Las auditorías, tareas y peticiones de API evitan registrar credenciales y cuerpos de respuestas externas.

Los resultados de seguridad ejecutados y su alcance están en [TEST_RESULTS.md](TEST_RESULTS.md).
