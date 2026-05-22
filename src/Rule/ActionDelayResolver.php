<?php

declare(strict_types=1);

namespace GrowthOrchestrator\Rule;

/**
 * Resolves action delay from params._delay at event match time, or falls back to delay_seconds.
 */
final class ActionDelayResolver
{
    /**
     * @param array<string, mixed> $action
     */
    public static function resolveSeconds(array $action, ?int $matchTimestamp = null): int
    {
        $matchTimestamp ??= time();
        $params = $action['params'] ?? null;

        if (is_array($params) && isset($params['_delay']) && is_array($params['_delay'])) {
            $schedule = $params['_delay'];
            if (! empty($schedule['active'])) {
                $computed = self::computeFromSchedule($schedule, $matchTimestamp);
                if ($computed > 0) {
                    return $computed;
                }
            }
        }

        return max(0, (int) ($action['delay_seconds'] ?? 0));
    }

    /**
     * @param array<string, mixed> $schedule
     */
    private static function computeFromSchedule(array $schedule, int $matchTimestamp): int
    {
        $ref = (new \DateTimeImmutable('@' . $matchTimestamp))->setTimezone(wp_timezone());

        $customTime = trim((string) ($schedule['customTime'] ?? ''));
        if ($customTime !== '') {
            $target = self::resolveTargetDateTime($schedule, $ref);
            $delta  = $target->getTimestamp() - $ref->getTimestamp();

            return max(0, $delta);
        }

        $total = 0;
        $dateMode = (string) ($schedule['dateMode'] ?? 'today');

        if ($dateMode === 'days') {
            $total += max(0, (int) ($schedule['days'] ?? 0)) * 86400;
        } elseif ($dateMode === 'custom') {
            $iso = trim((string) ($schedule['customDate'] ?? ''));
            if ($iso !== '') {
                $targetDay = self::startOfLocalDay(self::parseLocalDate($iso, $ref));
                $nowDay    = self::startOfLocalDay($ref);
                $total    += max(0, $targetDay->getTimestamp() - $nowDay->getTimestamp());
            }
        }

        $timeMode = (string) ($schedule['timeMode'] ?? 'none');
        $amount   = max(0, (int) ($schedule['relativeAmount'] ?? 0));

        if ($timeMode === 'seconds') {
            $total += $amount;
        } elseif ($timeMode === 'minutes') {
            $total += $amount * 60;
        } elseif ($timeMode === 'hours') {
            $total += $amount * 3600;
        }

        return $total;
    }

    /**
     * @param array<string, mixed> $schedule
     */
    private static function resolveTargetDateTime(array $schedule, \DateTimeImmutable $ref): \DateTimeImmutable
    {
        $dateMode = (string) ($schedule['dateMode'] ?? 'today');

        if ($dateMode === 'custom') {
            $iso = trim((string) ($schedule['customDate'] ?? ''));
            $target = $iso !== '' ? self::parseLocalDate($iso, $ref) : self::startOfLocalDay($ref);
        } elseif ($dateMode === 'days') {
            $days   = max(0, (int) ($schedule['days'] ?? 0));
            $target = self::startOfLocalDay($ref)->modify('+' . $days . ' days');
        } else {
            $target = self::startOfLocalDay($ref);
        }

        $customTime = trim((string) ($schedule['customTime'] ?? ''));
        if (preg_match('/^(\d{1,2}):(\d{2})$/', $customTime, $m) === 1) {
            $target = $target->setTime((int) $m[1], (int) $m[2], 0);
        }

        if ($target->getTimestamp() <= $ref->getTimestamp()) {
            $target = $target->modify('+1 day');
        }

        return $target;
    }

    private static function parseLocalDate(string $iso, \DateTimeImmutable $fallback): \DateTimeImmutable
    {
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', trim($iso), $m) !== 1) {
            return $fallback;
        }

        $tz = wp_timezone();

        return new \DateTimeImmutable(
            sprintf('%04d-%02d-%02d 00:00:00', (int) $m[1], (int) $m[2], (int) $m[3]),
            $tz
        );
    }

    private static function startOfLocalDay(\DateTimeImmutable $dt): \DateTimeImmutable
    {
        return $dt->setTime(0, 0, 0);
    }
}
