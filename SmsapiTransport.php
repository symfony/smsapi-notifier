<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Smsapi;

use Symfony\Component\Notifier\Exception\TransportException;
use Symfony\Component\Notifier\Exception\UnsupportedMessageTypeException;
use Symfony\Component\Notifier\Message\MessageInterface;
use Symfony\Component\Notifier\Message\SentMessage;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Transport\AbstractTransport;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Marcin Szepczynski <szepczynski@gmail.com>
 */
final class SmsapiTransport extends AbstractTransport
{
    protected const HOST = 'api.smsapi.pl';

    private $authToken;
    private $from;

    public function __construct(string $authToken, string $from, ?HttpClientInterface $client = null, ?EventDispatcherInterface $dispatcher = null)
    {
        $this->authToken = $authToken;
        $this->from = $from;

        parent::__construct($client, $dispatcher);
    }

    public function __toString(): string
    {
        return sprintf('smsapi://%s?from=%s', $this->getEndpoint(), $this->from);
    }

    public function supports(MessageInterface $message): bool
    {
        return $message instanceof SmsMessage;
    }

    protected function doSend(MessageInterface $message): SentMessage
    {
        if (!$message instanceof SmsMessage) {
            throw new UnsupportedMessageTypeException(__CLASS__, SmsMessage::class, $message);
        }

        $endpoint = sprintf('https://%s/sms.do', $this->getEndpoint());
        $response = $this->client->request('POST', $endpoint, [
            'auth_bearer' => $this->authToken,
            'body' => [
                'from' => $this->from,
                'to' => $message->getPhone(),
                'message' => $message->getSubject(),
                'format' => 'json',
                'encoding' => 'utf-8',
            ],
        ]);

        try {
            $statusCode = $response->getStatusCode();
        } catch (TransportExceptionInterface $e) {
            throw new TransportException('Could not reach the remote Smsapi server.', $response, 0, $e);
        }

        try {
            $content = $response->toArray(false);
        } catch (DecodingExceptionInterface $e) {
            throw new TransportException('Could not decode body to an array.', $response, 0, $e);
        }

        if (isset($content['error']) || 200 !== $statusCode) {
            throw new TransportException(sprintf('Unable to send the SMS: "%s".', $content['message'] ?? 'unknown error'), $response);
        }

        $sentMessage = new SentMessage($message, (string) $this);
        $sentMessage->setMessageId($content['list'][0]['id'] ?? '');

        return $sentMessage;
    }
}
