<?php

namespace App\Controller;

use App\Entity\User;
use Symfony\Component\Mime\Email;
use App\Repository\UserRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends AbstractController
{
    private $em;

    public function __construct(ManagerRegistry $doctrine)
    {
        $this->em = $doctrine->getManager();
        
    }

    #[Route(path: '/login', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_home');
        }

        $error = $authenticationUtils->getLastAuthenticationError();
        
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('security/login.html.twig', ['last_username' => $lastUsername, 'error' => $error]);
    }

    #[Route(path: '/logout', name: 'app_logout')]
    public function logout(): void
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }

    #[Route(path: '/verify-email', name: 'app_verify_email_notice')]
    public function app_verify_email_notice(): Response
    {
        $lastVerificationEmailAt = $this->getUser()->getLastVerificationEmailAt();
        if ($lastVerificationEmailAt && (clone $lastVerificationEmailAt)->modify('+1 day') < new \DateTimeImmutable()) {
            $this->getUser()->setCountVerificationEmails(0);
            $this->em->flush();
        }
        return $this->render('security/email_verification.html.twig', ['user' => $this->getUser(), 'countVerificationEmails' => $this->getUser()->getCountVerificationEmails(), 'lastVerificationEmailAt' => $lastVerificationEmailAt]);
    }

    #[Route('/send-email', name: 'send_email', options: ['expose' => true])]
    public function send(MailerInterface $mailer)
    {
        $lastVerificationEmailAt = $this->getUser()->getLastVerificationEmailAt();

        if ($lastVerificationEmailAt && (clone $lastVerificationEmailAt)->modify('+2 minutes') > new \DateTimeImmutable()) {
            return $this->json(['status' => 'too many requests'], 429);
        }

        $verificationToken = bin2hex(random_bytes(32));

        $userMail = $this->getUser()->getEmail();
        $link = $this->generateUrl(
            'account_activate',
            ['token' => $verificationToken],
            UrlGeneratorInterface::ABSOLUTE_URL
        );
        
        $email = (new TemplatedEmail())
            ->from('mohammedredalagciyer@gmail.com')
            ->to($userMail)
            ->subject('Activate your account')
            ->htmlTemplate('security/emails/activate_account.html.twig')
            ->context([
                'user' => $this->getUser(),
                'activationLink' => $link,
            ]);
        // dd($email);
        try {
            $mailer->send($email);
            $this->getUser()->setVerificationToken($verificationToken);
            $this->getUser()->setCountVerificationEmails($this->getUser()->getCountVerificationEmails() + 1);
            $this->getUser()->setLastVerificationEmailAt(new \DateTimeImmutable('now'));
            $this->em->flush();
        } catch (\Exception $e) {
            dd($e->getMessage());
        }

        return $this->json(['status' => 'email sent']);
    }

    #[Route('/activate/{token}', name: 'account_activate', options: ['expose' => true])]
    public function activate(string $token)
    {
        $user = $this->em->getRepository(User::class)->findOneBy(['verificationToken' => $token, 'id' => $this->getUser()->getId()]);

        if (!$user) {
            throw $this->createNotFoundException("Invalid activation token");
        }

        $user->setVerificationToken(null);
        $user->setVerifiedAt(new \DateTimeImmutable('now'));
        $user->setIsVerified(true); 
        $this->em->flush();

        return $this->redirectToRoute('app_home');
    }

    #[Route(path: '/reset-password', name: 'app_reset_password', options: ['expose' => true])]
    public function app_reset_password(): Response
    {
        return $this->render('security/reset_password/reset_password.html.twig');
    }
}
