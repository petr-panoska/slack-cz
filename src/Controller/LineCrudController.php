<?php

namespace App\Controller;

use App\Entity\Line;
use App\Entity\LineEdit;
use App\Entity\User;
use App\Enum\LineEditStatus;
use App\Enum\LineType;
use App\Form\LineForm;
use App\Repository\LineEditRepository;
use App\Repository\LineRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * Line CRUD + verification + edit-proposal flow.
 *
 * Trust model:
 * - Anyone logged in can create a new line; it lands as `isVerified=false`, owned by them.
 * - Owner of an unverified line (or any admin) can edit / delete it directly.
 * - Once admin marks a line `isVerified=true`, edits go through LineEdit (status=PENDING)
 *   and only an admin can approve them. Delete of a verified line is admin-only.
 *
 * The LineEdit table doubles as audit log: APPLIED rows for direct edits + approved proposals.
 */
final class LineCrudController extends AbstractController
{
    /**
     * Fields that are part of the form / snapshot. Listed once so the snapshot/apply pair stays in sync.
     *
     * `length` is NOT in the form — it's derived from point1/point2 (haversine) on submit.
     * We still snapshot it so the audit row reflects the post-derivation state.
     */
    private const FIELDS = [
        'name', 'type', 'height',
        'point1Latitude', 'point1Longitude', 'point2Latitude', 'point2Longitude',
        'parkingLatitude', 'parkingLongitude',
        'length',
        'country', 'region', 'area',
        'description', 'pointOneInfo', 'pointTwoInfo',
        'anchoring', 'approachMinutes', 'tensioningMinutes',
        'firstAscentBy', 'firstAscentDate', 'nameHistory',
    ];

    #[Route('/line/new', name: 'app_line_new', methods: ['GET', 'POST'], priority: 10)]
    #[IsGranted('ROLE_USER')]
    public function new(
        Request $request,
        EntityManagerInterface $em,
        LineRepository $lines,
        SluggerInterface $slugger,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        $line = new Line();
        $line->setType(LineType::Highline);
        $line->setCreatedBy($user);
        $line->setIsVerified(false);

        $form = $this->createForm(LineForm::class, $line);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->deriveGeometry($line);
            $line->setSlug($this->makeUniqueSlug((string) $line->getName(), $slugger, $lines));
            $em->persist($line);
            $em->flush();

            // Creation row — no prior state to diff against; beforeSnapshot stays null.
            $audit = $this->buildEdit($line, $user, LineEditStatus::APPLIED, null);
            $em->persist($audit);
            $em->flush();

            $this->addFlash('success', 'Lajna přidána. Pokud chceš, aby ji ostatní mohli rozšiřovat, požádej admina o verifikaci.');

            return $this->redirectToRoute('app_line_detail', ['slug' => $line->getSlug()]);
        }

