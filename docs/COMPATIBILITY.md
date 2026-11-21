# Compatibilidad

La base de desarrollo es PHP 8.3. La matriz de laboratorio ejecuta versiones oficiales publicadas de Moodle y MariaDB 11.4. Consulta [resultados](TEST_RESULTS.md) para ver la compilación del plugin y el alcance de cada prueba.

| Moodle | Versión de laboratorio | Raíz local del plugin |
|---|---|---|
| 5.0 | 5.0.12 | `local/moodlexhub` |
| 5.1 | 5.1.9 | `public/local/moodlexhub` |
| 5.2 | 5.2.5 | `public/local/moodlexhub` |
| 5.3 | 5.3.1 | `public/local/moodlexhub` |

La URL del panel es `/local/moodlexhub/index.php` relativa a la URL base del sitio en las cuatro ramas. Moodle 5.1 introdujo la raíz pública separada y las rutas de inicialización de las herramientas PHPUnit y Behat se resuelven según esa estructura. Los informes usan los formatos CSV, Excel y PDF habilitados en Moodle. Los temas basados en el renderizador estándar reciben plantillas y CSS propio del plugin.

Fuentes oficiales de Moodle para [compilaciones](https://moodledev.io/general/releases), [estructura 5.1](https://moodledev.io/general/releases/5.1), [requisitos 5.3](https://moodledev.io/general/releases/5.3) y [PHP](https://moodledev.io/general/development/policies/php).
