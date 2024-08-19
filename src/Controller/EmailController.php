<?php
//////voià le cheminnnnnnn : src/Controller/EmailController.php

namespace App\Controller;

use App\Service\ImapService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;

class EmailController extends AbstractController
{
    private $imapService;

    public function __construct(ImapService $imapService)
    {
        $this->imapService = $imapService;
    }

    /**
     * @Route("/inbox", name="app_inbox")
     */
    public function inbox(): Response
    {
        $emails = $this->imapService->getInboxMessages();
        return $this->render('email/inbox.html.twig', [
            'emails' => $emails,
        ]);
    }

    /**
     * @Route("/delete-email/{id}", name="app_delete_email", methods={"POST"})
     */
    public function deleteEmail(int $id): Response
    {
        try {
            $this->imapService->deleteEmail($id);
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors de la suppression de l\'email : ' . $e->getMessage());
        }
        return $this->redirectToRoute('app_inbox');
    }

    /**
     * @Route("/email/{id}", name="email_content")
     */
    public function showEmailContent(int $id): Response
    {
        try {
            $email = $this->imapService->getEmailById($id);
            return $this->render('email/content.html.twig', [
                'email' => $email,
            ]);
        } catch (\Exception $e) {
            $this->addFlash('error', 'Impossible de récupérer le contenu de l\'email : ' . $e->getMessage());
            return $this->redirectToRoute('app_inbox');
        }
    }

    /**
     * @Route("/email/list", name="email_list")
     */
    public function listEmails(): JsonResponse
    {
        try {
            $emails = $this->imapService->getInboxMessages();
            $emailData = array_map(function ($email) {
                return [
                    'id' => $email['id'],
                    'fromAddress' => $email['fromAddress'],
                    'subject' => $email['subject'],
                    'date' => $email['date'],
                    'url' => $this->generateUrl('email_content', ['id' => $email['id']]),
                ];
            }, $emails);

            return new JsonResponse($emailData);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * @Route("/fetch-new-emails", name="fetch_new_emails")
     */
    public function fetchNewEmails(): JsonResponse
    {
        try {
            $emails = $this->imapService->getInboxMessages();
            $emailData = array_map(function ($email) {
                return [
                    'id' => $email['id'],
                    'fromAddress' => $email['fromAddress'],
                    'subject' => $email['subject'],
                    'date' => $email['date'],
                ];
            }, $emails);

            return new JsonResponse($emailData);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }
}
