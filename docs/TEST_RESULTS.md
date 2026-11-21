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

## Beta 2

- **Moodle 5.3.1.** La CLI actualizó la instalación a la compilación `2026112019` desde el ZIP y comprobó el esquema. PHPUnit pasó con 21 pruebas y 79 aserciones, incluidas las dos comprobaciones del filtro de estado del banco de preguntas. Behat pasó con 6 escenarios y 58 pasos.
- **Copia asíncrona, Moodle 5.3.1.** La ficha del plugin abrió el formulario nativo de copia de Moodle. La prueba ejecutó la tarea nativa de backup y restore, verificó que se creó un curso distinto y comprobó la categoría y la visibilidad de destino.
- **GitHub Actions.** Pasaron PHPUnit, Behat, la instalación y la validación de XMLDB en Moodle 5.0.12, 5.1.9, 5.2.5 y 5.3.1. Pasaron también Moodle Code Checker y PHPDoc Checker en 5.3.1. Consulta la [matriz de la beta 2](https://github.com/Vic-developers/MoodleX-Hub---Centro-Integral-de-Gesti-n-Supervisi-n-Automatizaci-n-y-Asistencia-para-Moodle-LMS./actions/runs/44891421793) y descarga el [artefacto con el ZIP instalable](https://github.com/Vic-developers/MoodleX-Hub---Centro-Integral-de-Gesti-n-Supervisi-n-Automatizaci-n-y-Asistencia-para-Moodle-LMS./actions/runs/44891421793/artifacts/14702227718).

## Beta 3

La validación con un docente restringido detectó que Moodle podía devolver el modo de grupos como texto. La comparación estricta con la constante entera podía tratar un curso con grupos separados como un curso abierto a todos los grupos. La beta 3 corrige la comprobación en el panel, la ficha del curso, los informes y la automatización.

- **Docencia.** El curso de prueba contiene dos grupos. El docente consulta a su estudiante y la carga de su grupo. El selector rechaza el otro grupo. El constructor de informes rechaza el resumen de todo el curso para esa cuenta.
- **Coordinación.** Una cuenta sin privilegios de administración previsualiza, confirma y ejecuta una matrícula CSV. También audita un curso de su categoría. Fuera de la categoría carece de la capacidad de matrícula y la auditoría se rechaza.
- **Soporte.** Un agente de soporte puede responder al ticket que le asignaron. La comprobación de acceso rechaza un ticket ajeno sin asignación.

En los cuatro laboratorios se aplicó la actualización al ZIP de la beta 3 y se comprobó el esquema instalado. PHPUnit pasó con 23 pruebas y 98 aserciones por versión en Moodle 5.0.12, 5.1.9, 5.2.5 y 5.3.1, con PHP 8.3.37 y MariaDB 11.4.10. En el laboratorio Moodle 5.3.1, Behat pasó con 6 escenarios y 58 pasos.

En GitHub Actions pasaron la instalación, la validación XMLDB, PHPUnit y Behat en las cuatro ramas. El trabajo de empaquetado generó el [ZIP instalable](https://github.com/Vic-developers/MoodleX-Hub---Centro-Integral-de-Gesti-n-Supervisi-n-Automatizaci-n-y-Asistencia-para-Moodle-LMS./actions/runs/44894321392/artifacts/14703094535). Consulta los registros de la [matriz de la beta 3](https://github.com/Vic-developers/MoodleX-Hub---Centro-Integral-de-Gesti-n-Supervisi-n-Automatizaci-n-y-Asistencia-para-Moodle-LMS./actions/runs/44894321392).

## Beta 4

En Moodle 5.3.1, PHPUnit pasó con 25 pruebas y 119 aserciones. Los casos nuevos comprueban el aislamiento de grupos, la cola de casos abiertos y cerrados, la deduplicación y el borrado de datos personales. La evidencia usa una Tarea con extensión y entrega real, un intento de ensayo pendiente de corrección manual y un foro con finalización de Moodle.

La actualización de la beta 3 desde el ZIP y la comprobación del esquema fueron correctas. Behat pasó con 7 escenarios y 81 pasos en Moodle 5.3.1. El recorrido de seguimiento abrió un caso como docente, asignó responsable, guardó una intervención y verificó su aparición en la cola de cerrados.

Consulta los comandos en [TESTING.md](TESTING.md) y los criterios de distribución en [RELEASE_CHECKLIST.md](RELEASE_CHECKLIST.md).
