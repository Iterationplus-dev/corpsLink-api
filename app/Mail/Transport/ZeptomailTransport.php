<?php

namespace App\Mail\Transport;

use Illuminate\Support\Facades\Http;
use RuntimeException;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\MessageConverter;

/**
 * Sends mail through Zoho ZeptoMail's transactional email HTTP API.
 *
 * @see https://www.zoho.com/zeptomail/help/api/email-sending.html
 */
class ZeptomailTransport extends AbstractTransport
{
    public function __construct(
        protected string $url,
        protected string $token,
    ) {
        parent::__construct();
    }

    protected function doSend(SentMessage $message): void
    {
        $email = MessageConverter::toEmail($message->getOriginalMessage());

        $response = Http::withHeaders([
            'Authorization' => $this->authorizationHeader(),
            'Accept' => 'application/json',
        ])->post($this->url, $this->payload($email));

        if ($response->failed()) {
            throw new TransportException(
                'ZeptoMail transport failed: '.$response->body(),
                $response->status(),
            );
        }
    }

    /**
     * Zoho's dashboard issues the token as the full header value already
     * prefixed with "Zoho-enczapikey " — accept it either with or without
     * that prefix so a raw key works too.
     */
    protected function authorizationHeader(): string
    {
        return str_starts_with($this->token, 'Zoho-enczapikey ')
            ? $this->token
            : "Zoho-enczapikey {$this->token}";
    }

    /**
     * @return array<string, mixed>
     */
    protected function payload(Email $email): array
    {
        $from = $email->getFrom()[0] ?? throw new RuntimeException('ZeptoMail: message has no "from" address.');

        return array_filter([
            'from' => $this->addressPayload($from),
            'to' => $this->recipients($email->getTo()),
            'cc' => $this->recipients($email->getCc()) ?: null,
            'bcc' => $this->recipients($email->getBcc()) ?: null,
            'reply_to' => $this->recipients($email->getReplyTo()) ?: null,
            'subject' => $email->getSubject(),
            'htmlbody' => $email->getHtmlBody(),
            'textbody' => $email->getTextBody(),
        ]);
    }

    /**
     * @param  array<int, Address>  $addresses
     * @return array<int, array<string, mixed>>
     */
    protected function recipients(array $addresses): array
    {
        return array_map(
            fn (Address $address) => ['email_address' => $this->addressPayload($address)],
            $addresses,
        );
    }

    /**
     * @return array<string, string>
     */
    protected function addressPayload(Address $address): array
    {
        return array_filter([
            'address' => $address->getAddress(),
            'name' => $address->getName(),
        ]);
    }

    public function __toString(): string
    {
        return 'zeptomail';
    }
}
