# Pruebas

Desde el repositorio, Docker Compose usa Moodle 5.3.1 y publica la web en `http://localhost:8053`. Use proyectos y puertos distintos para probar en paralelo. Los volúmenes contienen bases y datos independientes.

```powershell
docker compose -p moodlexhub53 up -d --build
docker compose -p moodlexhub53 logs --tail 60 moodle
docker compose -p moodlexhub53 exec -T moodle php /opt/moodle-web/admin/tool/phpunit/cli/init.php --disable-composer
docker compose -p moodlexhub53 exec -T moodle vendor/bin/phpunit --testsuite local_moodlexhub_testsuite
docker compose -p moodlexhub53 exec -T -u www-data moodle php /opt/moodle-web/admin/tool/behat/cli/init.php --disable-composer
docker compose -p moodlexhub53 exec -T -u www-data moodle vendor/bin/behat --config /var/moodledata/behat/behatrun/behat/behat.yml --tags=@local_moodlexhub
```

Para las otras ramas, defina `$env:MOODLE_TAG` con `v5.0.12`, `v5.1.9` o `v5.2.5`, ajuste `$env:LAB_PORT` y use un nombre de proyecto distinto. Pueden usarse estas mismas variables en una shell POSIX mediante `export`.

La configuración de Compose contiene credenciales de desarrollo para un servicio limitado a localhost. En cualquier otro ambiente deben usarse los mecanismos propios del despliegue. PHPUnit y Behat usan prefijos y directorios de datos independientes. Ejecuta `php /opt/moodle-web/admin/cli/check_database_schema.php` en el contenedor para verificar la estructura instalada. Genera el ZIP con `python tools/package.py`. Los resultados se registran en [TEST_RESULTS.md](TEST_RESULTS.md).
