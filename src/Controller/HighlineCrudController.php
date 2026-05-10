<?php

namespace App\Controller;

use App\Entity\Highline;
use App\Entity\HighlineEdit;
use App\Entity\User;
use App\Enum\HighlineEditStatus;
use App\Enum\HighlineType;
use App\Form\HighlineForm;
use App\Repository\HighlineEditRepository;
use App\Repository\HighlineRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * Highline CRUD + verification + edit-proposal flow.
 *
 * Trust model:
 * - Anyone logged in can create a new highline; it lands as `isVerified=false`, owned by them.
 * - Owner of an unverified highline (or any admin) can edit / delete it directly.
 * - Once admin marks a highline `isVerified=true`, edits go through HighlineEdit (status=PENDING)
 *   and only an admin can approve them. Delete of a verified highline is admin-only.
 *
 * The HighlineEdit table doubles as audit log: APPLIED rows for direct edits + approved proposals.
 */
final class HighlineCrudController extends AbstractController
{
    /**
     * Fields that are part of the form / snapshot. Listed once so the snapshot/apply pair stays in sync.
     *
     * `length`, `latitude`, `longitude` are NOT in the form — they're derived from point1/point2
     * (length via haversine, lat/lng as midpoint) on submit. We still snapshot them so the audit
     * row reflects the post-derivation state.
     */
    private const FIELDS = [
        'name', 'type', 'height',
        'point1Latitude', 'point1Longitude', 'point2Latitude', 'point2Longitude',
        'length', 'latitude', 'longitude',
        'country', 'region', 'area',
        'description', 'pointOneInfo', 'pointTwoInfo',
        'anchoring', 'approachMinutes', 'tensioningMinutes',
        'firstAscentBy', 'firstAscentDate', 'nameHistory',
    ];

    #[Route('/highline/nova', name: 'app_highline_new', methods: ['GET', 'POST'], priority: 10)]
    #[IsGranted('ROLE_USER')]
    public function new(
        Request $request,
        EntityManagerInterface $em,
        HighlineRepository $highlines,
        SluggerInterface $slugger,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        $highline = new Highline();
        $highline->setType(HighlineType::Highline);
        $highline->setCreatedBy($user);
        $highline->setIsVerified(false);

        $form = $this->createForm(HighlineForm::class, $highline);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->deriveGeometry($highline);
            $highline->setSlug($this->makeUniqueSlug((string) $highline->getName(), $slugger, $highlines));
            $em->persist($highline);
            $em->flush();

            $audit = $this->buildEdit($highline, $user, HighlineEditStatus::APPLIED);
            $em->persist($audit);
            $em->flush();

            $this->addFlash('success', 'Lajna přidána. Pokud chceš, aby ji ostatní mohli rozšiřovat, požádej admina o verifikaci.');

            return $this->redirectToRoute('app_highline_detail', ['slug' => $highline->getSlug()]);
        }

