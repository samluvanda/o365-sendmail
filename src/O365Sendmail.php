<?php

namespace O365Sendmail;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\DataPart;

/**
 * O365Sendmail transport
 */
class O365Sendmail extends AbstractTransport
{
    /**
     * Create a new O365Sendmail instance.
     */
    public function __construct(
        private array $config,
        private ?Email $email = null,
        private array $message = [],
        private ?string $accessToken = null
    ) {
        parent::__construct();
    }

    /**
     * {@inheritDoc}
     */
    protected function doSend(SentMessage $message): void
    {
        $this->email = $message->getOriginalMessage();

        $this->prepareMessage();

        $data = [
            'message' => $this->message,
            'saveToSentItems' => false
        ];

        $this->requestAccessToken();
        Http::withToken($this->accessToken)
            ->withUrlParameters([
                'userPrincipalName' => $this->email->getFrom()[0]->getAddress()
            ])
            ->post('https://graph.microsoft.com/v1.0/users/{userPrincipalName}/sendMail', $data)
            ->throw();
    }

    /**
     * Get the string representation of the transport.
     *
     * @return string
     */
    public function __toString(): string
    {
        return 'o365-sendmail';
    }

    /**
     * Set the To: recipients for the message.
     *
     * @return array
     */
    private function to(): array
    {
        $addresses = $this->email->getTo();

        if (blank($addresses)) {
            return [];
        }

        return [
            'toRecipients' => Arr::map($addresses, function (Address $address) {
                return [
                    'emailAddress' => [
                        'address' => $address->getAddress(),
                        'name' => $address->getName(),
                    ]
                ];
            })
        ];
    }

    /**
     * Set the Cc: recipients for the message.
     *
     * @return array
     */
    private function cc(): array
    {
        $addresses = $this->email->getCc();

        if (blank($addresses)) {
            return [];
        }

        return [
            'ccRecipients' => Arr::map($addresses, function (Address $address) {
                return [
                    'emailAddress' => [
                        'address' => $address->getAddress(),
                        'name' => $address->getName(),
                    ]
                ];
            })
        ];
    }

    /**
     * Set the Bcc: recipients for the message.
     *
     * @return array
     */
    private function bcc(): array
    {
        $addresses = $this->email->getBcc();

        if (blank($addresses)) {
            return [];
        }

        return [
            'bccRecipients' => Arr::map($addresses, function (Address $address) {
                return [
                    'emailAddress' => [
                        'address' => $address->getAddress(),
                        'name' => $address->getName(),
                    ]
                ];
            })
        ];
    }

    /**
     * Set the email addresses to use when replying.
     *
     * @return array
     */
    private function replyTo(): array
    {
        $addresses = $this->email->getReplyTo();

        if (blank($addresses)) {
            return [];
        }

        return [
            'replyTo' => Arr::map($addresses, function (Address $address) {
                return [
                    'emailAddress' => [
                        'address' => $address->getAddress(),
                        'name' => $address->getName(),
                    ]
                ];
            })
        ];
    }

    /**
     * Set the subject of the message.
     *
     * @return array
     */
    private function subject(): array
    {
        $subject = $this->email->getSubject();

        if (is_null($subject)) {
            return [];
        }

        return [
            'subject' => $subject
        ];
    }

    /**
     * Set the body of the message.
     *
     * @return array
     */
    private function body(): array
    {
        $text = $this->email->getTextBody();
        $html = $this->email->getHtmlBody();

        if (is_null($text) && is_null($html)) {
            return [];
        }

        $content = $html ?? $text;
        $contentType = is_null($html) ? 'text' : 'html';

        return [
            'body' => compact('content', 'contentType')
        ];
    }

    /**
     * 	Set the fileAttachment attachments for the message.
     *
     * @return array
     */
    private function attachments(): array
    {
        $attachments = $this->email->getAttachments();

        if (blank($attachments)) {
            return [];
        }

        return [
            'attachments' => Arr::map($attachments, function (DataPart $attachment) {
                $isInline = $attachment->getDisposition() === 'inline';

                return array_merge(
                    [
                        'contentBytes' => $attachment->bodyToString(),
                        'name' => $attachment->getName(),
                        'contentType' => $attachment->getContentType(),
                        '@odata.type' => 'microsoft.graph.fileAttachment',
                    ],
                    compact('isInline'),
                    $isInline ? [
                        'contentId' => $attachment->getName()
                    ] : []
                );
            })
        ];
    }

    /**
     * Prepare the message before sending.
     *
     * @return void
     */
    private function prepareMessage(): void
    {
        $methods = [
            'to',
            'cc',
            'bcc',
            'replyTo',
            'attachments',
            'subject',
            'body'
        ];

        foreach ($methods as $method) {
            $this->message = array_merge($this->message, $this->{$method}());
        }
    }

    /**
     *  Request an access token from the Microsoft identity platform.
     *
     * @return void
     *
     * @throws \Illuminate\Http\Client\RequestException
     */
    private function requestAccessToken(): void
    {
        $config = array_merge(
            [
                'tenant' => '',
                'client_id' => '',
                'client_secret' => ''
            ],
            Arr::where($this->config, function (string $value, string $key) {
                return in_array($key, ['tenant', 'client_id', 'client_secret']);
            })
        );
        $this->accessToken = Http::asForm()
            ->withUrlParameters(Arr::only($config, ['tenant']))
            ->post(
                'https://login.microsoftonline.com/{tenant}/oauth2/v2.0/token',
                [
                    'client_id' => $config['client_id'],
                    'scope' => 'https://graph.microsoft.com/.default',
                    'client_secret' => $config['client_secret'],
                    'grant_type' => 'client_credentials',
                ]
            )
            ->throw()
            ->json('access_token');
    }
}
