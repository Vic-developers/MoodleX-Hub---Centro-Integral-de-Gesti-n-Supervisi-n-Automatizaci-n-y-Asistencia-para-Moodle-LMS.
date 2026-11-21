# Auditoría del entorno

20 de noviembre de 2026. La auditoría inicial fue de solo lectura. El repositorio local estaba vacío, con `master` sin commits ni remoto. GitHub confirmó que el repositorio enlazado de Vic-developers también estaba vacío y tiene `main` como rama predeterminada. La consulta a `MoodleX/moodlex-hub` devolvió HTTP 404 con la conexión disponible.

## Host y herramientas

Windows x64, compilación 26300, PowerShell 7.6.9, Git 2.49.0, GitHub CLI 2.97.0, Node.js 26.13.1 y npm 11.20.0. PHP CLI de XAMPP 8.2.12 x64 con `max_input_vars=5000` y `sodium`, `intl`, `curl`, `mbstring`, `mysqli`, `zip` y XML. El directorio de PHP 8.3 de Laragon solo contiene configuración. PostgreSQL 18.4 responde a `--version`. El estado del servidor no se verificó.

Docker Desktop 29.4.2 y Compose 5.1.3. El acceso inicial al motor estaba limitado por la sesión. Tras autorizar el desarrollo, la consulta al motor con acceso ampliado funcionó. WSL devolvió acceso denegado desde la sesión inicial. Composer 2 está previsto en la imagen Docker. El PHPUnit de XAMPP es incompatible con su PHP y falla al arrancar.

## Instalaciones encontradas

| Entorno | Ruta | Versión | Observación |
|---|---|---|---|
| XAMPP | `C:\xampp\htdocs\moodle` | 5.0.7 | Sin `config.php`. MariaDB 10.4.32 queda por debajo del mínimo oficial. |
| Compose en Windows | `C:\moodle-lab\moodle` | 5.1.6+ | Define PHP 8.3, MariaDB 10.11, cron, phpMyAdmin y Mailpit. |
| Laboratorio Docker existente | `/home/reyti/proyectos/moodle-lab/moodle` (montaje registrado por Docker) | Sin verificar | Contenedores existentes en ejecución y MariaDB 11.4. Se conserva separado del laboratorio nuevo. |

El Compose de Windows monta Moodle en `/var/www/html`, expone la raíz web `/var/www/html/public` y monta `C:\moodle-lab\moodledata` en `/var/www/moodledata`. Su volumen declarado `mariadb_data` se monta en `/var/lib/mysql`. Sus puertos configurados son 8000 para Moodle, 8080 para phpMyAdmin, 8025 para Mailpit web y 1025 para SMTP. En la consulta posterior de Docker, el laboratorio activo verificado con montaje de Linux publicaba Moodle en 8080.

## Matriz de pruebas

| Rama | Compilación base | Etiqueta verificada | Destino del plugin dentro de Moodle |
|---|---:|---|---|
| 5.0 | 2025041400 | `v5.0.12` | `local/moodlexhub` |
| 5.1 | 2025100600 | `v5.1.9` | `public/local/moodlexhub` |
| 5.2 | 2026042000 | `v5.2.5` | `public/local/moodlexhub` |
| 5.3 | 2026100500 | `v5.3.1` | `public/local/moodlexhub` |

El laboratorio de este repositorio usa PHP 8.3 y MariaDB 11.4, combinación admitida por las cuatro ramas. Consulte [pruebas](TESTING.md) para los comandos y [resultados](TEST_RESULTS.md) para la evidencia de ejecución.

Documentación oficial: [versiones de Moodle](https://moodledev.io/general/releases), [reubicación de plugins en Moodle 5.1](https://moodledev.io/general/releases/5.1), [versiones PHP](https://moodledev.io/general/development/policies/php) y [requisitos de Moodle 5.3](https://moodledev.io/general/releases/5.3).
