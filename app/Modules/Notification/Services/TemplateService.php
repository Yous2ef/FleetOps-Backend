<?php

/**
 * @file: TemplateService.php
 * @description: خدمة قوالب رسائل الإشعارات (محتوى ثنائي اللغة) - Notification Service (NF-04)
 * @module: Notification
 * @author: Team Leader (Khalid)
 */

namespace App\Modules\Notification\Services;

class TemplateService
{
    /**
     * Bilingual notification templates keyed by event_type.
     * Variables use the :variable_name syntax (replaced at build time).
     */
    protected array $templates = [
        'proximity_alert' => [
            'ar' => 'السائق على بعد 500 متر وسيصل قريباً لتسليم طلبك.',
            'en' => 'Your driver is 500m away and will arrive shortly to deliver your order.',
        ],
        'delay_alert' => [
            'ar' => 'نعتذر، تأخر في التسليم. الوقت المتوقع للوصول: :eta',
            'en' => 'We apologize for the delay. Expected delivery time: :eta',
        ],
        'status_update_in_transit' => [
            'ar' => 'طلبك رقم :order_id في الطريق إليك الآن.',
            'en' => 'Your order #:order_id is now on its way.',
        ],
        'status_update_delivered' => [
            'ar' => 'تم تسليم طلبك رقم :order_id بنجاح.',
            'en' => 'Your order #:order_id has been delivered successfully.',
        ],
        'status_update_returned' => [
            'ar' => 'لم نتمكن من تسليم طلبك رقم :order_id وسيتم إعادة المحاولة.',
            'en' => 'We could not deliver order #:order_id and will retry.',
        ],
        'status_update' => [
            'ar' => 'تم تحديث حالة الطلب رقم :order_id.',
            'en' => 'Order #:order_id status has been updated.',
        ],
        'maintenance_alert_odometer' => [
            'ar' => 'المركبة :plate_number وصلت لعداد الصيانة (:odometer كم). يُرجى جدولة صيانة.',
            'en' => 'Vehicle :plate_number has reached maintenance odometer (:odometer km). Please schedule maintenance.',
        ],
        'maintenance_alert_inspection' => [
            'ar' => 'تنبيه: انتهاء التأمين أو الفحص السنوي للمركبة :plate_number خلال 30 يوماً.',
            'en' => 'Alert: Insurance or annual inspection for vehicle :plate_number expires within 30 days.',
        ],
        'maintenance_alert' => [
            'ar' => 'تنبيه صيانة للمركبة :plate_number — :description',
            'en' => 'Maintenance alert for vehicle :plate_number — :description',
        ],
        'low_stock_alert' => [
            'ar' => 'مخزون :part_name وصل للحد الأدنى (:quantity :unit). يُرجى إعادة الطلب.',
            'en' => 'Stock for :part_name has reached minimum (:quantity :unit). Please reorder.',
        ],
        'incident_alert' => [
            'ar' => 'تم الإبلاغ عن حادث للمركبة :plate_number. الموقع: :location',
            'en' => 'An incident has been reported for vehicle :plate_number. Location: :location',
        ],
        'route_started' => [
            'ar' => 'تم بدء المسار :route_id. السائق :driver_name في الطريق.',
            'en' => 'Route :route_id has started. Driver :driver_name is on the way.',
        ],
        'shift_transfer' => [
            'ar' => 'تم نقل مسارك إلى السائق :driver_name. يُرجى المراجعة.',
            'en' => 'Your route has been transferred to driver :driver_name. Please review.',
        ],
    ];

    /**
     * Build a notification message from its template with variable substitution.
     *
     * @param string $eventType  The event type key (must match $templates keys)
     * @param string $language   'ar' or 'en' — falls back to 'ar' if language missing
     * @param array  $variables  Key-value pairs like [':eta' => '15:30', ':order_id' => '1001']
     * @return string            The final rendered message string
     *
     * @example
     *   $msg = $templateService->buildMessage('delay_alert', 'en', [':eta' => '16:45']);
     *   // → "We apologize for the delay. Expected delivery time: 16:45"
     */
    public function buildMessage(string $eventType, string $language = 'ar', array $variables = []): string
    {
        // Resolve template — fall back to 'ar' if the requested language is missing
        $template = $this->templates[$eventType][$language]
            ?? $this->templates[$eventType]['ar']
            ?? "Notification: {$eventType}";

        // Replace all :variable_name placeholders
        if (!empty($variables)) {
            $template = str_replace(
                array_keys($variables),
                array_values($variables),
                $template
            );
        }

        return $template;
    }

    /**
     * Get the raw template string for a given event type and language.
     *
     * @param string $eventType
     * @param string $language
     * @return string|null  null if the event type is not defined
     */
    public function getTemplate(string $eventType, string $language = 'ar'): ?string
    {
        return $this->templates[$eventType][$language] ?? null;
    }

    /**
     * Check whether a template exists for the given event type.
     *
     * @param string $eventType
     * @return bool
     */
    public function hasTemplate(string $eventType): bool
    {
        return isset($this->templates[$eventType]);
    }
}
