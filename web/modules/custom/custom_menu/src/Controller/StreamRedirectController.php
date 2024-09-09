<?php

namespace Drupal\custom_menu\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Drupal\user\Entity\User;

class StreamRedirectController extends ControllerBase {

  /**
   * Redirects the user based on their stream field.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   A redirect response.
   */
  public function redirectToStream() {
    
    if ($this->currentUser()->hasRole('student')) {
      $uid = $this->currentUser()->id();
      $user_entity = User::load($uid);

      $stream_value = $user_entity->get('field_stream')->target_id;

      $stream_value = strtolower($stream_value);
      $stream_value = str_replace(' ', '-', $stream_value);

      return new RedirectResponse('taxonomy/term/' . $stream_value);
    }

    throw new NotFoundHttpException();
  }
}
