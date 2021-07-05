<?php
/**
 * Lets Ads transport
 *
 * @author Maxim Petrovich <m.petrovich@artox.com>
 */
namespace  ArtoxLab\Component\Notifier\Bridge\LetsAds;

use Symfony\Component\Notifier\Exception\LogicException;
use Symfony\Component\Notifier\Exception\TransportException;
use Symfony\Component\Notifier\Message\MessageInterface;
use Symfony\Component\Notifier\Message\SentMessage;
use Symfony\Component\Notifier\Transport\AbstractTransport;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class LetsAdsTransport extends AbstractTransport
{

    public const HOST = 'letsads.com';

    /**
     * Password
     *
     * @var string
     */
    private $login;

    /**
     * Password
     *
     * @var string
     */
    private $password;

    /**
     * Sender name
     *
     * @var string
     */
    private $from;

    /**
     * SmsLineTransport constructor.
     *
     * @param string                        $login      Login
     * @param string                        $password   Password
     * @param string                        $from       Sender name
     * @param HttpClientInterface|null      $client     Http client
     * @param EventDispatcherInterface|null $dispatcher Event dispatcher
     */
    public function __construct(
        string $login,
        string $password,
        string $from,
        HttpClientInterface $client = null,
        EventDispatcherInterface $dispatcher = null
    ) {
        $this->login    = $login;
        $this->password = $password;
        $this->from     = $from;

        parent::__construct($client, $dispatcher);
    }

    /**
     * Send message
     *
     * @param MessageInterface $message Message
     *
     * @return SentMessage
     */
    protected function doSend(MessageInterface $message): SentMessage
    {
        if (false === $message instanceof SmsMessage) {
            throw new LogicException(sprintf('The "%s" transport only supports instances of "%s" (instance of "%s" given).', __CLASS__, SmsMessage::class, get_debug_type($message)));
        }

        $endpoint = sprintf('https://%s/api', $this->getEndpoint());
        $response = $this->client->request(
            'POST',
            $endpoint,
            [
                'body' => $this->buildRequestBody($message),
            ]
        );

        $xmlResponse = simplexml_load_string($response->getContent());

        if ('Error' === (string) $xmlResponse->name) {
            throw new TransportException(
                'Unable to send the SMS: ' . $xmlResponse->description,
                $response
            );
        }

        return new SentMessage($message, (string) $this);
    }

    /**
     * Supports
     *
     * @param MessageInterface $message Message
     *
     * @return bool
     */
    public function supports(MessageInterface $message): bool
    {
        return $message instanceof SmsMessage;
    }

    /**
     * To string
     *
     * @return string
     */
    public function __toString(): string
    {
        return sprintf('letsads://%s?from=%s', $this->getEndpoint(), $this->from);
    }

    /**
     * Build XML request body
     *
     * @param SmsMessage $message Sms message
     *
     * @return string
     */
    private function buildRequestBody(SmsMessage $message): string
    {
        $auth      = '<login>' . $this->login . '</login><password>' . $this->password . '</password>';
        $recipient = '<recipient>' . $message->getPhone() . '</recipient>';

        $body  = '<?xml version="1.0" encoding="UTF-8"?>';
        $body .= '<request>';
        $body .= '<auth>' . $auth . '</auth>';
        $body .= sprintf(
            '<message><from>%s</from><text>%s</text>%s</message>',
            $this->from,
            $message->getSubject(),
            $recipient
        );
        $body .= '</request>';

        return $body;
    }

}
