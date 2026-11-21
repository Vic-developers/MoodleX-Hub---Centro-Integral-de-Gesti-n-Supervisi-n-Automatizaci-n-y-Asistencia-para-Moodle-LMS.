# Asistente e inteligencia artificial

El asistente está desactivado al instalar MoodleX Hub. En Moodle 5.0–5.3 se conecta al subsistema nativo Moodle AI.

1. En **Administración del sitio → IA → Proveedores de IA**, añade un proveedor disponible en tu versión de Moodle. Configura su URL, modelo y credenciales allí. Para inferencia local puedes usar un proveedor Ollama accesible desde el servidor Moodle.
2. Habilita la acción nativa de generación de texto y revisa la política del sitio y del proveedor.
3. En **Extensiones locales → Configuración del asistente de MoodleX Hub**, habilita el asistente y fija la cuota por usuario.
4. Otorga `local/moodlexhub:useassistant` a los perfiles autorizados. `manageai` permite configurar y hacer la prueba de conexión.
5. Desde **MoodleX Hub → Asistente de IA**, abre **Probar la conexión del proveedor**, acepta la política y envía la prueba. Cada solicitud normal requiere aceptación explícita.

El asistente admite preguntas técnicas, orientación a estudiantes y docentes, referencias a artículos publicados y resumen de auditorías autorizadas. Se transmite la solicitud y la referencia mínima correspondiente. Las respuestas aparecen como texto en la pantalla. El historial del plugin muestra situación, volumen y fecha. Moodle AI puede conservar el contenido de las peticiones y respuestas según su política. Las credenciales del proveedor se gestionan en Moodle AI.

Para un modelo local, la conexión debe estar permitida por la política de seguridad HTTP de Moodle y por la red donde se despliega. El proveedor local disponible depende de la rama y de los complementos instalados. El laboratorio verificó la generación con Ollama en Moodle 5.3.1, en una red aislada con las opciones de prueba limitadas al proceso. Los proveedores externos se configuran desde Moodle AI.
