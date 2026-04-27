#!/usr/bin/env bash
# setup-elasticsearch.sh
#
# Registers the bin-activity index template and sets up the kibana_system
# user password so Kibana can connect.
#
# Run ONCE after Elasticsearch is healthy:
#   bash setup-elasticsearch.sh
#
# Or on Windows (Git Bash / WSL):
#   bash setup-elasticsearch.sh

set -euo pipefail

ES_HOST="${ELASTICSEARCH_HOST:-http://localhost:9200}"
ES_USER="${ELASTIC_USER:-elastic}"
ES_PASS="${ELASTIC_PASSWORD:-changeme}"
KIBANA_PASS="${KIBANA_PASSWORD:-changeme}"
TEMPLATE_FILE="$(dirname "$0")/templates/bin-activity-template.json"

echo "Waiting for Elasticsearch to be ready..."
until curl -s -u "${ES_USER}:${ES_PASS}" "${ES_HOST}/_cluster/health" | grep -q '"status"'; do
  sleep 3
done
echo "Elasticsearch is up."

# ------------------------------------------------------------------
# 1. Register the index template
# ------------------------------------------------------------------
echo "Registering bin-activity index template..."
curl -s -X PUT \
  -u "${ES_USER}:${ES_PASS}" \
  -H "Content-Type: application/json" \
  -d @"${TEMPLATE_FILE}" \
  "${ES_HOST}/_index_template/bin-activity" | python3 -m json.tool

# ------------------------------------------------------------------
# 2. Set kibana_system password (required for Kibana to connect)
# ------------------------------------------------------------------
echo ""
echo "Setting kibana_system password..."
curl -s -X POST \
  -u "${ES_USER}:${ES_PASS}" \
  -H "Content-Type: application/json" \
  -d "{\"password\": \"${KIBANA_PASS}\"}" \
  "${ES_HOST}/_security/user/kibana_system/_password" | python3 -m json.tool

# ------------------------------------------------------------------
# 3. Verify template was registered
# ------------------------------------------------------------------
echo ""
echo "Verifying template..."
curl -s -u "${ES_USER}:${ES_PASS}" \
  "${ES_HOST}/_index_template/bin-activity" | python3 -m json.tool

echo ""
echo "Setup complete."
echo "  Elasticsearch : ${ES_HOST}"
echo "  Kibana        : http://localhost:5601  (user: elastic / ${ES_PASS})"