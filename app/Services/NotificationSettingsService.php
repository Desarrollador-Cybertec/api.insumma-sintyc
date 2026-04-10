<?php

namespace App\Services;

use App\Models\MessageTemplate;
use App\Models\SystemSetting;

/**
 * Central service to resolve notification preferences from system_settings.
 *
 * Decides: is this notification enabled? Which channels? Which template? Who to copy?
 */
class NotificationSettingsService
{
    // ── Channel decisions ──────────────────────────

    public function shouldSendMail(): bool
    {
        return SystemSetting::getValue('emails_enabled', true);
    }

    public function shouldBroadcast(): bool
    {
        return SystemSetting::getValue('broadcast_enabled', false);
    }

    // ── Feature toggles ───────────────────────────

    public function isDailySummaryEnabled(): bool
    {
        return SystemSetting::getValue('daily_summary_enabled', true);
    }

    public function isOverdueDetectionEnabled(): bool
    {
        return SystemSetting::getValue('detect_overdue_enabled', true);
    }

    public function isRemindersEnabled(): bool
    {
        return SystemSetting::getValue('send_reminders_enabled', true);
    }

    public function isInactivityAlertEnabled(): bool
    {
        return SystemSetting::getValue('inactivity_alert_enabled', true);
    }

    // ── Parameters ────────────────────────────────

    public function getDueSoonDays(): int
    {
        return SystemSetting::getValue('alert_days_before_due', 3);
    }

    public function getInactivityDays(): int
    {
        return SystemSetting::getValue('inactivity_alert_days', 7);
    }

    // ── Copy rules ────────────────────────────────

    public function shouldCopyManager(): bool
    {
        return SystemSetting::getValue('copy_to_manager', true);
    }

    public function shouldCopySuperAdmin(): bool
    {
        return SystemSetting::getValue('copy_to_superadmin', false);
    }

    // ── Template resolution ───────────────────────

    public function getTemplate(string $slug): ?MessageTemplate
    {
        return MessageTemplate::where('slug', $slug)
            ->where('active', true)
            ->first();
    }

    /**
     * Render a template by replacing {placeholders} with actual values.
     */
    public function renderTemplate(MessageTemplate $template, array $variables): array
    {
        $subject = $template->subject;
        $body = $template->body;

        foreach ($variables as $key => $value) {
            $placeholder = '{' . $key . '}';
            $subject = str_replace($placeholder, (string) $value, $subject);
            $body = str_replace($placeholder, (string) $value, $body);
        }

        return [
            'subject' => $subject,
            'body' => $body,
        ];
    }

    // ── Mail builder ──────────────────────────────

    /**
     * Build a MailMessage using a DB template (or fallback) rendered through
     * the custom tape-notification Blade view (no "Hello!" / "Regards, Laravel").
     */
    public function buildMailMessage(
        string $templateSlug,
        array $variables,
        string $fallbackSubject,
        string $fallbackBody,
    ): \Illuminate\Notifications\Messages\MailMessage {
        $template = $this->getTemplate($templateSlug);

        if ($template) {
            $rendered = $this->renderTemplate($template, $variables);
            $viewData = array_merge(['body' => $rendered['body']], $variables);
            return (new \Illuminate\Notifications\Messages\MailMessage)
                ->subject($rendered['subject'])
                ->markdown('emails.tape-notification', $viewData);
        }

        $viewData = array_merge(['body' => $fallbackBody], $variables);
        return (new \Illuminate\Notifications\Messages\MailMessage)
            ->subject($fallbackSubject)
            ->markdown('emails.tape-notification', $viewData);
    }

    // ── Channels resolver ─────────────────────────

    /**
     * Resolve which channels a notification should be sent through.
     * Database is always included.
     */
    public function resolveChannels(): array
    {
        $channels = ['database'];

        if ($this->shouldSendMail()) {
            $channels[] = 'mail';
        }

        if ($this->shouldBroadcast()) {
            $channels[] = 'broadcast';
        }

        return $channels;
    }
}
