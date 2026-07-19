<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationForm;
use App\Repository\UserRepository;
use App\Security\EmailVerifier;
use App\UserEmoji;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use SymfonyCasts\Bundle\VerifyEmail\Exception\VerifyEmailExceptionInterface;

class RegistrationController extends AbstractController
{
    public function __construct(private EmailVerifier $emailVerifier)
    {
    }

    #[Route('/registrace', name: 'app_register')]
    public function register(
        Request $request,
        UserPasswordHasherInterface $userPasswordHasher,
        Security $security,
        EntityManagerInterface $entityManager,
        #[Target('registration_submission')] RateLimiterFactoryInterface $registrationSubmissionLimiter,
    ): Response
    {
        $user = new User();
        $user->setEmoji(UserEmoji::random());
        $form = $this->createForm(RegistrationForm::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $limit = $registrationSubmissionLimiter
                ->create($request->getClientIp() ?? 'unknown')
                ->consume();

            if (!$limit->isAccepted()) {
                $form->addError(new FormError('Zkus registraci prosím znovu za chvíli.'));

                return $this->render('registration/register.html.twig', [
                    'registrationForm' => $form,
                ]);
            }

            /** @var string $plainPassword */
            $plainPassword = $form->get('password')->get('plainPassword')->getData();

            // encode the plain password
            $user->setPassword($userPasswordHasher->hashPassword($user, $plainPassword));

            $user->setRegisteredAt(new \DateTimeImmutable());

            $entityManager->persist($user);
            $entityManager->flush();

            // TODO: Decide whether to restore email verification before enabling this again.
            // $this->emailVerifier->sendEmailConfirmation('app_verify_email', $user,
            //     (new TemplatedEmail())
            //         ->from(new Address('info@slack.cz', 'Slack.cz'))
            //         ->to((string) $user->getEmail())
            //         ->subject('Please Confirm your Email')
            //         ->htmlTemplate('registration/confirmation_email.html.twig')
            // );

            $this->addFlash('success', 'Děkujeme za registraci! Jestli chceš, můžeš o sobě uvést víc informací. Ať se daří 🍀');

            $security->login($user, 'form_login', 'main');

            return $this->redirectToRoute('app_profile_edit');
        }

        return $this->render('registration/register.html.twig', [
            'registrationForm' => $form,
        ]);
    }

    #[Route('/registrace/dostupnost', name: 'app_registration_availability', methods: ['POST'])]
    public function availability(
        Request $request,
        UserRepository $userRepository,
        ValidatorInterface $validator,
        #[Target('registration_availability')] RateLimiterFactoryInterface $registrationAvailabilityLimiter,
    ): JsonResponse {
        $limit = $registrationAvailabilityLimiter
            ->create($request->getClientIp() ?? 'unknown')
            ->consume();

        if (!$limit->isAccepted()) {
            return new JsonResponse(
                ['message' => 'Příliš mnoho kontrol. Zkus to prosím za chvíli.'],
                Response::HTTP_TOO_MANY_REQUESTS,
                ['Cache-Control' => 'no-store'],
            );
        }

        try {
            $payload = json_decode($request->getContent(), true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            $payload = null;
        }

        $field = is_array($payload) ? $payload['field'] ?? null : null;
        $value = is_array($payload) ? $payload['value'] ?? null : null;

        if (!is_string($field) || !is_string($value) || !in_array($field, ['email', 'nick'], true)) {
            return new JsonResponse(['message' => 'Neplatný požadavek.'], Response::HTTP_BAD_REQUEST, ['Cache-Control' => 'no-store']);
        }

        $value = trim($value);
        $isValid = $field === 'email'
            ? count($validator->validate($value, [new NotBlank(), new Email(mode: Email::VALIDATION_MODE_HTML5_ALLOW_NO_TLD)])) === 0
            : $value !== '' && mb_strlen($value) <= 30;

        if (!$isValid) {
            return new JsonResponse(['valid' => false], headers: ['Cache-Control' => 'no-store']);
        }

        return new JsonResponse([
            'valid' => true,
            'available' => $userRepository->findOneBy([$field => $value]) === null,
        ], headers: ['Cache-Control' => 'no-store']);
    }

    #[Route('/overeni-emailu', name: 'app_verify_email')]
    public function verifyUserEmail(Request $request, TranslatorInterface $translator, UserRepository $userRepository, Security $security): Response
    {
        $id = $request->query->get('id');

        if (null === $id) {
            return $this->redirectToRoute('app_register');
        }

        $user = $userRepository->find($id);

        if (null === $user) {
            return $this->redirectToRoute('app_register');
        }

        // validate email confirmation link, sets User::isVerified=true and persists
        try {
            $this->emailVerifier->handleEmailConfirmation($request, $user);
        } catch (VerifyEmailExceptionInterface $exception) {
            $this->addFlash('verify_email_error', $translator->trans($exception->getReason(), [], 'VerifyEmailBundle'));

            return $this->redirectToRoute('app_register');
        }

        // @TODO Change the redirect on success and handle or remove the flash message in your templates
        $this->addFlash('success', 'Your email address has been verified.');

        $security->login($user, 'form_login', 'main');

        return $this->redirectToRoute('app_user_diary', ['id' => $user->getId()]);
    }
}
