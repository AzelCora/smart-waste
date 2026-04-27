#!/bin/bash
# Script to create the 'bin-activity' Kafka topic
# This script runs when the Kafka container starts via docker compose entrypoint.

# Define Kafka connection details
BOOTSTRAP_SERVERS="localhost:29092"
TOPIC_NAME="bin-activity"
REPLICATION_FACTOR=1
PARTITIONS=1

echo "Attempting to create Kafka topic: ${TOPIC_NAME}..."

# Define the path to the kafka-topics script, based on the ls -l output
KAFKA_TOPICS_CMD="/usr/bin/kafka-topics"

# Check if the kafka-topics command exists and is executable
if [ ! -x "$KAFKA_TOPICS_CMD" ]; then
  echo "ERROR: Kafka topics script not found or not executable at ${KAFKA_TOPICS_CMD}. Please check the path within the container."
else
  # Execute the kafka-topics command using the explicit path
  "$KAFKA_TOPICS_CMD" --bootstrap-server "${BOOTSTRAP_SERVERS}" --create --topic "${TOPIC_NAME}" --replication-factor "${REPLICATION_FACTOR}" --partitions
"${PARTITIONS}" --if-not-exists

  # Capture the exit code of the command explicitly before checking
  EXIT_CODE=$?

  if [ "$EXIT_CODE" -eq 0 ]; then
    echo "Topic '${TOPIC_NAME}' created successfully or already exists."
  else
    echo "ERROR: Failed to create topic '${TOPIC_NAME}'. Command exited with code ${EXIT_CODE}. Please check Kafka logs for details."
  fi
fi