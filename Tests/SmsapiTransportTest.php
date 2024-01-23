<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Smsapi\Tests;

use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Notifier\Bridge\Smsapi\SmsapiTransport;
use Symfony\Component\Notifier\Exception\TransportException;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Test\TransportTestCase;
use Symfony\Component\Notifier\Tests\Transport\DummyMessage;
use Symfony\Component\Notifier\Transport\TransportInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class SmsapiTransportTest extends TransportTestCase
{
    /**
     * @return SmsapiTransport
     */
    public static function createTransport(?HttpClientInterface $client = null): TransportInterface
    {
        return (new SmsapiTransport('testToken', 'testFrom', $client ?? new MockHttpClient()))->setHost('test.host');
    }

    public static function toStringProvider(): iterable
    {
        yield ['smsapi://test.host?from=testFrom', self::createTransport()];
    }

    public static function supportedMessagesProvider(): iterable
    {
        yield [new SmsMessage('0611223344', 'Hello!')];
    }

    public static function unsupportedMessagesProvider(): iterable
    {
        yield [new ChatMessage('Hello!')];
        yield [new DummyMessage()];
    }

    public function createClient(int $statusCode, string $content): HttpClientInterface
    {
        return new MockHttpClient(new MockResponse($content, ['http_code' => $statusCode]));
    }

    public static function responseProvider(): iterable
    {
        $responses = [
            ['status' => 200, 'content' => '{"error":101,"message":"Authorization failed"}', 'errorMessage' => 'Unable to send the SMS: "Authorization failed".'],
            ['status' => 500, 'content' => '{}', 'errorMessage' => 'Unable to send the SMS: "unknown error".'],
            ['status' => 500, 'content' => '{"error":null,"message":"Unknown"}', 'errorMessage' => 'Unable to send the SMS: "Unknown".'],
            ['status' => 500, 'content' => '{"error":null,"message":null}', 'errorMessage' => 'Unable to send the SMS: "unknown error".'],
            ['status' => 500, 'content' => 'Internal error', 'errorMessage' => 'Could not decode body to an array.'],
            ['status' => 200, 'content' => 'Internal error', 'errorMessage' => 'Could not decode body to an array.'],
        ];

        foreach ($responses as $response) {
            yield [$response['status'], $response['content'], $response['errorMessage']];
        }
    }

    /**
     * @dataProvider responseProvider
     */
    public function testThrowExceptionWhenMessageWasNotSent(int $statusCode, string $content, string $errorMessage)
    {
        $client = $this->createClient($statusCode, $content);
        $transport = self::createTransport($client);
        $message = new SmsMessage('0611223344', 'Hello!');

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage($errorMessage);

        $transport->send($message);
    }
}
