<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\BinMessage;
use App\DTOs\BinState;
use App\DTOs\MessageType;
use App\Repository\AlertStateRepository;

final class MessageGenerator
{
    /** @var AlertRule[] */
    private array $rules;

    public function __construct(
        private readonly AlertStateRepository $stateRepo,
        array                                  $rules = [],
    ) {
        $this->rules = $rules;
    }

    public static function withDefaultRules(AlertStateRepository $stateRepo): self
    {
        return new self($stateRepo, [
            new UsageEventRule(),
            new CapacityWarningRule(50.0,  MessageType::CAPACITY_50),
            new CapacityWarningRule(75.0,  MessageType::CAPACITY_75),
            new CapacityWarningRule(90.0,  MessageType::CAPACITY_90),
            new LidAlertRule(),
            new BatteryWarningRule(50.0,  MessageType::BATTERY_50),
            new BatteryWarningRule(25.0,  MessageType::BATTERY_25),
            new BatteryWarningRule(10.0,  MessageType::BATTERY_10),
        ]);
    }

    public function addRule(AlertRule $rule): self
    {
        $this->rules[] = $rule;
        return $this;
    }

    /** @return BinMessage[] */
    public function generate(BinState $state): array
    {
        $messages = [];
        foreach ($this->rules as $rule) {
            $message = $rule->evaluate($state, $this->stateRepo);
            if ($message !== null) {
                $messages[] = $message;
            }
        }
        return $messages;
    }
}