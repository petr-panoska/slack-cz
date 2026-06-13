<?php

namespace App\Controller;

use App\Entity\LonglineCrossing;
use App\Entity\User;
use App\Form\LonglineCrossingForm;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class LonglineCrossingController extends AbstractController
{
    #[Route('/longline/novy', name: 'app_longline_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $longline = new LonglineCrossing();
        $longline->setUser($user);
        $longline->setCrossedAt(new \DateTimeImmutable('today'));

        $form = $this->createForm(LonglineCrossingForm::class, $longline);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($longline);
            $em->flush();
            $this->addFlash('success', 'Longline přechod přidán.');

            return $this->redirectToDenik($user->getId());
        }

        return $this->render('longline/form.html.twig', [
            'form' => $form,
            'longline' => $longline,
            'mode' => 'new',
        ]);
    }

    #[Route('/longline/{id}/upravit', name: 'app_longline_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function edit(Request $request, LonglineCrossing $longline, EntityManagerInterface $em): Response
    {
        $this->assertOwner($longline);

        $form = $this->createForm(LonglineCrossingForm::class, $longline);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Longline přechod upraven.');

            return $this->redirectToDenik($longline->getUser()->getId());
        }

        return $this->render('longline/form.html.twig', [
            'form' => $form,
            'longline' => $longline,
            'mode' => 'edit',
        ]);
    }

    #[Route('/longline/{id}/smazat', name: 'app_longline_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function delete(Request $request, LonglineCrossing $longline, EntityManagerInterface $em): Response
    {
        $this->assertOwner($longline);

        $token = (string) $request->request->get('_token', '');
        if (!$this->isCsrfTokenValid('delete-longline-' . $longline->getId(), $token)) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $userId = $longline->getUser()->getId();
        $em->remove($longline);
        $em->flush();
        $this->addFlash('success', 'Longline přechod smazán.');

        return $this->redirectToDenik($userId);
    }

    private function redirectToDenik(?int $userId): Response
    {
        // Land back on the Longline tab via its deep-link hash.
        return $this->redirect($this->generateUrl('app_user_denik', ['id' => $userId]) . '#longline');
    }

    private function assertOwner(LonglineCrossing $longline): void
    {
        $user = $this->getUser();
        if (!$user instanceof User || $longline->getUser()->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException('Tento přechod můžeš upravit jen ty, kdo ho zaznamenal.');
        }
    }
}
