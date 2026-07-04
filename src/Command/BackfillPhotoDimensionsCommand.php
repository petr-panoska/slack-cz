<?php

namespace App\Command;

use App\Repository\LinePhotoRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Vich\UploaderBundle\Storage\StorageInterface;

#[AsCommand(
    name: 'app:photo:backfill-dimensions',
    description: 'Fill line_photo.width/height from the stored masters (rows imported before the columns existed)',
)]
final class BackfillPhotoDimensionsCommand extends Command
{
    public function __construct(
        private readonly LinePhotoRepository $photos,
        private readonly StorageInterface $storage,
        private readonly EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $pending = $this->photos->findBy(['width' => null]);
        if ($pending === []) {
            $io->success('Nothing to backfill — all photos have dimensions.');
            return Command::SUCCESS;
        }

        $filled = 0;
        $missing = [];
        foreach ($pending as $photo) {
            $path = $this->storage->resolvePath($photo, 'file');
            $size = $path !== null && is_file($path) ? @getimagesize($path) : false;
            if ($size === false) {
                $missing[] = sprintf('#%d %s', $photo->getId(), $path ?? '(no path)');
                continue;
            }
            $photo->setDimensions($size[0], $size[1]);
            $filled++;
        }
        $this->em->flush();

        if ($missing !== []) {
            $io->warning(sprintf("%d file(s) unreadable, left NULL:\n%s", count($missing), implode("\n", $missing)));
        }
        $io->success(sprintf('Backfilled %d of %d photos.', $filled, count($pending)));

        return $missing === [] ? Command::SUCCESS : Command::FAILURE;
    }
}
