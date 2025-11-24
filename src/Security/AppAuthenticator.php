<?php

namespace App\Security;

use App\Entity\User;
use App\Entity\LoginAttempts;
use App\Repository\UserRepository;
use Doctrine\Persistence\ManagerRegistry;
use function Symfony\Component\Translation\t;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Util\TargetPathTrait;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\SecurityRequestAttributes;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
use Symfony\Component\Security\Http\Authenticator\AbstractLoginFormAuthenticator;

use Symfony\Component\Security\Http\Authenticator\Passport\Badge\RememberMeBadge;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\CustomCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;

class AppAuthenticator extends AbstractLoginFormAuthenticator
{
    use TargetPathTrait;

    public const LOGIN_ROUTE = 'app_login';
    private UserRepository $userRepository;
    private UserPasswordHasherInterface $passwordEncoder;
    private $em;

    public function __construct(ManagerRegistry $doctrine, private UrlGeneratorInterface $urlGenerator, UserPasswordHasherInterface $passwordEncoder, UserRepository $userRepository)
    {
        $this->urlGenerator = $urlGenerator;
        $this->passwordEncoder = $passwordEncoder;
        $this->userRepository = $userRepository;
        $this->em = $doctrine->getManager();
        
    }

    public function authenticate(Request $request): Passport
    {
        $email = $request->getPayload()->getString('email');
        $password = $request->getPayload()->getString('password');

        $request->getSession()->set(SecurityRequestAttributes::LAST_USERNAME, $email);

        return new Passport(
            new UserBadge($email, function($userIdentifier) {
                $user = $this->userRepository->findOneBy(['email' => $userIdentifier]);
                if (!$user) {
                    throw new CustomUserMessageAuthenticationException('Email introuvable.');
                }
                return $user;
            }),
            new CustomCredentials(function($credentials, $user) {
                if (!$user->isActive()) {
                    throw new CustomUserMessageAuthenticationException("Your account is not active!");
                }

                if ($user->isBlocked()) { 
                    throw new CustomUserMessageAuthenticationException("Your account is blocked!");
                }

                if (!$this->passwordEncoder->isPasswordValid($user, $credentials)) {
                    $user->setCountAttempts($user->getCountAttempts() + 1);

                    $loginAttempt = new LoginAttempts();
                    $loginAttempt->setUser($user);
                    $loginAttempt->setAttemptedAt(new \DateTimeImmutable());
                    $loginAttempt->setCount($user->getCountAttempts());

                    $this->em->persist($loginAttempt);

                    if ($user->getCountAttempts() >= 3) {
                        $user->setIsBlocked(true);
                        $user->setBlockedAt(new \DateTimeImmutable('now'));
                        $this->em->flush();
                        throw new CustomUserMessageAuthenticationException("Your password is incorrect! Your account is now blocked due to multiple failed login attempts.");
                    }

                    $this->em->flush();
                    throw new CustomUserMessageAuthenticationException("Your password is incorrect! you have ".(3 - $user->getCountAttempts())." attempts left.");
                }
                return true;
            }, $password),
            // new PasswordCredentials($request->getPayload()->getString('password')),
            [
                new CsrfTokenBadge('authenticate', $request->getPayload()->getString('_csrf_token')),
                new RememberMeBadge(),
            ]
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        $email = $request->getPayload()->getString('email');
        $user = $this->userRepository->findOneBy(['email' => $email]);
        if($user->isVerified() == false) {
            return new RedirectResponse($this->urlGenerator->generate('app_verify_email_notice'));
        }
        if ($targetPath = $this->getTargetPath($request->getSession(), $firewallName)) {
            return new RedirectResponse($targetPath);
        }

        // For example:
        return new RedirectResponse($this->urlGenerator->generate('app_home'));
        // throw new \Exception('TODO: provide a valid redirect inside '.__FILE__);
    }

    protected function getLoginUrl(Request $request): string
    {
        return $this->urlGenerator->generate(self::LOGIN_ROUTE);
    }
}
