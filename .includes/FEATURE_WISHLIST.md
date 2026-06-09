# Homelab Landing Page - Feature Wishlist

## Themes & Styling
- [ ] **Theme System** – Easy swap between pre-built themes (cyberpunk, minimal, etc.)
- [ ] **Dark/Light Mode Toggle** – User-selectable theme preference with localStorage persistence
- [ ] **Custom Color Configuration** – Allow users to override CSS variables via a config file or UI
- [ ] **CSS Variables Export** – Generate reusable theme files for portability

## Dashboard & Navigation
- [ ] **Custom Dashboard Widgets** – Drag-and-drop widget arrangement, save layout to config
- [ ] **Favorites/Pinned Services** – Mark frequently-used services to appear first
- [ ] **Search/Filter** – Quick search across all navigation links and services
- [ ] **Service Categories** – Group services beyond just sections (e.g., Media, Networking, Admin)
- [ ] **Mobile Responsive Improvements** – Collapsible sidebar, touch-friendly nav

## Monitoring & Health
- [ ] **Service Health Checks** – Ping endpoints to show service status (green/red indicator)
- [ ] **Custom Service Status** – Display status badges next to each nav link
- [ ] **Alert System** – Highlight critical stats (high CPU, low disk space) with notifications
- [ ] **Historical Stats** – Chart memory/CPU/disk usage over time (chart.js integration)

## System Stats Enhancements
- [ ] **Network I/O Stats** – Bandwidth in/out from `cat /proc/net/dev`
- [ ] **Temperature Monitoring** – CPU/GPU temps from `sensors` or thermal zones
- [ ] **Container Stats** – Show all Docker containers, not just Plex
- [ ] **Service Uptime** – Track when services started/restarted
- [ ] **GPU Stats** – GPU memory/utilization if available
- [ ] **UPS Status** – Battery level and charging state

## Configuration & Persistence
- [ ] **Config File System** – YAML or JSON config for all settings (theme, refresh rate, services)
- [ ] **Settings UI** – Web-based settings page instead of editing files
- [ ] **Auto-Refresh Control** – Configurable update intervals for different stat types
- [ ] **Export/Import Layouts** – Share configs between servers

## Integration & APIs
- [ ] **InfluxDB Integration** – Send stats to InfluxDB for long-term archival
- [ ] **Prometheus Metrics** – Expose metrics endpoint for scraping
- [ ] **Webhook Alerts** – Send alerts to Discord/Slack when thresholds breach
- [ ] **External API Services** – Display weather, calendar, or other web data

## User Experience
- [ ] **Welcome Modal** – First-time setup wizard for new installations
- [ ] **Help/Documentation Panel** – Collapsible info on what each stat means
- [ ] **Stats Tooltip Hover** – Show detailed breakdowns on hover
- [ ] **Screenshot/Export** – Save current dashboard state as image

## Performance & Optimization
- [ ] **Caching Strategy** – Cache stats for X seconds to reduce system calls
- [ ] **Lazy-Loading** – Load stats on-demand for fast initial page load
- [ ] **Minification** – Minify CSS/JS for production builds
- [ ] **HTTP Compression** – Enable gzip compression for responses

## Development
- [ ] **Automated Testing** – Unit tests for PHP stat collection
- [ ] **Docker Compose Setup** – One-command deployment across multiple servers
- [ ] **CI/CD Pipeline** – Automated linting and testing on commits
- [ ] **Version Tracking** – Update checker to notify of new releases
