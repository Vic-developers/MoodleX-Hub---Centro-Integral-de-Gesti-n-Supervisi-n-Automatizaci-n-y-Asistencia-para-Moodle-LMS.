# Contribución

Comprueba el estado de Git y el [estado de implementación](IMPLEMENTATION_STATUS.md) antes de editar. Usa ramas dedicadas y cambios revisables. Mantén las cadenas en español e inglés y usa contextos Moodle explícitos en cada caso de uso.

- Ejecuta [las pruebas](TESTING.md) en las ramas objetivo.
- Actualiza `db/install.xml` y añade una migración incremental en `db/upgrade.php` si cambia el modelo.
- Verifica las APIs de lectura y escritura para las ramas afectadas.
- Si se introducen datos personales, documenta su contexto, retención, exportación y borrado.
- Registra los resultados que realmente se ejecutaron. Revisa [la lista de publicación](RELEASE_CHECKLIST.md) antes de distribuir un ZIP.

El desarrollo requiere PHP 8.3. La distribución se licencia bajo GPL-3.0-or-later.
