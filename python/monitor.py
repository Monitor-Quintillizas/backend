# python/monitor.py
import psutil
import json

def get_system_metrics():
    # cpu_percent con interval=1 toma una muestra precisa de 1 segundo
    cpu = psutil.cpu_percent(interval=1)
    ram = psutil.virtual_memory()
    disk = psutil.disk_usage('/')

    metrics = {
        "cpu_usage_pct": cpu,
        "ram_used_mb": round(ram.used / (1024 * 1024), 2),
        "ram_usage_pct": ram.percent,
        "disk_usage_pct": disk.percent
    }
    
    # Imprimir en formato JSON para que PHP lo capture
    print(json.dumps(metrics))

if __name__ == "__main__":
    get_system_metrics()