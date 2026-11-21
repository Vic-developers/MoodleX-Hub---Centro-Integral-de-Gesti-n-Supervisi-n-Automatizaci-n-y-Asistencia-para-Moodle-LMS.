# Metodología de analítica

## Participantes y actividad

| Indicador | Cálculo |
|---|---|
| Estudiantes | Matriculados activos con `moodle/course:isincompletionreports`. Se excluyen usuarios eliminados y suspendidos. El filtro por grupo respeta el modo de grupos y las capacidades del curso. |
| Último acceso | Marca temporal del último acceso de Moodle al curso. La ausencia del registro se muestra como «Nunca». |
| Inactividad | Último acceso anterior a la fecha actual menos el número de días del filtro, incluida la ausencia de acceso. La cifra describe el acceso registrado. |
| Progreso de actividades | Actividades visibles con seguimiento y estado de finalización registrado, entre el total de actividades visibles con seguimiento. Se necesita acceso al informe de progreso. |
| Lecturas | Recuento de eventos de curso con operación de lectura en el registro Moodle y sin marca de anonimato. Varias lecturas de una persona cuentan por separado. |
| Participación | Recuento de eventos de curso con nivel educativo participativo, excluidas las operaciones de lectura y los eventos anónimos. |
| Tendencias | Dos ventanas consecutivas de idéntica duración con inicio incluido y fin excluido. La fecha de fin elegida se incluye hasta la medianoche siguiente en la zona horaria del usuario. |
| Informe individual | Los recuentos y la fila del estudiante se limitan a un usuario matriculado que cumple el filtro de grupo, inactividad y capacidades. |
| Informe por categoría | Revisa cursos accesibles dentro de la categoría seleccionada. El filtro docente usa los roles configurados como contactos de curso. Se suman las matrículas por curso y el número de estudiantes con finalización de curso registrada por Moodle. |

El informe agrupa eventos registrados por Moodle. La disponibilidad depende de los almacenes de registro y su retención. Interpreta la lectura y la participación como actividad registrada.

## Carga docente

| Indicador | Cálculo |
|---|---|
| Entregas | Envíos en estado entregado según la API de Tareas de Moodle y el alcance de grupo permitido. |
| Pendientes | Participantes esperados menos entregas, con mínimo cero. |
| Atrasadas | Entregas pendientes en Tareas cuya fecha límite ya transcurrió. |
| Por calificar | Envíos para los que el módulo Tarea calcula que falta una calificación o una revisión. |
| Próximas fechas | Hasta 25 fechas ofrecidas por las actividades visibles mediante la API nativa, ordenadas de menor a mayor. |

Las métricas individuales de pendientes y por calificar se muestran como no aplicables para entregas en equipo. Moodle presenta sus detalles en la pantalla de calificación de la actividad.

## Evidencia para seguimiento

| Motivo posible | Evidencia |
|---|---|
| Sin acceso reciente | Moodle carece de un acceso registrado al curso dentro de los últimos 14 días. Incluye a quienes nunca han entrado, por lo que conviene revisar matrículas recientes antes de intervenir. |
| Entrega vencida | No se ha entregado una Tarea individual con fecha efectiva vencida, o no se ha finalizado un cuestionario cuya fecha de cierre pasó. Se respetan las excepciones y extensiones aplicables. En foros, se compara el estado de finalización de Moodle con su fecha esperada. |
| Calificación pendiente | Una entrega de Tarea sigue sin una calificación vigente, o un intento finalizado de cuestionario contiene preguntas pendientes de corrección manual. En cuestionarios se requiere permiso para ver los nombres en el informe de calificación manual. |

La evidencia se vuelve a comprobar al abrir un caso. Se guarda un caso por estudiante, actividad y motivo en cada curso. El historial permanece disponible mientras el estudiante y la actividad sigan dentro del ámbito autorizado. Las Tareas en equipo y las Tareas con calificación ciega activa quedan fuera de la vista individual. El complemento usa [Moodle Activity completion API](https://moodledev.io/docs/5.2/apis/core/activitycompletion) para consultar el estado de finalización.
