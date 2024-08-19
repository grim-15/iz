<?php

namespace App\Service;

use PhpImap\Mailbox;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use App\Entity\Contact;
use DateTime;
use DateTimeZone;
use Exception;

class ImapService
{
    private $mailbox;
    private $mailer;
    private $cache;
    private $entityManager;

    public function __construct(string $imapDsn, MailerInterface $mailer, CacheInterface $cache, EntityManagerInterface $entityManager)
    {
        $urlComponents = parse_url($imapDsn);

        $host = $urlComponents['host'];
        $port = $urlComponents['port'];
        $user = $urlComponents['user'];
        $pass = urldecode($urlComponents['pass']);

        if (!$host || !$port || !$user || !$pass) {
            throw new \Exception('Le DSN IMAP est mal formaté ou incomplet.');
        }

        $this->mailbox = new Mailbox(
            '{' . $host . ':' . $port . '/imap/ssl}INBOX',
            $user,
            $pass,
            __DIR__
        );

        $this->mailer = $mailer;
        $this->cache = $cache;
        $this->entityManager = $entityManager;
    }

    //pagination
    public function getInboxMessages(int $limit = 20, int $offset = 0)
    {
        $allEmailIds = $this->cache->get('all_email_ids', function (ItemInterface $item) {
            $item->expiresAfter(3600);
            return $this->fetchEmailIds();
        });

        $pagedEmailIds = array_slice($allEmailIds, $offset, $limit);
        return $this->fetchEmailsByIds($pagedEmailIds);
    }

    private function fetchEmailIds(): array
    {
        try {
            $mailsIds = $this->mailbox->searchMailbox('ALL');
            if (empty($mailsIds)) {
                return [];
            }

            return array_reverse($mailsIds); 

        } catch (Exception $ex) {
            throw new \Exception('La connexion IMAP a échoué : ' . $ex->getMessage());
        }
    }

    private function fetchEmailsByIds(array $mailsIds): array
    {
        try {
            $contacts = $this->entityManager->getRepository(Contact::class)->findAll();
            $contactEmails = array_map(fn($contact) => strtolower(trim($contact->getEmail())), $contacts);

            $emails = [];
            foreach ($mailsIds as $mailId) {
                try {
                    $mail = $this->mailbox->getMail($mailId, false);

                    if (in_array(strtolower(trim($mail->fromAddress)), $contactEmails)) {
                        $emails[] = [
                            'id' => $mailId,
                            'fromAddress' => $mail->fromAddress,
                            'subject' => $mail->subject,
                            'date' => $this->normalizeDate($mail->date),
                        ];
                    }
                } catch (Exception $e) {
                    error_log('Erreur lors de la récupération de l\'email avec ID ' . $mailId . ': ' . $e->getMessage());
                }
            }

            usort($emails, fn($a, $b) => strtotime($b['date']) - strtotime($a['date']));

            return $emails;

        } catch (Exception $ex) {
            throw new \Exception('La connexion IMAP a échoué : ' . $ex->getMessage());
        }
    }

    public function sendEmail(string $to, string $subject, string $body)
    {
        $email = (new Email())
            ->from('tassaditgrim1@gmail.com')
            ->to($to)
            ->subject($subject)
            ->text($body);

        $this->mailer->send($email);
    }

    public function deleteEmail(int $id): void
    {
        try {
            $this->mailbox->deleteMail($id);
            $this->mailbox->expungeDeletedMails();
            $this->clearInboxCache();
        } catch (Exception $e) {
            throw new \Exception('Erreur lors de la suppression de l\'email : ' . $e->getMessage());
        }
    }

    public function getEmailById(int $id)
    {
        try {
            $mail = $this->mailbox->getMail($id);

            return [
                'id' => $id,
                'fromAddress' => $mail->fromAddress,
                'subject' => $mail->subject,
                'date' => $this->normalizeDate($mail->date),
                'htmlContent' => $mail->textHtml ?: $mail->textPlain,
            ];
        } catch (Exception $ex) {
            throw new \Exception('Impossible de récupérer le contenu de l\'email : ' . $ex->getMessage());
        }
    }

    
    private function normalizeDate($date): string
    {
        $datetime = new DateTime($date);
        $datetime->setTimezone(new DateTimeZone('Europe/Paris')); 
        return $datetime->format('Y-m-d H:i:s');
    }

    public function clearInboxCache()
    {
        $this->cache->delete('all_email_ids');
    }


    public function checkForNewEmails(): bool
    {
        $allEmailIds = $this->cache->get('all_email_ids', function (ItemInterface $item) {
            $item->expiresAfter(3600);
            return $this->fetchEmailIds();
        });

        $newEmailIds = $this->fetchEmailIds();

        return !empty(array_diff($newEmailIds, $allEmailIds));
    }
}
