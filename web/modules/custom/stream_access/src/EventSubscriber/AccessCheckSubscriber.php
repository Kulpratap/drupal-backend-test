<?php

namespace Drupal\stream_access\EventSubscriber;

use Drupal\Core\Session\AccountProxyInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\node\Entity\Node;
use Drupal\user\Entity\User;

/**
 * Event subscriber to restrict access to taxonomy terms and subject nodes based on user stream.
 */
class AccessCheckSubscriber implements EventSubscriberInterface {

  /**
   * The current user service.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * Constructs a AccessCheckSubscriber object.
   *
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user service.
   */
  public function __construct(AccountProxyInterface $current_user) {
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('current_user')
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[KernelEvents::REQUEST][] = ['checkAccess', 0];
    return $events;
  }

  /**
   * Checks access to the taxonomy term pages and subject nodes.
   *
   * @param \Symfony\Component\HttpKernel\Event\RequestEvent $event
   *   The request event.
   */
  public function checkAccess(RequestEvent $event) {
    $request = $event->getRequest();
    $path = $request->getPathInfo();

    if (preg_match('/^\/taxonomy\/term\/(\d+)$/', $path, $matches)) {
      $term_id = $matches[1];
      $this->checkTaxonomyAccess($term_id, $event);
    }
    elseif (preg_match('/^\/node\/(\d+)$/', $path, $matches)) {
      $node_id = $matches[1];
      $this->checkSubjectAccess($node_id, $event);
    }
  }

  /**
   * Checks access to taxonomy term pages.
   *
   * @param int $term_id
   *   The term ID.
   * @param \Symfony\Component\HttpKernel\Event\RequestEvent $event
   *   The request event.
   */
  protected function checkTaxonomyAccess($term_id, RequestEvent $event) {
    $user = $this->currentUser;

    if ($user->isAuthenticated() && $user->hasRole('student')) {
      $user_entity = User::load($user->id());
      $user_stream_tid = $user_entity->get('field_stream')->target_id;

      if ($term_id != $user_stream_tid) {
        $event->setResponse(new RedirectResponse('/error404'));
        return;
      }
    }
  }

  /**
   * Checks access to subject nodes.
   *
   * @param int $node_id
   *   The node ID.
   * @param \Symfony\Component\HttpKernel\Event\RequestEvent $event
   *   The request event.
   */
  protected function checkSubjectAccess($node_id, RequestEvent $event) {
    $node = Node::load($node_id);
    $user = $this->currentUser;

    if ($node && $node->getType() == 'subjects' && $user->isAuthenticated() && $user->hasRole('student')) {
      $user_entity = User::load($user->id());
      $user_stream_tid = $user_entity->get('field_stream')->target_id;

      $subject_stream_tid = $node->get('field_stream')->target_id;

      if ($subject_stream_tid != $user_stream_tid) {
        $event->setResponse(new RedirectResponse('/error404'));
        return;
      }
    }
  }

}
