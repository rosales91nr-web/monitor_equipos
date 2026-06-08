# LensWare Monitor v2.0

## Archivos
| Archivo | Función |
|---|---|
| config.php | ⚠️ Edita las rutas aquí |
| index.php | Panel General — KPIs + todos los equipos + historial |
| device.php | Panel por Equipo — vista individual |
| parse_log.php | Motor de parseo (Surfacing + Edging) |
| api.php | Endpoint JSON |

## Instalación rápida

### 1. Copiar a XAMPP
```
C:\xampp\htdocs\lensware_monitor\
```

### 2. Editar config.php
Las rutas ya están preconfiguradas:
```php
define('LOG_DIR_SURFACING', '\\\\172.16.8.32\\Lensware\\LensDeviceServer_Surfacing\\Log');
define('LOG_DIR_EDGING',    '\\\\172.16.8.32\\Lensware\\LensDeviceServer_Edging\\Log');
```

### 3. Si Apache no accede a rutas UNC, mapear unidades:
```
net use S: \\172.16.8.32\Lensware\LensDeviceServer_Surfacing\Log /persistent:yes
net use E: \\172.16.8.32\Lensware\LensDeviceServer_Edging\Log /persistent:yes
```
Y cambiar en config.php a `S:\\` y `E:\\`

### 4. Abrir en el navegador
- http://localhost/lensware_monitor/
- http://localhost/lensware_monitor/device.php

## Equipos

### Surfacing — 8 equipos
CCU004, CCU003, CCP002, CCP004, CCL004, HSE001, HSE003, HSS004

### Edging — 4 equipos activos (3 y 5 detenidos, no aparecen)
ESF001, ESF002, ESF004, 4RA001

## Notas
- Se recarga automáticamente cada 30 segundos (configurable en config.php)
- El parser detecta automáticamente prefijo IP o nombre de equipo
- Los equipos sin log no aparecen en el monitor
