# Instalación

Utiliza una instancia de pruebas y realiza una copia de seguridad de la base de datos antes de actualizar un entorno que ya tenga el plugin.

1. Comprueba que tu Moodle satisface los [requisitos oficiales](https://moodledev.io/general/releases). MoodleX Hub se desarrolla y prueba con PHP 8.3.
2. Si tienes un ZIP instalable, ábrelo desde **Administración del sitio → Extensiones → Instalar complementos**. Si despliegas archivos manualmente, coloca el contenido del plugin en la ruta de la tabla.
3. Entra a **Administración del sitio → Notificaciones** y aplica las migraciones. En CLI, ejecuta `php admin/cli/upgrade.php --non-interactive` desde la raíz del proyecto Moodle.
4. Abre `/local/moodlexhub/index.php` desde la URL del sitio. Activa el paquete de idioma español en Moodle si quieres usar la traducción española.
5. Asegúrate de que Moodle cron funciona. En el laboratorio puedes ejecutar `docker compose -p moodlexhub53 exec -T -u www-data moodle php admin/cli/cron.php`.

| Rama Moodle | Directorio desde la raíz del proyecto Moodle | URL desde la raíz web |
|---|---|---|
| 5.0 | `local/moodlexhub` | `/local/moodlexhub/index.php` |
| 5.1 | `public/local/moodlexhub` | `/local/moodlexhub/index.php` |
| 5.2 | `public/local/moodlexhub` | `/local/moodlexhub/index.php` |
| 5.3 | `public/local/moodlexhub` | `/local/moodlexhub/index.php` |

A partir de Moodle 5.1, el servidor debe publicar el directorio `public`. Consulte [laboratorio](TESTING.md) para instalar Moodle 5.3.1 con el plugin en Docker.

## Desinstalación

Exporta primero los registros que quieras conservar. Desde **Administración del sitio → Extensiones → Vista general de extensiones**, desinstala MoodleX Hub y retira su carpeta. Por CLI se puede usar `php admin/cli/uninstall_plugins.php --plugins=local_moodlexhub --run` desde la raíz de Moodle. La desinstalación elimina tablas, adjuntos y preferencias propios del plugin. Los recordatorios creados en el calendario personal siguen siendo eventos de usuario de Moodle.
