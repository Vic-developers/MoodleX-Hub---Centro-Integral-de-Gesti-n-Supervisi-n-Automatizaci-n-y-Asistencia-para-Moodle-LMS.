# Resultados de pruebas

Laboratorios Docker independientes con Moodle oficial, PHP 8.3.37 y MariaDB 11.4.10. PHPUnit 11.5 se ejecutó con `--fail-on-notice`. La compilación de la beta inicial es `2026112018`.

| Moodle | Instalación local | Actualización y esquema instalado | PHPUnit |
|---|---|---|---|
| 5.0.12 | Correcta | Correctos | 21 pruebas y 77 aserciones |
| 5.1.9 | Correcta | Correctos | 21 pruebas y 77 aserciones |
| 5.2.5 | Correcta | Correctos | 21 pruebas y 77 aserciones |
| 5.3.1 | Correcta | Correctos | 21 pruebas y 77 aserciones |

Las suites cubren acceso y visibilidad del catálogo, reglas y lotes de auditoría, previsualización y repetición de matrículas, grupos y calificaciones, exportación CSV/XLSX/PDF, almacenamiento, favoritos, privacidad de notas y adjuntos, estados de tickets, permisos y controles de credenciales de webhooks.

## Comprobaciones adicionales

- **REST, Moodle 5.3.1.** Verificación HTTP de las dos funciones mediante un token temporal del servicio nativo. Moodle rechazó un token inválido.
- **IA, Moodle 5.3.1.** El asistente recibió texto de Ollama con el modelo local `qwen2.5:0.5b` y registró el uso. Se empleó una instalación aislada y un mensaje de prueba.
- **Desinstalación, Moodle 5.3.1.** La CLI de Moodle quitó las tablas, un adjunto, una preferencia y un favorito del plugin. Se comprobó que un curso Moodle de prueba seguía registrado.
- **Behat.** La ejecución inicial de la beta sobre 5.3.1 registró 5 escenarios correctos de 6. El recorrido de cursos pasó por auditoría y calificaciones y seleccionó el enlace homónimo de Moodle Core al llegar al banco de preguntas. Se ajustó el escenario para seleccionar el enlace dentro del panel del plugin. La ejecución anterior de 5.0 y 5.1 detectó un identificador de idioma inexistente en el diagnóstico y se corrigió.
- **Escala y alcance.** La suite verifica un lote CSV ampliado con filas repetidas. Quedan por medir tiempos y consumo en instalaciones grandes y por completar la cobertura visual, de accesibilidad y de integraciones externas.

Consulta los comandos en [TESTING.md](TESTING.md) y los criterios de distribución en [RELEASE_CHECKLIST.md](RELEASE_CHECKLIST.md).
