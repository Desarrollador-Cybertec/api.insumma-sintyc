<?php

namespace App\Enums;

enum TaskStatusEnum: string
{
    case DRAFT = 'draft';
    case PENDING_ASSIGNMENT = 'pending_assignment';
    case PENDING = 'pending';
    case IN_PROGRESS = 'in_progress';
    case IN_REVIEW = 'in_review';
    case COMPLETED = 'completed';
    case REJECTED = 'rejected';
    case CANCELLED = 'cancelled';
    case OVERDUE = 'overdue';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Borrador',
            self::PENDING_ASSIGNMENT => 'Pendiente de asignación',
            self::PENDING => 'Pendiente',
            self::IN_PROGRESS => 'En progreso',
            self::IN_REVIEW => 'En revisión',
            self::COMPLETED => 'Completada',
            self::REJECTED => 'Rechazada',
            self::CANCELLED => 'Cancelada',
            self::OVERDUE => 'Vencida',
        };
    }

    /**
     * Valid transitions per status.
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::DRAFT => [self::PENDING_ASSIGNMENT, self::PENDING, self::CANCELLED],
            self::PENDING_ASSIGNMENT => [self::PENDING, self::CANCELLED],
            self::PENDING => [self::IN_PROGRESS, self::CANCELLED],
            self::IN_PROGRESS => [self::IN_REVIEW, self::COMPLETED, self::CANCELLED, self::OVERDUE],
            self::IN_REVIEW => [self::COMPLETED, self::REJECTED, self::CANCELLED],
            self::REJECTED => [self::IN_PROGRESS, self::CANCELLED],
            self::COMPLETED => [self::IN_PROGRESS],
            self::CANCELLED => [self::PENDING],
            self::OVERDUE => [self::IN_PROGRESS, self::CANCELLED],
        };
    }

    public function canTransitionTo(self $newStatus): bool
    {
        return in_array($newStatus, $this->allowedTransitions());
    }

    public function defaultProgress(): int
    {
        return match ($this) {
            self::DRAFT,
            self::PENDING_ASSIGNMENT,
            self::PENDING,
            self::CANCELLED   => 0,
            self::IN_PROGRESS,
            self::REJECTED,
            self::OVERDUE     => 25,
            self::IN_REVIEW   => 75,
            self::COMPLETED   => 100,
        };
    }
}
