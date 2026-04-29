#!/bin/bash

TOPIC_NAME="bin-activity"

echo "Creating Kafka topic: ${TOPIC_NAME}..."

docker exec kafka kafka-topics \
  --bootstrap-server kafka:29092 \
  --create \
  --if-not-exists \
  --topic "${TOPIC_NAME}" \
  --replication-factor 1 \
  --partitions 1

if [ $? -eq 0 ]; then
  echo "Topic '${TOPIC_NAME}' created successfully or already exists."
else
  echo "ERROR: Failed to create topic '${TOPIC_NAME}'."
  exit 1
fi
