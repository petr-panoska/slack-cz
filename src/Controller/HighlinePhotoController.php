<?php

namespace App\Controller;

use App\Entity\Highline;
use App\Entity\HighlinePhoto;
use App\Entity\HighlinePhotoComment;
use App\Entity\HighlinePhotoLike;
use App\Entity\User;
use App\Form\HighlinePhotoCommentForm;
use App\Form\HighlinePhotoForm;
use App\Repository\HighlinePhotoCommentRepository;
use App\Repository\HighlinePhotoLikeRepository;
use App\Repository\HighlinePhotoRepository;
use App\Service\PhotoNormalizer;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
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
        PhotoNormalizer $normalizer,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        $photo = new HighlinePhoto();
        $photo->setHighline($highline);
        $photo->setUploadedBy($user);

        $form = $this->createForm(HighlinePhotoForm::class, $photo);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Re-encode to a clean WebP master + pull date/GPS out before they're stripped.
            /** @var UploadedFile $upload */
            $upload = $photo->getFile();
            try {
                $normalized = $normalizer->normalize($upload->getPathname());
            } catch (\Throwable) {
                $this->addFlash('error', 'Fotku se nepodařilo zpracovat. Zkus jiný soubor (JPG, PNG, WebP, HEIC).');

                return $this->render('highline_photo/form.html.twig', [
                    'form' => $form,
                    'highline' => $highline,
                ]);
            }

            $photo->setFile(new UploadedFile($normalized->path, 'photo.webp', 'image/webp', null, true));
            if ($normalized->takenAt !== null) {
                $photo->setCreatedAt($normalized->takenAt);
            }
            $photo->setGps($normalized->gpsLat, $normalized->gpsLng);

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

    #[Route('/highline/{slug}/fotky/{id}', name: 'app_highline_photo_detail', requirements: ['slug' => '[a-z0-9-]+', 'id' => '\d+'], methods: ['GET', 'POST'])]
    public function detail(
        Request $request,
        #[MapEntity(mapping: ['slug' => 'slug'])]
        Highline $highline,
        #[MapEntity(id: 'id')]
        HighlinePhoto $photo,
        EntityManagerInterface $em,
        HighlinePhotoRepository $photos,
        HighlinePhotoCommentRepository $comments,
        HighlinePhotoLikeRepository $likes,
    ): Response {
        if ($photo->getHighline()->getId() !== $highline->getId()) {
            throw $this->createNotFoundException();
        }

        $user = $this->getUser();
        $comment = new HighlinePhotoComment();
        $comment->setPhoto($photo);
        if ($user instanceof User) {
            $comment->setAuthor($user);
        }

        $form = $this->createForm(HighlinePhotoCommentForm::class, $comment);
        if ($user instanceof User) {
            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {
                $em->persist($comment);
                $em->flush();

                return $this->redirectToRoute('app_highline_photo_detail', [
                    'slug' => $highline->getSlug(),
                    'id' => $photo->getId(),
                ]);
            }
        }

        $siblings = $photos->findForHighline($highline);
        $prev = null;
        $next = null;
        foreach ($siblings as $i => $p) {
            if ($p->getId() === $photo->getId()) {
                $prev = $siblings[$i - 1] ?? null;
                $next = $siblings[$i + 1] ?? null;
                break;
            }
        }

        $likeCount = $likes->countsForPhotos([$photo->getId()])[$photo->getId()] ?? 0;
        $userHasLiked = $user instanceof User
            && $likes->likedPhotoIdsForUser($user, [$photo->getId()]) !== [];

        return $this->render('highline_photo/detail.html.twig', [
            'highline' => $highline,
            'photo' => $photo,
            'comments' => $comments->findForPhoto($photo),
            'commentForm' => $form,
            'likeCount' => $likeCount,
            'userHasLiked' => $userHasLiked,
            'prev' => $prev,
            'next' => $next,
        ]);
    }

    #[Route('/highline/fotka/{id}/like', name: 'app_highline_photo_like', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function toggleLike(
        Request $request,
        HighlinePhoto $photo,
        EntityManagerInterface $em,
        HighlinePhotoLikeRepository $likes,
    ): Response {
        if (!$this->isCsrfTokenValid('like-photo-' . $photo->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        /** @var User $user */
        $user = $this->getUser();
        $existing = $likes->findOneByPhotoAndUser($photo, $user);

        $liked = false;
        if ($existing !== null) {
            $em->remove($existing);
            $em->flush();
        } else {
            try {
                $em->persist(new HighlinePhotoLike($photo, $user));
                $em->flush();
                $liked = true;
            } catch (UniqueConstraintViolationException) {
                // Race: another tab inserted first. Treat as already-liked.
                $liked = true;
            }
        }

        if ($this->wantsJson($request)) {
            $count = $likes->countsForPhotos([$photo->getId()])[$photo->getId()] ?? 0;
            return new JsonResponse(['liked' => $liked, 'count' => $count]);
        }

        $referer = $request->headers->get('referer');
        if ($referer !== null && str_contains($referer, $request->getSchemeAndHttpHost())) {
            return $this->redirect($referer);
        }
        return $this->redirectToRoute('app_highline_detail', ['slug' => $photo->getHighline()->getSlug()]);
    }

    private function wantsJson(Request $request): bool
    {
        $accept = (string) $request->headers->get('Accept', '');
        return str_contains($accept, 'application/json') || $request->isXmlHttpRequest();
    }

    #[Route('/highline/komentar/{id}/smazat', name: 'app_highline_photo_comment_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function deleteComment(
        Request $request,
        HighlinePhotoComment $comment,
        EntityManagerInterface $em,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        if (!$this->isGranted('ROLE_ADMIN') && !$comment->isOwnedBy($user)) {
            throw $this->createAccessDeniedException();
        }

        if (!$this->isCsrfTokenValid('delete-comment-' . $comment->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $photo = $comment->getPhoto();
        $em->remove($comment);
        $em->flush();
        $this->addFlash('success', 'Komentář smazán.');

        return $this->redirectToRoute('app_highline_photo_detail', [
            'slug' => $photo->getHighline()->getSlug(),
            'id' => $photo->getId(),
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
