# Resultados de pruebas

Laboratorios Docker independientes con Moodle oficial, PHP 8.3.37 y MariaDB 11.4.10. PHPUnit 11.5 se ejecutó con `--fail-on-notice`. La tabla corresponde a la compilación inicial `2026112018` y a su [ejecución de GitHub Actions](https://github.com/Vic-developers/MoodleX-Hub---Centro-Integral-de-Gesti-n-Supervisi-n-Automatizaci-n-y-Asistencia-para-Moodle-LMS./actions/runs/44890701869).

| Moodle | Instalación local | Actualización y esquema instalado | PHPUnit | Behat en GitHub Actions |
|---|---|---|---|---|
| 5.0.12 | Correcta | Correctos | 21 pruebas y 77 aserciones | Correcto |
| 5.1.9 | Correcta | Correctos | 21 pruebas y 77 aserciones | Correcto |
| 5.2.5 | Correcta | Correctos | 21 pruebas y 77 aserciones | Correcto |
| 5.3.1 | Correcta | Correctos | 21 pruebas y 77 aserciones | Correcto |

Las suites cubren acceso y visibilidad del catálogo, reglas y lotes de auditoría, previsualización y repetición de matrículas, grupos y calificaciones, exportación CSV/XLSX/PDF, almacenamiento, favoritos, privacidad de notas y adjuntos, estados de tickets, permisos y controles de credenciales de webhooks.

## Comprobaciones adicionales

- **REST, Moodle 5.3.1.** Verificación HTTP de las dos funciones mediante un token temporal del servicio nativo. Moodle rechazó un token inválido.
- **IA, Moodle 5.3.1.** El asistente recibió texto de Ollama con el modelo local `qwen2.5:0.5b` y registró el uso. Se empleó una instalación aislada y un mensaje de prueba.
- **Desinstalación, Moodle 5.3.1.** La CLI de Moodle quitó las tablas, un adjunto, una preferencia y un favorito del plugin. Se comprobó que un curso Moodle de prueba seguía registrado.
- **Tema Moove, Moodle 5.3.1.** El panel y el diagnóstico del banco de preguntas abrieron con una sesión administrativa y el tema Moove 5.3.1 activo. Se verificaron las respuestas HTTP y los elementos del plugin.
- **Code Checker, PHPDoc y empaquetado.** GitHub Actions completó los verificadores, validó XMLDB en las cuatro ramas y publicó el ZIP como artefacto.
- **Behat.** Una ejecución local reutilizó la versión anterior del recorrido y abrió el banco nativo de Moodle. La matriz limpia pasó con el selector del plugin. La ejecución de desarrollo en 5.0 y 5.1 detectó un identificador de idioma inexistente en el diagnóstico de calificaciones, corregido antes del primer commit.
- **Escala y alcance.** La suite verifica un lote CSV ampliado con filas repetidas. Quedan por medir tiempos y consumo en instalaciones grandes y por completar la cobertura visual, de accesibilidad y de integraciones externas.

Consulta los comandos en [TESTING.md](TESTING.md) y los criterios de distribución en [RELEASE_CHECKLIST.md](RELEASE_CHECKLIST.md).
