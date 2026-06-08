<?php
// ============================================================
// GmailService — Kirim email via Gmail API
// ============================================================

require_once __DIR__ . '/GoogleClientService.php';

class GmailService
{
    private Google\Service\Gmail $gmail;

    public function __construct()
    {
        $googleClient = new GoogleClientService();
        $this->gmail  = new Google\Service\Gmail($googleClient->getClient());
    }

    /**
     * Kirim email HTML ke satu penerima
     *
     * @param string $to       Alamat email penerima
     * @param string $subject  Subjek email
     * @param string $htmlBody Isi email dalam format HTML
     * @throws Google\Service\Exception
     */
    public function send(string $to, string $subject, string $htmlBody): void
    {
        $raw = $this->buildRawMessage($to, $subject, $htmlBody);

        $message = new Google\Service\Gmail\Message();
        $message->setRaw($raw);

        $this->gmail->users_messages->send('me', $message);
    }

    /**
     * Build raw RFC 2822 message encode base64url
     */
    private function buildRawMessage(string $to, string $subject, string $body): string
    {
        $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';

        $parts = [
            "To: {$to}",
            "Subject: {$encodedSubject}",
            "MIME-Version: 1.0",
            "Content-Type: text/html; charset=UTF-8",
            "Content-Transfer-Encoding: base64",
            "",
            chunk_split(base64_encode($body), 76, "\r\n"),
        ];

        $raw = implode("\r\n", $parts);
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }
}
