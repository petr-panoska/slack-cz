<?php

namespace App\Controller;

use App\Entity\Highline;
use App\Entity\HighlineCrossing;
use App\Entity\User;
use App\Form\HighlineCrossingForm;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class CrossingController extends AbstractController
{
    #[Route('/highline/{slug}/prechod/novy', name: 'app_crossing_new', requirements: ['slug' => '[a-z0-9-]+'], methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function new(
        Request $request,
        #[MapEntity(mapping: ['slug' => 'slug'])]
        Highline $highline,
        EntityManagerInterface $em,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        $crossing = new HighlineCrossing();
        $crossing->setHighline($highline);
        $crossing->setUser($user);
        $crossing->setCrossedAt(new \DateTimeImmutable('today'));

        $form = $this->createForm(HighlineCrossingForm::class, $crossing);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($crossing);
            $em->flush();
            $this->addFlash('success', 'Přechod přidán.');

            return $this->redirectToRoute('app_highline_detail', ['slug' => $highline->getSlug()]);
        }

        return $this->render('crossing/form.html.twig', [
            'form' => $form,
            'highline' => $highline,
            'crossing' => $crossing,
            'mode' => 'new',
        ]);
    }

    #[Route('/prechod/{id}/upravit', name: 'app_crossing_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function edit(
        Request $request,
        HighlineCrossing $crossing,
        EntityManagerInterface $em,
    ): Response {
        $this->assertOwner($crossing);

        $form = $this->createForm(HighlineCrossingForm::class, $crossing);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Přechod upraven.');

            return $this->redirectToRoute('app_user_denik', ['id' => $crossing->getUser()->getId()]);
        }

        return $this->render('crossing/form.html.twig', [
            'form' => $form,
            'highline' => $crossing->getHighline(),
            'crossing' => $crossing,
            'mode' => 'edit',
        ]);
    }

    #[Route('/prechod/{id}/smazat', name: 'app_crossing_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function delete(
        Request $request,
        HighlineCrossing $crossing,
        EntityManagerInterface $em,
    ): Response {
        $this->assertOwner($crossing);

        $token = (string) $request->request->get('_token', '');
        if (!$this->isCsrfTokenValid('delete-crossing-' . $crossing->getId(), $token)) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $userId = $crossing->getUser()->getId();
        $em->remove($crossing);
        $em->flush();
        $this->addFlash('success', 'Přechod smazán.');

        return $this->redirectToRoute('app_user_denik', ['id' => $userId]);
    }

    private function assertOwner(HighlineCrossing $crossing): void
    {
        $user = $this->getUser();
        if (!$user instanceof User || $crossing->getUser()->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException('Tento přechod můžeš upravit jen ty, kdo ho zaznamenal.');
        }
    }
}
