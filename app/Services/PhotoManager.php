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
    $fullPhotoPath = USERS_DIR . DS . $userId . DS . "profil_foto_full.jpg";
    $photoPath = USERS_DIR . DS . $userId . DS . "profil_foto.jpg";

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
      //todo
    }
  }
}