        return $this->render('highline_form/form.html.twig', [
            'form' => $form,
            'highline' => $highline,
            'mode' => 'new',
            'queueProposal' => false,
        ]);
    }

    #[Route('/highline/{slug}/upravit', name: 'app_highline_edit', requirements: ['slug' => '[a-z0-9-]+'], methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function edit(
        Request $request,
        #[MapEntity(mapping: ['slug' => 'slug'])]
        Highline $highline,
        EntityManagerInterface $em,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        $isAdmin = $this->isGranted('ROLE_ADMIN');
        $isOwner = $highline->isOwnedBy($user);
        $queueProposal = !$isAdmin && $highline->isVerified();

        if (!$isAdmin && !$isOwner && !$highline->isVerified()) {
            // Unverified lajna patří jen ownerovi (a adminovi). Cizí user nesmí editovat.
            throw $this->createAccessDeniedException('Tuhle neverifikovanou lajnu může upravit jen její autor.');
        }

        $form = $this->createForm(HighlineForm::class, $highline);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Recompute length + midpoint from the (possibly changed) endpoints — applies to both
            // direct edit and proposal flow so the snapshot reflects the canonical derived values.
            $this->deriveGeometry($highline);

            if ($queueProposal) {
                // Submitting form filled the entity in-memory. Snapshot the proposed values, then
                // discard the in-memory mutation by reloading the row from DB.
                $snapshot = $this->snapshot($highline);
                $em->refresh($highline);

                $proposal = new HighlineEdit();
                $proposal->setHighline($highline);
                $proposal->setProposedBy($user);
                $proposal->setSnapshot($snapshot);
                $proposal->setStatus(HighlineEditStatus::PENDING);
                $em->persist($proposal);
                $em->flush();

                $this->addFlash('success', 'Návrh úpravy odeslán k schválení adminovi.');
            } else {
                $em->flush();
                $audit = $this->buildEdit($highline, $user, HighlineEditStatus::APPLIED);
                $em->persist($audit);
                $em->flush();
                $this->addFlash('success', 'Lajna upravena.');
            }

            return $this->redirectToRoute('app_highline_detail', ['slug' => $highline->getSlug()]);
        }

        return $this->render('highline_form/form.html.twig', [
            'form' => $form,
            'highline' => $highline,
            'mode' => 'edit',
            'queueProposal' => $queueProposal,
        ]);
    }

    #[Route('/highline/{slug}/smazat', name: 'app_highline_delete', requirements: ['slug' => '[a-z0-9-]+'], methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function delete(
        Request $request,
        #[MapEntity(mapping: ['slug' => 'slug'])]
        Highline $highline,
        EntityManagerInterface $em,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        $isAdmin = $this->isGranted('ROLE_ADMIN');
        if (!$isAdmin) {
            if ($highline->isVerified()) {
                throw $this->createAccessDeniedException('Verifikovanou lajnu může smazat jen admin.');
            }
            if (!$highline->isOwnedBy($user)) {
                throw $this->createAccessDeniedException('Smazat lajnu může jen její autor.');
            }
        }

        $token = (string) $request->request->get('_token', '');
        if (!$this->isCsrfTokenValid('delete-highline-' . $highline->getId(), $token)) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $em->remove($highline);
        $em->flush();
        $this->addFlash('success', 'Lajna smazána.');

        return $this->redirectToRoute('app_highline_map');
    }

    #[Route('/highline/{slug}/verify', name: 'app_highline_verify', requirements: ['slug' => '[a-z0-9-]+'], methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function verify(
        Request $request,
        #[MapEntity(mapping: ['slug' => 'slug'])]
        Highline $highline,
        EntityManagerInterface $em,
    ): Response {
        $token = (string) $request->request->get('_token', '');
        if (!$this->isCsrfTokenValid('verify-highline-' . $highline->getId(), $token)) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $highline->setIsVerified(true);
        $em->flush();
        $this->addFlash('success', sprintf('Lajna „%s" verifikována.', $highline->getName()));

        return $this->redirectToRoute('app_highline_detail', ['slug' => $highline->getSlug()]);
    }

    #[Route('/admin/navrhy', name: 'app_admin_proposals', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function proposalIndex(HighlineEditRepository $edits): Response
    {
        $pending = $edits->findPending();

        $diffs = [];
        foreach ($pending as $edit) {
            $diffs[$edit->getId()] = $this->diff($this->snapshot($edit->getHighline()), $edit->getSnapshot());
        }

        return $this->render('highline_form/proposals.html.twig', [
            'pending' => $pending,
            'diffs' => $diffs,
        ]);
    }

    #[Route('/admin/navrhy/{id}/schvalit', name: 'app_admin_proposal_approve', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function proposalApprove(
        Request $request,
        HighlineEdit $edit,
        EntityManagerInterface $em,
    ): Response {
        $this->assertProposalCsrf($request, $edit, 'approve');
        $this->assertPending($edit);

        /** @var User $admin */
        $admin = $this->getUser();

        $this->applySnapshot($edit->getHighline(), $edit->getSnapshot());
        $edit->setStatus(HighlineEditStatus::APPLIED);
        $edit->setReviewedBy($admin);
        $edit->setReviewedAt(new \DateTimeImmutable());
        $em->flush();

        $this->addFlash('success', sprintf('Návrh #%d schválen a aplikován.', $edit->getId()));
        return $this->redirectToRoute('app_admin_proposals');
    }

    #[Route('/admin/navrhy/{id}/zamitnout', name: 'app_admin_proposal_reject', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function proposalReject(
        Request $request,
        HighlineEdit $edit,
        EntityManagerInterface $em,
    ): Response {
        $this->assertProposalCsrf($request, $edit, 'reject');
        $this->assertPending($edit);

        /** @var User $admin */
        $admin = $this->getUser();

        $edit->setStatus(HighlineEditStatus::REJECTED);
        $edit->setReviewedBy($admin);
        $edit->setReviewedAt(new \DateTimeImmutable());
        $em->flush();

        $this->addFlash('success', sprintf('Návrh #%d zamítnut.', $edit->getId()));
        return $this->redirectToRoute('app_admin_proposals');
    }

    private function assertProposalCsrf(Request $request, HighlineEdit $edit, string $action): void
    {
        $token = (string) $request->request->get('_token', '');
        if (!$this->isCsrfTokenValid('proposal-' . $action . '-' . $edit->getId(), $token)) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }
    }

    private function assertPending(HighlineEdit $edit): void
    {
        if ($edit->getStatus() !== HighlineEditStatus::PENDING) {
            throw $this->createAccessDeniedException('Návrh už není ve frontě.');
        }
    }

    private function buildEdit(Highline $highline, ?User $author, HighlineEditStatus $status): HighlineEdit
    {
        $edit = new HighlineEdit();
        $edit->setHighline($highline);
        $edit->setProposedBy($author);
        $edit->setSnapshot($this->snapshot($highline));
        $edit->setStatus($status);
        if ($status === HighlineEditStatus::APPLIED) {
            $edit->setReviewedBy($author);
            $edit->setReviewedAt(new \DateTimeImmutable());
        }
        return $edit;
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(Highline $h): array
    {
        return [
            'name' => $h->getName(),
            'type' => $h->getType()->value,
            'length' => $h->getLength(),
            'height' => $h->getHeight(),
            'latitude' => $h->getLatitude(),
            'longitude' => $h->getLongitude(),
            'point1Latitude' => $h->getPoint1Latitude(),
            'point1Longitude' => $h->getPoint1Longitude(),
            'point2Latitude' => $h->getPoint2Latitude(),
            'point2Longitude' => $h->getPoint2Longitude(),
            'country' => $h->getCountry(),
            'region' => $h->getRegion(),
            'area' => $h->getArea(),
            'description' => $h->getDescription(),
            'pointOneInfo' => $h->getPointOneInfo(),
            'pointTwoInfo' => $h->getPointTwoInfo(),
            'anchoring' => $h->getAnchoring(),
            'approachMinutes' => $h->getApproachMinutes(),
            'tensioningMinutes' => $h->getTensioningMinutes(),
            'firstAscentBy' => $h->getFirstAscentBy(),
            'firstAscentDate' => $h->getFirstAscentDate()?->format('Y-m-d'),
            'nameHistory' => $h->getNameHistory(),
        ];
    }

    /**
     * @param array<string, mixed> $s
     */
    private function applySnapshot(Highline $h, array $s): void
    {
        $h->setName((string) $s['name']);
        $h->setType(HighlineType::from((string) $s['type']));
        $h->setHeight((int) $s['height']);
        $h->setPoint1Latitude(isset($s['point1Latitude']) ? (string) $s['point1Latitude'] : null);
        $h->setPoint1Longitude(isset($s['point1Longitude']) ? (string) $s['point1Longitude'] : null);
        $h->setPoint2Latitude(isset($s['point2Latitude']) ? (string) $s['point2Latitude'] : null);
        $h->setPoint2Longitude(isset($s['point2Longitude']) ? (string) $s['point2Longitude'] : null);
        $h->setCountry($s['country'] ?? null);
        $h->setRegion($s['region'] ?? null);
        $h->setArea($s['area'] ?? null);
        $h->setDescription($s['description'] ?? null);
        $h->setPointOneInfo($s['pointOneInfo'] ?? null);
        $h->setPointTwoInfo($s['pointTwoInfo'] ?? null);
        $h->setAnchoring($s['anchoring'] ?? null);
        $h->setApproachMinutes(isset($s['approachMinutes']) && $s['approachMinutes'] !== null ? (int) $s['approachMinutes'] : null);
        $h->setTensioningMinutes(isset($s['tensioningMinutes']) && $s['tensioningMinutes'] !== null ? (int) $s['tensioningMinutes'] : null);
        $h->setFirstAscentBy($s['firstAscentBy'] ?? null);
        $h->setFirstAscentDate(!empty($s['firstAscentDate']) ? new \DateTimeImmutable((string) $s['firstAscentDate']) : null);
        $h->setNameHistory($s['nameHistory'] ?? null);

        // Derived values come from snapshot if present (admin's pending proposal had them computed),
        // otherwise recompute from points.
        $this->deriveGeometry($h);
    }

    /**
     * Compute length (haversine, integer meters) + midpoint (lat/lng) from point1/point2.
     * Called after every form-bound mutation; keeps derived columns in sync with endpoints.
     */
    private function deriveGeometry(Highline $h): void
    {
        $p1Lat = $this->floatOrNull($h->getPoint1Latitude());
        $p1Lng = $this->floatOrNull($h->getPoint1Longitude());
        $p2Lat = $this->floatOrNull($h->getPoint2Latitude());
        $p2Lng = $this->floatOrNull($h->getPoint2Longitude());

        if ($p1Lat === null || $p1Lng === null || $p2Lat === null || $p2Lng === null) {
            return;
        }

        $h->setLength((int) round($this->haversineMeters($p1Lat, $p1Lng, $p2Lat, $p2Lng)));
        $h->setLatitude((string) (($p1Lat + $p2Lat) / 2));
        $h->setLongitude((string) (($p1Lng + $p2Lng) / 2));
    }

    private function floatOrNull(?string $v): ?float
    {
        if ($v === null || $v === '') {
            return null;
        }
        return (float) $v;
    }

    private function haversineMeters(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $R = 6_371_000.0; // mean Earth radius in meters
        $phi1 = deg2rad($lat1);
        $phi2 = deg2rad($lat2);
        $dPhi = deg2rad($lat2 - $lat1);
        $dLambda = deg2rad($lng2 - $lng1);

        $a = sin($dPhi / 2) ** 2 + cos($phi1) * cos($phi2) * sin($dLambda / 2) ** 2;
        return 2 * $R * asin(min(1.0, sqrt($a)));
    }

    /**
     * @param array<string, mixed> $before
     * @param array<string, mixed> $after
     * @return array<string, array{before: mixed, after: mixed}>
     */
    private function diff(array $before, array $after): array
    {
        $diff = [];
        foreach (self::FIELDS as $field) {
            $b = $before[$field] ?? null;
            $a = $after[$field] ?? null;
            if ($b !== $a) {
                $diff[$field] = ['before' => $b, 'after' => $a];
            }
        }
        return $diff;
    }

    private function makeUniqueSlug(string $name, SluggerInterface $slugger, HighlineRepository $repo): string
    {
        $base = strtolower($slugger->slug($name)->toString());
        if ($base === '') {
            $base = 'highline';
        }
        $slug = $base;
        $i = 2;
        while ($repo->findOneBy(['slug' => $slug]) !== null) {
            $slug = $base . '-' . $i++;
        }
        return $slug;
    }
}
