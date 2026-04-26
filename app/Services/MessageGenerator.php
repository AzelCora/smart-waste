<?php

declare(strict_types=1);

namespace SmartBin;

/**
 * MessageGenerator
 *
 * Orchestrates all registered {@see AlertRule}s against a {@see BinState}
 * and returns zero-or-more {@see BinMessage} objects.
 *
 * Typical usage
 * -------------
 * ```php
 * $repo      = new InMemoryAlertStateRepository();
 * $generator = MessageGenerator::withDefaultRules($repo);
 *
 * $state     = new BinState(
 *     binId:          'BIN-001',
 *     locationX:      -8.6538,
 *     locationY:       41.1579,
 *     maxCapacity:    100.0,
 *     currentWeight:   76.3,
 *     batteryLevel:    48.0,
 *     lidClosed:       false,
 *     lastUsageEvent:  new UsageEvent('a1b2c3d4', 2.3),
 * );
 *
 * $messages = $generator->generate($state);
 * ```
 *
 * Extension
 * ---------
 * Inject additional {@see AlertRule} implementations via the constructor or
 * call {@see addRule()} before invoking generate().
 */
final class MessageGenerator
{
    /** @var AlertRule[] */
    private array $rules;

    /**
     * @param AlertStateRepository $stateRepo Persistence layer for dedup state
     * @param AlertRule[]          $rules     Ordered list of rules to evaluate
     */
    public function __construct(
        private readonly AlertStateRepository $stateRepo,
        array                                  $rules = [],
    ) {
        $this->rules = $rules;
    }

    // ------------------------------------------------------------------
    // Factory helpers
    // ------------------------------------------------------------------

    /**
     * Returns a generator pre-loaded with the full default rule set:
     *   - UsageEventRule
     *   - CapacityWarningRule  ×3 (50 %, 75 %, 90 %)
     *   - LidAlertRule
     *   - BatteryWarningRule   ×3 (50 %, 25 %, 10 %)
     */
    public static function withDefaultRules(AlertStateRepository $stateRepo): self
    {
        return new self($stateRepo, self::defaultRules());
    }

    /**
     * Returns the canonical default rule set as an array.
     * Useful when you want to prepend or append custom rules.
     *
     * @return AlertRule[]
     */
    public static function defaultRules(): array
    {
        return [
            // Usage events — always first so capacity % in the payload is fresh
            new UsageEventRule(),

            // Capacity thresholds — evaluated in ascending order so lower bands
            // fire before higher ones in a single pass, if multiple are crossed.
            new CapacityWarningRule(50.0,  MessageType::CAPACITY_50),
            new CapacityWarningRule(75.0,  MessageType::CAPACITY_75),
            new CapacityWarningRule(90.0,  MessageType::CAPACITY_90),

            // Lid
            new LidAlertRule(),

            // Battery thresholds — descending so the highest-priority (10 %)
            // band fires before less-critical ones when multiple are crossed.
            new BatteryWarningRule(50.0,  MessageType::BATTERY_50),
            new BatteryWarningRule(25.0,  MessageType::BATTERY_25),
            new BatteryWarningRule(10.0,  MessageType::BATTERY_10),
        ];
    }

    // ------------------------------------------------------------------
    // Rule management
    // ------------------------------------------------------------------

    /**
     * Append a custom rule at runtime (e.g. temperature, fill-rate, …).
     */
    public function addRule(AlertRule $rule): self
    {
        $this->rules[] = $rule;
        return $this;
    }

    /** @return AlertRule[] */
    public function getRules(): array
    {
        return $this->rules;
    }

    // ------------------------------------------------------------------
    // Core logic
    // ------------------------------------------------------------------

    /**
     * Evaluate all rules against $state and collect resulting messages.
     *
     * @param BinState $state Current sensor snapshot for one bin
     * @return BinMessage[]   Zero or more messages (never null entries)
     */
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

    /**
     * Convenience: generate messages and immediately serialise to a JSON string.
     *
     * @param BinState $state
     * @param int      $flags  json_encode flags (default: pretty-print)
     */
    public function generateJson(BinState $state, int $flags = JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE): string
    {
        $messages = array_map(
            static fn (BinMessage $m): array => $m->toArray(),
            $this->generate($state)
        );

        $json = json_encode($messages, $flags);
        if ($json === false) {
            throw new \RuntimeException('Failed to JSON-encode bin messages: ' . json_last_error_msg());
        }

        return $json;
    }
}
