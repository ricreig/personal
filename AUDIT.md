# AUDIT

## Resumen ejecutivo
- Se saneó la capa API de prestaciones (PECO, TXT, Vacaciones e Incapacidades) eliminando mezclas de parámetros posicionales y nombrados, añadiendo encabezados JSON homogéneos y validaciones de estaciones basadas en `guard_allowed_oaci`.【F:api/pecos_list.php†L1-L110】【F:api/txt_list.php†L1-L106】【F:api/vacaciones_list.php†L1-L160】【F:api/incapacidades_list.php†L1-L70】
- Se reimplementó `lib/vacaciones_calc.php` para calcular asignaciones VAC/PR/ANT, consumos y banderas de carry-over, habilitando pruebas unitarias que documentan las reglas de negocio.【F:lib/vacaciones_calc.php†L1-L170】【F:tests/VacacionesCalcTest.php†L1-L69】
- La interfaz `/public/prestaciones.php` y su script asociado fueron reescritos para estabilizar el cambio Persona/Año, usar `credentials:'same-origin'`, aplicar filtros de estaciones confiables y mostrar tablas con columnas explícitas y resúmenes de vacaciones por año.【F:public/prestaciones.php†L15-L339】【F:public/assets/prestaciones.js†L1-L477】

## Matriz de endpoints
| Endpoint | Método | Parámetros soportados | SQL principal | Respuesta |
|----------|--------|-----------------------|---------------|-----------|
| `/api/filters_meta.php` | GET | `tipo` opcional (`puestos`\|`areas`) | `SELECT DISTINCT ... FROM empleados` | `{ ok, estaciones, puestos?, areas?, note? }` tolerant a valores desconocidos.【F:api/filters_meta.php†L1-L24】 |
| `/api/pecos_list.php` | GET | `mode=persona|anio`, `control`, `year`, `stations` CSV | Persona: `SELECT ... FROM pecos WHERE control=?`; Año: `LEFT JOIN pecos` filtrando OACI permitidos | `{ ok, mode, rows }` con series anuales completas para 12 días económicos.【F:api/pecos_list.php†L27-L110】 |
| `/api/txt_list.php` | GET | Igual que PECO | Persona: `SELECT ... FROM txt WHERE control=?`; Año: `LEFT JOIN txt` | `{ ok, mode, rows }` con campos JS/VS/DM/DS/MUERT/ONO y fecha de nacimiento.【F:api/txt_list.php†L27-L106】 |
| `/api/vacaciones_list.php` | GET | `mode`, `control`, `year`, `stations` | Persona: `SELECT ... FROM vacaciones WHERE control=?`; Año: `SELECT control, year, tipo, SUM(dias)` para controles filtrados | `{ ok, mode, summary, rows, available_years }` en persona y `{ ok, rows }` en año con campos asignados/usados/restantes y banderas.【F:api/vacaciones_list.php†L28-L160】 |
| `/api/incapacidades_list.php` | GET | `mode`, `control`, `year`, `stations` | Persona: `SELECT ... FROM incapacidad WHERE NC=?`; Año: `INNER JOIN incapacidad` filtrando por año | `{ ok, rows }` con fechas, folio y diagnóstico por modo.【F:api/incapacidades_list.php†L27-L70】 |
| `/api/diag_env.php` | GET | — | — | `{ ok, php_version, sapi, pdo_drivers, ini }` para diagnóstico ambiental.【F:api/diag_env.php†L1-L12】 |
| `/api/db_ping.php` | GET | — | `SELECT 1`, `SELECT COUNT(*) FROM empleados` | `{ ok, select1, empleados_count, driver, server_version, client_version }` o `{ ok:false, error }` en fallo.【F:api/db_ping.php†L1-L18】 |
| `/api/ping.php` | GET | — | — | `{ ok:true, php, time }` con `declare(strict_types=1)` validado al inicio.【F:api/ping.php†L1-L10】 |

## Hallazgos críticos
1. **Binds inconsistentes y errores HY093**: los endpoints de prestaciones mezclaban parámetros `?` con `:named`, provocando errores HY093 y consultas inestables. Se reescribieron todas las preparadas para usar placeholders posicionales exclusivos.【F:api/pecos_list.php†L78-L110】【F:api/txt_list.php†L79-L106】【F:api/incapacidades_list.php†L58-L70】【F:api/vacaciones_list.php†L108-L160】
2. **Cálculo de vacaciones incompleto**: la lógica previa devolvía cadenas (`'-'`, `'NO INFO'`) y no computaba banderas ni sumatorias por año. El nuevo módulo `vc_summary` produce asignaciones, usos, remanentes y flags de carry-over compatibles con reglas VAC/PR/ANT.【F:lib/vacaciones_calc.php†L56-L170】
3. **Front-end sin estado persistente ni credenciales**: `public/assets/prestaciones.js` usaba rutas relativas, no enviaba `credentials` y generaba tablas a partir de `Object.keys`, provocando columnas erróneas. Se rediseñó el flujo de inicialización, persistencia de modo (localStorage) y render explícito por campos reales.【F:public/assets/prestaciones.js†L1-L477】