        return $this->render('line_form/form.html.twig', [
            'form' => $form,
            'line' => $line,
            'mode' => 'new',
            'queueProposal' => false,
        ]);
    }

    #[Route('/line/{slug}/edit', name: 'app_line_edit', requirements: ['slug' => '[a-z0-9-]+'], methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function edit(
        Request $request,
        #[MapEntity(mapping: ['slug' => 'slug'])]
        Line $line,
        EntityManagerInterface $em,
        SluggerInterface $slugger,
        LineRepository $lines,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        $isAdmin = $this->isGranted('ROLE_ADMIN');
        $isOwner = $line->isOwnedBy($user);
        // Napřímo edituje jen admin nebo owner své (ještě neverifikované) lajny. Kdokoli jiný
        // přihlášený — včetně úprav legacy lajn (bez ownera) i cizích lajn — pošle návrh do
        // admin fronty místo aby dostal access denied.
        $canEditDirect = $isAdmin || ($isOwner && !$line->isVerified());
        $queueProposal = !$canEditDirect;

        // Capture the pre-edit snapshot BEFORE form binding so we can persist the row's own
        // beforeSnapshot. This makes the audit row self-describing — its diff doesn't depend
        // on the existence of neighbouring rows.
        $beforeSnapshot = $this->snapshot($line);

        $form = $this->createForm(LineForm::class, $line);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Recompute length + midpoint from the (possibly changed) endpoints — applies to both
            // direct edit and proposal flow so the snapshot reflects the canonical derived values.
            $this->deriveGeometry($line);

            if ($queueProposal) {
                // Submitting form filled the entity in-memory. Snapshot the proposed values, then
                // discard the in-memory mutation by reloading the row from DB.
                $snapshot = $this->snapshot($line);
                $em->refresh($line);

                // No-op submit (uživatel jen klikl Uložit bez změn) — neneřaduju prázdný návrh
                // do queue. Bez tohohle by admin viděl proposal s prázdným diffem.
                if ($this->diff($beforeSnapshot, $snapshot) === []) {
                    $this->addFlash('info', 'Žádné změny — návrh se neukládal.');
                    return $this->redirectToRoute('app_line_detail', ['slug' => $line->getSlug()]);
                }

                $proposal = new LineEdit();
                $proposal->setLine($line);
                $proposal->setProposedBy($user);
                $proposal->setSnapshot($snapshot);
                $proposal->setBeforeSnapshot($beforeSnapshot);
                $proposal->setStatus(LineEditStatus::PENDING);
                $em->persist($proposal);
                $em->flush();

                $this->addFlash('success', 'Návrh úpravy odeslán k schválení adminovi.');
            } else {
                $afterSnapshot = $this->snapshot($line);
                if ($this->diff($beforeSnapshot, $afterSnapshot) === []) {
                    // No-op direct edit — entitě sice projde flush (žádné změny), ale netřeba
                    // zapisovat audit row, který nic neříká.
                    $this->addFlash('info', 'Žádné změny — záznam historie se neukládal.');
                    return $this->redirectToRoute('app_line_detail', ['slug' => $line->getSlug()]);
                }

                // Unverified lajny mají URL stále vázaný na aktuální název — když user přejmenuje,
                // regeneruje se i slug. Verified lajny si slug drží napevno (name field je v takovém
                // případě disabled, sem to nikdy nedoteče).
                if (!$line->isVerified() && ($beforeSnapshot['name'] ?? null) !== $line->getName()) {
                    $line->setSlug($this->makeUniqueSlug((string) $line->getName(), $slugger, $lines, $line->getId()));
                }

                $em->flush();
                $audit = $this->buildEdit($line, $user, LineEditStatus::APPLIED, $beforeSnapshot);
                $em->persist($audit);
                $em->flush();
                $this->addFlash('success', 'Lajna upravena.');
            }

            return $this->redirectToRoute('app_line_detail', ['slug' => $line->getSlug()]);
        }

        return $this->render('line_form/form.html.twig', [
            'form' => $form,
            'line' => $line,
            'mode' => 'edit',
            'queueProposal' => $queueProposal,
        ]);
    }

    #[Route('/line/{slug}/delete', name: 'app_line_delete', requirements: ['slug' => '[a-z0-9-]+'], methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function delete(
        Request $request,
        #[MapEntity(mapping: ['slug' => 'slug'])]
        Line $line,
        EntityManagerInterface $em,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        $isAdmin = $this->isGranted('ROLE_ADMIN');
        if (!$isAdmin) {
            if ($line->isVerified()) {
                throw $this->createAccessDeniedException('Verifikovanou lajnu může smazat jen admin.');
            }
            if (!$line->isOwnedBy($user)) {
                throw $this->createAccessDeniedException('Smazat lajnu může jen její autor.');
            }
        }

        $token = (string) $request->request->get('_token', '');
        if (!$this->isCsrfTokenValid('delete-line-' . $line->getId(), $token)) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $em->remove($line);
        $em->flush();
        $this->addFlash('success', 'Lajna smazána.');

        return $this->redirectToRoute('app_line_map');
    }

    #[Route('/line/{slug}/verify', name: 'app_line_verify', requirements: ['slug' => '[a-z0-9-]+'], methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function verify(
        Request $request,
        #[MapEntity(mapping: ['slug' => 'slug'])]
        Line $line,
        EntityManagerInterface $em,
        LineEditRepository $edits,
    ): Response {
        $token = (string) $request->request->get('_token', '');
        if (!$this->isCsrfTokenValid('verify-line-' . $line->getId(), $token)) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        /** @var User $admin */
        $admin = $this->getUser();

        $line->setIsVerified(true);
        $em->flush();

        // Verifikace = "fresh slate" pro audit log. Smažeme všechny existující revize (rename-y
        // z unverified éry, které by po stack-popu měnily title → slug, což u verified lajny
        // nechceme) a zapíšeme jeden APPLIED row se snapshotem současného stavu jako novou
        // creation. Original autor zůstává jako proposedBy, admin je reviewer.
        foreach ($edits->findForLine($line) as $row) {
            $em->remove($row);
        }
        $em->flush();

        $seed = new LineEdit();
        $seed->setLine($line);
        $seed->setProposedBy($line->getCreatedBy());
        $seed->setSnapshot($this->snapshot($line));
        $seed->setBeforeSnapshot(null);
        $seed->setStatus(LineEditStatus::APPLIED);
        $seed->setReviewedBy($admin);
        $seed->setReviewedAt(new \DateTimeImmutable());
        $em->persist($seed);
        $em->flush();

        $this->addFlash('success', sprintf('Lajna „%s" verifikována. Historie sloučena do jedné creation revize.', $line->getName()));

        return $this->redirectToRoute('app_line_detail', ['slug' => $line->getSlug()]);
    }

    #[Route('/line/{slug}/history', name: 'app_line_history', requirements: ['slug' => '[a-z0-9-]+'], methods: ['GET'])]
    public function history(
        #[MapEntity(mapping: ['slug' => 'slug'])]
        Line $line,
        LineEditRepository $edits,
    ): Response {
        $rows = $edits->findHistoryFor($line);

        // Each row is self-describing: diff comes from its own beforeSnapshot, not the
        // neighbouring row's. Deleting a middle row therefore can't bleed its changes
        // into the next one.
        $entries = [];
        foreach ($rows as $edit) {
            $before = $edit->getBeforeSnapshot();
            $diff = $before !== null ? $this->diff($before, $edit->getSnapshot()) : [];
            $isCreation = $before === null && $edit->getStatus() === LineEditStatus::APPLIED;
            $entries[] = ['edit' => $edit, 'diff' => $diff, 'isCreation' => $isCreation];
        }

        // Newest first for display.
        $entries = array_reverse($entries);

        return $this->render('line_form/history.html.twig', [
            'line' => $line,
            'entries' => $entries,
        ]);
    }

    #[Route('/line/{slug}/history/{editId}/delete', name: 'app_line_history_delete', requirements: ['slug' => '[a-z0-9-]+', 'editId' => '\d+'], methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function historyDelete(
        Request $request,
        #[MapEntity(mapping: ['slug' => 'slug'])]
        Line $line,
        #[MapEntity(mapping: ['editId' => 'id'])]
        LineEdit $edit,
        EntityManagerInterface $em,
        LineEditRepository $edits,
    ): Response {
        if ($edit->getLine()->getId() !== $line->getId()) {
            throw $this->createAccessDeniedException('Záznam nepatří k této lajně.');
        }

        $token = (string) $request->request->get('_token', '');
        if (!$this->isCsrfTokenValid('history-delete-' . $edit->getId(), $token)) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        // Mažeme jen úplně poslední revizi (stack-pop). Smazání prostředního řádku by zanechalo
        // nekonzistenci mezi audit logem a stavem lajny, takže to vůbec nepovolujeme.
        $history = $edits->findHistoryFor($line); // chrono ASC
        if ($history === []) {
            throw $this->createAccessDeniedException('Historie je prázdná.');
        }
        $latestRow = $history[count($history) - 1];
        if ($latestRow->getId() !== $edit->getId()) {
            throw $this->createAccessDeniedException('Smazat lze jen poslední revizi.');
        }
        if ($history[0]->getId() === $edit->getId()) {
            // Stejný záznam je první i poslední → jediný v historii (creation). Nemazat.
            throw $this->createAccessDeniedException('První záznam historie smazat nelze.');
        }

        $em->remove($edit);
        $em->flush();

        // Po popnutí latest se lajna vrátí do snapshot-u toho, co bylo před smazaným editem.
        // Refresh z DB zruší případné Doctrine in-memory vazby na smazaný edit.
        $em->refresh($line);
        $newLatest = $edits->findLatestAppliedFor($line);
        if ($newLatest !== null) {
            $this->applySnapshot($line, $newLatest->getSnapshot());
            $em->flush();
        }

        $this->addFlash('success', sprintf('Revize #%d smazána, lajna vrácena do předchozího stavu.', $edit->getId()));
        return $this->redirectToRoute('app_line_history', ['slug' => $line->getSlug()]);
    }

    #[Route('/admin/proposals', name: 'app_admin_proposals', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function proposalIndex(LineEditRepository $edits): Response
    {
        $pending = $edits->findPending();

        $diffs = [];
        foreach ($pending as $edit) {
            $diffs[$edit->getId()] = $this->diff($this->snapshot($edit->getLine()), $edit->getSnapshot());
        }

        return $this->render('line_form/proposals.html.twig', [
            'pending' => $pending,
            'diffs' => $diffs,
        ]);
    }

    #[Route('/admin/proposals/{id}/approve', name: 'app_admin_proposal_approve', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function proposalApprove(
        Request $request,
        LineEdit $edit,
        EntityManagerInterface $em,
    ): Response {
        $this->assertProposalCsrf($request, $edit, 'approve');
        $this->assertPending($edit);

        /** @var User $admin */
        $admin = $this->getUser();

        $this->applySnapshot($edit->getLine(), $edit->getSnapshot());
        $edit->setStatus(LineEditStatus::APPLIED);
        $edit->setReviewedBy($admin);
        $edit->setReviewedAt(new \DateTimeImmutable());
        $em->flush();

        $this->addFlash('success', sprintf('Návrh #%d schválen a aplikován.', $edit->getId()));
        return $this->redirectToRoute('app_admin_proposals');
    }

    #[Route('/admin/proposals/{id}/reject', name: 'app_admin_proposal_reject', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function proposalReject(
        Request $request,
        LineEdit $edit,
        EntityManagerInterface $em,
    ): Response {
        $this->assertProposalCsrf($request, $edit, 'reject');
        $this->assertPending($edit);

        /** @var User $admin */
        $admin = $this->getUser();

        $edit->setStatus(LineEditStatus::REJECTED);
        $edit->setReviewedBy($admin);
        $edit->setReviewedAt(new \DateTimeImmutable());
        $em->flush();

        $this->addFlash('success', sprintf('Návrh #%d zamítnut.', $edit->getId()));
        return $this->redirectToRoute('app_admin_proposals');
    }

    private function assertProposalCsrf(Request $request, LineEdit $edit, string $action): void
    {
        $token = (string) $request->request->get('_token', '');
        if (!$this->isCsrfTokenValid('proposal-' . $action . '-' . $edit->getId(), $token)) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }
    }

    private function assertPending(LineEdit $edit): void
    {
        if ($edit->getStatus() !== LineEditStatus::PENDING) {
            throw $this->createAccessDeniedException('Návrh už není ve frontě.');
        }
    }

    /**
     * @param array<string, mixed>|null $beforeSnapshot — pre-edit state; NULL means creation row.
     */
    private function buildEdit(Line $line, ?User $author, LineEditStatus $status, ?array $beforeSnapshot): LineEdit
    {
        $edit = new LineEdit();
        $edit->setLine($line);
        $edit->setProposedBy($author);
        $edit->setSnapshot($this->snapshot($line));
        $edit->setBeforeSnapshot($beforeSnapshot);
        $edit->setStatus($status);
        if ($status === LineEditStatus::APPLIED) {
            $edit->setReviewedBy($author);
            $edit->setReviewedAt(new \DateTimeImmutable());
        }
        return $edit;
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(Line $h): array
    {
        return [
            'name' => $h->getName(),
            'type' => $h->getType()->value,
            'length' => $h->getLength(),
            'height' => $h->getHeight(),
            'point1Latitude' => $h->getPoint1Latitude(),
            'point1Longitude' => $h->getPoint1Longitude(),
            'point2Latitude' => $h->getPoint2Latitude(),
            'point2Longitude' => $h->getPoint2Longitude(),
            'parkingLatitude' => $h->getParkingLatitude(),
            'parkingLongitude' => $h->getParkingLongitude(),
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
    private function applySnapshot(Line $h, array $s): void
    {
        $h->setName((string) $s['name']);
        $h->setType(LineType::from((string) $s['type']));
        $h->setHeight((int) $s['height']);
        $h->setPoint1Latitude(isset($s['point1Latitude']) ? (string) $s['point1Latitude'] : null);
        $h->setPoint1Longitude(isset($s['point1Longitude']) ? (string) $s['point1Longitude'] : null);
        $h->setPoint2Latitude(isset($s['point2Latitude']) ? (string) $s['point2Latitude'] : null);
        $h->setPoint2Longitude(isset($s['point2Longitude']) ? (string) $s['point2Longitude'] : null);
        $h->setParkingLatitude(isset($s['parkingLatitude']) ? (string) $s['parkingLatitude'] : null);
        $h->setParkingLongitude(isset($s['parkingLongitude']) ? (string) $s['parkingLongitude'] : null);
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
     * Compute length (haversine, integer meters) from point1/point2. A line is defined
     * solely by its two anchors — there is no separate stored midpoint coordinate.
     * Called after every form-bound mutation to keep the derived length in sync.
     */
    private function deriveGeometry(Line $h): void
    {
        $p1Lat = $this->floatOrNull($h->getPoint1Latitude());
        $p1Lng = $this->floatOrNull($h->getPoint1Longitude());
        $p2Lat = $this->floatOrNull($h->getPoint2Latitude());
        $p2Lng = $this->floatOrNull($h->getPoint2Longitude());

        if ($p1Lat === null || $p1Lng === null || $p2Lat === null || $p2Lng === null) {
            return;
        }

        $h->setLength((int) round($this->haversineMeters($p1Lat, $p1Lng, $p2Lat, $p2Lng)));
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

    /**
     * @param int|null $excludeId — když přepočítáváme slug pro existující lajnu (rename na unverified),
     *                              nesmíme se srážet sami se sebou v DB.
     */
    private function makeUniqueSlug(string $name, SluggerInterface $slugger, LineRepository $repo, ?int $excludeId = null): string
    {
        $base = strtolower($slugger->slug($name)->toString());
        if ($base === '') {
            $base = 'line';
        }
        $slug = $base;
        $i = 2;
        while (true) {
            $existing = $repo->findOneBy(['slug' => $slug]);
            if ($existing === null || $existing->getId() === $excludeId) {
                break;
            }
            $slug = $base . '-' . $i++;
        }
        return $slug;
    }
}
