# Smart Waste

Real-time smart bin monitoring system. Bins simulate IoT sensors that publish events to Kafka. A Laravel consumer persists events to PostgreSQL. A web dashboard visualises bin fill level, battery, lid status, and an optimised collection route on live maps.

---

## Quick Start

```bash
# 1. Clone and install PHP dependencies
composer install

# 2. Copy environment file (edit DB/Kafka credentials if needed)
cp .env.example .env

# 3. Start everything
bash start.sh
```

`start.sh` will:
1. Start all Docker services
2. Wait for Elasticsearch, Kafka, and PostgreSQL to be healthy
3. Create the Kafka topic
4. Run database migrations
5. Start the Kafka consumer inside the Laravel container
6. Launch the bin simulator network

Open **http://localhost** to view the dashboard.

---

## Architecture

```
┌─────────────────────────────────────────────────────────────┐
│  Bin Simulators (PHP CLI, run on host)                      │
│  app/Services/bin-runner.php  ×8 bins                       │
│  Publishes JSON events → Kafka topic: bin-activity          │
└────────────────────────┬────────────────────────────────────┘
                         │ kafka:29092
                         ▼
┌─────────────────────────────────────────────────────────────┐
│  Kafka  (confluentinc/cp-kafka:7.4.0)                       │
│  Topic: bin-activity  │  Kafka UI: http://localhost:8080    │
└────────────────────────┬────────────────────────────────────┘
                         │ consumed by
                         ▼
┌─────────────────────────────────────────────────────────────┐
│  Laravel (Sail / PHP 8.5)  http://localhost                  │
│  php artisan kafka:consume  →  bin_events table             │
│  GET /api/bin-states        →  latest state per bin         │
│  GET /api/route             →  optimised collection route   │
│  GET /                      →  dashboard (Blade + Leaflet)  │
└────────────────────────┬────────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────────┐
│  PostgreSQL 17  (sail-pgsql)                                │
│  Table: bin_events                                          │
└─────────────────────────────────────────────────────────────┘

Also running (ELK stack, optional):
  Elasticsearch  http://localhost:9200
  Kibana         http://localhost:5601
  Logstash       consumes bin-activity → Elasticsearch
```

---

## Project Structure

```
smart-waste/
├── app/
│   ├── Console/Commands/
│   │   └── KafkaConsume.php        # Artisan command: consumes Kafka → PostgreSQL
│   ├── DTOs/
│   │   ├── BinMessage.php          # Value object for a single bin event
│   │   ├── BinState.php            # Current state snapshot of a bin
│   │   ├── MessageType.php         # Enum of all event types
│   │   └── UsageEvent.php          # A single deposit by a user
│   ├── Http/Controllers/
│   │   └── DashboardController.php # Web + API endpoints
│   ├── Models/
│   │   └── BinEvent.php            # Eloquent model for bin_events table
│   ├── Repository/
│   │   ├── AlertStateRepository.php
│   │   └── InMemoryAlertStateRepository.php
│   └── Services/
│       ├── AlertRule.php           # Interface for alert rules
│       ├── BatteryWarningRule.php  # Fires at 50/25/10% battery
│       ├── BinSimulator.php        # Simulates bin state per tick
│       ├── CapacityWarningRule.php # Fires at 50/75/90% fill
│       ├── LidAlertRule.php        # Fires when lid is left open
│       ├── MessageGenerator.php    # Applies all rules to a BinState
│       ├── RouteOptimizer.php      # Nearest-neighbour TSP route planner
│       ├── UsageEventRule.php      # Fires on every deposit
│       ├── bin-runner.php          # CLI entry point for one bin
│       └── run-network.sh          # Launches all 8 bin simulators
├── database/
│   └── migrations/
│       └── ..._create_bin_events_table.php
├── resources/views/
│   └── dashboard.blade.php         # Single-page dashboard (Leaflet maps)
├── routes/
│   └── web.php                     # Route definitions
├── logstash/
│   ├── pipeline/bin-activity.conf  # Logstash pipeline: Kafka → Elasticsearch
│   └── logstash.yml
├── kafka-init/
│   └── 01-create-topic.sh          # Creates bin-activity topic
├── compose.yaml                    # All Docker services
├── start.sh                        # One-command startup script
└── .env                            # Environment configuration
```

---

## Event Types

| Type | Trigger | Key Payload Fields |
|---|---|---|
| `usage_event` | Every deposit | `current_weight`, `capacity_percent`, `battery_level`, `lid_closed` |
| `capacity_warning_50/75/90` | Fill crosses threshold | `capacity_percent`, `current_weight` |
| `lid_open_alert` | Lid not closed after use | `lid_closed: false` |
| `battery_warning_50/25/10` | Battery crosses threshold | `battery_level` |

---

## Database

**Table: `bin_events`**

| Column | Type | Description |
|---|---|---|
| `id` | bigint | Primary key |
| `bin_id` | string | e.g. `BIN-001` |
| `location_x` | decimal | Longitude |
| `location_y` | decimal | Latitude |
| `type` | string | Event type (see above) |
| `payload` | jsonb | Event-specific data |
| `occurred_at` | timestamptz | When the event happened |

---

## Dashboard

Four live maps, auto-refreshing every 10 seconds (route every 30s):

- **Fill Level** — bins coloured green/yellow/orange/red by capacity %
- **Battery Level** — bins coloured green/yellow/red by battery %
- **Lid Status** — green (closed) / red (open)
- **Collection Route** — optimised driving route (via OSRM) for bins ≥30% full, with numbered stops and total distance

---

## Route Optimization

`app/Services/RouteOptimizer.php` implements a **nearest-neighbour heuristic** for the Travelling Salesman Problem:

1. Filter bins with fill ≥ 30%
2. Start from the depot (configurable in `DashboardController`)
3. Repeatedly visit the closest unvisited bin
4. Return to depot

The frontend fetches the ordered waypoints and draws the road-following route using the [OSRM public API](https://router.project-osrm.org).

---

## Services & Ports

| Service | URL |
|---|---|
| Laravel app / Dashboard | http://localhost |
| Kafka UI | http://localhost:8080 |
| Kibana | http://localhost:5601 |
| Elasticsearch | http://localhost:9200 |
| PostgreSQL | localhost:5432 |
| Kafka | localhost:9092 |
