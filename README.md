# Módulo Backend - Monitor Quintillizas

## API RESTful, Telemetría del Kernel y Gestión de Sesiones (PHP 8.2 + Python 3 psutil + PDO)
**Carrera:** Ingeniería en Sistemas Computacionales (5to Semestre)  
**Asignatura:** Programación Web / Sistemas Distribuidos / Redes  
**Proyecto:** Monitor Quintillizas (Telemetría de Servidores en Tiempo Real)  
**Repositorio Oficial:** [https://github.com/Monitor-Quintillizas/backend](https://github.com/Monitor-Quintillizas/backend)

---

## 1. Descripción del Módulo Backend

El backend se encarga de:
1. **Enrutamiento y Procesamiento RESTful (`services.php`):** Centraliza la recepción de peticiones HTTP del cliente y despacha las acciones correspondientes.
2. **Autenticación y Sesiones (`sessions/Session.php`):** Valida credenciales con hashes Bcrypt, emite tokens Bearer y gestiona el ciclo de vida de la sesión activa de los operadores.
3. **Telemetría de Hardware en Tiempo Real (`python/telemetry.py`):** Ejecuta scripts del intérprete de Python que leen métricas directas del kernel (CPU, RAM, Disco) mediante la librería `psutil`.
4. **Persistencia y Capa de Datos (`connection.php`):** Administra la conexión mediante PDO a MySQL 8.0 con soporte de contingencia automática a SQLite si no hay un servidor MySQL disponible.
5. **Motor de Evaluación de Umbrales:** Compara automáticamente cada muestra entrante contra las reglas activas en la tabla `THRESHOLDS` y registra disparos en la tabla `ALERTS`.
6. **Manejo Estandarizado de Errores (`Errors.php`, `ValueValidation.php`):** Sanitiza entradas y emite respuestas JSON semánticas con los códigos de estado HTTP apropiados (200, 400, 401, 404, 405, 500).

---

## 2. Estructura de Archivos del Backend

```text
backend/
├── sessions/
│   └── Session.php         # Manejo de sesiones activas, tokens y validación de usuarios
├── config/
│   └── (configuración de red)
├── python/
│   └── telemetry.py        # Script Python que lee CPU, RAM y Disco con psutil
├── Errors.php              # Catálogo centralizado de errores y códigos de respuesta
├── Tools.php               # Utilerías de ejecución de procesos del sistema y formateo
├── ValueValidation.php     # Sanitización y validación estricta de tipos de datos
├── connection.php          # Conexión PDO a MySQL con fallback automático a SQLite
├── services.php            # Enrutador principal de endpoints RESTful
├── Monitor_Postman_Collection.json # Colección de pruebas para Postman
├── Dockerfile              # Manifiesto de contenedor PHP 8.2 Apache + Python
└── README.md               # Documentación técnica del módulo
```

---

## 3. Catálogo de Endpoints RESTful

Todos los endpoints se consumen a través del enrutador central `http://localhost:8000/services.php?action=<ACCION>`.

| Acción (`action`) | Método HTTP | Parámetros / Body | Autenticación | Código Éxito | Descripción |
| :--- | :---: | :--- | :---: | :---: | :--- |
| `login` | `POST` | `{"username": "admin", "password": "..."}` | Pública | `200 OK` | Valida credenciales e inicia sesión entregando token y rol. |
| `register` | `POST` | `{"username": "...", "email": "...", "password": "..."}` | Pública | `200 OK` | Registra una nueva cuenta de operador en la base de datos. |
| `logout` | `GET/POST` | Ninguno | Requerida | `200 OK` | Invalida y destruye la sesión activa del usuario. |
| `check_session` | `GET` | Ninguno | Requerida | `200 OK` | Verifica la validez y expiración del token de sesión. |
| `get_hosts` | `GET` | Ninguno | Requerida | `200 OK` | Retorna la lista de servidores y hosts registrados. |
| `get_metrics` | `GET` | `host_id` (opcional, default: 1) | Requerida | `200 OK` | Lee telemetría en vivo vía Python, persiste la muestra y evalúa umbrales. |
| `get_history` | `GET` | `host_id`, `start_date`, `end_date` | Requerida | `200 OK` | Consulta muestras históricas en un rango de fechas. |
| `get_thresholds` | `GET` | `host_id` (opcional) | Requerida | `200 OK` | Lista de umbrales configurados para el equipo. |
| `create_threshold`| `POST` | `{"host_id":1, "metric_name":"cpu_usage_pct", "comparison_operator":">", "threshold_value":80}` | Requerida | `200 OK` | Da de alta una nueva regla de monitoreo. |
| `update_threshold`| `POST` | `{"threshold_id":1, "metric_name":"...", "comparison_operator":"...", "threshold_value":85, "is_active":1}` | Requerida | `200 OK` | Modifica una regla de umbral existente. |
| `delete_threshold`| `POST` | `{"threshold_id": 1}` | Requerida | `200 OK` | Elimina una regla de umbral de la base de datos. |
| `get_alerts` | `GET` | Ninguno | Requerida | `200 OK` | Retorna el historial de alarmas y advertencias disparadas. |
| `get_all_users_performance` | `GET` | Ninguno | Requerida (Admin) | `200 OK` | Reporte en tiempo real de consumo de recursos por usuario activo. |
| `delete_account` | `POST` | `{"user_id": 2}` | Requerida | `200 OK` | Elimina una cuenta de usuario con baja en cascada. |

---

## 4. Respuestas HTTP y Manejo de Errores

El backend implementa un formato JSON estándar tanto para respuestas exitosas como para excepciones:

### Respuesta Exitosa (`200 OK`):
```json
{
  "ok": true,
  "mensaje": "Operación completada con éxito",
  "data": { ... }
}
```

### Respuesta de Error (`400`, `401`, `404`, `500`):
```json
{
  "ok": false,
  "Error": {
    "Type": 401,
    "Category": 1,
    "Description": "Credenciales inválidas o usuario inexistente."
  }
}
```

---

## 5. Colección y Pruebas con Postman

Se incluye la colección oficial lista para importar en Postman: [`Monitor_Postman_Collection.json`](file:///c:/Users/Adrian/OneDrive/Documentos/ITESI/Servicio%20Social/Monitor%20Quintillizas/back/Monitor_Postman_Collection.json).

### Matriz de Casos de Prueba Validados:

| # | Caso de Prueba | Endpoint | Entrada Enviada | Código HTTP Esperado | Resultado |
| :-: | :--- | :--- | :--- | :-: | :---: |
| 1 | Login Exitoso | `POST ?action=login` | `username: "admin"`, `password: "1234"` | `200 OK` | ✅ Pasa |
| 2 | Contraseña Incorrecta | `POST ?action=login` | `username: "admin"`, `password: "erronea"` | `401 Unauthorized` | ✅ Pasa |
| 3 | Campos Vacíos en Login | `POST ?action=login` | `username: ""`, `password: ""` | `400 Bad Request` | ✅ Pasa |
| 4 | Usuario Inexistente | `POST ?action=login` | `username: "no_existe"`, `password: "1234"` | `401 Unauthorized` | ✅ Pasa |
| 5 | Petición sin Sesión | `GET ?action=get_metrics` | Sin cabecera `Authorization` ni cookie | `401 Unauthorized` | ✅ Pasa |
| 6 | Telemetría en Vivo | `GET ?action=get_metrics&host_id=1` | Con Bearer Token válido | `200 OK` | ✅ Pasa |
| 7 | Historial Válido | `GET ?action=get_history` | `start_date`, `end_date` válidos | `200 OK` | ✅ Pasa |
| 8 | Alta de Umbral | `POST ?action=create_threshold` | JSON con métrica y límite | `200 OK` | ✅ Pasa |
| 9 | Modificación de Umbral | `POST ?action=update_threshold` | JSON con `threshold_id` existente | `200 OK` | ✅ Pasa |
| 10| Eliminación de Umbral | `POST ?action=delete_threshold` | `threshold_id: 1` | `200 OK` | ✅ Pasa |

---

## 6. Guía de Preguntas Teóricas para la Defensa del Backend

1. **¿Cómo sabe el Backend que una petición proviene de un usuario con sesión iniciada?**  
   El backend examina la cabecera HTTP `Authorization: Bearer <token>` o la cookie nativa de sesión PHP (`PHPSESSID`). La clase `Session.php` valida si el identificador existe en el almacén de sesiones activas del servidor y comprueba que no haya expirado.

2. **¿Por qué se utiliza PDO en lugar de funciones `mysqli_*` o sentencias SQL concatenadas?**  
   PDO (*PHP Data Objects*) provee una capa de abstracción uniforme para interactuar con diferentes motores de base de datos (MySQL, MariaDB, SQLite) y ofrece soporte nativo para **sentencias preparadas con enlace de parámetros (*Prepared Statements*)**, lo que neutraliza por completo los riesgos de **Inyección SQL (*SQL Injection*)**.

3. **¿Cómo se comunica PHP con Python para obtener las métricas de hardware?**  
   PHP invoca al intérprete de Python mediante `proc_open` o `shell_exec` ejecutando el script [`python/telemetry.py`](file:///c:/Users/Adrian/OneDrive/Documentos/ITESI/Servicio%20Social/Monitor%20Quintillizas/back/python/telemetry.py). Python consulta el subsistema del kernel mediante la librería `psutil`, genera un objeto JSON y lo imprime a la salida estándar (`stdout`). PHP captura esta salida, la decodifica y la retorna al Frontend mientras persiste la muestra en la base de datos.

4. **¿Cómo funciona el mecanismo de contingencia (*fallback*) a SQLite?**  
   En `connection.php`, si el intento de conexión PDO a MySQL falla (por ejemplo, en computadoras donde no esté corriendo el servicio MySQL o Docker), el backend automáticamente inicializa una base de datos local SQLite (`local_monitor.sqlite`), ejecuta las migraciones de tablas y crea el usuario por defecto `admin`, permitiendo que el proyecto funcione de manera inmediata en cualquier computadora sin configuración adicional.
