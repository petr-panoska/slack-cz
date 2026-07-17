<?php

namespace Slack\Service;

use Nette\Http\FileUpload;
use Nette\Image;

class PhotoManager
{
  /**
   * @param int $userId
   * @param FileUpload $photo
   * @param bool $deletePrevious
   * @throws \RuntimeException
   * @throws \Exception
   */
  public function saveProfilePhoto($userId, $photo, $deletePrevious = false)
  {
    $userDir = USERS_DIR . DS . $userId;
    $fullPhotoPath = $userDir . DS . "profil_foto_full.jpg";
    $photoPath = $userDir . DS . "profil_foto.jpg";

    // Vytvoří adresář pro fotky uživatele, pokud neexistuje (s bezpečnými právy 0755)
    if (!is_dir($userDir)) {
      if (!mkdir($userDir, 0755, true)) {
        throw new \RuntimeException("Nepodařilo se vytvořit adresář pro uživatele: $userDir");
      }
    }

    // Smazání předchozích fotek, pokud je vyžadováno
    if ($deletePrevious) {
      if (file_exists($fullPhotoPath)) {
        unlink($fullPhotoPath);
      }
      if (file_exists($photoPath)) {
        unlink($photoPath);
      }
    }

    try {
      // Uložení plné velikosti
      $imageFull = $photo->toImage();
      $imageFull->resize(900, NULL, Image::SHRINK_ONLY);
      $imageFull->save($fullPhotoPath);

      // Uložení náhledu
      $image = $photo->toImage();
      $image->resize(150, NULL, Image::SHRINK_ONLY);
      $image->save($photoPath);
    } catch (\Exception $e) {
      // Logování neočekávané chyby při zpracování obrázku (např. poškozený upload)
      \Nette\Diagnostics\Debugger::log($e, \Nette\Diagnostics\Debugger::ERROR);
      throw $e;
    }
  }
}