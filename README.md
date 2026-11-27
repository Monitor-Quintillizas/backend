# Módulo Backend - Monitor Quintillizas

## API RESTful, Telemetría del Kernel y Gestión de Sesiones (PHP 8.2 + Python 3 psutil + PDO)
**Carrera:** Ingeniería en Sistemas Computacionales (5to Semestre)  
**Asignatura:** Programación Web / Sistemas Distribuidos / Redes  
**Proyecto:** Monitor Quintillizas (Telemetría de Servidores en Tiempo Real)  
**Repositorio Oficial:** [https://github.com/Monitor-Quintillizas/backend](https://github.com/Monitor-Quintillizas/backend)

---

## 1. Descripción del Módulo Backend

El backend se encarga de:
1. **Enrutamiento y Procesamiento RESTful (`services.php`):** Centraliza la recepción de peticiones HTTP del cliente y de los agentes, y despacha las acciones correspondientes.
2. **Autenticación y Sesiones (`sessions/Session.php`):** Valida credenciales con hashes Bcrypt, emite tokens Bearer y gestiona el ciclo de vida de la sesión activa de los operadores.
3. **Agente de Telemetría Remoto (`python/monitor.py`):** Programa independiente que corre en cada computadora monitoreada, recolecta métricas del sistema mediante `psutil` y las envía periódicamente por HTTP POST (`report_metrics`) usando `requests`.
4. **Persistencia y Capa de Datos (`database/connection.php`):** Administra la conexión mediante PDO a MySQL 8.0 con soporte de contingencia automática a SQLite si no hay un servidor MySQL disponible.
5. **Motor de Evaluación de Umbrales:** En `report_metrics`, compara automáticamente cada muestra entrante contra las reglas activas en la tabla `THRESHOLDS` y registra disparos en la tabla `ALERTS`, incluso si no hay ningún dashboard abierto.
6. **Manejo Estandarizado de Errores (`utils/Errors.php`, `utils/ValueValidation.php`):** Sanitiza entradas y emite respuestas JSON semánticas con los códigos de estado HTTP apropiados (200, 201, 400, 401, 404, 409, 500).

---

## 2. Estructura de Archivos del Backend

```text
back/
├── database/
│   └── connection.php              # Conexión PDO a MySQL con fallback automático a SQLite
├── python/
│   ├── .env.example                # Plantilla de configuración del agente (API_URL, AGENT_KEY, INTERVAL)
│   ├── monitor.py                  # Agente de telemetría (envía métricas cada INTERVAL segundos)
│   └── requirements.txt            # Dependencias Python del agente (psutil, requests)
├── sessions/
│   └── Session.php                 # Manejo de sesiones activas, tokens y validación de usuarios
├── utils/
│   ├── Errors.php                  # Catálogo centralizado de errores y códigos de respuesta
│   └── ValueValidation.php         # Sanitización y validación estricta de tipos de datos
├── .env.example                    # Plantilla de variables de entorno del backend (AGENT_KEY, DB_*)
├── Monitor_Postman_Collection.json # Colección de pruebas para Postman
├── Dockerfile                      # Manifiesto de contenedor PHP 8.2 Apache + Python
├── services.php                    # Enrutador principal de endpoints RESTful
└── README.md                       # Documentación técnica del módulo
```

---

## 3. Catálogo de Endpoints RESTful

Todos los endpoints se consumen a través del enrutador central `http://localhost:8000/services.php?action=<ACCION>`.

| Acción (`action`) | Método HTTP | Parámetros / Body | Autenticación | Código Éxito | Descripción |
| :--- | :---: | :--- | :---: | :---: | :--- |
| `login` | `POST` | `{"username": "admin", "password": "..."}` | Pública | `200 OK` | Valida credenciales e inicia sesión entregando token y rol. |
| `register` | `POST` | `{"username": "...", "email": "...", "password": "..."}` | Pública | `201 Created` | Registra una nueva cuenta de operador en la base de datos. |
| `logout` | `GET/POST` | Ninguno | Requerida | `200 OK` | Invalida y destruye la sesión activa del usuario. |
| `check_session` | `GET` | Ninguno | Requerida | `200 OK` | Verifica la validez y expiración del token de sesión. |
| `report_metrics` | `POST` | JSON con `hostname`, `cpu_usage_pct`, `ram_used_mb`, `ram_usage_pct`, `disk_usage_pct`, etc. | Cabecera `X-Agent-Key` | `200 OK` | Registra/actualiza el host, guarda la lectura en `METRICS` y evalúa `THRESHOLDS` para crear `ALERTS`. |
| `get_hosts` | `GET` | Ninguno | Pública | `200 OK` | Retorna la lista de equipos registrados junto con `last_seen_at`, `seconds_ago` e `is_online` (< 30s). |
| `get_metrics` | `GET` | `host_id` (opcional, default: 1) | Requerida | `200 OK` | Lee la última lectura registrada en `METRICS` para el `host_id` indicado. Responde `404` si no tiene lecturas. |
| `get_history` | `GET` | `host_id`, `start_date`, `end_date` | Requerida | `200 OK` | Consulta muestras históricas en un rango de fechas. |
| `get_thresholds` | `GET` | `host_id` (opcional) | Requerida | `200 OK` | Lista de umbrales configurados para el equipo. |
| `create_threshold`| `POST` | `{"host_id":1, "metric_name":"cpu_usage_pct", "comparison_operator":">", "threshold_value":80}` | Requerida | `201 Created` | Da de alta una nueva regla de monitoreo. |
| `update_threshold`| `POST` | `{"threshold_id":1, "metric_name":"...", "comparison_operator":"...", "threshold_value":85, "is_active":1}` | Requerida | `200 OK` | Modifica una regla de umbral existente. |
| `delete_threshold`| `POST` | `{"threshold_id": 1}` | Requerida | `200 OK` | Elimina una regla de umbral de la base de datos. |
| `get_alerts` | `GET` | Ninguno | Requerida | `200 OK` | Retorna el historial de alarmas y advertencias disparadas. |
| `get_all_users_performance` | `GET` | Ninguno | Requerida (Admin) | `200 OK` | Reporte en tiempo real de consumo de recursos por usuario activo. |
| `delete_account` | `POST` | `{"user_id": 2}` | Requerida | `200 OK` | Elimina una cuenta de usuario con baja en cascada. |

---

## 4. Configuración de la Clave del Backend y Despliegue del Agente

### 4.1 Configurar la clave en el servidor backend (`AGENT_KEY`)

El endpoint `report_metrics` **no utiliza sesión de usuario**; en su lugar exige la cabecera HTTP `X-Agent-Key` y la compara con la variable de entorno `AGENT_KEY` del servidor.

1. Copia la plantilla del backend (o define la variable en tu sistema / `docker-compose.yml`):
   ```bash
   cp .env.example .env
   ```
2. Edita `.env` y define una clave secreta segura:
   ```env
   AGENT_KEY=mi-clave-super-secreta
   ```
3. Inicia el servidor PHP (por ejemplo, `php -S 0.0.0.0:8000`) o pasa `-e AGENT_KEY=mi-clave-super-secreta` al contenedor. Si `AGENT_KEY` no coincide o no está configurada, el servidor responderá **`401 Unauthorized`**.

### 4.2 Instalar y correr el agente (`python/monitor.py`) en otra computadora

El agente se puede ejecutar en cualquier equipo (Windows, Linux, VM o contenedor) que tenga conectividad HTTP hacia el backend.

1. Copia la carpeta `python/` (o clona el repositorio) en la computadora que deseas monitorear.
2. Instala las dependencias de Python:
   ```bash
   cd python
   pip install -r requirements.txt
   ```
3. Crea el archivo de configuración `.env` a partir de `.env.example`:
   ```bash
   cp .env.example .env
   ```
4. Edita `python/.env` apuntando a la IP o dominio del servidor donde corre el backend y usando exactamente la misma `AGENT_KEY`:
   ```env
   API_URL=http://192.168.1.50:8000/services.php
   AGENT_KEY=mi-clave-super-secreta
   INTERVAL=5
   ```
   *(También puedes pasar `API_URL`, `AGENT_KEY` e `INTERVAL` directamente como variables de entorno del sistema o contenedor sin crear `.env`.)*
5. Ejecuta el agente:
   ```bash
   python monitor.py
   ```
   - Si el backend está apagado o responde error, el agente mostrará un aviso en la terminal y seguirá reintentando cada `INTERVAL` segundos sin cerrarse.
   - Para detener el agente de forma limpia presiona `Ctrl + C`.

---

## 5. Especificación del Endpoint `report_metrics`

- **URL:** `POST /services.php?action=report_metrics`
- **Cabeceras requeridas:**
  - `Content-Type: application/json`
  - `X-Agent-Key: <AGENT_KEY>`

### Cuerpo JSON que recibe:
```json
{
  "hostname": "Laptop-Ventas",
  "os_name": "Windows 11",
  "cpu_usage_pct": 24.5,
  "cpu_cores_logical": 16,
  "cpu_cores_physical": 8,
  "ram_total_mb": 16384.0,
  "ram_used_mb": 8192.5,
  "ram_free_mb": 8191.5,
  "ram_usage_pct": 50.0,
  "disk_total_gb": 476.94,
  "disk_used_gb": 210.3,
  "disk_free_gb": 266.64,
  "disk_usage_pct": 44.1,
  "uptime_seconds": 3620
}
```
- **Campos obligatorios y validaciones (`ValueValidation`):**
  - `hostname`: texto no vacío (1 a 100 caracteres).
  - `cpu_usage_pct`, `ram_usage_pct`, `disk_usage_pct`: números entre `0` y `100`.
  - `ram_used_mb`: número mayor o igual a `0`.
  - Los campos de especificaciones (`cpu_cores_*`, `ram_total_mb`, `ram_free_mb`, `disk_*_gb`, `uptime_seconds`) y `os_name` son opcionales; si se envían deben ser valores válidos no negativos.

### Respuestas de `report_metrics`:
- **`200 OK`** (lectura guardada, host creado/actualizado y umbrales evaluados):
  ```json
  {
    "ok": true,
    "mensaje": "Métricas registradas correctamente.",
    "host_id": 4,
    "metric_id": 128,
    "alert_triggered": false,
    "active_alerts": [],
    "timestamp": "2026-11-27 15:20:00"
  }
  ```
- **`400 Bad Request`** (JSON inválido, `hostname` vacío o porcentajes fuera de `0..100`):
  ```json
  {
    "Error": {
      "Type": 1,
      "Category": 1,
      "Description": "Datos inválidos: hostname no vacío, porcentajes entre 0 y 100 y ram_used_mb son obligatorios."
    }
  }
  ```
- **`401 Unauthorized`** (cabecera `X-Agent-Key` ausente o distinta a `AGENT_KEY`):
  ```json
  {
    "Error": {
      "Type": 2,
      "Category": 1,
      "Description": "Clave de agente inválida o faltante."
    }
  }
  ```

---

## 6. Respuestas HTTP y Manejo de Errores

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
  "Error": {
    "Type": 2,
    "Category": 1,
    "Description": "Credenciales inválidas o usuario inexistente."
  }
}
```

---

## 7. Colección y Pruebas con Postman

Se incluye la colección oficial lista para importar en Postman: [`Monitor_Postman_Collection.json`](./Monitor_Postman_Collection.json).

### Matriz de Casos de Prueba Validados:

| # | Caso de Prueba | Endpoint | Entrada Enviada | Código HTTP Esperado | Resultado |
| :-: | :--- | :--- | :--- | :-: | :---: |
| 1 | Login Exitoso | `POST ?action=login` | `username: "admin"`, `password: "1234"` | `200 OK` | ✅ Pasa |
| 2 | Contraseña Incorrecta | `POST ?action=login` | `username: "admin"`, `password: "erronea"` | `401 Unauthorized` | ✅ Pasa |
| 3 | Campos Vacíos en Login | `POST ?action=login` | `username: ""`, `password: ""` | `400 Bad Request` | ✅ Pasa |
| 4 | Usuario Inexistente | `POST ?action=login` | `username: "no_existe"`, `password: "1234"` | `401 Unauthorized` | ✅ Pasa |
| 5 | Petición sin Sesión | `GET ?action=get_metrics` | Sin cabecera `Authorization` ni cookie | `401 Unauthorized` | ✅ Pasa |
| 6 | Última Lectura de Host | `GET ?action=get_metrics&host_id=1` | Con Bearer Token válido (o `404` si no hay lecturas) | `200 OK` / `404` | ✅ Pasa |
| 7 | Reporte de Agente Válido | `POST ?action=report_metrics` | Cabecera `X-Agent-Key` válida y JSON de métricas | `200 OK` | ✅ Pasa |
| 8 | Reporte sin Clave / Inválida | `POST ?action=report_metrics` | `X-Agent-Key` incorrecta o ausente | `401 Unauthorized` | ✅ Pasa |
| 9 | Reporte con Datos Inválidos | `POST ?action=report_metrics` | `hostname` vacío o porcentaje fuera de `0..100` | `400 Bad Request` | ✅ Pasa |
| 10| Historial Válido | `GET ?action=get_history` | `start_date`, `end_date` válidos | `200 OK` | ✅ Pasa |
| 11| Alta de Umbral | `POST ?action=create_threshold` | JSON con métrica y límite | `201 Created` | ✅ Pasa |
| 12| Modificación de Umbral | `POST ?action=update_threshold` | JSON con `threshold_id` existente | `200 OK` | ✅ Pasa |
| 13| Eliminación de Umbral | `POST ?action=delete_threshold` | `threshold_id: 1` | `200 OK` | ✅ Pasa |

## Commits

Las issues de este proyecto están en `Monitor-Quintillizas/fronted`. Para que tus commits aparezcan en la issue, basta con escribir el número:

```bash
git commit -m "v1.2.0 #7"
```

El hook `.githooks/commit-msg` cambia `#7` por `Monitor-Quintillizas/fronted#7` al hacer el commit. Para activarlo, corre esto **una sola vez** en tu copia del repositorio (y otra vez si lo clonas en otra computadora):

```bash
git config core.hooksPath .githooks
```
