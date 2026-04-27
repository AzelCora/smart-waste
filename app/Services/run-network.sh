#!/usr/bin/env bash
# run-network.sh
#
# Launches a small city network of smart bins, each as its own background
# process. All output goes to the same terminal with the bin ID printed on
# every line, so you can tell them apart at a glance.
#
# Usage:
#   chmod +x run-network.sh
#   ./run-network.sh
#
# Stop all bins:
#   kill $(cat .bin-pids)   OR just Ctrl-C (the trap below handles it)

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
RUNNER="$SCRIPT_DIR/bin-runner.php"
PID_FILE="$SCRIPT_DIR/.bin-pids"

# Tick interval in seconds for every bin (lower = faster simulation)
TICK=2

# ------------------------------------------------------------------
# Bin fleet definition:  id  x          y          max_cap  battery  init_weight
# ------------------------------------------------------------------
declare -a BINS=(
    "BIN-001  -8.6538  41.1579  100   100   0"
    "BIN-002  -8.6100  41.1480  150    85  30"
    "BIN-003  -8.6200  41.1600   80    60  40"
    "BIN-004  -8.6350  41.1520  120    40  70"
    "BIN-005  -8.6450  41.1650  200    95   5"
    # Added new bins with invented properties
    "BIN-006  -8.6700  41.1400   90    75  10" # Small bin, low initial weight, decent battery
    "BIN-007  -8.5900  41.1700  180    50  80" # Large bin, high initial weight, lower battery
    "BIN-008  -8.7000  41.1550  110    99   0" # Medium bin, almost full capacity, high battery
)

# ------------------------------------------------------------------
# Clean up background processes on exit / Ctrl-C
# ------------------------------------------------------------------
cleanup() {
    echo -e "\n\033[1mStopping all bin simulators…\033[0m"
    if [[ -f "$PID_FILE" ]]; then
        while IFS= read -r pid; do
            kill "$pid" 2>/dev/null && echo "  killed PID $pid"
        done < "$PID_FILE"
        rm -f "$PID_FILE"
    fi
    exit 0
}
trap cleanup INT TERM

# ------------------------------------------------------------------
# Launch
# ------------------------------------------------------------------
> "$PID_FILE"   # truncate / create PID file

echo -e "\033[1m╔══════════════════════════════════════════════╗"
echo -e "║   Smart Bin City Network — starting up…     ║"
echo -e "╚══════════════════════════════════════════════╝\033[0m"

for bin_def in "${BINS[@]}"; do
    read -r bin_id x y cap bat wt <<< "$bin_def"
    php "$RUNNER" "$bin_id" "$x" "$y" "$cap" "$TICK" "$bat" "$wt" &
    pid=$!
    echo "$pid" >> "$PID_FILE"
    echo "  ▶ Started $bin_id (PID $pid)  cap=${cap}kg  bat=${bat}%  weight=${wt}kg"
done

echo -e "\n\033[2mPress Ctrl-C to stop all bins.\033[0m\n"

# Wait for all children; this keeps the script alive so the trap fires
wait
