<?php

namespace Slack\Service;

use Exception;

class CaptchaValidator
{
  /**
   * @param $response
   * @return bool
   */
  public function validate($response)
  {
    $secret = '6LddQNsUAAAAADI52_tfNFkzlqYOVq27pixa31n_';
    $url = "https://www.google.com/recaptcha/api/siteverify";
    try {
      $ch = curl_init($url);
      curl_setopt($ch, CURLOPT_POST, 1);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      $queryString = http_build_query([
          'secret' => $secret,
          'response' => $response
      ]);
      curl_setopt($ch, CURLOPT_POSTFIELDS, $queryString);
      $output = curl_exec($ch);
      curl_close($ch);
      $jsonResponse = json_decode($output, true);
      return $jsonResponse['success'] === true;
    } catch (Exception $e) {
      return false;
    }
  }
}