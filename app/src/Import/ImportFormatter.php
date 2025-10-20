<?php

declare(strict_types=1);

namespace Multilotka\Import;

use DateTimeInterface;

final class ImportFormatter
{
    public static function formatJob(ImportJob $job): array
    {
        $status = $job->status();
        $mode = $job->mode();

        return [
            'id' => $job->id(),
            'status' => $status->value,
            'status_label' => self::statusLabel($status),
            'status_badge_class' => self::statusBadgeClass($status),
            'mode' => $mode->value,
            'mode_label' => self::modeLabel($mode),
            'progress_percent' => $job->progressPercent(),
            'current_stage' => $job->currentStage(),
            'started_at_iso' => $job->startedAt()?->format(DateTimeInterface::ATOM),
            'started_at_human' => $job->startedAt()?->format('d.m.Y H:i'),
            'finished_at_iso' => $job->finishedAt()?->format(DateTimeInterface::ATOM),
            'finished_at_human' => $job->finishedAt()?->format('d.m.Y H:i'),
            'error_message' => $job->errorMessage(),
        ];
    }

    public static function formatEvent(ImportJobEvent $event): array
    {
        $payload = $event->payload() ?? [];

        return [
            'type' => $event->type()->value,
            'type_label' => self::eventTypeLabel($event->type()),
            'message' => self::eventMessage($event->type(), $payload),
            'payload' => $payload,
            'created_at_iso' => $event->createdAt()->format(DateTimeInterface::ATOM),
            'created_at_human' => $event->createdAt()->format('d.m H:i:s'),
        ];
    }

    private static function statusLabel(ImportStatus $status): string
    {
        return match ($status) {
            ImportStatus::QUEUED => 'Zakolejkowany',
            ImportStatus::RUNNING => 'W toku',
            ImportStatus::SUCCEEDED => 'Zakończony',
            ImportStatus::FAILED => 'Błąd',
            ImportStatus::CANCELLED => 'Anulowany',
        };
    }

    private static function statusBadgeClass(ImportStatus $status): string
    {
        return match ($status) {
            ImportStatus::SUCCEEDED => 'text-lime-300',
            ImportStatus::FAILED => 'text-rose-300',
            ImportStatus::CANCELLED => 'text-slate-400',
            default => 'text-amber-300',
        };
    }

    private static function modeLabel(ImportMode $mode): string
    {
        return match ($mode) {
            ImportMode::FULL => 'Pełny',
            ImportMode::INCREMENTAL => 'Przyrostowy',
        };
    }

    private static function eventTypeLabel(ImportJobEventType $type): string
    {
        return match ($type) {
            ImportJobEventType::QUEUED => 'Zakolejkowano',
            ImportJobEventType::CONNECTION_ESTABLISHED => 'Rozpoczęto',
            ImportJobEventType::BATCH_INSERTED => 'Zapisano partię',
            ImportJobEventType::PROGRESS_UPDATE => 'Postęp',
            ImportJobEventType::COMPLETED => 'Zakończono',
            ImportJobEventType::FAILED => 'Błąd',
            ImportJobEventType::CANCELLED => 'Anulowano',
        };
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function eventMessage(ImportJobEventType $type, array $payload): string
    {
        if (isset($payload['message']) && is_string($payload['message'])) {
            return $payload['message'];
        }

        return match ($type) {
            ImportJobEventType::PROGRESS_UPDATE => self::progressMessage($payload),
            ImportJobEventType::BATCH_INSERTED => self::batchMessage($payload),
            default => '',
        };
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function progressMessage(array $payload): string
    {
        $percent = isset($payload['percent']) ? (int) $payload['percent'] : null;
        $processed = isset($payload['processed_draws']) ? (int) $payload['processed_draws'] : null;

        if ($percent !== null && $processed !== null) {
            return sprintf('Postęp: %d%% (losowań: %d).', $percent, $processed);
        }

        if ($percent !== null) {
            return sprintf('Postęp: %d%%.', $percent);
        }

        return '';
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function batchMessage(array $payload): string
    {
        $batch = isset($payload['batch']) ? (int) $payload['batch'] : null;
        $size = isset($payload['size']) ? (int) $payload['size'] : null;

        if ($batch !== null && $size !== null) {
            return sprintf('Zapisano partię #%d (rekordów: %d).', $batch, $size);
        }

        return '';
    }
}