## Mapa de dependencias
- `public/prestaciones.php` consume `public/assets/prestaciones.js` y los endpoints JSON mencionados, apoyándose en estilos locales y `cr_print_js_globals` para obtener `API_BASE`.【F:public/prestaciones.php†L15-L339】
- Endpoints `/api/*` dependen de `lib/session.php` (boot), `lib/guard.php` (autenticación/permisos) y `lib/vacaciones_calc.php` para resumir asignaciones.【F:api/vacaciones_list.php†L7-L16】【F:lib/guard.php†L1-L64】【F:lib/vacaciones_calc.php†L1-L170】
- `lib/guard.php` a su vez se apoya en `lib/auth.php` y, cuando existe, en `user_station_matrix` para mapear permisos por estación.【F:lib/guard.php†L4-L64】

## Esquema de tablas inferido
- `empleados`: campos `control`, `nombres`, `estacion`, `ant`, `tipo1`, `fingreso`, `fecha_nacimiento` se usan para unir con `estaciones`, calcular antigüedad y poblar combos.【F:api/vacaciones_list.php†L33-L79】【F:api/txt_list.php†L46-L68】
- `estaciones`: columnas `id_estacion`, `oaci` permiten filtrar por OACI autorizada y poblar listas iniciales.【F:api/prestaciones_init.php†L22-L46】【F:lib/guard.php†L52-L56】
- `pecos`, `txt`, `vacaciones`, `incapacidad`: almacenan métricas anuales por `control`, con agregados (`SUM(dias)`) o registros crudos (vacaciones mov/periodo, incapacidades con `INICIA`, `TERMINA`, `DIAS`, `FOLIO`).【F:api/pecos_list.php†L47-L107】【F:api/txt_list.php†L46-L103】【F:api/vacaciones_list.php†L55-L156】【F:api/incapacidades_list.php†L46-L68】
- Discrepancias: se estandarizó la referencia a estación vía `e.estacion` + `JOIN estaciones`, reemplazando antiguos usos de `e.oaci` inexistentes.

## Plan de corrección aplicado
- Refactor de los endpoints de prestaciones para validar permisos, castear valores (`(string)` en `str_pad`) y devolver respuestas consistentes.【F:api/pecos_list.php†L73-L110】【F:api/txt_list.php†L74-L106】【F:api/incapacidades_list.php†L53-L70】【F:api/vacaciones_list.php†L80-L160】
- Actualización de `lib/vacaciones_calc.php` con escalas constantes, helpers de normalización y `vc_summary` reutilizable en pruebas.【F:lib/vacaciones_calc.php†L4-L170】
- Rediseño del front-end: estilos “folder look”, toolbar estable, selectores Persona/Año, banner de error y renderizaciones con cabeceras explícitas.【F:public/prestaciones.php†L20-L339】【F:public/assets/prestaciones.js†L13-L477】
- Refuerzo de guardas (`lib/guard.php`) y `api/ping.php` para cumplir con `declare(strict_types=1)` al inicio.【F:lib/guard.php†L1-L64】【F:api/ping.php†L1-L10】
- Implementación de scripts de soporte (lint, escáneres, golpeo de endpoints) y suite PHPUnit mínima.【F:tools/run_php_lint.sh†L1-L5】【F:tools/scan_mixed_params.php†L1-L42】【F:tools/hit_endpoints.php†L1-L84】【F:tests/VacacionesCalcTest.php†L1-L69】

## Checklist de verificación
- `tools/run_php_lint.sh` sin errores de sintaxis.【58f66c†L1-L16】【67461e†L1-L53】
- `php tools/scan_mixed_params.php` → sin mezclas detectadas.【be6b5a†L1-L2】
- `php tools/scan_oaci_estacion.php` → sin referencias a `e.oaci` pendientes.【543556†L1-L2】
- `php phpunit.phar --configuration phpunit.xml` → pruebas OK (con advertencias de deprecación propias de PHPUnit 9 sobre PHP 8.2).【f3d6cd†L1-L116】

## Plan de verificación manual
1. Ejecutar `/public/diagnose.php` y corroborar accesos a `api/diag_env.php` y `api/db_ping.php` respondan `ok:true`.
2. En `/public/prestaciones.php`, alternar entre “Persona” y “Año” confirmando que los botones mantienen estilo activo/inactivo y que los selectores correctos permanecen visibles.【F:public/prestaciones.php†L181-L199】
3. Seleccionar estaciones desde el dropdown (incluyendo “Seleccionar todas”) y validar que las vistas por año reflejan el filtro en PECO, TXT, Vacaciones e Incapacidades.【F:public/assets/prestaciones.js†L62-L109】
4. Elegir un control y año específicos en modo Persona; revisar que el resumen VAC muestre asignadas/usadas/restantes coherentes y que los movimientos listados correspondan al histórico.【F:public/assets/prestaciones.js†L257-L312】
5. Verificar que las tablas muestren banderas “Pendiente/Riesgo” cuando existan remanentes de vacaciones del año previo (fechas cercanas al 30 de junio).【F:public/assets/prestaciones.js†L246-L312】
