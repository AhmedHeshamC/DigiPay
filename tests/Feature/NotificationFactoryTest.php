<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Services\Notifications\NotificationFactory;
use App\Services\Notifications\EmailSender;
use App\Services\Notifications\SmsSender;
use App\Services\Notifications\PushSender;
use App\Contracts\NotificationSender;
use App\Exceptions\UnsupportedNotificationChannel;

class NotificationFactoryTest extends TestCase
{
    public function test_factory_returns_email_sender_for_email_channel(): void
    {
        $factory = new NotificationFactory();
        $sender = $factory->make('email');

        $this->assertInstanceOf(EmailSender::class, $sender);
        $this->assertInstanceOf(NotificationSender::class, $sender);
    }

    public function test_factory_returns_sms_sender_for_sms_channel(): void
    {
        $factory = new NotificationFactory();
        $sender = $factory->make('sms');

        $this->assertInstanceOf(SmsSender::class, $sender);
        $this->assertInstanceOf(NotificationSender::class, $sender);
    }

    public function test_factory_returns_push_sender_for_push_channel(): void
    {
        $factory = new NotificationFactory();
        $sender = $factory->make('push');

        $this->assertInstanceOf(PushSender::class, $sender);
        $this->assertInstanceOf(NotificationSender::class, $sender);
    }

    public function test_factory_is_case_insensitive(): void
    {
        $factory = new NotificationFactory();

        $sender1 = $factory->make('EMAIL');
        $sender2 = $factory->make('Sms');
        $sender3 = $factory->make('PuSh');

        $this->assertInstanceOf(EmailSender::class, $sender1);
        $this->assertInstanceOf(SmsSender::class, $sender2);
        $this->assertInstanceOf(PushSender::class, $sender3);
    }

    public function test_factory_throws_exception_for_unknown_channel(): void
    {
        $factory = new NotificationFactory();

        $this->expectException(UnsupportedNotificationChannel::class);

        $factory->make('whatsapp');
    }

    public function test_factory_returns_new_instance_each_time(): void
    {
        $factory = new NotificationFactory();

        $sender1 = $factory->make('email');
        $sender2 = $factory->make('email');

        $this->assertNotSame($sender1, $sender2);
    }

    public function test_factory_can_be_used_statically(): void
    {
        $sender = NotificationFactory::create('email');

        $this->assertInstanceOf(EmailSender::class, $sender);
    }

    public function test_static_create_throws_exception_for_unknown_channel(): void
    {
        $this->expectException(UnsupportedNotificationChannel::class);

        NotificationFactory::create('telegram');
    }
}
