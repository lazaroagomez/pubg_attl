# PUBG Clan Leaderboard

Una aplicación web completa para mostrar un leaderboard avanzado de tu clan de PUBG con sistema de confianza basado en partidas jugadas.

## 🚀 Características

- **Sistema de Rating Avanzado**: 4 métricas independientes (Combat, Survival, Support, PUBG Rating)
- **Factor de Confianza**: Los ratings se ajustan según el número de partidas jugadas
- **Integración con API de PUBG**: Actualización automática de estadísticas
- **Panel de Administración**: Gestión de jugadores y configuración
- **Dark/Light Mode**: Interfaz adaptable a las preferencias del usuario
- **Responsive Design**: Funciona perfectamente en dispositivos móviles
- **Docker Ready**: Despliegue sencillo con un único contenedor

## 📊 Sistema de Confianza

El sistema ajusta los ratings basándose en el número de partidas jugadas:

- 🔴 **Baja Confianza** (0-19 partidas): Factor 0.0-0.38
- 🟡 **Confianza Media** (20-49 partidas): Factor 0.4-0.98
- 🟢 **Alta Confianza** (50+ partidas): Factor 1.0 (100%)

## 🛠️ Requisitos

- Docker y Docker Compose
- API Key de PUBG (obtener en https://developer.pubg.com/)

## 📦 Instalación

1. **Clona el repositorio**
```bash
git clone https://github.com/tu-usuario/pubg-leaderboard.git
cd pubg-leaderboard
```

2. **Crea el archivo `.env`**
```bash
cp env.example .env
```

3. **Configura las variables de entorno**
```env
# PUBG API Configuration
PUBG_API_KEY=tu_api_key_aqui
PUBG_PLATFORM=steam
PUBG_SHARD=steam

# App Configuration
APP_PORT=8080
APP_NAME=PUBG Clan Leaderboard
CLAN_NAME=Tu Clan

# Admin Configuration
ADMIN_USERNAME=admin
ADMIN_PASSWORD=cambiar_password_segura
```

4. **Inicia la aplicación**
```bash
docker-compose up -d
```

La aplicación estará disponible en `http://localhost:8080`

## 🎮 Uso

### Panel de Administración

1. Accede a `/admin.php`
2. Ingresa con las credenciales configuradas
3. Añade jugadores usando su nombre exacto de PUBG
4. Los datos se actualizarán automáticamente cada minuto

### Páginas Principales

- **`/`** - Leaderboard principal con todos los jugadores
- **`/member.php?id=X`** - Perfil detallado de un jugador
- **`/confidence.php`** - Dashboard de niveles de confianza
- **`/admin.php`** - Panel de administración

## 🔧 Configuración Avanzada

### Cache TTL (segundos)
```env
CACHE_TTL_SEASONS=86400        # 24 horas
CACHE_TTL_PLAYER_STATS=3600    # 1 hora
CACHE_TTL_WEAPON_MASTERY=21600 # 6 horas
CACHE_TTL_LEADERBOARDS=7200    # 2 horas
CACHE_TTL_LIFETIME_STATS=43200 # 12 horas
```

### Rate Limiting
```env
API_RATE_LIMIT=10             # Máximo de requests por ventana
API_RATE_LIMIT_WINDOW=60      # Ventana en segundos
```

## 📈 Fórmulas de Rating

### Combat Score (0-100)
```
(K/D × 20) + (Damage/100) + (Headshot% × 50) + (Accuracy × 100)
```

### Survival Score (0-100)
```
(WinRate × 100) + (Top10Rate × 50) + (PlacementScore × 50)
```

### Support Score (0-100)
```
(Assists/Match × 10) + (Revives/Match × 15) + (TeamKillShare × 50)
```

### PUBG Rating Final
```
(Combat × 0.35) + (Survival × 0.25) + (Support × 0.20) + (Consistency × 0.20)
```

## 🐛 Troubleshooting

### Los datos no se actualizan
- Verifica que la API key sea válida
- Revisa los logs: `docker-compose logs -f`
- Asegúrate de que los nombres de jugador sean exactos

### Error de rate limit
- La aplicación respeta el límite de 10 requests/minuto
- Usa el batch endpoint para optimizar las llamadas

### Base de datos corrupta
- Detén el contenedor: `docker-compose down`
- Elimina la base de datos: `rm database/data.sqlite`
- Reinicia: `docker-compose up -d`

## 📝 Licencia

MIT License - ver archivo LICENSE

## 🤝 Contribuciones

Las contribuciones son bienvenidas. Por favor:
1. Fork el proyecto
2. Crea tu feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit tus cambios (`git commit -m 'Add some AmazingFeature'`)
4. Push al branch (`git push origin feature/AmazingFeature`)
5. Abre un Pull Request 