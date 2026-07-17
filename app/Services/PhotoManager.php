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
   */
  public function saveProfilePhoto($userId, $photo, $deletePrevious = false)
  {
    $userDir = USERS_DIR . DS . $userId;
    $fullPhotoPath = $userDir . DS . "profil_foto_full.jpg";
    $photoPath = $userDir . DS . "profil_foto.jpg";

    // Zajisti, že adresář pro fotky uživatele existuje a má správná oprávnění
    if (!is_dir($userDir)) {
      $parentDir = dirname($userDir);
      $mkdir_result = @mkdir($userDir, 0777, true);
      
      // Log výsledek mkdir pro debug
      \Nette\Diagnostics\Debugger::log(
        "mkdir($userDir) = " . ($mkdir_result ? 'OK' : 'FAILED') . 
        " | Parent writable: " . (is_writable($parentDir) ? 'YES' : 'NO') . 
        " | After: is_dir=" . (is_dir($userDir) ? 'YES' : 'NO'),
        \Nette\Diagnostics\Debugger::INFO
      );
    }
    
    // Zkus nastavit chmod i když existuje
    @chmod($userDir, 0777);

    if ($deletePrevious) {
      if (file_exists($fullPhotoPath)) {
        unlink($fullPhotoPath);
      }
      if (file_exists($photoPath)) {
        unlink($photoPath);
      }
    }

    try {
      $imageFull = $photo->toImage();
      $imageFull->resize(900, NULL, Image::SHRINK_ONLY);
      $imageFull->save($fullPhotoPath);

      $image = $photo->toImage();
      $image->resize(150, NULL, Image::SHRINK_ONLY);
      $image->save($photoPath);
    } catch (\Exception $e) {
      // Log chybu pro debug
      \Nette\Diagnostics\Debugger::log(
        'Photo save failed: ' . $e->getMessage() . 
        " | Dir: $userDir | Exists: " . (is_dir($userDir) ? 'YES' : 'NO') . 
        " | Writable: " . (is_writable($userDir) ? 'YES' : 'NO'),
        \Nette\Diagnostics\Debugger::ERROR
      );
      throw $e;
    }
  }
}