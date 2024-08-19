<?php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Entity\Contact;
use Doctrine\ORM\EntityManagerInterface;
use App\Form\ContactType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use App\Form\MailType;
use App\Service\ImapService;

class HomeController extends AbstractController
{
    private ImapService $imapService;
    private EntityManagerInterface $entityManager;

    public function __construct(ImapService $imapService, EntityManagerInterface $entityManager)
    {
        $this->imapService = $imapService;
        $this->entityManager = $entityManager;
    }

    #[Route('/', name: 'app_home')]
    public function index(): Response
    {
        $contacts = $this->entityManager->getRepository(Contact::class)->findAll();
        $form = $this->createForm(MailType::class);

        return $this->render('home/index.html.twig', [
            'contacts' => $contacts,
            'emailForm' => $form->createView(),
        ]);
    }

    #[Route('/add-contact', name: 'app_add_contact')]
    public function addContact(Request $request): Response
    {
        $contact = new Contact();
        $form = $this->createForm(ContactType::class, $contact);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->persist($contact);
            $this->entityManager->flush();

            // C est ça le secret c'est d invalider le cache après l ajout du contact!!!!!!
            $this->imapService->clearInboxCache();

            return new RedirectResponse($this->generateUrl('app_home'));
        }

        return $this->render('home/add_contact.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/delete-contact/{id}', name: 'app_delete_contact')]
    public function deleteContact(int $id): RedirectResponse
    {
        $contact = $this->entityManager->getRepository(Contact::class)->find($id);

        if ($contact) {
            $this->entityManager->remove($contact);
            $this->entityManager->flush();

        
            $this->imapService->clearInboxCache();
        }

        return new RedirectResponse($this->generateUrl('app_home'));
    }

    #[Route('/test-email', name: 'app_test_email')]
    public function testEmail(MailerInterface $mailer): Response
    {
        $email = (new Email())
            ->from('grimtassadit7@gmail.com')
            ->to('tassaditgrim1@gmail.com')
            ->subject('Test Email')
            ->text('Test');

        $mailer->send($email);

        return new Response('Email sent successfully');
    }

    #[Route('/send-email/{id}', name: 'app_send_email')]
    public function sendEmail(Request $request, MailerInterface $mailer, int $id): Response
    {
        $contact = $this->entityManager->getRepository(Contact::class)->find($id);
        $form = $this->createForm(MailType::class, [
            'to' => $contact->getEmail(),
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            $email = (new Email())
                ->from('tassaditgrim1@gmail.com') 
                ->to($data['to'])
                ->subject($data['subject'])
                ->text($data['body']);

            $mailer->send($email);

            $this->addFlash('success', 'Email envoyé avec succès !');

            return $this->redirectToRoute('app_send_email', ['id' => $id]);
        }

        return $this->render('home/send_email.html.twig', [
            'emailForm' => $form->createView(),
        ]);
    }

    #[Route('/inbox-messages', name: 'app_inbox_messages', methods: ['GET'])]
    public function inboxMessages(): JsonResponse
    {
        try {
            $emails = $this->imapService->getInboxMessages();
            return new JsonResponse($emails);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
