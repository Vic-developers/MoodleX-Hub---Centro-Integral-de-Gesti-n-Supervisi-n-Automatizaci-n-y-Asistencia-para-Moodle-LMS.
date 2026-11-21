# Estado de implementación

Fase actual de distribución de `0.1.0-beta.4`. El código contiene flujos funcionales en los 17 módulos. La guía de uso describe las operaciones disponibles y los resultados identifican las pruebas ejecutadas por compilación.

| Módulo | Alcance presente |
|---|---|
| Núcleo | Panel, capacidades, preferencias, módulos del sitio, búsqueda, notificaciones, idioma y navegación por perfil. |
| Cursos | Catálogo y filtros, detalles, comparación, registro nativo y copia con el flujo de Moodle. |
| Usuarios y matrículas | Directorio de usuarios, duplicados por correo y CSV con previsualización, lotes y reintentos. |
| Calidad | Motor ponderado, auditoría por curso y por categoría, historial, tareas y recomendaciones. |
| Analítica | Indicadores de acceso y finalización, comparación de eventos, informes por curso y categoría. |
| Calificaciones | Diagnóstico de estructura y avisos, con histórico de resúmenes. |
| Almacenamiento | Análisis asíncrono, clasificación, deduplicación, referencias, historial y vista por categoría. |
| Docentes | Panel por curso y grupo, próximas fechas, carga de Tareas y seguimiento individual de acceso, Tareas, cuestionarios y foros con fecha esperada. |
| Estudiantes | Calendario, plazos, finalización, favoritos, notas privadas y recordatorios personales. |
| Soporte | Artículos, preguntas frecuentes, tutoriales, tickets, adjuntos, estados, historial y notificaciones. |
| Preguntas | Filtros por banco, categoría, tipo y autor, última versión y referencias directas autorizadas. |
| Supervisión | Información técnica, tareas y registro con paginación. |
| Automatización | Reglas programadas o manuales con condiciones y operaciones disponibles por permiso. |
| IA | Solicitudes informativas, contextos delimitados, política, cuota y metadatos mediante Moodle AI. |
| Retroalimentación | Plantillas, notas de rúbrica, favoritos, borradores y enlace al calificador nativo. |
| Integraciones | Web Services, cuota, eventos y webhooks salientes firmados. |
| Informes | Definiciones programables, reconstrucción en segundo plano, historial y formatos Moodle. |

La detección del banco de preguntas compara títulos y cuenta referencias directas en cuestionarios autorizados. Las métricas de entrega y calificación se obtienen del módulo Tarea. La ficha de usuarios enlaza a herramientas de Moodle para la gestión individual. La verificación de rendimiento institucional, los destinos externos y la cobertura visual completa siguen pendientes. Los resultados registran las incidencias corregidas y el alcance de aceptación.

Fuentes de seguimiento en [decisiones técnicas](TECHNICAL_DECISIONS.md), [pruebas ejecutadas](TEST_RESULTS.md) y [lista de publicación](RELEASE_CHECKLIST.md).
