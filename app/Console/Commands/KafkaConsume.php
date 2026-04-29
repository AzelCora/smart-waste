<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\BinEvent;
use Illuminate\Console\Command;
use longlang\phpkafka\Consumer\Consumer;
use longlang\phpkafka\Consumer\ConsumerConfig;

class KafkaConsume extends Command
{
    protected $signature = 'kafka:consume
        {--broker=kafka:29092 : Kafka bootstrap server}
        {--topic=bin-activity : Topic to consume}
        {--group=laravel-bin-consumer : Consumer group ID}';

    protected $description = 'Consume bin-activity messages from Kafka and persist to PostgreSQL';

    public function handle(): int
    {
        $broker = $this->option('broker');
        $topic  = $this->option('topic');
        $group  = $this->option('group');

        $this->info("Connecting to Kafka at {$broker}, topic={$topic}, group={$group}");

        $config = new ConsumerConfig();
        $config->setBroker($broker);
        $config->setTopic($topic);
        $config->setGroupId($group);
        $config->setAutoCommit(false);

        $consumer = new Consumer($config);

        $this->info('Listening for messages… (Ctrl+C to stop)');

        while (true) {
            $message = $consumer->consume();

            if ($message === null || $message->getValue() === null) {
                continue;
            }

            $data = json_decode($message->getValue(), true);

            if (!is_array($data)) {
                $this->warn('Skipping non-JSON message: ' . $message->getValue());
                $consumer->ack($message);
                continue;
            }

            try {
                BinEvent::create([
                    'bin_id'      => $data['bin_id'],
                    'location_x'  => $data['location']['x'],
                    'location_y'  => $data['location']['y'],
                    'type'        => $data['type'],
                    'payload'     => $data['payload'],
                    'occurred_at' => $data['occurred_at'],
                ]);

                $consumer->ack($message);

                $this->line(sprintf(
                    '[%s] Stored: bin=%s type=%s',
                    now()->toTimeString(),
                    $data['bin_id'],
                    $data['type'],
                ));
            } catch (\Throwable $e) {
                $this->error('Failed to store message: ' . $e->getMessage());
            }
        }

        return self::SUCCESS;
    }
}
