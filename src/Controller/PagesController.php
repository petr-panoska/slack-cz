<?php

namespace App\Controller;

use App\Entity\User;
use App\Feed\FeedFetcherInterface;
use App\Form\UserForm;
use App\Repository\LineCrossingRepository;
use App\Repository\LinePhotoRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PagesController extends AbstractController
{
    #[Route('/', name: 'app_index')]
    public function index(
        FeedFetcherInterface $feed,
        LineCrossingRepository $crossings,
        LinePhotoRepository $photos,
    ): Response {
        return $this->render('pages/index.html.twig', [
            'feed_items' => $feed->fetch(12),
            'recent_crossings' => $crossings->findRecent(),
            'recent_photos' => $photos->findRecentForHomepage(10),
        ]);
    }

    #[Route('/profil', name: 'app_profile')]
    public function profile(LineCrossingRepository $crossings): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        return $this->render('pages/profile.html.twig', [
            'first_crossing_date' => $crossings->findFirstCrossingDate($this->getUser()),
        ]);
    }

    #[Route('/profil/uprava', name: 'app_profile_edit')]
    public function editProfile(Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        /** @var User $user */
        $user = $this->getUser();
        $form = $this->createForm(UserForm::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();
            $this->addFlash('success', 'Profil byl uložen.');

            return $this->redirectToRoute('app_profile');
        }

        return $this->render('pages/profile_edit.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/galerie', name: 'app_gallery')]
    public function gallery(LinePhotoRepository $photos): Response
    {
        // Year sections, newest first; photos without a date close the chronicle (key null).
        $byYear = [];
        foreach ($photos->findForGallery() as $photo) {
            $year = $photo->getCreatedAt()?->format('Y');
            $byYear[$year ?? 'null'][] = $photo;
        }

        return $this->render('pages/gallery.html.twig', [
            'photos_by_year' => $byYear,
            'total' => array_sum(array_map('count', $byYear)),
        ]);
    }

    #[Route('/o-projektu', name: 'app_about')]
    public function about(): Response
    {
        return $this->render('pages/about.html.twig');
    }

    // Stranou, zatím nelinkováno z nav — později se rozhodne, jak ji ukázat uživatelům.
    #[Route('/intro', name: 'app_intro')]
    public function intro(): Response
    {
        return $this->render('pages/intro.html.twig');
    }

    #[Route('/ochrana-osobnich-udaju', name: 'app_privacy')]
    public function privacy(): Response
    {
        return $this->render('pages/privacy.html.twig');
    }
}
