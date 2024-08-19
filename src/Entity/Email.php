<?php
// src/Controller/EmailController.php

namespace App\Controller;

use App\Service\ImapService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class EmailController extends AbstractController
{
    private $imapService;

    public function __construct(ImapService $imapService)
    {
        $this->imapService = $imapService;
    }

    /**
     * @Route("/email/{id}", name="email_content")
     */
    public function showEmailContent(int $id): Response
    {
        $emails = $this->imapService->getInboxMessages(); 

        $email = array_filter($emails, function($email) use ($id) {
            return $email['id'] === $id;
        });

        if (empty($email)) {
            throw $this->createNotFoundException('Email not found');
        }

        $email = array_shift($email);

        return $this->render('email/content.html.twig', [
            'email' => $email,
        ]);
    }
}
