<?php
declare(strict_types=1);
namespace App\DTOs;

enum MessageType: string
{
    case USAGE_EVENT    = 'usage_event';
    case CAPACITY_50    = 'capacity_warning_50';
    case CAPACITY_75    = 'capacity_warning_75';
    case CAPACITY_90    = 'capacity_warning_90';
    case LID_OPEN_ALERT = 'lid_open_alert';
    case BATTERY_50     = 'battery_warning_50';
    case BATTERY_25     = 'battery_warning_25';
    case BATTERY_10     = 'battery_warning_10';
}