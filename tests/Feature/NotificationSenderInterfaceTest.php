<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Contracts\NotificationSender;
use App\DTO\NotificationPayload;
use App\Exceptions\UnsupportedNotificationChannel;

class NotificationSenderInterfaceTest extends TestCase
{
    public function test_interface_defines_send_method(): void
    {
        $reflection = new \ReflectionClass(NotificationSender::class);

        $this->assertTrue($reflection->isInterface());
        $this->assertTrue($reflection->hasMethod('send'));

        $sendMethod = $reflection->getMethod('send');
        $this->assertEquals('void', $sendMethod->getReturnType()->getName());
    }

    public function test_interface_send_method_accepts_payload(): void
    {
        $reflection = new \ReflectionClass(NotificationSender::class);
        $sendMethod = $reflection->getMethod('send');
        $parameters = $sendMethod->getParameters();

        $this->assertCount(1, $parameters);
        $this->assertEquals('payload', $parameters[0]->getName());
        $this->assertEquals(NotificationPayload::class, $parameters[0]->getType()->getName());
    }
}
