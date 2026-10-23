<?php

namespace App\Services\Platform;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EmailDeliveryTelemetry
{
    public static function requested($event): void
    {
        try {
            $id = (string) Str::uuid();
            $recipient = request()->attributes->get('platform_email_recipient') ?? request()->user();
            $event->message->getHeaders()->addTextHeader('X-Platform-Delivery-ID', $id);
            DB::table('platform_email_deliveries')->insert(['id' => $id, 'provider' => config('mail.default'), 'tenant_id' => $recipient?->tenant_id, 'user_id' => $recipient?->id, 'product' => request()->attributes->get('platform_product'), 'status' => 'requested', 'created_at' => now(), 'updated_at' => now()]);
            request()->attributes->set('platform_email_delivery_id', $id);
        } catch (\Throwable) {
            \Log::warning('Email delivery metadata unavailable.');
        }
    }

    public static function accepted($event): void
    {
        try {
            $id = $event->message->getHeaders()->get('X-Platform-Delivery-ID')?->getBodyAsString();
            $messageId = $event->sent->getMessageId();
            if ($id) {
                DB::table('platform_email_deliveries')->where('id', $id)->update(['status' => 'accepted', 'message_hash' => hash_hmac('sha256', $messageId, (string) config('app.key')), 'updated_at' => now()]);
            }
        } catch (\Throwable) {
            \Log::warning('Email acceptance metadata unavailable.');
        }
    }

    public static function failed(): void
    {
        try {
            DB::table('platform_email_deliveries')->where('id', request()->attributes->get('platform_email_delivery_id'))->where('status', 'requested')->update(['status' => 'failed', 'updated_at' => now()]);
        } catch (\Throwable) {
        }
    }
}
