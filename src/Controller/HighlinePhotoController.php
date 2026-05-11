<?php

namespace App\Controller;

use App\Entity\Highline;
use App\Entity\HighlinePhoto;
use App\Entity\User;
use App\Form\HighlinePhotoForm;
use App\Repository\HighlinePhotoRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class HighlinePhotoController extends AbstractController
{
    #[Route('/highline/{slug}/fotky/pridat', name: 'app_highline_photo_new', requirements: ['slug' => '[a-z0-9-]+'], methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function new(
        Request $request,
        #[MapEntity(mapping: ['slug' => 'slug'])]
        Highline $highline,
        EntityManagerInterface $em,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        $photo = new HighlinePhoto();
        $photo->setHighline($highline);
        $photo->setUploadedBy($user);

        $form = $this->createForm(HighlinePhotoForm::class, $photo);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($photo);
            $em->flush();
            $this->addFlash('success', 'Fotka nahraná.');

            return $this->redirectToRoute('app_highline_detail', ['slug' => $highline->getSlug()]);
        }

        return $this->render('highline_photo/form.html.twig', [
            'form' => $form,
            'highline' => $highline,
        ]);
    }

    #[Route('/highline/fotka/{id}/smazat', name: 'app_highline_photo_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function delete(
        Request $request,
        HighlinePhoto $photo,
        EntityManagerInterface $em,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        $isAdmin = $this->isGranted('ROLE_ADMIN');
        if (!$isAdmin && !$photo->isOwnedBy($user)) {
            throw $this->createAccessDeniedException();
        }

        if (!$this->isCsrfTokenValid('delete-photo-' . $photo->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $highline = $photo->getHighline();
        $em->remove($photo);
        $em->flush();
        $this->addFlash('success', 'Fotka smazána.');

        return $this->redirectToRoute('app_highline_detail', ['slug' => $highline->getSlug()]);
    }
}
