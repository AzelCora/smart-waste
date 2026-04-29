#!/bin/bash

echo "Starting Docker Compose services..."
docker compose up -d

echo "Waiting for Elasticsearch to be ready..."
until docker exec elasticsearch curl -s -u elastic:changeme http://localhost:9200/_cluster/health > /dev/null 2>&1; do
  echo "Elasticsearch not ready, retrying in 2s..."
  sleep 2
done

echo "Setting up Kibana user..."
bash setup-kibana.sh
docker restart kibana

echo "Waiting for Kafka to be ready..."
until docker exec kafka kafka-topics --bootstrap-server kafka:29092 --list > /dev/null 2>&1; do
  echo "Kafka not ready, retrying in 2s..."
  sleep 2
done

echo "Creating Kafka topic..."
bash kafka-init/01-create-topic.sh

echo "Waiting for PostgreSQL to be ready..."
until docker compose exec -T pgsql pg_isready -q -U sail > /dev/null 2>&1; do
  echo "PostgreSQL not ready, retrying in 2s..."
  sleep 2
done

echo "Running database migrations..."
docker compose exec -T laravel.test php artisan optimize:clear
docker compose exec -T laravel.test php artisan migrate --force

echo "Starting Kafka consumer..."
docker compose exec -T laravel.test bash -c "nohup php artisan kafka:consume > /tmp/kafka-consumer.log 2>&1 &"

echo "Starting bin runners..."
bash app/Services/run-network.sh

echo "All services started successfully!"
