# Decisiones técnicas

- Un plugin local integrado, `local_moodlexhub`. Los módulos comparten capacidades por contexto, Output API, Task API, Privacy API y componentes de infraestructura donde se justifique.
- Laboratorio con etiquetas oficiales de Moodle, PHP 8.3 y MariaDB 11.4. Los proyectos Compose separan instancias y datos de prueba, y respetan la ruta `public/local` desde 5.1.
- El panel usa Moodle Enrolment API. Las preferencias individuales usan el almacén nativo `user_preferences` y declaran exportación por Privacy API. Las opciones del sitio usan ajustes administrativos nativos.
- La documentación distingue funcionalidad disponible de requisitos aún pendientes. El estado de compatibilidad se decide con pruebas ejecutadas por rama.
