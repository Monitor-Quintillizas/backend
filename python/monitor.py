# python/monitor.py
import sys
import os
import platform
import json
import time

try:
    import psutil
except ImportError:
    print(json.dumps({"error": "psutil_not_found"}))
    sys.exit(0)

def get_system_metrics():
    try:
        # Sondeo rápido de CPU sin bloqueo excesivo (interval=0.05 para respuesta instantánea)
        cpu = psutil.cpu_percent(interval=0.05)
        ram = psutil.virtual_memory()
        
        # Partición según el SO
        if platform.system() == "Windows":
            root_path = os.getenv("SystemDrive", "C:") + "\\"
        else:
            root_path = "/"
            
        disk = psutil.disk_usage(root_path)

        boot_time = psutil.boot_time()
        uptime_seconds = int(time.time() - boot_time)

        metrics = {
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
            "uptime_seconds": uptime_seconds
        }
        
        print(json.dumps(metrics))
    except Exception as e:
        print(json.dumps({"error": str(e)}))

if __name__ == "__main__":
    get_system_metrics()