# MoodleX Hub

Plugin local de Moodle para administración de cursos y usuarios, gestión académica, soporte, supervisión y automatización. Componente `local_moodlexhub`. Disponible en español e inglés bajo GPL-3.0-or-later.

La versión `0.1.0-beta.2` está destinada a pruebas en un sitio independiente. Empieza por la [guía de prueba para Moodle 5.3](docs/INSTALLATION.md) y consulta la [evidencia disponible](docs/TEST_RESULTS.md).

## Instalar y abrir

1. Verifica los [requisitos y la matriz de compatibilidad](docs/COMPATIBILITY.md). Para la primera prueba, usa Moodle 5.3.1 y PHP 8.3.
2. Consulta la [guía de instalación](docs/INSTALLATION.md) para aplicar las migraciones. El contenido del plugin se coloca en `local/moodlexhub` para Moodle 5.0 y en `public/local/moodlexhub` desde Moodle 5.1.
3. Abre `/local/moodlexhub/index.php` relativo a tu sitio Moodle o busca **MoodleX Hub** en **Administración del sitio → Extensiones → Extensiones locales**.

El panel revela herramientas según las capacidades y el contexto. La [guía de uso](docs/USER_GUIDE.md) indica la ubicación, los pasos y qué datos consulta cada función. El asistente, los webhooks salientes y el servicio web predefinido requieren activación explícita.

## Documentación

- [Funciones y rutas](docs/USER_GUIDE.md) · [Configuración y perfiles](docs/ADMIN_GUIDE.md) · [Solución de problemas](docs/TROUBLESHOOTING.md)
- [Arquitectura](docs/ARCHITECTURE.md) · [Base de datos](docs/DATABASE.md) · [Seguridad](docs/SECURITY.md) · [Privacidad](docs/PRIVACY.md)
- [Servicios web y webhooks](docs/API.md) · [Asistente IA](docs/AI_INTEGRATION.md)
- [Laboratorio](docs/TESTING.md) · [Pruebas ejecutadas](docs/TEST_RESULTS.md) · [Estado de implementación](docs/IMPLEMENTATION_STATUS.md)
- [Auditoría del entorno](docs/ENVIRONMENT_AUDIT.md) · [Contribución](docs/CONTRIBUTING.md) · [Lista de publicación](docs/RELEASE_CHECKLIST.md)
