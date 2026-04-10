<?php

// @formatter:off
// phpcs:ignoreFile
/**
 * A helper file for your Eloquent Models
 * Copy the phpDocs from this file to the correct Model,
 * And remove them from this file, to prevent double declarations.
 *
 * @author Barry vd. Heuvel <barryvdh@gmail.com>
 */


namespace App\Models{
/**
 * @property int $id
 * @property int|null $user_id
 * @property string $module
 * @property string $action
 * @property string|null $subject_type
 * @property int|null $subject_id
 * @property string|null $description
 * @property array<array-key, mixed>|null $metadata
 * @property \Illuminate\Support\Carbon $created_at
 * @property-read \App\Models\User|null $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ActivityLog newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ActivityLog newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ActivityLog query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ActivityLog whereAction($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ActivityLog whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ActivityLog whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ActivityLog whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ActivityLog whereMetadata($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ActivityLog whereModule($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ActivityLog whereSubjectId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ActivityLog whereSubjectType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ActivityLog whereUserId($value)
 */
	class ActivityLog extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property int|null $manager_user_id
 * @property bool $active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $process_identifier
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\User> $activeMembers
 * @property-read int|null $active_members_count
 * @property-read \App\Models\User|null $manager
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\User> $members
 * @property-read int|null $members_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Task> $tasks
 * @property-read int|null $tasks_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Area newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Area newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Area query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Area whereActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Area whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Area whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Area whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Area whereManagerUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Area whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Area whereProcessIdentifier($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Area whereUpdatedAt($value)
 */
	class Area extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $area_id
 * @property int $user_id
 * @property int|null $assigned_by
 * @property int|null $claimed_by
 * @property \Illuminate\Support\Carbon|null $joined_at
 * @property \Illuminate\Support\Carbon|null $left_at
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Area $area
 * @property-read \App\Models\User|null $assignedByUser
 * @property-read \App\Models\User|null $claimedByUser
 * @property-read \App\Models\User $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AreaMember newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AreaMember newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AreaMember query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AreaMember whereAreaId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AreaMember whereAssignedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AreaMember whereClaimedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AreaMember whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AreaMember whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AreaMember whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AreaMember whereJoinedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AreaMember whereLeftAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AreaMember whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AreaMember whereUserId($value)
 */
	class AreaMember extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $title
 * @property \Illuminate\Support\Carbon $meeting_date
 * @property int|null $area_id
 * @property \App\Enums\MeetingClassificationEnum|null $classification
 * @property string|null $notes
 * @property int $created_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Area|null $area
 * @property-read \App\Models\User $creator
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Task> $tasks
 * @property-read int|null $tasks_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Meeting newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Meeting newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Meeting query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Meeting whereAreaId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Meeting whereClassification($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Meeting whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Meeting whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Meeting whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Meeting whereMeetingDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Meeting whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Meeting whereTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Meeting whereUpdatedAt($value)
 */
	class Meeting extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $slug
 * @property string $name
 * @property string $subject
 * @property string $body
 * @property bool $active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MessageTemplate newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MessageTemplate newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MessageTemplate query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MessageTemplate whereActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MessageTemplate whereBody($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MessageTemplate whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MessageTemplate whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MessageTemplate whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MessageTemplate whereSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MessageTemplate whereSubject($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MessageTemplate whereUpdatedAt($value)
 */
	class MessageTemplate extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\User> $users
 * @property-read int|null $users_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role whereSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role whereUpdatedAt($value)
 */
	class Role extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $key
 * @property string|null $value
 * @property string $type
 * @property string $group
 * @property string|null $description
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SystemSetting newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SystemSetting newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SystemSetting query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SystemSetting whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SystemSetting whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SystemSetting whereGroup($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SystemSetting whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SystemSetting whereKey($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SystemSetting whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SystemSetting whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SystemSetting whereValue($value)
 */
	class SystemSetting extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $title
 * @property string|null $description
 * @property TaskStatusEnum $status
 * @property TaskPriorityEnum $priority
 * @property Carbon|null $start_date
 * @property Carbon|null $due_date
 * @property Carbon|null $completed_at
 * @property Carbon|null $completion_notified_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property int|null $created_by
 * @property int|null $assigned_by
 * @property int|null $assigned_to_user_id
 * @property int|null $assigned_to_area_id
 * @property int|null $delegated_by
 * @property int|null $current_responsible_user_id
 * @property int|null $area_id
 * @property int|null $closed_by
 * @property int|null $cancelled_by
 * @property int|null $meeting_id
 * @property int $progress_percent
 * @property bool $requires_attachment
 * @property bool $requires_completion_comment
 * @property bool $requires_manager_approval
 * @property bool $requires_completion_notification
 * @property bool $requires_due_date
 * @property bool $requires_progress_report
 * @property bool $notify_on_due
 * @property bool $notify_on_overdue
 * @property bool $notify_on_completion
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \App\Models\Area|null $area
 * @property-read \App\Models\Area|null $assignedArea
 * @property-read \App\Models\User|null $assignedUser
 * @property-read \App\Models\User|null $assigner
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\TaskAttachment> $attachments
 * @property-read int|null $attachments_count
 * @property-read \App\Models\User|null $cancelledByUser
 * @property-read \App\Models\User|null $closedByUser
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\TaskComment> $comments
 * @property-read int|null $comments_count
 * @property-read \App\Models\User $creator
 * @property-read \App\Models\User|null $currentResponsible
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\TaskDelegation> $delegations
 * @property-read int|null $delegations_count
 * @property-read \App\Models\User|null $delegator
 * @property-read \App\Models\Meeting|null $meeting
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\TaskNotification> $notifications
 * @property-read int|null $notifications_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\TaskStatusHistory> $statusHistory
 * @property-read int|null $status_history_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\TaskUpdate> $updates
 * @property-read int|null $updates_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Task newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Task newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Task onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Task query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Task whereAreaId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Task whereAssignedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Task whereAssignedToAreaId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Task whereAssignedToUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Task whereCancelledBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Task whereClosedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Task whereCompletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Task whereCompletionNotifiedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Task whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Task whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Task whereCurrentResponsibleUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Task whereDelegatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Task whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Task whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Task whereDueDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Task whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Task whereMeetingId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Task whereNotifyOnCompletion($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Task whereNotifyOnDue($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Task whereNotifyOnOverdue($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Task wherePriority($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Task whereProgressPercent($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Task whereRequiresAttachment($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Task whereRequiresCompletionComment($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Task whereRequiresCompletionNotification($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Task whereRequiresDueDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Task whereRequiresManagerApproval($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Task whereRequiresProgressReport($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Task whereStartDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Task whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Task whereTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Task whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Task withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Task withoutTrashed()
 */
	class Task extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $task_id
 * @property int $uploaded_by
 * @property string $file_name
 * @property string $file_path
 * @property string $mime_type
 * @property int $file_size
 * @property \App\Enums\AttachmentTypeEnum $attachment_type
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Task $task
 * @property-read \App\Models\User $uploader
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskAttachment newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskAttachment newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskAttachment query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskAttachment whereAttachmentType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskAttachment whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskAttachment whereFileName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskAttachment whereFilePath($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskAttachment whereFileSize($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskAttachment whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskAttachment whereMimeType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskAttachment whereTaskId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskAttachment whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskAttachment whereUploadedBy($value)
 */
	class TaskAttachment extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $task_id
 * @property int $user_id
 * @property string $comment
 * @property \App\Enums\CommentTypeEnum $type
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Task $task
 * @property-read \App\Models\User $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskComment newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskComment newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskComment query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskComment whereComment($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskComment whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskComment whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskComment whereTaskId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskComment whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskComment whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskComment whereUserId($value)
 */
	class TaskComment extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $task_id
 * @property int $from_user_id
 * @property int $to_user_id
 * @property int|null $from_area_id
 * @property int|null $to_area_id
 * @property string|null $note
 * @property \Illuminate\Support\Carbon $delegated_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Area|null $fromArea
 * @property-read \App\Models\User $fromUser
 * @property-read \App\Models\Task $task
 * @property-read \App\Models\Area|null $toArea
 * @property-read \App\Models\User $toUser
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskDelegation newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskDelegation newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskDelegation query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskDelegation whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskDelegation whereDelegatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskDelegation whereFromAreaId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskDelegation whereFromUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskDelegation whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskDelegation whereNote($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskDelegation whereTaskId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskDelegation whereToAreaId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskDelegation whereToUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskDelegation whereUpdatedAt($value)
 */
	class TaskDelegation extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $task_id
 * @property int $triggered_by
 * @property int $notify_to_user_id
 * @property \App\Enums\NotificationChannelEnum $channel
 * @property string $message
 * @property \Illuminate\Support\Carbon|null $sent_at
 * @property string $status
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\User $notifyToUser
 * @property-read \App\Models\Task $task
 * @property-read \App\Models\User $triggeredByUser
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskNotification newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskNotification newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskNotification query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskNotification whereChannel($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskNotification whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskNotification whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskNotification whereMessage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskNotification whereNotifyToUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskNotification whereSentAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskNotification whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskNotification whereTaskId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskNotification whereTriggeredBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskNotification whereUpdatedAt($value)
 */
	class TaskNotification extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $task_id
 * @property int $changed_by
 * @property \App\Enums\TaskStatusEnum|null $from_status
 * @property \App\Enums\TaskStatusEnum $to_status
 * @property string|null $note
 * @property \Illuminate\Support\Carbon $created_at
 * @property-read \App\Models\User $changedByUser
 * @property-read \App\Models\Task $task
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskStatusHistory newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskStatusHistory newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskStatusHistory query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskStatusHistory whereChangedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskStatusHistory whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskStatusHistory whereFromStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskStatusHistory whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskStatusHistory whereNote($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskStatusHistory whereTaskId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskStatusHistory whereToStatus($value)
 */
	class TaskStatusHistory extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $task_id
 * @property int $user_id
 * @property \App\Enums\UpdateTypeEnum $update_type
 * @property string|null $comment
 * @property int|null $progress_percent
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Task $task
 * @property-read \App\Models\User $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskUpdate newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskUpdate newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskUpdate query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskUpdate whereComment($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskUpdate whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskUpdate whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskUpdate whereProgressPercent($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskUpdate whereTaskId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskUpdate whereUpdateType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskUpdate whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskUpdate whereUserId($value)
 */
	class TaskUpdate extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property \Illuminate\Support\Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $remember_token
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property int|null $role_id
 * @property bool $active
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Area> $activeAreas
 * @property-read int|null $active_areas_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Area> $areas
 * @property-read int|null $areas_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Task> $assignedTasks
 * @property-read int|null $assigned_tasks_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Task> $createdTasks
 * @property-read int|null $created_tasks_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Area> $managedAreas
 * @property-read int|null $managed_areas_count
 * @property-read \Illuminate\Notifications\DatabaseNotificationCollection<int, \Illuminate\Notifications\DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Task> $responsibleTasks
 * @property-read int|null $responsible_tasks_count
 * @property-read \App\Models\Role|null $role
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\TaskComment> $taskComments
 * @property-read int|null $task_comments_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Laravel\Sanctum\PersonalAccessToken> $tokens
 * @property-read int|null $tokens_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\TaskAttachment> $uploadedAttachments
 * @property-read int|null $uploaded_attachments_count
 * @method static \Database\Factories\UserFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmailVerifiedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User wherePassword($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereRememberToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereRoleId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereUpdatedAt($value)
 */
	class User extends \Eloquent {}
}

