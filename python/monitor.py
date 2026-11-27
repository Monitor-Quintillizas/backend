# python/monitor.py
import os
import platform
import sys
import time
from datetime import datetime
from pathlib import Path

try:
    import psutil
    import requests
except ImportError as exc:
    print(f"Error: falta la dependencia '{exc.name}'. Instala con: pip install -r requirements.txt")
    sys.exit(1)


def load_env_file(env_path: Path) -> None:
    if not env_path.is_file():
        return

    for raw_line in env_path.read_text(encoding="utf-8").splitlines():
        line = raw_line.strip()
        if not line or line.startswith("#") or "=" not in line:
            continue

        key, value = line.split("=", 1)
        key = key.strip()
        value = value.strip()

        if not key:
            continue

        if (value.startswith('"') and value.endswith('"')) or (
            value.startswith("'") and value.endswith("'")
        ):
            value = value[1:-1]

        os.environ.setdefault(key, value)


def load_config() -> tuple[str, str, float]:
    script_dir = Path(__file__).resolve().parent
    load_env_file(script_dir / ".env")
    if Path.cwd() != script_dir:
        load_env_file(Path.cwd() / ".env")

    api_url = os.getenv("API_URL", "").strip()
    agent_key = os.getenv("AGENT_KEY", "").strip()
    interval_raw = os.getenv("INTERVAL", "").strip()

    missing = []
    if not api_url:
        missing.append("API_URL")
    if not agent_key:
        missing.append("AGENT_KEY")
    if not interval_raw:
        missing.append("INTERVAL")

    if missing:
        print(
            f"Error de configuración: faltan las variables {', '.join(missing)}. "
            "Defínelas en el entorno o en un archivo .env (consulta .env.example)."
        )
        sys.exit(1)

    try:
        interval = float(interval_raw)
        if interval <= 0:
            raise ValueError
    except ValueError:
        print("Error de configuración: INTERVAL debe ser un número mayor a 0.")
        sys.exit(1)

    if "action=" not in api_url:
        separator = "&" if "?" in api_url else "?"
        api_url = f"{api_url}{separator}action=report_metrics"

    return api_url, agent_key, interval


def get_system_metrics() -> dict:
    cpu = psutil.cpu_percent(interval=0.2)
    ram = psutil.virtual_memory()
    root_path = os.getenv("SystemDrive", "C:") + "\\" if platform.system() == "Windows" else "/"
    disk = psutil.disk_usage(root_path)
    uptime_seconds = max(0, int(time.time() - psutil.boot_time()))

    return {
        "cpu_usage_pct": float(cpu),
        "cpu_cores_logical": psutil.cpu_count(logical=True),
        "cpu_cores_physical": psutil.cpu_count(logical=False) or psutil.cpu_count(logical=True),
        "ram_total_mb": round(ram.total / (1024 * 1024), 2),
        "ram_used_mb": round(ram.used / (1024 * 1024), 2),
        "ram_free_mb": round(ram.available / (1024 * 1024), 2),
        "ram_usage_pct": float(ram.percent),
        "disk_total_gb": round(disk.total / (1024 ** 3), 2),
        "disk_used_gb": round(disk.used / (1024 ** 3), 2),
        "disk_free_gb": round(disk.free / (1024 ** 3), 2),
        "disk_usage_pct": float(disk.percent),
        "hostname": platform.node(),
        "os_name": f"{platform.system()} {platform.release()}",
        "os_machine": platform.machine(),
        "uptime_seconds": uptime_seconds,
    }


def extract_error_detail(response: requests.Response) -> str:
    try:
        payload = response.json()
        if isinstance(payload, dict):
            err = payload.get("Error")
            if isinstance(err, dict) and err.get("Description"):
                return str(err["Description"])
            if payload.get("mensaje"):
                return str(payload["mensaje"])
    except ValueError:
        pass
    return response.reason or "Error desconocido"


def run_agent() -> None:
    endpoint_url, agent_key, interval = load_config()
    headers = {
        "X-Agent-Key": agent_key,
        "Content-Type": "application/json",
        "Accept": "application/json",
    }

    print(f"Agente iniciado. Enviando métricas a {endpoint_url} cada {interval:g}s (Ctrl + C para salir).")

    while True:
        timestamp = datetime.now().strftime("%H:%M:%S")
        try:
            metrics = get_system_metrics()
            response = requests.post(endpoint_url, json=metrics, headers=headers, timeout=5)

            if response.ok:
                print(
                    f"[{timestamp}] Enviado ({metrics['hostname']}): "
                    f"CPU {metrics['cpu_usage_pct']}% | "
                    f"RAM {metrics['ram_usage_pct']}% | "
                    f"Disco {metrics['disk_usage_pct']}%"
                )
                try:
                    data = response.json()
                    if isinstance(data, dict) and data.get("alert_triggered"):
                        print(f"[{timestamp}] Alerta disparada en el servidor ({len(data.get('active_alerts', []))}).")
                except ValueError:
                    pass
            else:
                detail = extract_error_detail(response)
                print(
                    f"[{timestamp}] Aviso: el backend respondió HTTP {response.status_code} ({detail}). "
                    f"Reintentando en {interval:g}s..."
                )
        except requests.exceptions.RequestException as exc:
            print(
                f"[{timestamp}] Aviso: no se pudo conectar con el backend ({exc.__class__.__name__}). "
                f"Reintentando en {interval:g}s..."
            )
        except Exception as exc:
            print(f"[{timestamp}] Aviso al recolectar/enviar métricas: {exc}. Reintentando en {interval:g}s...")

        time.sleep(interval)


if __name__ == "__main__":
    try:
        run_agent()
    except KeyboardInterrupt:
        print("\nAgente detenido correctamente.")
        sys.exit(0)